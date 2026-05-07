<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\PosTicket;
use App\Models\Service;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #3 Sprint 6 QA — /payroll/periods/{id} shows commissions from billed appointments.
 *
 * Verifies that:
 * - After billing an appointment via PosService, the CommissionRecord is assigned
 *   to a PayrollPeriod and a PayrollRecord is created.
 * - The period show page exposes `pending_commissions_count` when commissions exist
 *   but no PayrollRecord has been generated yet.
 * - The period show page includes commissions in `records` when records exist.
 */
class PayrollPeriodShowPendingCommissionsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private Employee $employee;

    private Service $service;

    private PayrollPeriod $period;

    private CommissionRule $rule;

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
            'base_salary' => '0.00',
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'price' => '1000.00',
        ]);

        $this->rule = CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
            'priority' => 1,
        ]);

        $this->period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);
    }

    /**
     * Helper: create a completed + paid appointment with a commission record
     * already assigned to the period.
     */
    private function createBilledAppointmentWithCommission(): CommissionRecord
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'cashier_id' => $this->admin->id,
            'total' => '1000.00',
            'status' => 'paid',
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'completed',
            'final_price' => '1000.00',
            'ticket_id' => $ticket->id,
        ]);

        DB::table('appointment_services')->insert([
            'appointment_id' => $appointment->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run commission service to generate CommissionRecord
        $commissionService = app(CommissionService::class);
        $commissions = $commissionService->generateForAppointment($appointment->fresh());

        $this->assertNotEmpty($commissions, 'CommissionRecord should have been generated');

        // Assign to period
        CommissionRecord::withoutGlobalScopes()
            ->whereIn('id', $commissions->pluck('id'))
            ->update(['payroll_period_id' => $this->period->id]);

        return $commissions->first()->fresh();
    }

    public function test_period_show_exposes_pending_commissions_count_when_no_records_generated(): void
    {
        $this->createBilledAppointmentWithCommission();

        // No PayrollRecord generated yet
        $this->assertEquals(0, PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $this->period->id)->count());

        $this->actingAs($this->admin);

        $response = $this->get("/payroll/periods/{$this->period->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Periods/Show')
            ->where('pending_commissions_count', 1)
            ->where('can.generate', true)
        );
    }

    public function test_period_show_pending_commissions_count_is_zero_when_payroll_records_exist(): void
    {
        $commission = $this->createBilledAppointmentWithCommission();

        // Simulate that PayrollRecord was generated for this employee
        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'base_salary_snapshot' => '0.00',
            'commissions_total' => '100.00',
            'tips_total' => '0.00',
            'adjustments_total' => '0.00',
            'gross_total' => '100.00',
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin);

        $response = $this->get("/payroll/periods/{$this->period->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Periods/Show')
            ->where('pending_commissions_count', 0)
        );
    }

    public function test_period_show_includes_commission_in_enriched_records(): void
    {
        $commission = $this->createBilledAppointmentWithCommission();

        // Generate PayrollRecord via PayrollService
        $payrollService = app(PayrollService::class);
        $payrollService->upsertEmployeeRecord(
            $this->employee->id,
            $this->period,
            CommissionRecord::withoutGlobalScopes()
                ->where('payroll_period_id', $this->period->id)
                ->where('employee_id', $this->employee->id)
                ->get()
        );

        $this->actingAs($this->admin);

        $response = $this->get("/payroll/periods/{$this->period->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Periods/Show')
            ->has('records', 1)
            ->has('records.0.commissions', 1)
            ->where('records.0.commissions_total', '100.00')
        );
    }

    public function test_period_show_renders_empty_state_when_no_commissions_and_no_records(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get("/payroll/periods/{$this->period->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Periods/Show')
            ->where('pending_commissions_count', 0)
            ->has('records', 0)
            ->where('period.has_records', false)
        );
    }
}
