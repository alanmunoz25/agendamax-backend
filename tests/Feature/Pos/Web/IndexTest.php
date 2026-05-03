<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->cashier = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);
    }

    public function test_pos_index_renders_for_authenticated_user(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Pos/Index'));
    }

    public function test_pos_index_shows_today_appointments(): void
    {
        $client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
        ]);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'scheduled_at' => now()->startOfDay()->addHours(10),
            'status' => 'completed',
            'ticket_id' => null,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('today_appointments', 1)
        );
    }

    public function test_pos_index_provides_today_summary(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('today_summary.total_tickets')
            ->has('today_summary.uncollected_count')
            ->has('today_summary.collected_count')
            ->has('today_summary.total_sales_today')
        );
    }

    public function test_pos_index_provides_service_categories(): void
    {
        ServiceCategory::factory()->create(['business_id' => $this->business->id, 'name' => 'Cortes']);
        ServiceCategory::factory()->create(['business_id' => $this->business->id, 'name' => 'Color']);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('service_categories', 2)
        );
    }

    public function test_pos_index_provides_employees_for_walkin(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('employees_for_walkin', 1)
        );
    }

    public function test_pos_index_multi_tenant_isolation(): void
    {
        $businessB = Business::factory()->create();

        // Appointment belongs to business B
        Appointment::factory()->create([
            'business_id' => $businessB->id,
            'scheduled_at' => now()->startOfDay()->addHours(9),
            'status' => 'pending',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        // Business A cashier should not see Business B appointments
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('today_appointments', 0)
        );
    }

    public function test_pos_index_requires_authentication(): void
    {
        $response = $this->get(route('pos.index'));

        $response->assertRedirect('/login');
    }

    public function test_pos_index_excludes_old_appointments(): void
    {
        // Appointment from yesterday should not appear
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'scheduled_at' => now()->subDay()->startOfDay()->addHours(10),
            'status' => 'completed',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('today_appointments', 0)
        );
    }
}
