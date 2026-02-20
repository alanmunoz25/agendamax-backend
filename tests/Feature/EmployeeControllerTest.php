<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private User $employeeUser;

    private Employee $employee;

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

        $this->employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $this->employeeUser->id,
            'bio' => 'Test Bio',
            'is_active' => true,
        ]);
    }

    public function test_business_admin_can_view_employees_index(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/employees');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Index')
            ->has('employees.data', 1)
            ->has('employees.data.0', fn ($employee) => $employee
                ->where('id', $this->employee->id)
                ->where('user_id', $this->employeeUser->id)
                ->etc()
            )
        );
    }

    public function test_employees_index_can_be_searched(): void
    {
        $anotherUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'name' => 'John Doe',
        ]);

        Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $anotherUser->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/employees?search=John');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.user.name', 'John Doe')
        );
    }

    public function test_employees_index_can_be_filtered_by_active_status(): void
    {
        $inactiveUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $inactiveUser->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/employees?is_active=0');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.is_active', false)
        );
    }

    public function test_business_admin_can_view_create_form(): void
    {
        // Create a user without employee profile
        $availableUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/employees/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Create')
            ->has('availableUsers')
            ->has('services')
        );
    }

    public function test_business_admin_can_create_employee(): void
    {
        $newUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $service = Service::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->post('/employees', [
                'user_id' => $newUser->id,
                'bio' => 'New Employee Bio',
                'photo_url' => 'https://example.com/photo.jpg',
                'is_active' => true,
                'service_ids' => [$service->id],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'business_id' => $this->business->id,
            'user_id' => $newUser->id,
            'bio' => 'New Employee Bio',
            'photo_url' => 'https://example.com/photo.jpg',
            'is_active' => true,
        ]);

        $employee = Employee::where('user_id', $newUser->id)->first();
        $this->assertTrue($employee->services->contains($service));
    }

    public function test_business_admin_cannot_create_employee_with_invalid_data(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/employees', [
                'user_id' => 999999,  // Invalid: non-existent user
                'photo_url' => 'not-a-url',  // Invalid: not a URL
            ]);

        $response->assertSessionHasErrors(['user_id', 'photo_url']);
    }

    public function test_business_admin_can_view_employee(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Show')
            ->has('employee', fn ($employee) => $employee
                ->where('id', $this->employee->id)
                ->where('user_id', $this->employeeUser->id)
                ->etc()
            )
        );
    }

    public function test_business_admin_can_view_edit_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/employees/{$this->employee->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Edit')
            ->has('employee')
            ->has('services')
        );
    }

    public function test_business_admin_can_update_employee(): void
    {
        $service = Service::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->put("/employees/{$this->employee->id}", [
                'bio' => 'Updated Bio',
                'photo_url' => 'https://example.com/new-photo.jpg',
                'is_active' => false,
                'service_ids' => [$service->id],
            ]);

        $response->assertRedirect("/employees/{$this->employee->id}");
        $response->assertSessionHas('success');

        $this->employee->refresh();
        $this->assertEquals('Updated Bio', $this->employee->bio);
        $this->assertEquals('https://example.com/new-photo.jpg', $this->employee->photo_url);
        $this->assertFalse($this->employee->is_active);
        $this->assertTrue($this->employee->services->contains($service));
    }

    public function test_business_admin_can_delete_employee(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->delete("/employees/{$this->employee->id}");

        $response->assertRedirect('/employees');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('employees', [
            'id' => $this->employee->id,
        ]);

        // User account should still exist
        $this->assertDatabaseHas('users', [
            'id' => $this->employeeUser->id,
        ]);
    }

    public function test_regular_employee_can_view_but_not_create(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        // Can view
        $response = $this->actingAs($employeeUser)
            ->get('/employees');

        $response->assertOk();

        // Cannot create
        $response = $this->actingAs($employeeUser)
            ->get('/employees/create');

        $response->assertForbidden();
    }

    public function test_regular_employee_cannot_update_employee(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employeeUser)
            ->put("/employees/{$this->employee->id}", [
                'bio' => 'Hacked Bio',
            ]);

        $response->assertForbidden();

        $this->employee->refresh();
        $this->assertEquals('Test Bio', $this->employee->bio);
    }

    public function test_regular_employee_cannot_delete_employee(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employeeUser)
            ->delete("/employees/{$this->employee->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('employees', [
            'id' => $this->employee->id,
        ]);
    }

    public function test_user_from_different_business_cannot_access_employee(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
        ]);

        // Should not see the employee
        $response = $this->actingAs($otherAdmin)
            ->get('/employees');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('employees.data', 0)
        );

        // Cannot view (404 because global scope filters it out)
        $response = $this->actingAs($otherAdmin)
            ->get("/employees/{$this->employee->id}");

        $response->assertNotFound();
    }

    public function test_guest_cannot_access_employee_routes(): void
    {
        $this->get('/employees')->assertRedirect('/login');
        $this->get('/employees/create')->assertRedirect('/login');
        $this->post('/employees', [])->assertRedirect('/login');
        $this->get("/employees/{$this->employee->id}")->assertRedirect('/login');
        $this->get("/employees/{$this->employee->id}/edit")->assertRedirect('/login');
        $this->put("/employees/{$this->employee->id}", [])->assertRedirect('/login');
        $this->delete("/employees/{$this->employee->id}")->assertRedirect('/login');
    }

    public function test_user_without_business_cannot_access_employee_routes(): void
    {
        $userWithoutBusiness = User::factory()->create([
            'business_id' => null,
            'role' => 'client',
        ]);

        $response = $this->actingAs($userWithoutBusiness)
            ->get('/employees');

        $response->assertForbidden();
    }

    public function test_employee_can_be_created_without_services(): void
    {
        $newUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->post('/employees', [
                'user_id' => $newUser->id,
                'is_active' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'business_id' => $this->business->id,
            'user_id' => $newUser->id,
        ]);

        $employee = Employee::where('user_id', $newUser->id)->first();
        $this->assertCount(0, $employee->services);
    }

    public function test_services_can_be_synced_when_updating_employee(): void
    {
        $service1 = Service::factory()->create(['business_id' => $this->business->id]);
        $service2 = Service::factory()->create(['business_id' => $this->business->id]);

        // Initially assign service1
        $this->employee->services()->sync([$service1->id]);
        $this->assertCount(1, $this->employee->fresh()->services);

        // Update to service2
        $response = $this->actingAs($this->businessAdmin)
            ->put("/employees/{$this->employee->id}", [
                'is_active' => true,
                'service_ids' => [$service2->id],
            ]);

        $response->assertRedirect("/employees/{$this->employee->id}");

        $this->employee->refresh();
        $this->assertCount(1, $this->employee->services);
        $this->assertTrue($this->employee->services->contains($service2));
        $this->assertFalse($this->employee->services->contains($service1));
    }
}
