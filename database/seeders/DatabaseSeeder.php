<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create first business
        $business1 = Business::firstOrCreate(
            ['name' => 'Test Barber Shop'],
            [
                'slug' => 'test-barber-shop',
                'email' => 'contact@testbarber.com',
                'phone' => '+1234567890',
                'address' => '123 Main St, City, State 12345',
                'invitation_code' => 'BARBER123',
                'loyalty_stamps_required' => 10,
                'loyalty_reward_description' => 'Free haircut after 10 visits',
                'description' => 'Professional barber shop providing quality haircuts and grooming services.',
            ]
        );

        // Create second business for multi-tenant testing
        $business2 = Business::firstOrCreate(
            ['name' => 'Elite Salon'],
            [
                'slug' => 'elite-salon',
                'email' => 'info@elitesalon.com',
                'phone' => '+0987654321',
                'address' => '456 Oak Ave, City, State 54321',
                'invitation_code' => 'SALON456',
                'loyalty_stamps_required' => 8,
                'loyalty_reward_description' => 'Free styling after 8 visits',
                'description' => 'Luxury salon offering premium hair and beauty services.',
            ]
        );

        // Create super admin (no business_id)
        User::firstOrCreate(
            ['email' => 'superadmin@crezer.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'business_id' => null,
                'role' => 'super_admin',
            ]
        );

        // Create business admin for first business
        $admin1 = User::firstOrCreate(
            ['email' => 'admin@testbarber.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'business_id' => $business1->id,
                'role' => 'business_admin',
            ]
        );

        // Create business admin for second business
        $admin2 = User::firstOrCreate(
            ['email' => 'admin@elitesalon.com'],
            [
                'name' => 'Elite Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'business_id' => $business2->id,
                'role' => 'business_admin',
            ]
        );

        // Create employee for first business
        $employee1 = User::firstOrCreate(
            ['email' => 'employee@testbarber.com'],
            [
                'name' => 'John Barber',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'business_id' => $business1->id,
                'role' => 'employee',
            ]
        );

        // Create Employee model entry
        $employeeModel = Employee::firstOrCreate(
            ['user_id' => $employee1->id],
            [
                'business_id' => $business1->id,
                'bio' => 'Experienced barber with 10 years in the industry.',
                'is_active' => true,
            ]
        );

        // Create services for first business
        $service1 = Service::firstOrCreate(
            [
                'business_id' => $business1->id,
                'name' => 'Haircut',
            ],
            [
                'description' => 'Professional haircut and styling',
                'price' => 25.00,
                'duration' => 30,
                'is_active' => true,
            ]
        );

        $service2 = Service::firstOrCreate(
            [
                'business_id' => $business1->id,
                'name' => 'Beard Trim',
            ],
            [
                'description' => 'Beard trimming and shaping',
                'price' => 15.00,
                'duration' => 15,
                'is_active' => true,
            ]
        );

        // Create clients for first business
        $client1 = User::firstOrCreate(
            ['email' => 'client@testbarber.com'],
            [
                'name' => 'Client User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'business_id' => $business1->id,
                'role' => 'client',
                'phone' => '+1111111111',
            ]
        );

        // Create additional clients
        $client2 = User::firstOrCreate(
            ['email' => 'sarah@example.com'],
            [
                'name' => 'Sarah Johnson',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'business_id' => $business1->id,
                'role' => 'client',
                'phone' => '+1222222222',
            ]
        );

        $client3 = User::firstOrCreate(
            ['email' => 'mike@example.com'],
            [
                'name' => 'Mike Davis',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'business_id' => $business1->id,
                'role' => 'client',
                'phone' => '+1333333333',
            ]
        );

        // Create appointments for first business
        \App\Models\Appointment::firstOrCreate(
            [
                'business_id' => $business1->id,
                'client_id' => $client1->id,
                'employee_id' => $employeeModel->id,
                'service_id' => $service1->id,
            ],
            [
                'scheduled_at' => now()->addDays(2)->setTime(10, 0),
                'scheduled_until' => now()->addDays(2)->setTime(10, 30),
                'status' => 'confirmed',
                'notes' => 'Regular customer',
            ]
        );

        \App\Models\Appointment::firstOrCreate(
            [
                'business_id' => $business1->id,
                'client_id' => $client2->id,
                'employee_id' => $employeeModel->id,
                'service_id' => $service2->id,
            ],
            [
                'scheduled_at' => now()->addDays(3)->setTime(14, 0),
                'scheduled_until' => now()->addDays(3)->setTime(14, 15),
                'status' => 'pending',
            ]
        );

        \App\Models\Appointment::firstOrCreate(
            [
                'business_id' => $business1->id,
                'client_id' => $client3->id,
                'employee_id' => $employeeModel->id,
                'service_id' => $service1->id,
            ],
            [
                'scheduled_at' => now()->subDays(5)->setTime(11, 0),
                'scheduled_until' => now()->subDays(5)->setTime(11, 30),
                'status' => 'completed',
                'notes' => 'Requested short fade',
            ]
        );

        \App\Models\Appointment::firstOrCreate(
            [
                'business_id' => $business1->id,
                'client_id' => $client1->id,
                'employee_id' => $employeeModel->id,
                'service_id' => $service1->id,
                'scheduled_at' => now()->addDay()->setTime(15, 30),
            ],
            [
                'scheduled_until' => now()->addDay()->setTime(16, 0),
                'status' => 'confirmed',
            ]
        );
    }
}
