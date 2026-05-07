<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosIndexTest extends TestCase
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

    public function test_pos_index_returns_200_for_authenticated_business_admin(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertStatus(200);
    }

    public function test_pos_index_loads_services_with_service_category_relationship(): void
    {
        $category = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->cashier);

        // The services_catalog is a deferred Inertia prop; we assert the page
        // loads without triggering the "undefined relationship [category]" error.
        $response = $this->get(route('pos.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Pos/Index'));
    }

    public function test_pos_index_services_catalog_uses_service_category_not_category(): void
    {
        $category = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Hair Services',
        ]);

        $service = Service::factory()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $category->id,
            'is_active' => true,
        ]);

        // Directly assert the relationship can be eager-loaded without exceptions.
        $loaded = Service::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->with('serviceCategory')
            ->get();

        $this->assertCount(1, $loaded);
        $this->assertNotNull($loaded->first()->serviceCategory);
        $this->assertSame('Hair Services', $loaded->first()->serviceCategory->name);
    }

    public function test_unauthenticated_user_is_redirected_from_pos_index(): void
    {
        $response = $this->get(route('pos.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_without_business_id_receives_422(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin);

        $response = $this->get(route('pos.index'));

        $response->assertStatus(422);
    }

    public function test_super_admin_with_business_id_query_param_can_access_pos(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin);

        $response = $this->get(route('pos.index', ['business_id' => $this->business->id]));

        $response->assertStatus(200);
    }
}
