<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

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
 * AgendaMax — Decisión #2: Comisiones retroactivas.
 *
 * Una comisión generada después del cierre del período A cae en el siguiente
 * período abierto B, usando commission_records.created_at como criterio de
 * inclusión. El período cerrado nunca se reabre.
 *
 * Referencia: .taskmaster/decisions/payroll-commissions.md § 2
 */
class PayrollRetroactiveCommissionTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    private Business $business;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollService::class);
        $this->adminUser = User::factory()->create(['role' => 'super_admin']);
        $this->business = Business::factory()->create();
    }

    /**
     * M-09 — Decisión #2 core: comisión con created_at en período B no contamina período A cerrado.
     *
     * Escenario:
     * - Período A (enero 2026): cerrado y pagado.
     * - Período B (febrero 2026): abierto.
     * - CommissionRecord creado con created_at en febrero (though appointment.completed_at en enero).
     * - generateRecords(B) debe incluir la comisión de febrero en B, no en A.
     */
    public function test_commission_created_after_period_a_closed_falls_into_period_b(): void
    {
        // 1. Setup: período A (enero 2026).
        $periodA = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-31',
        ]);

        // 2. Empleado activo con comisión EN ENERO — para que generateRecords(A) produzca records.
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => 0,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
        ]);

        $service = Service::factory()->create(['business_id' => $this->business->id]);

        $appointmentServiceIdJan = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Comisión de enero — inside period A.
        $commissionJan = CommissionRecord::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceIdJan,
            'commission_amount' => 100.00,
            'created_at' => Carbon::parse('2026-01-15 10:00:00'),
        ]);

        // 3. Generar records de A, aprobar y pagar → auto-cierra A.
        $this->service->generateRecords($periodA, $this->adminUser);
        $this->service->approve($periodA, $this->adminUser);

        $recordA = PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $periodA->id)
            ->first();

        $this->service->markPaid($recordA, $this->adminUser, ['payment_method' => 'cash']);

        // Verificar que A está cerrado.
        $periodA->refresh();
        $this->assertEquals('closed', $periodA->status, 'El período A debe estar closed después de marcar paid.');

        // Guardar el gross_total de A para verificar inmutabilidad después.
        $grossTotalA = $recordA->fresh()->gross_total;

        // 4. Crear período B (febrero 2026) — abierto.
        $periodB = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-02-01',
            'ends_on' => '2026-02-28',
        ]);

        // 5. Crear comisión con created_at en FEBRERO — appointment puede ser de enero,
        //    pero la comisión fue calculada tarde (en febrero). Decisión #2: cae en B.
        $appointmentLate = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
        ]);

        $serviceB = Service::factory()->create(['business_id' => $this->business->id]);

        $appointmentServiceIdFeb = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointmentLate->id,
            'service_id' => $serviceB->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Creamos la comisión primero con el timestamp actual, luego lo forzamos a febrero.
        $commissionFeb = CommissionRecord::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointmentLate->id,
            'appointment_service_id' => $appointmentServiceIdFeb,
            'commission_amount' => 200.00,
        ]);

        // Forzar created_at a febrero (simula comisión calculada tarde).
        DB::statement(
            'UPDATE commission_records SET created_at = ? WHERE id = ?',
            [Carbon::parse('2026-02-10 09:00:00')->toDateTimeString(), $commissionFeb->id]
        );
        $commissionFeb->refresh();

        $this->assertEquals(
            '2026-02-10',
            $commissionFeb->created_at->toDateString(),
            'El created_at de la comisión retroactiva debe ser en febrero.'
        );

        // 6. Generar records de B.
        $recordsB = $this->service->generateRecords($periodB, $this->adminUser);

        // 7. Assert: B incluye la comisión de febrero.
        $this->assertCount(1, $recordsB, 'Período B debe generar exactamente 1 record (el mismo empleado).');

        $recordB = $recordsB->first();
        $this->assertEquals('200.00', $recordB->commissions_total, 'El record de B debe incluir la comisión retroactiva de $200.');

        // La comisión de febrero ahora está asignada a B.
        $this->assertDatabaseHas('commission_records', [
            'id' => $commissionFeb->id,
            'payroll_period_id' => $periodB->id,
        ]);

        // 8. Assert: A NO fue modificado — sigue closed y gross_total intacto.
        $periodA->refresh();
        $this->assertEquals('closed', $periodA->status, 'Período A no debe reabrirse.');

        $recordA->refresh();
        $this->assertEquals(
            $grossTotalA,
            $recordA->gross_total,
            'El gross_total del record A no debe cambiar por la comisión retroactiva.'
        );

        // La comisión de enero sigue asignada a A y en estado paid.
        $this->assertDatabaseHas('commission_records', [
            'id' => $commissionJan->id,
            'payroll_period_id' => $periodA->id,
        ]);
    }

    /**
     * M-09 — Guard: intentar generar registros en período A cerrado está bloqueado.
     *
     * Complementa el test anterior verificando que el período A es inmutable:
     * no se puede re-generar aunque la comisión de enero siga existiendo en el sistema.
     */
    public function test_generate_records_on_closed_period_throws_period_not_open_exception(): void
    {
        // 1. Crear período A cerrado directamente vía factory (estado final — no open).
        $periodA = PayrollPeriod::factory()->forBusiness($this->business)->closed()->create([
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-31',
        ]);

        // 2. Intentar generateRecords sobre A — debe fallar.
        $this->expectException(\App\Exceptions\Payroll\PeriodNotOpenException::class);

        $this->service->generateRecords($periodA, $this->adminUser);
    }

    /**
     * M-09 — Comisión con created_at exactamente en el primer día de B (boundary check).
     *
     * Verifica que el rango inclusivo de B comienza el 2026-02-01 00:00:00.
     */
    public function test_commission_on_first_day_of_period_b_is_included(): void
    {
        // Período B abierto.
        $periodB = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-02-01',
            'ends_on' => '2026-02-28',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => 0,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
        ]);

        $service = Service::factory()->create(['business_id' => $this->business->id]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commission = CommissionRecord::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'commission_amount' => 75.00,
        ]);

        // Forzar created_at al primer instante del día de inicio de B.
        DB::statement(
            'UPDATE commission_records SET created_at = ? WHERE id = ?',
            [Carbon::parse('2026-02-01 00:00:00')->toDateTimeString(), $commission->id]
        );
        $commission->refresh();

        $records = $this->service->generateRecords($periodB, $this->adminUser);

        $this->assertCount(1, $records);
        $this->assertEquals('75.00', $records->first()->commissions_total);

        $this->assertDatabaseHas('commission_records', [
            'id' => $commission->id,
            'payroll_period_id' => $periodB->id,
        ]);
    }

    /**
     * M-09 — Comisión con created_at justo fuera del rango (un día antes del inicio de B)
     * no se incluye en B.
     *
     * Esto garantiza que la boundary exclusiva del lado inferior funciona correctamente:
     * una comisión de enero tampoco cae accidentalmente en febrero.
     */
    public function test_commission_one_day_before_period_b_start_is_excluded(): void
    {
        // Período B abierto.
        $periodB = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-02-01',
            'ends_on' => '2026-02-28',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => 0,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
        ]);

        $service = Service::factory()->create(['business_id' => $this->business->id]);

        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commission = CommissionRecord::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $employee->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'commission_amount' => 50.00,
        ]);

        // Forzar created_at al día anterior al inicio de B (31 de enero, fin del día).
        DB::statement(
            'UPDATE commission_records SET created_at = ? WHERE id = ?',
            [Carbon::parse('2026-01-31 23:59:59')->toDateTimeString(), $commission->id]
        );
        $commission->refresh();

        // generateRecords no debe encontrar empleados con actividad en B → colección vacía.
        $records = $this->service->generateRecords($periodB, $this->adminUser);

        $this->assertCount(0, $records, 'No debe haber records en B para una comisión del 31 de enero.');

        // La comisión debe seguir sin período asignado.
        $this->assertDatabaseHas('commission_records', [
            'id' => $commission->id,
            'payroll_period_id' => null,
        ]);
    }
}
