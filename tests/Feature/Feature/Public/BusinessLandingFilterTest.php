<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Public;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for the BusinessLanding category filter.
 *
 * Issue UX #4 — Sprint 6 QA Round 2.
 * Verifies:
 *   - GET /negocio/{slug} passes categories with children eager-loaded.
 *   - services prop contains all active services for the business.
 */
class BusinessLandingFilterTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'slug' => 'filter-salon',
            'status' => 'active',
        ]);
    }

    /**
     * GET /negocio/{slug} returns categories with children eager-loaded.
     */
    public function test_landing_returns_categories_with_children(): void
    {
        $parent = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Beauty',
            'parent_id' => null,
        ]);

        ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Hair',
            'parent_id' => $parent->id,
        ]);

        ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Nails',
            'parent_id' => $parent->id,
        ]);

        $response = $this->get('/negocio/filter-salon');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Public/BusinessLanding')
                ->has('categories', 1)
                ->has('categories.0.children', 2),
        );
    }

    /**
     * GET /negocio/{slug} returns active services with their service_category relation.
     */
    public function test_landing_returns_services_with_category(): void
    {
        $parent = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Skin Care',
            'parent_id' => null,
        ]);

        $child = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Facials',
            'parent_id' => $parent->id,
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Deep Cleanse Facial',
            'is_active' => true,
            'service_category_id' => $child->id,
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Invisible Service',
            'is_active' => false,
            'service_category_id' => $child->id,
        ]);

        $response = $this->get('/negocio/filter-salon');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Public/BusinessLanding')
                ->has('services', 1)
                ->where('services.0.name', 'Deep Cleanse Facial')
                ->where('services.0.service_category.name', 'Facials')
                ->where('services.0.service_category.parent.name', 'Skin Care'),
        );
    }

    /**
     * GET /negocio/{slug} passes both services and categories props together.
     */
    public function test_landing_passes_services_and_categories_props(): void
    {
        $response = $this->get('/negocio/filter-salon');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Public/BusinessLanding')
                ->has('services')
                ->has('categories'),
        );
    }
}
