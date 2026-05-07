<?php

declare(strict_types=1);

namespace Tests\Feature\MultiTenant;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Promotion;
use App\Models\QrCode;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Track A — Sprint 6: Web scope isolation regression suite.
 *
 * Verifies that a business_admin acting on behalf of biz1 cannot see
 * data belonging to biz2 through any of the 7 web Inertia endpoints.
 */
class WebScopeIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Business $biz1;

    private Business $biz2;

    private User $admin1;

    private User $admin2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->biz1 = Business::factory()->create(['name' => 'Business One']);
        $this->biz2 = Business::factory()->create(['name' => 'Business Two']);

        $this->admin1 = User::factory()->create([
            'business_id' => $this->biz1->id,
            'role' => 'business_admin',
        ]);

        $this->admin2 = User::factory()->create([
            'business_id' => $this->biz2->id,
            'role' => 'business_admin',
        ]);

        // Seed biz1 data (5 of each)
        $this->seedBusiness($this->biz1, $this->admin1);

        // Seed biz2 data (5 of each) — must NOT appear for admin1
        $this->seedBusiness($this->biz2, $this->admin2);
    }

    /**
     * Create 5 employees, 5 service categories, 5 services, 5 clients,
     * 5 appointments, 5 promotions, 5 QR codes, and 1 payroll period
     * for the given business.
     */
    private function seedBusiness(Business $business, User $admin): void
    {
        // 5 employees (each backed by their own user)
        Employee::factory()->count(5)->create([
            'business_id' => $business->id,
        ]);

        // 5 root service categories
        ServiceCategory::factory()->count(5)->create([
            'business_id' => $business->id,
            'parent_id' => null,
        ]);

        // 5 services
        $services = Service::factory()->count(5)->create([
            'business_id' => $business->id,
        ]);

        // 5 clients
        $clients = User::factory()->count(5)->create([
            'business_id' => $business->id,
            'role' => 'client',
        ]);

        // Pick an employee from this business to attach to appointments
        $employee = Employee::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->first();

        // 5 appointments
        foreach (range(1, 5) as $i) {
            Appointment::factory()->create([
                'business_id' => $business->id,
                'service_id' => $services->first()->id,
                'employee_id' => $employee->id,
                'client_id' => $clients->get($i - 1)->id,
            ]);
        }

        // 5 promotions
        Promotion::factory()->count(5)->create([
            'business_id' => $business->id,
        ]);

        // 5 QR codes
        QrCode::factory()->count(5)->create([
            'business_id' => $business->id,
        ]);

        // 1 payroll period
        PayrollPeriod::factory()->forBusiness($business)->create();
    }

    public function test_admin_appointments_index_only_sees_own_business(): void
    {
        $response = $this->actingAs($this->admin1)->get('/appointments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Appointments/Index')
            ->has('appointments.data', 5)
        );
    }

    public function test_admin_services_index_only_sees_own_business(): void
    {
        $response = $this->actingAs($this->admin1)->get('/services');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Services/Index')
            ->has('services.data', 5)
        );
    }

    public function test_admin_service_categories_index_only_sees_own_business(): void
    {
        $response = $this->actingAs($this->admin1)->get('/service-categories');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ServiceCategories/Index')
            ->has('categories.data', 5)
        );
    }

    public function test_admin_clients_index_only_sees_own_business(): void
    {
        $response = $this->actingAs($this->admin1)->get('/clients');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data', 5)
        );
    }

    public function test_admin_promotions_index_only_sees_own_business(): void
    {
        $response = $this->actingAs($this->admin1)->get('/promotions');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Promotions/Index')
            ->has('promotions.data', 5)
        );
    }

    public function test_admin_qr_codes_index_only_sees_own_business(): void
    {
        $response = $this->actingAs($this->admin1)->get('/qr-codes');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes', 5)
        );
    }

    public function test_admin_payroll_periods_index_only_sees_own_business(): void
    {
        $response = $this->actingAs($this->admin1)->get('/payroll/periods');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Periods/Index')
            ->has('periods.data', 1)
        );
    }
}
