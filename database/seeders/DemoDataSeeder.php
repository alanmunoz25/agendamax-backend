<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎯 Seeding demo data for Crezer...');

        // Create 3 realistic businesses
        $businesses = $this->createBusinesses();

        foreach ($businesses as $business) {
            $this->command->info("📍 Creating data for {$business->name}...");

            // Create services for this business
            $services = $this->createServicesForBusiness($business);

            // Create employees for this business
            $employees = $this->createEmployeesForBusiness($business, count($services));

            // Attach services to employees (many-to-many)
            $this->attachServicesToEmployees($employees, $services);

            $this->command->info("   ✅ {$business->name}: ".count($services).' services, '.count($employees).' employees');
        }

        // Create client users for testing
        $this->createClientUsers();

        $this->command->newLine();
        $this->command->info('✨ Demo data seeded successfully!');
        $this->command->newLine();
        $this->displaySummary($businesses);
    }

    /**
     * Create realistic businesses.
     */
    private function createBusinesses(): array
    {
        $businessData = [
            [
                'name' => 'Luxe Beauty Salon',
                'email' => 'contact@luxebeauty.com',
                'phone' => '+1 (555) 123-4567',
                'address' => '123 Main Street, Downtown, Los Angeles, CA 90012',
                'invitation_code' => 'LUXE2024',
                'loyalty_stamps_required' => 10,
                'loyalty_reward_description' => 'Get a FREE haircut after 10 visits!',
            ],
            [
                'name' => 'Urban Cuts Barbershop',
                'email' => 'hello@urbancuts.com',
                'phone' => '+1 (555) 987-6543',
                'address' => '456 Oak Avenue, Brooklyn, NY 11201',
                'invitation_code' => 'URBAN123',
                'loyalty_stamps_required' => 8,
                'loyalty_reward_description' => 'Earn a free beard trim after 8 visits!',
            ],
            [
                'name' => 'Serenity Spa & Wellness',
                'email' => 'info@serenityspa.com',
                'phone' => '+1 (555) 246-8135',
                'address' => '789 Beach Boulevard, Miami Beach, FL 33139',
                'invitation_code' => 'SERENITY',
                'loyalty_stamps_required' => 12,
                'loyalty_reward_description' => 'Complimentary 30-min massage after 12 visits!',
            ],
        ];

        return collect($businessData)->map(function ($data) {
            return Business::create([
                ...$data,
                'slug' => Str::slug($data['name']),
                'status' => 'active',
                'timezone' => 'America/New_York',
            ]);
        })->all();
    }

    /**
     * Create services based on business type.
     */
    private function createServicesForBusiness(Business $business): array
    {
        $servicesByBusiness = [
            'Luxe Beauty Salon' => [
                ['name' => 'Women\'s Haircut', 'category' => 'Hair', 'duration' => 45, 'price' => 65.00, 'description' => 'Professional cut and style'],
                ['name' => 'Men\'s Haircut', 'category' => 'Hair', 'duration' => 30, 'price' => 35.00, 'description' => 'Classic or modern styles'],
                ['name' => 'Hair Coloring', 'category' => 'Hair', 'duration' => 120, 'price' => 120.00, 'description' => 'Full color or highlights'],
                ['name' => 'Balayage', 'category' => 'Hair', 'duration' => 180, 'price' => 200.00, 'description' => 'Hand-painted highlights'],
                ['name' => 'Manicure', 'category' => 'Nails', 'duration' => 45, 'price' => 35.00, 'description' => 'Classic nail care and polish'],
                ['name' => 'Pedicure', 'category' => 'Nails', 'duration' => 60, 'price' => 50.00, 'description' => 'Foot spa and nail care'],
                ['name' => 'Gel Nails', 'category' => 'Nails', 'duration' => 75, 'price' => 60.00, 'description' => 'Long-lasting gel polish'],
                ['name' => 'Facial Treatment', 'category' => 'Skin', 'duration' => 60, 'price' => 85.00, 'description' => 'Deep cleansing facial'],
            ],
            'Urban Cuts Barbershop' => [
                ['name' => 'Classic Haircut', 'category' => 'Hair', 'duration' => 30, 'price' => 30.00, 'description' => 'Traditional barbershop cut'],
                ['name' => 'Fade Haircut', 'category' => 'Hair', 'duration' => 45, 'price' => 40.00, 'description' => 'Modern fade styling'],
                ['name' => 'Beard Trim', 'category' => 'Grooming', 'duration' => 20, 'price' => 20.00, 'description' => 'Shape and trim'],
                ['name' => 'Hot Towel Shave', 'category' => 'Grooming', 'duration' => 30, 'price' => 35.00, 'description' => 'Traditional straight razor shave'],
                ['name' => 'Haircut + Beard', 'category' => 'Combo', 'duration' => 60, 'price' => 55.00, 'description' => 'Complete grooming package'],
                ['name' => 'Kids Haircut', 'category' => 'Hair', 'duration' => 25, 'price' => 22.00, 'description' => 'Ages 12 and under'],
            ],
            'Serenity Spa & Wellness' => [
                ['name' => 'Swedish Massage', 'category' => 'Massage', 'duration' => 60, 'price' => 90.00, 'description' => 'Relaxing full-body massage'],
                ['name' => 'Deep Tissue Massage', 'category' => 'Massage', 'duration' => 90, 'price' => 120.00, 'description' => 'Therapeutic deep pressure'],
                ['name' => 'Hot Stone Massage', 'category' => 'Massage', 'duration' => 75, 'price' => 110.00, 'description' => 'Heated stones therapy'],
                ['name' => 'Aromatherapy', 'category' => 'Wellness', 'duration' => 60, 'price' => 95.00, 'description' => 'Essential oils relaxation'],
                ['name' => 'Couples Massage', 'category' => 'Massage', 'duration' => 60, 'price' => 180.00, 'description' => 'Side-by-side massage'],
                ['name' => 'Signature Facial', 'category' => 'Skin', 'duration' => 75, 'price' => 100.00, 'description' => 'Customized skin treatment'],
                ['name' => 'Body Scrub', 'category' => 'Wellness', 'duration' => 45, 'price' => 80.00, 'description' => 'Exfoliating body treatment'],
            ],
        ];

        $services = $servicesByBusiness[$business->name] ?? [];

        return collect($services)->map(function ($service) use ($business) {
            return Service::create([
                'business_id' => $business->id,
                'name' => $service['name'],
                'category' => $service['category'],
                'duration' => $service['duration'],
                'price' => $service['price'],
                'description' => $service['description'],
                'is_active' => true,
            ]);
        })->all();
    }

    /**
     * Create employees for a business.
     */
    private function createEmployeesForBusiness(Business $business, int $serviceCount): array
    {
        $employeeCount = min(4, max(2, (int) ceil($serviceCount / 2))); // 2-4 employees

        $employeeNames = [
            'Luxe Beauty Salon' => [
                ['name' => 'Sarah Johnson', 'email' => 'sarah@luxebeauty.com', 'bio' => '15 years of experience in hair coloring and styling'],
                ['name' => 'Michael Chen', 'email' => 'michael@luxebeauty.com', 'bio' => 'Certified nail technician and makeup artist'],
                ['name' => 'Emily Rodriguez', 'email' => 'emily@luxebeauty.com', 'bio' => 'Balayage specialist with international training'],
                ['name' => 'Jessica Williams', 'email' => 'jessica@luxebeauty.com', 'bio' => 'Expert in bridal styling and updos'],
            ],
            'Urban Cuts Barbershop' => [
                ['name' => 'Marcus Thompson', 'email' => 'marcus@urbancuts.com', 'bio' => 'Master barber with 20+ years experience'],
                ['name' => 'David Park', 'email' => 'david@urbancuts.com', 'bio' => 'Fade specialist and men\'s grooming expert'],
                ['name' => 'Anthony Russo', 'email' => 'anthony@urbancuts.com', 'bio' => 'Traditional barbering and hot towel shaves'],
            ],
            'Serenity Spa & Wellness' => [
                ['name' => 'Lisa Martinez', 'email' => 'lisa@serenityspa.com', 'bio' => 'Licensed massage therapist, deep tissue specialist'],
                ['name' => 'Amanda Taylor', 'email' => 'amanda@serenityspa.com', 'bio' => 'Holistic wellness practitioner and aromatherapist'],
                ['name' => 'Rachel Green', 'email' => 'rachel@serenityspa.com', 'bio' => 'Esthetician with focus on organic skincare'],
                ['name' => 'Nicole Brown', 'email' => 'nicole@serenityspa.com', 'bio' => 'Hot stone massage and couples therapy expert'],
            ],
        ];

        $names = array_slice($employeeNames[$business->name] ?? [], 0, $employeeCount);

        return collect($names)->map(function ($data) use ($business) {
            // Create user for employee
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => 'password', // Hashed automatically
                'phone' => fake()->phoneNumber(),
                'role' => 'employee',
                'business_id' => $business->id,
            ]);

            // Create employee profile
            return Employee::create([
                'user_id' => $user->id,
                'business_id' => $business->id,
                'bio' => $data['bio'],
                'is_active' => true,
            ]);
        })->all();
    }

    /**
     * Attach services to employees (many-to-many relationship).
     */
    private function attachServicesToEmployees(array $employees, array $services): void
    {
        foreach ($employees as $index => $employee) {
            // Each employee can perform 3-6 services
            $serviceCount = rand(3, min(6, count($services)));

            // First employee gets all services, others get a subset
            if ($index === 0) {
                $employeeServices = $services;
            } else {
                $employeeServices = collect($services)->random(min($serviceCount, count($services)));
            }

            $employee->services()->attach(
                collect($employeeServices)->pluck('id')->toArray()
            );
        }
    }

    /**
     * Create test client users.
     */
    private function createClientUsers(): void
    {
        $clients = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => '+1 (555) 111-2222'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'phone' => '+1 (555) 333-4444'],
            ['name' => 'Bob Wilson', 'email' => 'bob@example.com', 'phone' => '+1 (555) 555-6666'],
        ];

        foreach ($clients as $client) {
            User::create([
                'name' => $client['name'],
                'email' => $client['email'],
                'password' => 'password', // Default password for testing
                'phone' => $client['phone'],
                'role' => 'client',
            ]);
        }

        $this->command->info('👥 Created 3 client users for testing');
    }

    /**
     * Display summary of seeded data.
     */
    private function displaySummary(array $businesses): void
    {
        $this->command->info('📊 Demo Data Summary:');
        $this->command->newLine();

        foreach ($businesses as $business) {
            $this->command->table(
                ['Business', 'Invitation Code', 'Services', 'Employees'],
                [[
                    $business->name,
                    $business->invitation_code,
                    $business->services()->count(),
                    $business->employees()->count(),
                ]]
            );
        }

        $this->command->newLine();
        $this->command->info('🔑 Test Credentials:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Client', 'john@example.com', 'password'],
                ['Client', 'jane@example.com', 'password'],
                ['Client', 'bob@example.com', 'password'],
                ['Employee', 'sarah@luxebeauty.com', 'password'],
                ['Employee', 'marcus@urbancuts.com', 'password'],
                ['Employee', 'lisa@serenityspa.com', 'password'],
            ]
        );

        $this->command->newLine();
        $this->command->info('🎯 Postman Testing:');
        $this->command->line('  • Use invitation codes: LUXE2024, URBAN123, SERENITY');
        $this->command->line('  • Login with any test account above');
        $this->command->line('  • Business IDs: 1 (Luxe), 2 (Urban), 3 (Serenity)');
    }
}
