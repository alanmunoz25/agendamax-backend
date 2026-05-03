<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\Service;
use App\Models\Tip;
use App\Services\CommissionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AgendaMax Payroll Demo Seeder — seeds salary, commission rules, completed appointments, and tips.
 *
 * Prerequisites: MobileBookingDemoSeeder must have run first.
 *
 * Idempotent: safe to run multiple times without duplicate data.
 *
 * Run with:
 *   ddev exec --dir /var/www/html/backend php artisan db:seed --class=PayrollDemoSeeder
 */
class PayrollDemoSeeder extends Seeder
{
    private const DEMO_SLUGS = [
        'salon-bella-vista',
        'spa-serenidad',
        'barberia-el-clasico',
        'centro-belleza-aurora',
        'estudio-unas-glam',
    ];

    private const MIN_SALARY = 8000;

    private const MAX_SALARY = 18000;

    private const MIN_TIP = 50;

    private const MAX_TIP = 500;

    private const COMPLETED_TARGET_PER_BUSINESS = 20;

    private const LOOKBACK_DAYS = 60;

    public function __construct(
        private readonly CommissionService $commissionService
    ) {}

    public function run(): void
    {
        $this->command->info('Iniciando PayrollDemoSeeder...');

        $businesses = Business::withoutGlobalScopes()
            ->whereIn('slug', self::DEMO_SLUGS)
            ->get();

        if ($businesses->isEmpty()) {
            $this->command->error('No se encontraron los negocios demo. Corre MobileBookingDemoSeeder primero.');

            return;
        }

        $totalCommissions = 0;
        $totalTips = 0;

        foreach ($businesses as $business) {
            $this->command->info("  Procesando: {$business->name}");

            $employees = Employee::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->get();

            if ($employees->isEmpty()) {
                $this->command->warn("    Sin empleados en {$business->name}, saltando.");

                continue;
            }

            $this->assignBaseSalaries($employees);
            $this->createCommissionRules($business, $employees);
            $created = $this->createCompletedAppointments($business, $employees);
            $tipCount = $this->createTips($business);

            $totalCommissions += CommissionRule::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->count();
            $totalTips += $tipCount;

            $this->command->info("    Citas completadas creadas: {$created} | Tips creados: {$tipCount}");
        }

        $this->displaySummary($businesses->count(), $totalCommissions, $totalTips);
    }

    /**
     * Assign a random base_salary to each employee that does not have one yet.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Employee>  $employees
     */
    private function assignBaseSalaries(\Illuminate\Database\Eloquent\Collection $employees): void
    {
        foreach ($employees as $employee) {
            if ($employee->base_salary === null || (float) $employee->base_salary === 0.0) {
                $salary = rand(self::MIN_SALARY, self::MAX_SALARY);
                $employee->update(['base_salary' => $salary]);
            }
        }
    }

    /**
     * Create commission rules for the business. Creates:
     *   - One global percentage rule (10%) for the entire business.
     *   - One per-employee rule (12-15%) for each employee without a personal rule.
     */
    private function createCommissionRules(
        Business $business,
        \Illuminate\Database\Eloquent\Collection $employees
    ): void {
        // Global rule — applies to all employees if no specific rule overrides.
        CommissionRule::withoutGlobalScopes()->firstOrCreate(
            [
                'business_id' => $business->id,
                'employee_id' => null,
                'service_id' => null,
            ],
            [
                'type' => 'percentage',
                'value' => 10.00,
                'priority' => 0,
                'is_active' => true,
                'effective_from' => null,
                'effective_until' => null,
            ]
        );

        // Per-employee rules — higher percentage to simulate individual agreements.
        foreach ($employees as $employee) {
            $hasPersonalRule = CommissionRule::withoutGlobalScopes()
                ->where('business_id', $business->id)
                ->where('employee_id', $employee->id)
                ->whereNull('service_id')
                ->exists();

            if (! $hasPersonalRule) {
                CommissionRule::withoutGlobalScopes()->create([
                    'business_id' => $business->id,
                    'employee_id' => $employee->id,
                    'service_id' => null,
                    'type' => 'percentage',
                    'value' => (float) rand(12, 15),
                    'priority' => 1,
                    'is_active' => true,
                    'effective_from' => null,
                    'effective_until' => null,
                ]);
            }
        }
    }

