<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Produces a complete, consistent dataset for end-to-end mobile booking validation.
 *
 * Idempotent: safe to run multiple times without unique-constraint errors.
 *
 * Run with:
 *   ddev exec --dir /var/www/html/backend php artisan db:seed --class=MobileBookingDemoSeeder
 */
class MobileBookingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📱 Iniciando MobileBookingDemoSeeder...');

        $businessConfigs = $this->seedBusinesses();

        foreach ($businessConfigs as &$config) {
            $business = $config['model'];
            $this->command->info("📍 {$business->name}...");

            $categories = $this->seedCategories($business, $config['categories']);
            $services = $this->seedServices($business, $categories, $config['services']);
            $employees = $this->seedEmployees($business, $config['employees']);
            $this->seedSchedules($employees);
            $this->attachServicesToEmployees($employees, $services);

            $config['seeded_services'] = $services;
            $config['seeded_employees'] = $employees;
        }
        unset($config);

        $admins = $this->seedAdmins($businessConfigs);
        $clients = $this->seedClients();
        $this->seedAppointments($businessConfigs, $clients);

        $this->verifyConsistency($businessConfigs);
        $this->displaySummary($businessConfigs, $admins, $clients);
    }

    // ── Businesses ────────────────────────────────────────────────────────────

    /**
     * @return array<int, array{model: Business, categories: string[], employees: array<int, array{name: string, email: string, bio: string}>, services: array<int, array{name: string, category: string, duration: int, price: int}>}>
     */
    private function seedBusinesses(): array
    {
        $specs = [
            [
                'slug' => 'salon-bella-vista',
                'name' => 'Salón Bella Vista',
                'email' => 'contacto@bellavista.test',
                'admin_email' => 'admin@bellavista.test',
                'phone' => '809-555-0001',
                'address' => 'Av. 27 de Febrero #123, Santo Domingo',
                'invitation_code' => 'BELLAVISTA',
                'categories' => ['Cabello', 'Coloración', 'Manicura'],
                'employees' => [
                    ['name' => 'Ana García', 'email' => 'empleado1@bellavista.test', 'bio' => 'Especialista en coloración y peinados'],
                    ['name' => 'Carla Pérez', 'email' => 'empleado2@bellavista.test', 'bio' => 'Técnica en manicura y pedicura'],
                    ['name' => 'Marta López', 'email' => 'empleado3@bellavista.test', 'bio' => 'Estilista con 10 años de experiencia'],
                    ['name' => 'Rosa Montero', 'email' => 'empleado4@bellavista.test', 'bio' => 'Especialista en tratamientos capilares'],
                ],
                'services' => [
                    ['name' => 'Corte de cabello mujer', 'category' => 'Cabello', 'duration' => 45, 'price' => 350],
                    ['name' => 'Corte de cabello hombre', 'category' => 'Cabello', 'duration' => 30, 'price' => 200],
                    ['name' => 'Coloración completa', 'category' => 'Coloración', 'duration' => 120, 'price' => 800],
                    ['name' => 'Balayage', 'category' => 'Coloración', 'duration' => 180, 'price' => 1500],
                    ['name' => 'Manicura clásica', 'category' => 'Manicura', 'duration' => 45, 'price' => 250],
                    ['name' => 'Pedicura spa', 'category' => 'Manicura', 'duration' => 60, 'price' => 350],
                    ['name' => 'Tratamiento keratina', 'category' => 'Cabello', 'duration' => 150, 'price' => 2000],
                ],
            ],
            [
                'slug' => 'spa-serenidad',
                'name' => 'Spa Serenidad',
                'email' => 'info@spaserenidad.test',
                'admin_email' => 'admin@spaserenidad.test',
                'phone' => '809-555-0002',
                'address' => 'Calle El Conde #45, Santo Domingo',
                'invitation_code' => 'SERENIDAD',
                'categories' => ['Masajes', 'Facial', 'Bienestar'],
                'employees' => [
                    ['name' => 'Laura Sánchez', 'email' => 'empleado1@spaserenidad.test', 'bio' => 'Terapeuta certificada en masajes relajantes'],
                    ['name' => 'Patricia Díaz', 'email' => 'empleado2@spaserenidad.test', 'bio' => 'Especialista en tratamientos faciales orgánicos'],
                    ['name' => 'Isabel Ramos', 'email' => 'empleado3@spaserenidad.test', 'bio' => 'Masajista con técnicas tailandesas'],
                ],
                'services' => [
                    ['name' => 'Masaje sueco', 'category' => 'Masajes', 'duration' => 60, 'price' => 900],
                    ['name' => 'Masaje de tejido profundo', 'category' => 'Masajes', 'duration' => 90, 'price' => 1200],
                    ['name' => 'Masaje con piedras calientes', 'category' => 'Masajes', 'duration' => 75, 'price' => 1100],
                    ['name' => 'Facial hidratante', 'category' => 'Facial', 'duration' => 60, 'price' => 800],
                    ['name' => 'Facial anti-edad', 'category' => 'Facial', 'duration' => 75, 'price' => 1000],
                    ['name' => 'Exfoliación corporal', 'category' => 'Bienestar', 'duration' => 45, 'price' => 700],
                    ['name' => 'Aromaterapia', 'category' => 'Bienestar', 'duration' => 60, 'price' => 850],
                ],
            ],
            [
                'slug' => 'barberia-el-clasico',
                'name' => 'Barbería El Clásico',
                'email' => 'hola@elclasico.test',
                'admin_email' => 'admin@elclasico.test',
                'phone' => '809-555-0003',
                'address' => 'Calle Mella #200, Santiago',
                'invitation_code' => 'ELCLASICO',
                'categories' => ['Cortes', 'Barba', 'Combos'],
                'employees' => [
                    ['name' => 'Carlos Jiménez', 'email' => 'empleado1@elclasico.test', 'bio' => 'Barbero clásico con 15 años de experiencia'],
                    ['name' => 'Miguel Torres', 'email' => 'empleado2@elclasico.test', 'bio' => 'Especialista en fade y cortes modernos'],
                    ['name' => 'José Reyes', 'email' => 'empleado3@elclasico.test', 'bio' => 'Experto en afeitado con navaja'],
                ],
                'services' => [
                    ['name' => 'Corte clásico', 'category' => 'Cortes', 'duration' => 30, 'price' => 200],
                    ['name' => 'Corte fade', 'category' => 'Cortes', 'duration' => 45, 'price' => 300],
                    ['name' => 'Corte niños', 'category' => 'Cortes', 'duration' => 25, 'price' => 150],
                    ['name' => 'Arreglo de barba', 'category' => 'Barba', 'duration' => 20, 'price' => 150],
                    ['name' => 'Afeitado con navaja', 'category' => 'Barba', 'duration' => 30, 'price' => 250],
                    ['name' => 'Corte + Barba', 'category' => 'Combos', 'duration' => 60, 'price' => 400],
                    ['name' => 'Corte + Barba + Cejas', 'category' => 'Combos', 'duration' => 75, 'price' => 500],
                ],
            ],
            [
                'slug' => 'centro-belleza-aurora',
                'name' => 'Centro de Belleza Aurora',
                'email' => 'contacto@aurora.test',
                'admin_email' => 'admin@aurora.test',
                'phone' => '809-555-0004',
                'address' => 'Av. Luperón #300, Santo Domingo',
                'invitation_code' => 'AURORABELLA',
                'categories' => ['Cabello', 'Estética', 'Depilación'],
                'employees' => [
                    ['name' => 'Sandra Vargas', 'email' => 'empleado1@aurora.test', 'bio' => 'Estilista profesional, especialista en bodas'],
                    ['name' => 'Daniela Morales', 'email' => 'empleado2@aurora.test', 'bio' => 'Cosmetóloga certificada'],
                    ['name' => 'Fernanda Cruz', 'email' => 'empleado3@aurora.test', 'bio' => 'Especialista en depilación y cejas'],
                    ['name' => 'Viviana Herrera', 'email' => 'empleado4@aurora.test', 'bio' => 'Técnica en extensiones y uñas'],
                ],
                'services' => [
                    ['name' => 'Corte y peinado', 'category' => 'Cabello', 'duration' => 60, 'price' => 450],
                    ['name' => 'Tinte de cabello', 'category' => 'Cabello', 'duration' => 100, 'price' => 700],
                    ['name' => 'Limpieza facial', 'category' => 'Estética', 'duration' => 45, 'price' => 500],
                    ['name' => 'Maquillaje profesional', 'category' => 'Estética', 'duration' => 60, 'price' => 800],
                    ['name' => 'Depilación piernas', 'category' => 'Depilación', 'duration' => 30, 'price' => 400],
                    ['name' => 'Depilación axila', 'category' => 'Depilación', 'duration' => 15, 'price' => 200],
                    ['name' => 'Diseño de cejas', 'category' => 'Depilación', 'duration' => 20, 'price' => 150],
                    ['name' => 'Lifting de pestañas', 'category' => 'Estética', 'duration' => 60, 'price' => 600],
                ],
            ],
            [
                'slug' => 'estudio-unas-glam',
                'name' => 'Estudio de Uñas Glam',
                'email' => 'glam@estudionails.test',
                'admin_email' => 'admin@glamnails.test',
                'phone' => '809-555-0005',
                'address' => 'Calle Las Damas #15, Zona Colonial',
                'invitation_code' => 'GLAMNAILS',
                'categories' => ['Manicura', 'Pedicura', 'Diseño de Uñas'],
                'employees' => [
                    ['name' => 'Valentina Núñez', 'email' => 'empleado1@glamnails.test', 'bio' => 'Nail artist con estilo propio'],
                    ['name' => 'Alejandra Ríos', 'email' => 'empleado2@glamnails.test', 'bio' => 'Especialista en nail art y extensiones'],
                    ['name' => 'Paola Medina', 'email' => 'empleado3@glamnails.test', 'bio' => 'Técnica certificada en gel y acrílico'],
                ],
                'services' => [
                    ['name' => 'Manicura clásica', 'category' => 'Manicura', 'duration' => 30, 'price' => 200],
                    ['name' => 'Manicura semi-permanente', 'category' => 'Manicura', 'duration' => 45, 'price' => 350],
                    ['name' => 'Uñas en gel', 'category' => 'Manicura', 'duration' => 60, 'price' => 500],
                    ['name' => 'Uñas en acrílico', 'category' => 'Manicura', 'duration' => 75, 'price' => 600],
                    ['name' => 'Pedicura clásica', 'category' => 'Pedicura', 'duration' => 45, 'price' => 300],
                    ['name' => 'Pedicura spa', 'category' => 'Pedicura', 'duration' => 60, 'price' => 400],
                    ['name' => 'Nail art (diseño)', 'category' => 'Diseño de Uñas', 'duration' => 30, 'price' => 150],
                    ['name' => 'Extensiones de uñas', 'category' => 'Diseño de Uñas', 'duration' => 90, 'price' => 800],
                ],
            ],
        ];

        return collect($specs)->map(function (array $spec): array {
            $model = Business::firstOrCreate(
                ['slug' => $spec['slug']],
                [
                    'name' => $spec['name'],
                    'email' => $spec['email'],
                    'phone' => $spec['phone'],
                    'address' => $spec['address'],
                    'invitation_code' => $spec['invitation_code'],
                    'status' => 'active',
                    'timezone' => 'America/Santo_Domingo',
                    'loyalty_stamps_required' => 10,
                    'loyalty_reward_description' => 'Servicio gratis al acumular 10 sellos',
                ]
            );

            $spec['model'] = $model;

            return $spec;
        })->all();
    }

    // ── Categories ────────────────────────────────────────────────────────────

    /**
     * @param  string[]  $names
     * @return array<string, ServiceCategory>
     */
    private function seedCategories(Business $business, array $names): array
    {
        $categories = [];

        foreach ($names as $i => $name) {
            $categories[$name] = ServiceCategory::firstOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                [
                    'slug' => Str::slug($name).'-'.$business->id,
                    'description' => $name,
                    'sort_order' => $i + 1,
                    'is_active' => true,
                ]
            );
        }

        return $categories;
    }

    // ── Services ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, ServiceCategory>  $categories
     * @param  array<int, array{name: string, category: string, duration: int, price: int}>  $specs
     * @return Service[]
     */
    private function seedServices(Business $business, array $categories, array $specs): array
    {
        return collect($specs)->map(function (array $spec) use ($business, $categories): Service {
            $category = $categories[$spec['category']] ?? null;

            return Service::firstOrCreate(
                ['business_id' => $business->id, 'name' => $spec['name']],
                [
                    'description' => $spec['name'],
                    'duration' => $spec['duration'],
                    'price' => $spec['price'],
                    'category' => $spec['category'],
                    'service_category_id' => $category?->id,
                    'is_active' => true,
                ]
            );
        })->all();
    }

    // ── Employees ─────────────────────────────────────────────────────────────

    /**
     * @param  array<int, array{name: string, email: string, bio: string}>  $specs
     * @return Employee[]
     */
    private function seedEmployees(Business $business, array $specs): array
    {
        return collect($specs)->map(function (array $spec) use ($business): Employee {
            $user = User::firstOrCreate(
                ['email' => $spec['email']],
                [
                    'name' => $spec['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'role' => 'employee',
                    'business_id' => $business->id,
                ]
            );

            return Employee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_id' => $business->id,
                    'bio' => $spec['bio'],
                    'is_active' => true,
                ]
            );
        })->all();
    }

    // ── Schedules ─────────────────────────────────────────────────────────────

    /**
     * Creates Mon-Fri 09:00-18:00, Sat 09:00-13:00, Sun unavailable.
     *
     * @param  Employee[]  $employees
     */
    private function seedSchedules(array $employees): void
    {
        $slots = [
            ['day' => 0, 'start' => '09:00', 'end' => '13:00', 'available' => false], // Sun
            ['day' => 1, 'start' => '09:00', 'end' => '18:00', 'available' => true],  // Mon
            ['day' => 2, 'start' => '09:00', 'end' => '18:00', 'available' => true],  // Tue
            ['day' => 3, 'start' => '09:00', 'end' => '18:00', 'available' => true],  // Wed
            ['day' => 4, 'start' => '09:00', 'end' => '18:00', 'available' => true],  // Thu
            ['day' => 5, 'start' => '09:00', 'end' => '18:00', 'available' => true],  // Fri
            ['day' => 6, 'start' => '09:00', 'end' => '13:00', 'available' => true],  // Sat
        ];

        foreach ($employees as $employee) {
            foreach ($slots as $slot) {
                EmployeeSchedule::firstOrCreate(
                    ['employee_id' => $employee->id, 'day_of_week' => $slot['day']],
                    [
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'is_available' => $slot['available'],
                    ]
                );
            }
        }
    }

    // ── Employee ↔ Service pivot ───────────────────────────────────────────────

    /**
     * First employee handles all services; others handle at least 60% of them.
     * Guarantees every service has ≥1 employee.
     *
     * @param  Employee[]  $employees
     * @param  Service[]  $services
     */
    private function attachServicesToEmployees(array $employees, array $services): void
    {
        if (empty($employees) || empty($services)) {
            return;
        }

        $allIds = collect($services)->pluck('id')->toArray();

        // First employee covers all services — guarantees every service has at least one.
        $employees[0]->services()->syncWithoutDetaching($allIds);

        foreach (array_slice($employees, 1) as $employee) {
            $count = max(2, (int) ceil(count($allIds) * 0.6));
            $subset = collect($allIds)->shuffle()->take(min($count, count($allIds)))->toArray();
            $employee->services()->syncWithoutDetaching($subset);
        }
    }

    // ── Admins ────────────────────────────────────────────────────────────────

    /**
     * Creates one business_admin user per business.
     *
     * @param  array<int, array{model: Business, admin_email: string, name: string}>  $businessConfigs
     * @return User[]
     */
    private function seedAdmins(array $businessConfigs): array
    {
        return collect($businessConfigs)->map(function (array $config): User {
            $business = $config['model'];

            $user = User::where('email', $config['admin_email'])->first();
            if (! $user) {
                $user = new User;
                $user->fill([
                    'name' => 'Admin '.$business->name,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]);
                $user->forceFill([
                    'email' => $config['admin_email'],
                    'role' => 'business_admin',
                    'business_id' => $business->id,
                ])->save();
            }

            return $user;
        })->all();
    }

    // ── Clients ───────────────────────────────────────────────────────────────

    /**
     * @return User[]
     */
    private function seedClients(): array
    {
        $clientSpecs = [
            ['name' => 'Cliente Uno',  'email' => 'cliente1@test.com', 'phone' => '809-555-1001'],
            ['name' => 'Cliente Dos',  'email' => 'cliente2@test.com', 'phone' => '809-555-1002'],
            ['name' => 'Cliente Tres', 'email' => 'cliente3@test.com', 'phone' => '809-555-1003'],
        ];

        return collect($clientSpecs)->map(function (array $spec): User {
            $user = User::where('email', $spec['email'])->first();
            if (! $user) {
                $user = new User;
                $user->fill([
                    'name' => $spec['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'phone' => $spec['phone'],
                ]);
                $user->forceFill([
                    'email' => $spec['email'],
                    'role' => 'client',
                ])->save();
            }

            return $user;
        })->all();
    }

    // ── Appointments ──────────────────────────────────────────────────────────

    /**
     * Creates 6 appointments per business (3 past, 3 future) with mixed statuses.
     * Skips businesses that already have appointments to stay idempotent.
     *
     * @param  array<int, array{model: Business, seeded_employees?: Employee[], seeded_services?: Service[]}>  $businessConfigs
     * @param  User[]  $clients
     */
    private function seedAppointments(array $businessConfigs, array $clients): void
    {
        foreach ($businessConfigs as $config) {
            $business = $config['model'];

            if (Appointment::where('business_id', $business->id)->exists()) {
                continue;
            }

            $employees = $config['seeded_employees'] ?? [];

            if (empty($employees)) {
                continue;
            }

            $pastStatuses = ['completed', 'cancelled'];
            $futureStatuses = ['pending', 'confirmed'];

            for ($i = 0; $i < 6; $i++) {
                $employee = $employees[$i % count($employees)];
                $service = $employee->services()->first();

                if (! $service) {
                    continue;
                }

                $isPast = $i < 3;
                $daysOffset = $isPast ? -rand(2, 30) : rand(1, 14);
                $hour = rand(9, 16);

                $scheduledAt = Carbon::now()->addDays($daysOffset)->setTime($hour, 0, 0);
                $scheduledUntil = $scheduledAt->copy()->addMinutes($service->duration);
                $status = $isPast
                    ? $pastStatuses[$i % count($pastStatuses)]
                    : $futureStatuses[$i % count($futureStatuses)];

                Appointment::create([
                    'business_id' => $business->id,
                    'client_id' => $clients[$i % count($clients)]->id,
                    'employee_id' => $employee->id,
                    'service_id' => $service->id,
                    'scheduled_at' => $scheduledAt,
                    'scheduled_until' => $scheduledUntil,
                    'status' => $status,
                ]);
            }
        }
    }

    // ── Consistency guard ─────────────────────────────────────────────────────

    /**
     * @param  array<int, array{model: Business}>  $businessConfigs
     */
    private function verifyConsistency(array $businessConfigs): void
    {
        $businessIds = collect($businessConfigs)->pluck('model.id')->toArray();

        $businessesWithoutServices = Business::whereIn('id', $businessIds)
            ->doesntHave('services')
            ->count();

        $servicesWithoutEmployees = Service::whereIn('business_id', $businessIds)
            ->doesntHave('employees')
            ->count();

        $employeesWithoutSchedule = Employee::whereIn('business_id', $businessIds)
            ->doesntHave('schedules')
            ->count();

        if ($businessesWithoutServices > 0 || $servicesWithoutEmployees > 0 || $employeesWithoutSchedule > 0) {
            throw new \RuntimeException(
                'Seeder produjo datos inconsistentes: '
                ."businesses sin services={$businessesWithoutServices}, "
                ."services sin employees={$servicesWithoutEmployees}, "
                ."employees sin schedule={$employeesWithoutSchedule}"
            );
        }

        $this->command->info('✅ Verificación de consistencia: OK');
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    /**
     * @param  array<int, array{model: Business}>  $businessConfigs
     * @param  User[]  $admins
     * @param  User[]  $clients
     */
    private function displaySummary(array $businessConfigs, array $admins, array $clients): void
    {
        $this->command->newLine();
        $this->command->info('📊 Resumen:');
        $this->command->table(
            ['Negocio', 'Código', 'Servicios', 'Empleados', 'Citas'],
            collect($businessConfigs)->map(function (array $config): array {
                $b = $config['model'];

                return [
                    $b->name,
                    $b->invitation_code,
                    Service::where('business_id', $b->id)->count(),
                    Employee::where('business_id', $b->id)->count(),
                    Appointment::where('business_id', $b->id)->count(),
                ];
            })->toArray()
        );

        $this->command->newLine();
        $this->command->info('🔑 Credenciales (password: password):');
        $this->command->table(
            ['Rol', 'Email', 'Negocio'],
            array_merge(
                collect($admins)->map(function (User $u) use ($businessConfigs): array {
                    $businessName = collect($businessConfigs)
                        ->first(fn ($c) => $c['model']->id === $u->business_id)['model']->name ?? '—';

                    return ['Admin', $u->email, $businessName];
                })->toArray(),
                collect($clients)->map(fn (User $u) => ['Cliente', $u->email, '—'])->toArray(),
                collect($businessConfigs)->flatMap(function (array $config): array {
                    $b = $config['model'];

                    return User::where('business_id', $b->id)
                        ->where('role', 'employee')
                        ->get()
                        ->map(fn (User $u) => ['Empleado', $u->email, $b->name])
                        ->toArray();
                })->toArray()
            )
        );

        $this->command->newLine();
        $this->command->info('✨ MobileBookingDemoSeeder completado.');
    }
}
