<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Models\Business;
use App\Models\Employee;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Track C #5 — POS checkout sync smoke test.
 * Verifies that Pos/Index renders with the correct props that enable
 * the walk-in lifted-state and CheckoutDrawer sync.
 */
class PosCheckoutSyncTest extends TestCase
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
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function test_pos_index_renders_with_service_categories(): void
    {
        ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Cortes',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            // services_catalog is deferred — only service_categories is available in initial response
            ->has('service_categories', 1)
        );
    }

    /** @test */
    public function test_pos_index_provides_employees_for_walkin_selector(): void
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
            ->where('employees_for_walkin.0.user.name', $employeeUser->name)
        );
    }

    /** @test */
    public function test_pos_index_passes_ecf_enabled_prop(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('ecf_enabled')
        );
    }

    /** @test */
    public function test_pos_index_provides_has_open_shift_prop(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('has_open_shift')
        );
    }
}
