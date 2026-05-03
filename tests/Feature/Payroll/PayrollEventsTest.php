<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Events\Payroll\PayrollAdjustmentCreated;
use App\Events\Payroll\PayrollPeriodClosed;
use App\Events\Payroll\PayrollRecordApproved;
use App\Events\Payroll\PayrollRecordPaid;
use App\Events\Payroll\PayrollRecordVoided;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * HTTP tests for payroll event dispatch (Fase 4 — PASO 7).
 *
 * Covers: PayrollRecordApproved, PayrollRecordPaid, PayrollRecordVoided,
 *         PayrollAdjustmentCreated, PayrollPeriodClosed broadcast events.
 */
class PayrollEventsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private Employee $employee;

    private PayrollPeriod $openPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->openPeriod = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);
    }

    // ── approve ───────────────────────────────────────────────────────────────

    /** @test */
    public function test_approve_dispatches_payroll_record_approved_for_each_draft_record(): void
    {
        Event::fake();

        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/payroll/periods/{$this->openPeriod->id}/approve")
            ->assertRedirect();

        Event::assertDispatched(PayrollRecordApproved::class, 1);
        Event::assertDispatched(PayrollRecordApproved::class, function (PayrollRecordApproved $event) {
            return $event->record->employee_id === $this->employee->id;
        });
    }

    /** @test */
    public function test_approve_dispatches_one_event_per_draft_record(): void
    {
        Event::fake();

        $anotherEmployeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);
        $anotherEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $anotherEmployeeUser->id,
        ]);

        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
        ]);
        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $anotherEmployee->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/payroll/periods/{$this->openPeriod->id}/approve")
            ->assertRedirect();

        Event::assertDispatched(PayrollRecordApproved::class, 2);
    }

    /** @test */
    public function test_approve_does_not_dispatch_event_on_failure(): void
    {
        Event::fake();

        // Period with no records → approve() throws InvalidPayrollTransitionException
        $this->actingAs($this->admin)
            ->post("/payroll/periods/{$this->openPeriod->id}/approve")
            ->assertRedirect();

        Event::assertNotDispatched(PayrollRecordApproved::class);
    }

    // ── markPaid ──────────────────────────────────────────────────────────────

    /** @test */
    public function test_mark_paid_dispatches_payroll_record_paid(): void
    {
        Event::fake();

        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '1500.00',
        ]);

        $this->actingAs($this->admin)
            ->post("/payroll/records/{$record->id}/mark-paid", [
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect();

        Event::assertDispatched(PayrollRecordPaid::class, function (PayrollRecordPaid $event) use ($record) {
            return $event->record->id === $record->id
                && $event->record->payment_method === 'bank_transfer';
        });
    }

    /** @test */
    public function test_mark_paid_dispatches_period_closed_when_last_record_paid(): void
    {
        Event::fake();

        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/payroll/records/{$record->id}/mark-paid", [
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        Event::assertDispatched(PayrollRecordPaid::class);
        Event::assertDispatched(PayrollPeriodClosed::class, function (PayrollPeriodClosed $event) {
            return $event->period->id === $this->openPeriod->id;
        });
    }

    /** @test */
    public function test_mark_paid_does_not_dispatch_period_closed_when_other_records_pending(): void
    {
        Event::fake();

        $anotherEmployeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);
        $anotherEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $anotherEmployeeUser->id,
        ]);

        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
        ]);

        // Second record still in draft → period won't auto-close
        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $anotherEmployee->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/payroll/records/{$record->id}/mark-paid", [
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        Event::assertDispatched(PayrollRecordPaid::class);
        Event::assertNotDispatched(PayrollPeriodClosed::class);
    }

    // ── void ──────────────────────────────────────────────────────────────────

    /** @test */
    public function test_void_dispatches_payroll_record_voided(): void
    {
        Event::fake();

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/payroll/records/{$record->id}/void", [
                'reason' => 'Error en generación del record de prueba',
            ])
            ->assertRedirect();

        Event::assertDispatched(PayrollRecordVoided::class, function (PayrollRecordVoided $event) use ($record) {
            return $event->record->id === $record->id;
        });
    }

    /** @test */
    public function test_void_from_paid_dispatches_adjustment_created_for_compensation(): void
    {
        Event::fake();

        // Create a next open period for the compensation
        $nextPeriod = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
            'status' => 'open',
        ]);

        $record = PayrollRecord::factory()->paid()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
            'gross_total' => '500.00',
        ]);

        $this->actingAs($this->admin)
            ->post("/payroll/records/{$record->id}/void", [
                'reason' => 'Error en el pago registrado previamente',
            ])
            ->assertRedirect();

        Event::assertDispatched(PayrollRecordVoided::class);
        Event::assertDispatched(PayrollAdjustmentCreated::class, function (PayrollAdjustmentCreated $event) use ($nextPeriod) {
            return $event->adjustment->payroll_period_id === $nextPeriod->id
                && $event->adjustment->type === 'debit'
                && str_starts_with($event->adjustment->reason, 'Void compensation:');
        });
    }

    /** @test */
    public function test_void_from_draft_does_not_dispatch_adjustment_created(): void
    {
        Event::fake();

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/payroll/records/{$record->id}/void", [
                'reason' => 'Error en generación del record de prueba',
            ])
            ->assertRedirect();

        Event::assertDispatched(PayrollRecordVoided::class);
        Event::assertNotDispatched(PayrollAdjustmentCreated::class);
    }

    // ── addAdjustment ─────────────────────────────────────────────────────────

    /** @test */
    public function test_add_adjustment_dispatches_payroll_adjustment_created(): void
    {
        Event::fake();

        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->openPeriod->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/payroll/periods/{$this->openPeriod->id}/adjustments", [
                'employee_id' => $this->employee->id,
                'type' => 'credit',
                'amount' => '150.00',
                'reason' => 'Bono de productividad mensual',
            ])
            ->assertRedirect();

        Event::assertDispatched(PayrollAdjustmentCreated::class, function (PayrollAdjustmentCreated $event) {
            return $event->adjustment->employee_id === $this->employee->id
                && $event->adjustment->type === 'credit'
                && ! $event->adjustment->is_compensation ?? true;
        });
    }

    /** @test */
    public function test_add_adjustment_does_not_dispatch_on_validation_failure(): void
    {
        Event::fake();

        $this->actingAs($this->admin)
            ->post("/payroll/periods/{$this->openPeriod->id}/adjustments", [
                'employee_id' => $this->employee->id,
                'type' => 'invalid_type',
                'amount' => '-100',
                'reason' => 'X', // too short
            ]);

        Event::assertNotDispatched(PayrollAdjustmentCreated::class);
    }
}
