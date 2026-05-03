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

        // Helper: find or create user with guarded fields (role, business_id).
        // firstOrCreate cannot set guarded fields; we use firstOrNew + forceFill instead.
        $createUser = function (array $search, array $guarded, array $fillable) {
            $user = User::where($search)->first();
            if (! $user) {
                $user = new User;
                $user->fill($fillable);
                $user->forceFill(array_merge($search, $guarded))->save();
            }

            return $user;
        };

        // Create super admin (no business_id)
        $createUser(
            ['email' => 'superadmin@crezer.com'],
            ['role' => 'super_admin', 'business_id' => null],
            ['name' => 'Super Admin', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );

        // Create business admin for first business
        $admin1 = $createUser(
            ['email' => 'admin@testbarber.com'],
            ['role' => 'business_admin', 'business_id' => $business1->id],
            ['name' => 'Admin User', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );

        // Create business admin for second business
        $admin2 = $createUser(
            ['email' => 'admin@elitesalon.com'],
            ['role' => 'business_admin', 'business_id' => $business2->id],
            ['name' => 'Elite Admin', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );

        // Create employee for first business
        $employee1 = $createUser(
            ['email' => 'employee@testbarber.com'],
            ['role' => 'employee', 'business_id' => $business1->id],
            ['name' => 'John Barber', 'password' => bcrypt('password'), 'email_verified_at' => now()]
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
        $client1 = $createUser(
            ['email' => 'client@testbarber.com'],
            ['role' => 'client', 'business_id' => $business1->id],
            ['name' => 'Client User', 'password' => bcrypt('password'), 'email_verified_at' => now(), 'phone' => '+1111111111']
        );

        // Create additional clients
        $client2 = $createUser(
            ['email' => 'sarah@example.com'],
            ['role' => 'client', 'business_id' => $business1->id],
            ['name' => 'Sarah Johnson', 'password' => bcrypt('password'), 'email_verified_at' => now(), 'phone' => '+1222222222']
        );

        $client3 = $createUser(
            ['email' => 'mike@example.com'],
            ['role' => 'client', 'business_id' => $business1->id],
            ['name' => 'Mike Davis', 'password' => bcrypt('password'), 'email_verified_at' => now(), 'phone' => '+1333333333']
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
