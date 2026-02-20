<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['status' => 'active']);
    }

    public function test_can_list_categories_for_business(): void
    {
        $parentCategory = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Hair Services',
            'slug' => 'hair-services',
            'is_active' => true,
        ]);

        $child = ServiceCategory::factory()->child($parentCategory)->create([
            'name' => 'Cortes',
            'slug' => 'cortes',
            'is_active' => true,
        ]);

        ServiceCategory::factory()->child($parentCategory)->create([
            'name' => 'Coloring',
            'slug' => 'coloring',
            'is_active' => true,
        ]);

        // Add a service to the child category
        Service::factory()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $child->id,
            'name' => 'Basic Haircut',
            'price' => 25.00,
            'duration' => 30,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/categories");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'description', 'sort_order', 'is_active', 'children', 'services_count'],
                ],
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.children')
            ->assertJsonPath('data.0.slug', 'hair-services');

        // Verify children contain services with required fields
        $children = $response->json('data.0.children');
        $cortesChild = collect($children)->firstWhere('name', 'Cortes');
        $this->assertNotNull($cortesChild);
        $this->assertEquals('cortes', $cortesChild['slug']);
        $this->assertCount(1, $cortesChild['services']);
        $this->assertArrayHasKey('id', $cortesChild['services'][0]);
        $this->assertArrayHasKey('name', $cortesChild['services'][0]);
        $this->assertArrayHasKey('price', $cortesChild['services'][0]);
        $this->assertArrayHasKey('duration', $cortesChild['services'][0]);
    }

    public function test_categories_include_services_count(): void
    {
        $category = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Hair',
            'is_active' => true,
        ]);

        Service::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'service_category_id' => $category->id,
            'is_active' => true,
        ]);

        // Inactive service should not count
        Service::factory()->inactive()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $category->id,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/categories");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.services_count', 3);
    }

    public function test_can_show_category_with_services(): void
    {
        $category = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Nails',
            'slug' => 'nails',
            'is_active' => true,
        ]);

        Service::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'service_category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'slug', 'description', 'sort_order', 'is_active',
                    'children', 'services_count',
                    'services' => [
                        '*' => ['id', 'name', 'price', 'duration'],
                    ],
                ],
            ])
            ->assertJsonPath('data.slug', 'nails')
            ->assertJsonCount(2, 'data.services');
    }

    public function test_returns_404_for_invalid_business(): void
    {
        $response = $this->getJson('/api/v1/businesses/99999/categories');

        $response->assertStatus(404);
    }

    public function test_only_active_categories_are_returned(): void
    {
        ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Active Category',
            'is_active' => true,
        ]);

        ServiceCategory::factory()->inactive()->create([
            'business_id' => $this->business->id,
            'name' => 'Inactive Category',
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/categories");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Category');
    }
}
