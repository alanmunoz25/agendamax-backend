<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_scope_filters_queries_by_business_id(): void
    {
        $business1 = Business::factory()->create(['name' => 'Business 1']);
        $business2 = Business::factory()->create(['name' => 'Business 2']);

        $user1 = User::factory()->create([
            'business_id' => $business1->id,
            'role' => 'business_admin',
        ]);

        $user2 = User::factory()->create([
            'business_id' => $business2->id,
            'role' => 'business_admin',
        ]);

        $employee1 = Employee::factory()->create([
            'user_id' => $user1->id,
            'business_id' => $business1->id,
        ]);

        $employee2 = Employee::factory()->create([
            'user_id' => $user2->id,
            'business_id' => $business2->id,
        ]);

        // Act as user from business 1
        $this->actingAs($user1);

        // Should only see employees from business 1
        $employees = Employee::all();

        $this->assertCount(1, $employees);
        $this->assertEquals($employee1->id, $employees->first()->id);
        $this->assertNotContains($employee2->id, $employees->pluck('id'));
    }

    public function test_middleware_blocks_non_admin_users_without_business(): void
    {
        $user = User::factory()->create([
            'business_id' => null,
            'role' => 'client',
        ]);

        $response = $this->actingAs($user)
            ->get('/services');

        $response->assertForbidden();
    }

    public function test_super_admin_can_access_dashboard_without_business(): void
    {
        $user = User::factory()->create([
            'business_id' => null,
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
    }

    public function test_super_admin_sees_all_businesses_records(): void
    {
        $business1 = Business::factory()->create();
        $business2 = Business::factory()->create();

        $user1 = User::factory()->create([
            'business_id' => $business1->id,
            'role' => 'employee',
        ]);

        $user2 = User::factory()->create([
            'business_id' => $business2->id,
            'role' => 'employee',
        ]);

        Employee::factory()->create([
            'user_id' => $user1->id,
            'business_id' => $business1->id,
        ]);

        Employee::factory()->create([
            'user_id' => $user2->id,
            'business_id' => $business2->id,
        ]);

        $superAdmin = User::factory()->create([
            'business_id' => null,
            'role' => 'super_admin',
        ]);

        $this->actingAs($superAdmin);

        $employees = Employee::all();

        $this->assertCount(2, $employees);
    }

    public function test_middleware_allows_users_with_business(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create([
            'business_id' => $business->id,
            'role' => 'business_admin',
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
    }

    public function test_users_cannot_access_data_from_other_businesses(): void
    {
        $business1 = Business::factory()->create();
        $business2 = Business::factory()->create();

        $user1 = User::factory()->create([
            'business_id' => $business1->id,
            'role' => 'business_admin',
        ]);

        $user2 = User::factory()->create([
            'business_id' => $business2->id,
            'role' => 'business_admin',
        ]);

        $employee2 = Employee::factory()->create([
            'user_id' => $user2->id,
            'business_id' => $business2->id,
        ]);

        // Act as user from business 1
        $this->actingAs($user1);

        // Try to find employee from business 2 - should return null
        $foundEmployee = Employee::find($employee2->id);

        $this->assertNull($foundEmployee);
    }

    public function test_belongs_to_business_trait_applies_global_scope(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create([
            'business_id' => $business->id,
            'role' => 'employee',
        ]);

        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
        ]);

        $this->actingAs($user);

        // Employee model uses BelongsToBusiness trait
        $this->assertTrue(
            in_array('App\Models\Traits\BelongsToBusiness', class_uses(Employee::class))
        );

        // Verify business relationship exists
        $this->assertInstanceOf(Business::class, $employee->business);
        $this->assertEquals($business->id, $employee->business->id);
    }

    public function test_user_role_helper_methods_work_correctly(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $businessAdmin = User::factory()->create(['role' => 'business_admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $client = User::factory()->create(['role' => 'client']);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($superAdmin->isBusinessAdmin());

        $this->assertTrue($businessAdmin->isBusinessAdmin());
        $this->assertFalse($businessAdmin->isEmployee());

        $this->assertTrue($employee->isEmployee());
        $this->assertFalse($employee->isClient());

        $this->assertTrue($client->isClient());
        $this->assertFalse($client->isSuperAdmin());
    }
}
