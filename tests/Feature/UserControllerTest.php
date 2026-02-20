<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $superAdmin;

    private User $businessAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->superAdmin = User::factory()->create([
            'business_id' => null,
            'role' => 'super_admin',
        ]);

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);
    }

    public function test_super_admin_can_list_all_users(): void
    {
        User::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/users');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->has('users.data', 5) // 3 clients + superAdmin + businessAdmin
        );
    }

    public function test_business_admin_can_list_own_business_users(): void
    {
        $otherBusiness = Business::factory()->create();
        User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'client',
        ]);

        User::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/users');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->has('users.data', 3) // businessAdmin + 2 clients (not the other business user)
        );
    }

    public function test_super_admin_can_change_user_role(): void
    {
        $user = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->put("/users/{$user->id}", [
                'role' => 'business_admin',
                'business_id' => $this->business->id,
            ]);

        $response->assertRedirect('/users');
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals('business_admin', $user->role);
    }

    public function test_super_admin_can_assign_user_to_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $user = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->put("/users/{$user->id}", [
                'role' => 'client',
                'business_id' => $otherBusiness->id,
            ]);

        $response->assertRedirect('/users');

        $user->refresh();
        $this->assertEquals($otherBusiness->id, $user->business_id);
    }

    public function test_business_admin_cannot_promote_to_super_admin(): void
    {
        $user = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->put("/users/{$user->id}", [
                'role' => 'super_admin',
            ]);

        // Validation rejects super_admin since it's not in the allowed roles for business_admin
        $response->assertSessionHasErrors('role');

        $user->refresh();
        $this->assertEquals('client', $user->role);
    }

    public function test_employee_cannot_access_user_management(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->get('/users');

        $response->assertForbidden();
    }

    public function test_client_cannot_access_user_management(): void
    {
        $client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($client)
            ->get('/users');

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_user_management(): void
    {
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_super_admin_can_filter_users_by_role(): void
    {
        User::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get('/users?role=client');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->has('users.data', 2)
        );
    }

    public function test_super_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'role' => 'client',
                'business_id' => $this->business->id,
            ]);

        $response->assertRedirect('/users');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'client',
            'business_id' => $this->business->id,
        ]);
    }

    public function test_super_admin_can_create_business_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/users', [
                'name' => 'New Admin',
                'email' => 'newadmin@example.com',
                'password' => 'password123',
                'role' => 'business_admin',
                'business_id' => $this->business->id,
            ]);

        $response->assertRedirect('/users');

        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@example.com',
            'role' => 'business_admin',
        ]);
    }

    public function test_business_admin_can_create_employee(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/users', [
                'name' => 'New Employee',
                'email' => 'employee@example.com',
                'password' => 'password123',
                'role' => 'employee',
            ]);

        $response->assertRedirect('/users');

        $user = User::where('email', 'employee@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('employee', $user->role);
        // business_id forced to business_admin's own business
        $this->assertEquals($this->business->id, $user->business_id);
    }

    public function test_business_admin_cannot_create_super_admin(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/users', [
                'name' => 'Hacker Admin',
                'email' => 'hacker@example.com',
                'password' => 'password123',
                'role' => 'super_admin',
            ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'hacker@example.com']);
    }

    public function test_employee_cannot_create_users(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)
            ->post('/users', [
                'name' => 'Some User',
                'email' => 'some@example.com',
                'password' => 'password123',
                'role' => 'client',
            ]);

        $response->assertForbidden();
    }

    public function test_create_user_requires_unique_email(): void
    {
        $existing = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post('/users', [
                'name' => 'Duplicate',
                'email' => $existing->email,
                'password' => 'password123',
                'role' => 'client',
                'business_id' => $this->business->id,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_business_admin_cannot_change_business_id(): void
    {
        $otherBusiness = Business::factory()->create();
        $user = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->put("/users/{$user->id}", [
                'role' => 'employee',
                'business_id' => $otherBusiness->id,
            ]);

        $response->assertRedirect('/users');

        $user->refresh();
        // business_id should NOT have changed (business_admin can't change it)
        $this->assertEquals($this->business->id, $user->business_id);
        // But role should have changed
        $this->assertEquals('employee', $user->role);
    }
}
