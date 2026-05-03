<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 3.1 — T-3.1.1
 * Verifies that mass assignment cannot alter state machine and audit fields on payroll models.
 * All status transitions must go through PayrollService via forceFill() only.
 */
class PayrollMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // PayrollRecord
    // -------------------------------------------------------------------------

    public function test_payroll_record_status_cannot_be_mass_assigned(): void
    {
        $record = PayrollRecord::factory()->create();

        $record->update(['status' => 'paid']);
        $record->refresh();

        $this->assertSame('draft', $record->status);
    }

    public function test_payroll_record_payment_metadata_cannot_be_mass_assigned(): void
    {
        $record = PayrollRecord::factory()->create();

        $record->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_by' => 999,
            'payment_method' => 'cash',
            'payment_reference' => 'REF-HACK',
        ]);
        $record->refresh();

        $this->assertSame('draft', $record->status);
        $this->assertNull($record->paid_at);
        $this->assertNull($record->paid_by);
        $this->assertNull($record->payment_method);
        $this->assertNull($record->payment_reference);
    }

    public function test_payroll_record_approved_metadata_cannot_be_mass_assigned(): void
    {
        $record = PayrollRecord::factory()->create();

        $record->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 999,
        ]);
        $record->refresh();

        $this->assertSame('draft', $record->status);
        $this->assertNull($record->approved_at);
        $this->assertNull($record->approved_by);
    }

    public function test_payroll_record_voided_metadata_cannot_be_mass_assigned(): void
    {
        $record = PayrollRecord::factory()->create();

        $record->update([
            'status' => 'voided',
            'voided_at' => now(),
            'voided_by' => 999,
            'void_reason' => 'Hack attempt',
        ]);
        $record->refresh();

        $this->assertSame('draft', $record->status);
        $this->assertNull($record->voided_at);
        $this->assertNull($record->voided_by);
        $this->assertNull($record->void_reason);
    }

    // -------------------------------------------------------------------------
    // PayrollPeriod
    // -------------------------------------------------------------------------

    public function test_payroll_period_closed_metadata_cannot_be_mass_assigned(): void
    {
        $period = PayrollPeriod::factory()->create();

        $period->update([
            'closed_at' => now(),
            'closed_by' => 999,
        ]);
        $period->refresh();

        $this->assertNull($period->closed_at);
        $this->assertNull($period->closed_by);
    }

    public function test_payroll_period_status_open_can_be_mass_assigned_on_create(): void
    {
        // status IS in fillable for PayrollPeriod (needed for createPeriod initial insert).
        $business = Business::factory()->create();
        $period = PayrollPeriod::create([
            'business_id' => $business->id,
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
            'status' => 'open',
        ]);

        $this->assertSame('open', $period->status);
    }

    // -------------------------------------------------------------------------
    // PayrollAdjustment
    // -------------------------------------------------------------------------

    public function test_payroll_adjustment_core_fields_can_be_mass_assigned(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $admin = User::factory()->create(['business_id' => $business->id]);

        $adjustment = PayrollAdjustment::create([
            'business_id' => $business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'type' => 'credit',
            'amount' => 100.00,
            'reason' => 'Performance bonus',
            'created_by' => $admin->id,
        ]);

        $this->assertSame('credit', $adjustment->type);
        $this->assertEquals('100.00', $adjustment->amount);
        $this->assertSame('Performance bonus', $adjustment->reason);
    }

    // -------------------------------------------------------------------------
    // CommissionRecord
    // -------------------------------------------------------------------------

    public function test_commission_record_status_cannot_be_mass_assigned(): void
    {
        $record = CommissionRecord::factory()->create();

        $record->update(['status' => 'paid']);
        $record->refresh();

        $this->assertSame('pending', $record->status);
    }

    public function test_commission_record_payroll_period_id_cannot_be_mass_assigned(): void
    {
        $record = CommissionRecord::factory()->create();

        $record->update(['payroll_period_id' => 999]);
        $record->refresh();

        $this->assertNull($record->payroll_period_id);
    }

    public function test_commission_record_locked_at_cannot_be_mass_assigned(): void
    {
        $record = CommissionRecord::factory()->create();

        $record->update(['locked_at' => now(), 'status' => 'locked']);
        $record->refresh();

        $this->assertSame('pending', $record->status);
        $this->assertNull($record->locked_at);
    }

    public function test_commission_record_paid_at_cannot_be_mass_assigned(): void
    {
        $record = CommissionRecord::factory()->create();

        $record->update(['paid_at' => now(), 'status' => 'paid']);
        $record->refresh();

        $this->assertSame('pending', $record->status);
        $this->assertNull($record->paid_at);
    }
}
