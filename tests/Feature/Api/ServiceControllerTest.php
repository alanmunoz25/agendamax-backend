<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private ServiceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['status' => 'active']);

        $this->category = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Hair Services',
            'is_active' => true,
        ]);
    }

    public function test_can_list_services_for_business(): void
    {
        Service::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'service_category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/services");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'duration', 'price', 'category', 'is_active', 'service_category', 'employees_count'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_services_by_category(): void
    {
        $otherCategory = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Nails',
            'is_active' => true,
        ]);

        Service::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'service_category_id' => $this->category->id,
            'is_active' => true,
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $otherCategory->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/services?category_id={$this->category->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_search_services(): void
    {
        Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Premium Haircut',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Manicure',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/services?search=Haircut");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Premium Haircut');
    }

    public function test_can_show_single_service_with_employees(): void
    {
        $service = Service::factory()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $employee->services()->attach($service->id);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/services/{$service->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'description', 'duration', 'price',
                    'service_category',
                    'employees_count',
                    'employees' => [
                        '*' => ['id', 'name', 'photo_url', 'bio', 'is_active'],
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data.employees');
    }

    public function test_returns_404_for_invalid_business(): void
    {
        $response = $this->getJson('/api/v1/businesses/99999/services');

        $response->assertStatus(404);
    }

    public function test_returns_404_for_service_in_different_business(): void
    {
        $otherBusiness = Business::factory()->create(['status' => 'active']);

        $service = Service::factory()->create([
            'business_id' => $otherBusiness->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/services/{$service->id}");

        $response->assertStatus(404);
    }

    public function test_inactive_services_are_excluded(): void
    {
        Service::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        Service::factory()->inactive()->create([
            'business_id' => $this->business->id,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/services");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_services_include_category_info(): void
    {
        $childCategory = ServiceCategory::factory()->child($this->category)->create([
            'name' => 'Cortes',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $childCategory->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/services");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.service_category.name', 'Cortes')
            ->assertJsonPath('data.0.service_category.parent.name', 'Hair Services');
    }
}
