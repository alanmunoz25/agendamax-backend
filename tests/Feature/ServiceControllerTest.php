<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'name' => 'Test Business',
        ]);

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Test Service',
            'description' => 'Test Description',
            'duration' => 60,
            'price' => 50.00,
            'category' => 'Test Category',
            'is_active' => true,
        ]);
    }

    public function test_business_admin_can_view_services_index(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/services');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Services/Index')
            ->has('services.data', 1)
            ->has('services.data.0', fn ($service) => $service
                ->where('id', $this->service->id)
                ->where('name', 'Test Service')
                ->etc()
            )
        );
    }

    public function test_services_index_can_be_searched(): void
    {
        Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Haircut',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/services?search=Haircut');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.name', 'Haircut')
        );
    }

    public function test_services_index_can_be_filtered_by_category(): void
    {
        $hairCategory = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Hair',
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Balayage',
            'service_category_id' => $hairCategory->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/services?category_id='.$hairCategory->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.name', 'Balayage')
        );
    }

    public function test_services_index_can_be_filtered_by_active_status(): void
    {
        Service::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/services?is_active=0');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.is_active', false)
        );
    }

    public function test_business_admin_can_view_create_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/services/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Services/Create')
        );
    }

    public function test_business_admin_can_create_service(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/services', [
                'name' => 'New Service',
                'description' => 'New Description',
                'duration' => 90,
                'price' => 75.00,
                'category' => 'New Category',
                'is_active' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('services', [
            'business_id' => $this->business->id,
            'name' => 'New Service',
            'description' => 'New Description',
            'duration' => 90,
            'price' => 75.00,
            'category' => 'New Category',
            'is_active' => true,
        ]);
    }

    public function test_business_admin_cannot_create_service_with_invalid_data(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/services', [
                'name' => '',  // Invalid: required
                'duration' => 5,  // Invalid: min 15
                'price' => -10,  // Invalid: min 0
            ]);

        $response->assertSessionHasErrors(['name', 'duration', 'price']);
    }

    public function test_business_admin_can_view_service(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Services/Show')
            ->has('service', fn ($service) => $service
                ->where('id', $this->service->id)
                ->where('name', 'Test Service')
                ->etc()
            )
        );
    }

    public function test_business_admin_can_view_edit_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Services/Edit')
            ->has('service')
        );
    }

    public function test_business_admin_can_update_service(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put("/services/{$this->service->id}", [
                'name' => 'Updated Service',
                'description' => 'Updated Description',
                'duration' => 120,
                'price' => 100.00,
                'category' => 'Updated Category',
                'is_active' => false,
            ]);

        $response->assertRedirect("/services/{$this->service->id}");
        $response->assertSessionHas('success');

        $this->service->refresh();
        $this->assertEquals('Updated Service', $this->service->name);
        $this->assertEquals('Updated Description', $this->service->description);
        $this->assertEquals(120, $this->service->duration);
        $this->assertEquals(100.00, $this->service->price);
        $this->assertEquals('Updated Category', $this->service->category);
        $this->assertFalse($this->service->is_active);
    }

    public function test_business_admin_can_delete_service(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->delete("/services/{$this->service->id}");

        $response->assertRedirect('/services');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('services', [
            'id' => $this->service->id,
        ]);
    }

    public function test_employee_can_view_but_not_create_service(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        // Can view
        $response = $this->actingAs($employee)
            ->get('/services');

        $response->assertOk();

        // Cannot create
        $response = $this->actingAs($employee)
            ->get('/services/create');

        $response->assertForbidden();
    }

    public function test_employee_cannot_update_service(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->put("/services/{$this->service->id}", [
                'name' => 'Hacked Service',
            ]);

        $response->assertForbidden();

        $this->service->refresh();
        $this->assertEquals('Test Service', $this->service->name);
    }

    public function test_employee_cannot_delete_service(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->delete("/services/{$this->service->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('services', [
            'id' => $this->service->id,
        ]);
    }

    public function test_user_from_different_business_cannot_access_service(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
        ]);

        // Should not see the service
        $response = $this->actingAs($otherAdmin)
            ->get('/services');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('services.data', 0)
        );

        // Cannot view (404 because global scope filters it out)
        $response = $this->actingAs($otherAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertNotFound();
    }

    public function test_guest_cannot_access_service_routes(): void
    {
        $this->get('/services')->assertRedirect('/login');
        $this->get('/services/create')->assertRedirect('/login');
        $this->post('/services', [])->assertRedirect('/login');
        $this->get("/services/{$this->service->id}")->assertRedirect('/login');
        $this->get("/services/{$this->service->id}/edit")->assertRedirect('/login');
        $this->put("/services/{$this->service->id}", [])->assertRedirect('/login');
        $this->delete("/services/{$this->service->id}")->assertRedirect('/login');
    }

    public function test_user_without_business_cannot_access_service_routes(): void
    {
        $userWithoutBusiness = User::factory()->create([
            'business_id' => null,
            'role' => 'client',
        ]);

        $response = $this->actingAs($userWithoutBusiness)
            ->get('/services');

        $response->assertForbidden();
    }
}