    /**
     * Create completed appointments in the last 60 days for the given business.
     * Uses the appointment_services pivot so CommissionService can generate records.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Employee>  $employees
     */
    private function createCompletedAppointments(
        Business $business,
        \Illuminate\Database\Eloquent\Collection $employees
    ): int {
        $existingCompleted = Appointment::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('status', 'completed')
            ->count();

        if ($existingCompleted >= self::COMPLETED_TARGET_PER_BUSINESS) {
            $this->command->info("    Ya tiene {$existingCompleted} citas completadas, saltando creación.");

            return 0;
        }

        $needed = self::COMPLETED_TARGET_PER_BUSINESS - $existingCompleted;

        // Fetch a client to assign appointments to — take the first available client.
        $client = \App\Models\User::withoutGlobalScopes()
            ->where('role', 'client')
            ->first();

        if ($client === null) {
            $this->command->warn('    Sin clientes disponibles, saltando appointments.');

            return 0;
        }

        $created = 0;

        for ($i = 0; $i < $needed; $i++) {
            $employee = $employees[$i % $employees->count()];

            /** @var Service|null $service */
            $service = $employee->services()->inRandomOrder()->first();

            if ($service === null) {
                continue;
            }

            $daysBack = rand(1, self::LOOKBACK_DAYS);
            $hour = rand(9, 16);
            $scheduledAt = Carbon::now()->subDays($daysBack)->setTime($hour, 0, 0);
            $scheduledUntil = $scheduledAt->copy()->addMinutes($service->duration ?? 30);

            $appointment = DB::transaction(function () use (
                $business,
                $client,
                $employee,
                $service,
                $scheduledAt,
                $scheduledUntil
            ): Appointment {
                /** @var Appointment $appt */
                $appt = Appointment::withoutGlobalScopes()->forceCreate([
                    'business_id' => $business->id,
                    'client_id' => $client->id,
                    'employee_id' => $employee->id,
                    'service_id' => $service->id,
                    'scheduled_at' => $scheduledAt,
                    'scheduled_until' => $scheduledUntil,
                    'status' => 'completed',
                    'completed_at' => $scheduledAt,
                    'final_price' => $service->price,
                ]);

                // Create the appointment_services pivot row — required for CommissionService.
                DB::table('appointment_services')->insert([
                    'appointment_id' => $appt->id,
                    'service_id' => $service->id,
                    'employee_id' => $employee->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $appt;
            });

            // Generate commission records. The Observer dispatches a Job when status changes via update(),
            // but here we insert directly (forceCreate) bypassing the observer. We call the service explicitly.
            try {
                $this->commissionService->generateForAppointment($appointment);
            } catch (\Throwable $e) {
                $this->command->warn("    Commission generation failed for appointment {$appointment->id}: {$e->getMessage()}");
            }

            $created++;
        }

        return $created;
    }

    /**
     * Create tips for completed appointments in this business that do not already have one.
     * Only processes appointments that were seeded in the current run (no prior tips).
     * Approximately 50% of eligible appointments receive a tip.
     * Idempotent: appointments that already have a tip are skipped permanently.
     */
    private function createTips(Business $business): int
    {
        // Only target appointments that were seeded by PayrollDemoSeeder (have a pivot row
        // in appointment_services) and that have no tip yet.
        $appointments = Appointment::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('status', 'completed')
            ->whereHas('services')
            ->doesntHave('tips')
            ->with('employee')
            ->get();

        $created = 0;

        foreach ($appointments as $appointment) {
            if (! $appointment->employee_id) {
                continue;
            }

            // ~50% probability.
            if (rand(0, 1) === 0) {
                continue;
            }

            Tip::create([
                'business_id' => $business->id,
                'appointment_id' => $appointment->id,
                'employee_id' => $appointment->employee_id,
                'payroll_period_id' => null,
                'amount' => rand(self::MIN_TIP, self::MAX_TIP),
                'payment_method' => collect(['cash', 'card', 'transfer'])->random(),
                'notes' => null,
                'received_at' => $appointment->completed_at ?? $appointment->scheduled_at,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Display a summary table of counts after seeding.
     */
    private function displaySummary(int $businessCount, int $totalRules, int $totalTips): void
    {
        $this->command->newLine();
        $this->command->info('PayrollDemoSeeder completado.');
        $this->command->table(
            ['Concepto', 'Total'],
            [
                ['Negocios procesados', $businessCount],
                ['Employees con base_salary', Employee::withoutGlobalScopes()->where('base_salary', '>', 0)->count()],
                ['Commission rules', CommissionRule::withoutGlobalScopes()->count()],
                ['Appointments completados', Appointment::withoutGlobalScopes()->where('status', 'completed')->count()],
                ['Commission records', \App\Models\CommissionRecord::withoutGlobalScopes()->count()],
                ['Tips', Tip::withoutGlobalScopes()->count()],
            ]
        );
    }
}
