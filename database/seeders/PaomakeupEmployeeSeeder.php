<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaomakeupEmployeeSeeder extends Seeder
{
    private const BUSINESS_ID = 4;

    /**
     * Seed employees, schedules, and service assignments for Paomakeup Beauty Salon.
     */
    public function run(): void
    {
        $this->command->info('Seeding employees for Paomakeup Beauty Salon (business_id=4)...');

        $allServiceIds = Service::where('business_id', self::BUSINESS_ID)
            ->pluck('id')
            ->toArray();

        if (empty($allServiceIds)) {
            $this->command->error('No services found for business_id=4. Aborting.');

            return;
        }

        // Group service IDs by category for specialist assignments
        $servicesByCategory = Service::where('business_id', self::BUSINESS_ID)
            ->get(['id', 'service_category_id'])
            ->groupBy('service_category_id')
            ->map(fn ($group) => $group->pluck('id')->toArray());

        $employees = [
            [
                'name' => 'Paola Mendez',
                'email' => 'paola@paomakeup.com',
                'bio' => 'Fundadora y maquilladora profesional con 10+ años de experiencia',
                'categories' => [32, 33, 35, 36, 37, 38, 54, 55, 56, 45], // Maquillaje, Fantasia, Brows, Lashes, Retoques, Depilacion, Semipermanente, Cejas, Labios, Otros
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'maria@paomakeup.com',
                'bio' => 'Especialista en pestañas, cejas y retoques',
                'categories' => [35, 36, 37, 55], // Brows, Lashes, Retoques, Cejas
            ],
            [
                'name' => 'Carmen Rivera',
                'email' => 'carmen@paomakeup.com',
                'bio' => 'Técnica de uñas certificada en gel, acrílico y press-on',
                'categories' => [39, 40, 41, 42, 43, 44], // Beauty Nails, Manicure & Pedicure, Pinturas, Gel Builder, Press On, Retiros
            ],
            [
                'name' => 'Ana Garcia',
                'email' => 'ana@paomakeup.com',
                'bio' => 'Estilista capilar con especialidad en color y tratamientos',
                'categories' => [46, 47, 48, 49, 50, 51, 52, 53], // Beauty Hair, Tratamiento pelo, Lavados, Tratamientos, Rizos, Cortes, Peinados, Color
            ],
        ];

        foreach ($employees as $data) {
            // Skip if user already exists
            if (User::where('email', $data['email'])->where('business_id', self::BUSINESS_ID)->exists()) {
                $this->command->warn("  Skipping {$data['name']} (already exists)");

                continue;
            }

            $user = new User;
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => 'password',
            ]);
            $user->forceFill([
                'role' => 'employee',
                'business_id' => self::BUSINESS_ID,
            ])->save();

            $employee = Employee::create([
                'user_id' => $user->id,
                'business_id' => self::BUSINESS_ID,
                'bio' => $data['bio'],
                'is_active' => true,
            ]);

            // Create schedule: Mon-Sat, 9:00 AM - 6:00 PM
            foreach (range(1, 6) as $dayOfWeek) { // 1=Monday ... 6=Saturday
                EmployeeSchedule::create([
                    'employee_id' => $employee->id,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'is_available' => true,
                ]);
            }

            // Sunday off
            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'day_of_week' => 0,
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'is_available' => false,
            ]);

            // Attach services based on speciality categories
            $serviceIds = collect($data['categories'])
                ->flatMap(fn ($catId) => $servicesByCategory->get($catId, []))
                ->unique()
                ->toArray();

            $employee->services()->attach($serviceIds);

            $this->command->info("  Created {$data['name']}: {$employee->id} with ".count($serviceIds).' services, Mon-Sat 9am-6pm');
        }

        $this->command->info('Paomakeup employees seeded successfully!');
    }
}
