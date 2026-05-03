<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollAuditLog;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TD-036: Immutable audit logs for payroll state transitions.
 */
class PayrollAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    private Business $business;

    private User $adminUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollService::class);
        $this->adminUser = User::factory()->create(['role' => 'super_admin']);
        $this->business = Business::factory()->create();
        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'base_salary' => 1000,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createPeriodWithRecord(string $startsOn, string $endsOn, string $status = 'open'): array
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => $status,
        ]);

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '1500.00',
            'base_salary_snapshot' => '1000.00',
            'commissions_total' => '500.00',
            'tips_total' => '0.00',
            'adjustments_total' => '0.00',
        ]);

        return ['period' => $period, 'record' => $record];
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /** @test */
    public function test_approve_creates_audit_log_entry(): void
    {
        ['period' => $period, 'record' => $record] = $this->createPeriodWithRecord('2026-05-01', '2026-05-31');

        $this->service->approve($period, $this->adminUser);

        $log = PayrollAuditLog::where('payroll_record_id', $record->id)
            ->where('action', 'approve')
            ->first();

        $this->assertNotNull($log, 'Audit log for approve not found');
        $this->assertSame('draft', $log->previous_status);
        $this->assertSame('approved', $log->new_status);
        $this->assertSame($this->business->id, $log->business_id);
        $this->assertSame($this->adminUser->id, $log->user_id);
    }

    /** @test */
    public function test_mark_paid_audit_log_includes_payment_method(): void
    {
        ['period' => $period, 'record' => $record] = $this->createPeriodWithRecord('2026-05-01', '2026-05-31');

        $this->service->approve($period, $this->adminUser);

        $record->refresh();
        $this->service->markPaid($record, $this->adminUser, [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'REF-001',
        ]);

        $log = PayrollAuditLog::where('payroll_record_id', $record->id)
            ->where('action', 'mark_paid')
            ->first();

        $this->assertNotNull($log, 'Audit log for mark_paid not found');
        $this->assertSame('approved', $log->previous_status);
        $this->assertSame('paid', $log->new_status);
        $this->assertSame('bank_transfer', $log->payload['payment_method']);
        $this->assertSame('REF-001', $log->payload['payment_reference']);
    }

    /** @test */
    public function test_void_from_paid_logs_compensation_adjustment_creation_too(): void
    {
        ['period' => $period, 'record' => $record] = $this->createPeriodWithRecord('2026-04-01', '2026-04-30');

        $this->service->approve($period, $this->adminUser);
        $record->refresh();
        $this->service->markPaid($record, $this->adminUser, ['payment_method' => 'cash']);

        // Close the period by force so we can add a next open period
        $period->forceFill(['status' => 'closed'])->save();

        // Next open period required for compensation
        $nextPeriod = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
            'status' => 'open',
        ]);

        $record->refresh();
        $this->service->void($record, $this->adminUser, 'Error in payment');

        // Void log
        $voidLog = PayrollAuditLog::where('payroll_record_id', $record->id)
            ->where('action', 'void')
            ->first();
        $this->assertNotNull($voidLog);
        $this->assertSame('paid', $voidLog->previous_status);
        $this->assertSame('voided', $voidLog->new_status);

        // Compensation adjustment log in next period
        $compensationLog = PayrollAuditLog::where('payroll_period_id', $nextPeriod->id)
            ->where('action', 'add_adjustment')
            ->first();
        $this->assertNotNull($compensationLog, 'Compensation adjustment audit log not found');
        $this->assertSame('debit', $compensationLog->payload['type']);
    }

    /** @test */
    public function test_audit_log_cannot_be_updated(): void
    {
        $log = PayrollAuditLog::create([
            'business_id' => $this->business->id,
            'payroll_record_id' => null,
            'payroll_period_id' => null,
            'user_id' => $this->adminUser->id,
            'action' => 'approve',
            'previous_status' => 'draft',
            'new_status' => 'approved',
            'payload' => [],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('PayrollAuditLog is append-only.');

        $log->update(['action' => 'tampered']);
    }

    /** @test */
    public function test_audit_log_cannot_be_deleted(): void
    {
        $log = PayrollAuditLog::create([
            'business_id' => $this->business->id,
            'payroll_record_id' => null,
            'payroll_period_id' => null,
            'user_id' => $this->adminUser->id,
            'action' => 'approve',
            'previous_status' => 'draft',
            'new_status' => 'approved',
            'payload' => [],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('PayrollAuditLog is append-only.');

        $log->delete();
    }

    /** @test */
    public function test_failed_transaction_does_not_create_audit_log(): void
    {
        $initialCount = PayrollAuditLog::withoutGlobalScopes()->count();

        try {
            DB::transaction(function (): void {
                PayrollAuditLog::create([
                    'business_id' => $this->business->id,
                    'payroll_record_id' => null,
                    'payroll_period_id' => null,
                    'user_id' => $this->adminUser->id,
                    'action' => 'approve',
                    'previous_status' => 'draft',
                    'new_status' => 'approved',
                    'payload' => [],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Test',
                ]);

                // Force a rollback
                throw new \RuntimeException('Simulated failure');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertSame(
            $initialCount,
            PayrollAuditLog::withoutGlobalScopes()->count(),
            'Rolled-back transaction should not persist audit log'
        );
    }

    /** @test */
    public function test_audit_log_isolated_per_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create(['role' => 'super_admin']);
        $otherEmployee = Employee::factory()->create([
            'business_id' => $otherBusiness->id,
            'is_active' => true,
            'base_salary' => 500,
        ]);

        // Approve for this business
        ['period' => $period] = $this->createPeriodWithRecord('2026-05-01', '2026-05-31');
        $this->service->approve($period, $this->adminUser);

        // Approve for other business
        $otherPeriod = PayrollPeriod::factory()->forBusiness($otherBusiness)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
            'status' => 'open',
        ]);
        PayrollRecord::factory()->draft()->create([
            'business_id' => $otherBusiness->id,
            'payroll_period_id' => $otherPeriod->id,
            'employee_id' => $otherEmployee->id,
            'gross_total' => '800.00',
        ]);
        $this->service->approve($otherPeriod, $otherAdmin);

        $thisBusinessLogs = PayrollAuditLog::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->where('action', 'approve')
            ->count();

        $otherBusinessLogs = PayrollAuditLog::withoutGlobalScopes()
            ->where('business_id', $otherBusiness->id)
            ->where('action', 'approve')
            ->count();

        $this->assertGreaterThan(0, $thisBusinessLogs);
        $this->assertGreaterThan(0, $otherBusinessLogs);

        // Ensure no cross-contamination
        $this->assertSame(
            0,
            PayrollAuditLog::withoutGlobalScopes()
                ->where('business_id', $this->business->id)
                ->where('user_id', $otherAdmin->id)
                ->count(),
            'Other business admin logs should not appear in this business logs'
        );
    }
}
