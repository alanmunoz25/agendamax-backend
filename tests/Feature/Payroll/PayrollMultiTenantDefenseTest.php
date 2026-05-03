<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 3.1 — T-3.1.7: Multi-tenant defense in PayrollService.
 *
 * Covers DN-02: every public method that mutates payroll data asserts that the acting User
 * belongs to the same business as the target resource. Super admins bypass this check.
 */
class PayrollMultiTenantDefenseTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    private Business $businessA;

    private Business $businessB;

    private Employee $employeeA;

    /** Admin of business A — legitimate actor. */
    private User $adminA;

    /** Admin of business B — cross-business actor (should be rejected). */
    private User $adminB;

    /** Super admin — bypasses all business checks. */
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollService::class);

        $this->businessA = Business::factory()->create();
        $this->businessB = Business::factory()->create();

        $this->adminA = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->businessA->id,
        ]);

        $this->adminB = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->businessB->id,
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'business_id' => null,
        ]);

        $this->employeeA = Employee::factory()->create([
            'business_id' => $this->businessA->id,
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // approve()
    // -------------------------------------------------------------------------

    /** @test */
    public function test_approve_throws_when_user_business_mismatch(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageMatches('/Cross-business operation rejected/');

        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        // Admin of business B tries to approve a period belonging to business A.
        $this->service->approve($period, $this->adminB);
    }

    /** @test */
    public function test_approve_throws_does_not_change_record_status(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
        ]);

        try {
            $this->service->approve($period, $this->adminB);
        } catch (AuthorizationException) {
            // Expected — verify the record was not touched.
        }

        $record->refresh();
        $this->assertSame('draft', $record->status, 'Record must remain draft after rejected cross-business approve');
    }

    /** @test */
    public function test_approve_succeeds_when_user_is_super_admin(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
        ]);

        // Super admin operates across any business — must succeed.
        $this->service->approve($period, $this->superAdmin);

        $record->refresh();
        $this->assertSame('approved', $record->status);
    }

    /** @test */
    public function test_approve_succeeds_when_user_belongs_to_same_business(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
        ]);

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
        ]);

        // Same-business admin must succeed.
        $this->service->approve($period, $this->adminA);

        $record->refresh();
        $this->assertSame('approved', $record->status);
    }

    // -------------------------------------------------------------------------
    // markPaid()
    // -------------------------------------------------------------------------

    /** @test */
    public function test_mark_paid_throws_when_user_business_mismatch(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageMatches('/Cross-business operation rejected/');

        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
        ]);

        // Admin of business B tries to mark a record of business A as paid.
        $this->service->markPaid($record, $this->adminB, ['payment_method' => 'cash']);
    }

    /** @test */
    public function test_mark_paid_record_status_unchanged_on_mismatch(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
        ]);

        try {
            $this->service->markPaid($record, $this->adminB, ['payment_method' => 'cash']);
        } catch (AuthorizationException) {
            // Expected.
        }

        $record->refresh();
        $this->assertSame('approved', $record->status, 'Record must remain approved after rejected cross-business markPaid');
    }

    /** @test */
    public function test_mark_paid_succeeds_when_user_is_super_admin(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
        ]);

        $this->service->markPaid($record, $this->superAdmin, ['payment_method' => 'bank_transfer']);

        $record->refresh();
        $this->assertSame('paid', $record->status);
    }

    // -------------------------------------------------------------------------
    // void()
    // -------------------------------------------------------------------------

    /** @test */
    public function test_void_throws_when_user_business_mismatch(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageMatches('/Cross-business operation rejected/');

        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
        ]);

        // Admin of business B tries to void a record of business A.
        $this->service->void($record, $this->adminB, 'Cross-business void attempt');
    }

    /** @test */
    public function test_void_record_status_unchanged_on_mismatch(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
        ]);

        try {
            $this->service->void($record, $this->adminB, 'Unauthorized void');
        } catch (AuthorizationException) {
            // Expected.
        }

        $record->refresh();
        $this->assertSame('draft', $record->status, 'Record must remain draft after rejected cross-business void');
    }

    /** @test */
    public function test_void_succeeds_when_user_is_super_admin(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
        ]);

        $this->service->void($record, $this->superAdmin, 'Super admin override');

        $record->refresh();
        $this->assertSame('voided', $record->status);
    }

    // -------------------------------------------------------------------------
    // addAdjustment()
    // -------------------------------------------------------------------------

    /** @test */
    public function test_add_adjustment_throws_when_user_business_mismatch(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageMatches('/Cross-business operation rejected/');

        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        // Admin of business B tries to add an adjustment to a period of business A.
        $this->service->addAdjustment(
            $period,
            $this->employeeA,
            'credit',
            100.00,
            'Cross-business adjustment attempt',
            $this->adminB
        );
    }

    /** @test */
    public function test_add_adjustment_no_record_created_on_mismatch(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        try {
            $this->service->addAdjustment(
                $period,
                $this->employeeA,
                'credit',
                100.00,
                'Should not persist',
                $this->adminB
            );
        } catch (AuthorizationException) {
            // Expected.
        }

        $this->assertDatabaseMissing('payroll_adjustments', [
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employeeA->id,
        ]);
    }

    /** @test */
    public function test_add_adjustment_succeeds_when_user_is_super_admin(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);

        $adj = $this->service->addAdjustment(
            $period,
            $this->employeeA,
            'credit',
            50.00,
            'Super admin adjustment',
            $this->superAdmin
        );

        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $adj->id,
            'payroll_period_id' => $period->id,
            'type' => 'credit',
        ]);
    }

    // -------------------------------------------------------------------------
    // createPeriod() — TD-016
    // -------------------------------------------------------------------------

    /** @test */
    public function test_create_period_throws_when_user_business_mismatch(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageMatches('/Cross-business operation rejected/');

        // Admin of business B tries to create a period for business A.
        $this->service->createPeriod(
            $this->businessA,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
            $this->adminB
        );
    }

    /** @test */
    public function test_create_period_succeeds_for_super_admin(): void
    {
        // Super admin may create a period for any business.
        $period = $this->service->createPeriod(
            $this->businessA,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            $this->superAdmin
        );

        $this->assertInstanceOf(PayrollPeriod::class, $period);
        $this->assertSame('open', $period->status);
        $this->assertSame($this->businessA->id, $period->business_id);
    }

    // -------------------------------------------------------------------------
    // generateRecords() — TD-016
    // -------------------------------------------------------------------------

    /** @test */
    public function test_generate_records_throws_when_user_business_mismatch(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageMatches('/Cross-business operation rejected/');

        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
        ]);

        // Admin of business B tries to generate records for a period of business A.
        $this->service->generateRecords($period, $this->adminB);
    }

    /** @test */
    public function test_generate_records_succeeds_for_super_admin(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
        ]);

        // Super admin may generate records for any business period.
        // No active employees with activity — returns empty collection without throwing.
        $records = $this->service->generateRecords($period, $this->superAdmin);

        $this->assertCount(0, $records);
    }

    // -------------------------------------------------------------------------
    // addAdjustment() — same business
    // -------------------------------------------------------------------------

    /** @test */
    public function test_add_adjustment_succeeds_when_user_belongs_to_same_business(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->businessA)->open()->create([
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
        ]);

        $adj = $this->service->addAdjustment(
            $period,
            $this->employeeA,
            'debit',
            25.00,
            'Same-business adjustment',
            $this->adminA
        );

        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $adj->id,
            'payroll_period_id' => $period->id,
            'type' => 'debit',
            'created_by' => $this->adminA->id,
        ]);
    }
}
