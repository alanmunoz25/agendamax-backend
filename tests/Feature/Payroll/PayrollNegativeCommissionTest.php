<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Exceptions\Payroll\NegativeCommissionDetectedException;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Service;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 3.1 — T-3.1.6 (DN-06)
 *
 * Verifies the double defense against negative commission amounts:
 * 1. DB-level CHECK constraint (MySQL/MariaDB) — blocks insert at the database layer.
 * 2. Service-layer NegativeCommissionDetectedException — guards all drivers (including SQLite in tests).
 */
class PayrollNegativeCommissionTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    private Business $business;

    private User $adminUser;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollService::class);
        $this->adminUser = User::factory()->create(['role' => 'super_admin']);
        $this->business = Business::factory()->create();

        $this->period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-31',
        ]);
    }

    /** @test */
    public function test_commission_amount_zero_is_allowed(): void
    {
        $commission = CommissionRecord::factory()->create([
            'commission_amount' => 0,
        ]);

        $commission->refresh();

        $this->assertDatabaseHas('commission_records', [
            'id' => $commission->id,
            'commission_amount' => '0.00',
        ]);
    }

    /** @test */
    public function test_commission_amount_positive_is_allowed(): void
    {
        $commission = CommissionRecord::factory()->create([
            'commission_amount' => 50.25,
        ]);

        $commission->refresh();

        $this->assertDatabaseHas('commission_records', [
            'id' => $commission->id,
            'commission_amount' => '50.25',
        ]);
    }

    /** @test */
    public function test_inserting_negative_commission_amount_is_blocked_or_guarded(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'sqlite') {
            // MySQL/MariaDB: DB CHECK constraint should reject the insert outright.
            $this->expectException(\Throwable::class);

            CommissionRecord::factory()->create([
                'commission_amount' => -5,
            ]);
        } else {
            // SQLite: the constraint does not apply at DB level.
            // Verify instead that the service-layer guard (NegativeCommissionDetectedException)
            // fires during generateRecords — covered by the dedicated test below.
            $this->assertTrue(true, 'SQLite skip: DB-level constraint not enforced; service-layer guard is tested separately.');
        }
    }

    /** @test */
    public function test_generate_records_throws_when_negative_commission_present(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped(
                'On MariaDB/MySQL the DB CHECK constraint prevents forcing a negative amount via raw UPDATE. '.
                'DB-level enforcement is verified by test_inserting_negative_commission_amount_is_blocked.'
            );
        }

        $employee = $this->createActiveEmployee(base_salary: 0);

        // Create a commission record normally, then force the amount to negative via raw SQL
        // to bypass Eloquent casts. Only works on SQLite (no CHECK constraint).
        $commission = $this->insertCommissionForEmployee($employee, amount: '10.00');

        DB::statement('UPDATE commission_records SET commission_amount = -5 WHERE id = ?', [$commission->id]);

        $this->expectException(NegativeCommissionDetectedException::class);
        $this->expectExceptionMessageMatches('/Negative commission amount detected/');

        $this->service->generateRecords($this->period, $this->adminUser);

        // No PayrollRecord should have been created for this employee.
        $this->assertDatabaseMissing('payroll_records', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employee->id,
        ]);
    }

    /** @test */
    public function test_generate_records_exception_exposes_commission_context(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped(
                'On MariaDB/MySQL the DB CHECK constraint prevents forcing a negative amount via raw UPDATE. '.
                'DB-level enforcement is verified by test_inserting_negative_commission_amount_is_blocked.'
            );
        }

        $employee = $this->createActiveEmployee(base_salary: 0);

        $commission = $this->insertCommissionForEmployee($employee, amount: '25.00');
        DB::statement('UPDATE commission_records SET commission_amount = -5 WHERE id = ?', [$commission->id]);

        try {
            $this->service->generateRecords($this->period, $this->adminUser);
            $this->fail('Expected NegativeCommissionDetectedException was not thrown.');
        } catch (NegativeCommissionDetectedException $e) {
            $this->assertSame($commission->id, $e->commission()->id);
            $this->assertSame($this->business->id, $e->commission()->business_id);
            $this->assertStringContainsString((string) $commission->id, $e->getMessage());
            $this->assertStringContainsString((string) $this->business->id, $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create an active employee attached to $this->business with the given base salary.
     */
    private function createActiveEmployee(float|int $base_salary): Employee
    {
        $user = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        return Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $user->id,
            'is_active' => true,
            'base_salary' => $base_salary,
        ]);
    }

    /**
     * Insert a CommissionRecord directly via DB for the given employee inside $this->period's date range.
     * Returns a fresh CommissionRecord model instance.
     */
    private function insertCommissionForEmployee(Employee $employee, string $amount): CommissionRecord
    {
        $service = Service::factory()->create(['business_id' => $this->business->id]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
        ]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $id = DB::table('commission_records')->insertGetId([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'service_id' => $service->id,
            'commission_rule_id' => null,
            'service_price_snapshot' => '100.00',
            'rule_type_snapshot' => 'percentage',
            'rule_value_snapshot' => '10.00',
            'commission_amount' => $amount,
            'status' => 'pending',
            'payroll_period_id' => null,
            'generated_at' => now()->toDateTimeString(),
            'created_at' => Carbon::parse('2026-05-15')->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        return CommissionRecord::withoutGlobalScopes()->findOrFail($id);
    }
}
