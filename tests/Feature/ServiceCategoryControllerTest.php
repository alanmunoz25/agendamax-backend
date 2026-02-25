<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private ServiceCategory $category;

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

        $this->category = ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Hair Services',
            'description' => 'All hair services',
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    public function test_business_admin_can_view_categories_index(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/service-categories');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ServiceCategories/Index')
            ->has('categories.data', 1)
            ->has('categories.data.0', fn ($cat) => $cat
                ->where('id', $this->category->id)
                ->where('name', 'Hair Services')
                ->etc()
            )
        );
    }

    public function test_categories_index_can_be_searched(): void
    {
        ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Nail Care',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/service-categories?search=Nail');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Nail Care')
        );
    }

    public function test_categories_index_can_be_filtered_by_status(): void
    {
        ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Inactive Category',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/service-categories?is_active=0');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.is_active', false)
        );
    }

    public function test_business_admin_can_view_create_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/service-categories/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ServiceCategories/Create')
            ->has('parentCategories')
        );
    }

    public function test_business_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/service-categories', [
                'name' => 'Spa Treatments',
                'description' => 'Relaxation services',
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $response->assertRedirect('/service-categories');

        $this->assertDatabaseHas('service_categories', [
            'business_id' => $this->business->id,
            'name' => 'Spa Treatments',
            'slug' => 'spa-treatments',
            'description' => 'Relaxation services',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_business_admin_can_create_subcategory(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/service-categories', [
                'name' => 'Haircuts',
                'description' => 'Haircut services',
                'parent_id' => $this->category->id,
                'sort_order' => 0,
                'is_active' => true,
            ]);

        $response->assertRedirect('/service-categories');

        $this->assertDatabaseHas('service_categories', [
            'business_id' => $this->business->id,
            'name' => 'Haircuts',
            'parent_id' => $this->category->id,
        ]);
    }

    public function test_business_admin_cannot_create_category_with_invalid_data(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/service-categories', [
                'name' => '',
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_business_admin_can_view_edit_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/service-categories/{$this->category->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ServiceCategories/Edit')
            ->has('category')
            ->has('parentCategories')
        );
    }

    public function test_edit_form_excludes_self_from_parent_options(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/service-categories/{$this->category->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('parentCategories', fn ($parents) => collect($parents)->where('id', $this->category->id)->isEmpty()
            )
        );
    }

    public function test_business_admin_can_update_category(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put("/service-categories/{$this->category->id}", [
                'name' => 'Updated Hair Services',
                'description' => 'Updated description',
                'sort_order' => 5,
                'is_active' => false,
            ]);

        $response->assertRedirect('/service-categories');
        $response->assertSessionHas('success');

        $this->category->refresh();
        $this->assertEquals('Updated Hair Services', $this->category->name);
        $this->assertEquals('updated-hair-services', $this->category->slug);
        $this->assertEquals('Updated description', $this->category->description);
        $this->assertEquals(5, $this->category->sort_order);
        $this->assertFalse($this->category->is_active);
    }

    public function test_business_admin_can_delete_category_without_services(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->delete("/service-categories/{$this->category->id}");

        $response->assertRedirect('/service-categories');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('service_categories', [
            'id' => $this->category->id,
        ]);
    }

    public function test_business_admin_cannot_delete_category_with_services(): void
    {
        Service::factory()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->delete("/service-categories/{$this->category->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('service_categories', [
            'id' => $this->category->id,
        ]);
    }

    public function test_business_admin_cannot_delete_parent_with_children_having_services(): void
    {
        $child = ServiceCategory::factory()->child($this->category)->create([
            'name' => 'Haircuts',
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'service_category_id' => $child->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->delete("/service-categories/{$this->category->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('service_categories', [
            'id' => $this->category->id,
        ]);
        $this->assertDatabaseHas('service_categories', [
            'id' => $child->id,
        ]);
    }

    public function test_deleting_parent_also_deletes_children(): void
    {
        $child = ServiceCategory::factory()->child($this->category)->create([
            'name' => 'Haircuts',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->delete("/service-categories/{$this->category->id}");

        $response->assertRedirect('/service-categories');

        $this->assertDatabaseMissing('service_categories', ['id' => $this->category->id]);
        $this->assertDatabaseMissing('service_categories', ['id' => $child->id]);
    }

    public function test_employee_cannot_create_category(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->get('/service-categories/create');

        $response->assertForbidden();

        $response = $this->actingAs($employee)
            ->post('/service-categories', [
                'name' => 'Hacked Category',
            ]);

        $response->assertForbidden();
    }

    public function test_employee_cannot_update_category(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->put("/service-categories/{$this->category->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();

        $this->category->refresh();
        $this->assertEquals('Hair Services', $this->category->name);
    }

    public function test_employee_cannot_delete_category(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->delete("/service-categories/{$this->category->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('service_categories', [
            'id' => $this->category->id,
        ]);
    }

    public function test_user_from_different_business_cannot_access_category(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
        ]);

        $response = $this->actingAs($otherAdmin)
            ->get('/service-categories');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('categories.data', 0)
        );

        $response = $this->actingAs($otherAdmin)
            ->get("/service-categories/{$this->category->id}/edit");

        $response->assertNotFound();
    }

    public function test_guest_cannot_access_category_routes(): void
    {
        $this->get('/service-categories')->assertRedirect('/login');
        $this->get('/service-categories/create')->assertRedirect('/login');
        $this->post('/service-categories', [])->assertRedirect('/login');
        $this->get("/service-categories/{$this->category->id}/edit")->assertRedirect('/login');
        $this->put("/service-categories/{$this->category->id}", [])->assertRedirect('/login');
        $this->delete("/service-categories/{$this->category->id}")->assertRedirect('/login');
    }

    public function test_user_without_business_cannot_access_category_routes(): void
    {
        $userWithoutBusiness = User::factory()->create([
            'business_id' => null,
            'role' => 'client',
        ]);

        $response = $this->actingAs($userWithoutBusiness)
            ->get('/service-categories');

        $response->assertForbidden();
    }
}
