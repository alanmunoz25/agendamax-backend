<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\Stamp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Business $otherBusiness;

    private User $businessAdmin;

    private User $otherBusinessAdmin;

    private User $employee;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'name' => 'Test Business',
            'loyalty_stamps_required' => 10,
            'loyalty_reward_description' => 'Free service',
        ]);

        $this->otherBusiness = Business::factory()->create([
            'name' => 'Other Business',
        ]);

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $this->otherBusinessAdmin = User::factory()->create([
            'business_id' => $this->otherBusiness->id,
            'role' => 'business_admin',
        ]);

        $this->employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
            'name' => 'Test Client',
            'email' => 'client@test.com',
            'phone' => '+1234567890',
        ]);
    }

    /**
     * Create an Employee record for the employee user and link them to the client via an appointment.
     */
    private function linkEmployeeToClient(): Employee
    {
        $employeeRecord = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $this->employee->id,
            'is_active' => true,
        ]);

        $service = Service::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $service->id,
            'employee_id' => $employeeRecord->id,
            'client_id' => $this->client->id,
            'scheduled_at' => now()->addDay(),
        ]);

        return $employeeRecord;
    }

    public function test_business_admin_can_view_clients_index(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/clients');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data', 1)
            ->has('clients.data.0', fn ($item) => $item
                ->where('id', $this->client->id)
                ->where('name', 'Test Client')
                ->where('email', 'client@test.com')
                ->where('phone', '+1234567890')
                ->has('appointments_count')
                ->has('stamps_count')
                ->etc()
            )
        );
    }

    public function test_employee_can_view_clients_index(): void
    {
        $this->linkEmployeeToClient();

        $response = $this->actingAs($this->employee)
            ->get('/clients');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $this->client->id)
        );
    }

    public function test_clients_index_shows_only_business_clients(): void
    {
        $otherClient = User::factory()->create([
            'business_id' => $this->otherBusiness->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/clients');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $this->client->id)
        );
    }

    public function test_clients_index_search_filters_results(): void
    {
        $anotherClient = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/clients?search=John');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $anotherClient->id)
        );
    }

    public function test_clients_index_can_sort_by_name(): void
    {
        User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
            'name' => 'Alice Smith',
        ]);

        User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
            'name' => 'Zoe Williams',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/clients?sort=name&direction=asc');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data', 3)
            ->where('clients.data.0.name', 'Alice Smith')
        );
    }

    public function test_business_admin_can_view_create_client_page(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/clients/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Create')
        );
    }

    public function test_business_admin_can_create_client(): void
    {
        $clientData = [
            'name' => 'New Client',
            'email' => 'newclient@test.com',
            'phone' => '+9876543210',
            'avatar_url' => 'https://example.com/avatar.jpg',
        ];

        $response = $this->actingAs($this->businessAdmin)
            ->post('/clients', $clientData);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'New Client',
            'email' => 'newclient@test.com',
            'phone' => '+9876543210',
            'avatar_url' => 'https://example.com/avatar.jpg',
            'primary_business_id' => $this->business->id,
            'role' => 'client',
        ]);
    }

    public function test_create_client_requires_name(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/clients', [
                'email' => 'test@test.com',
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_create_client_requires_email(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/clients', [
                'name' => 'Test Client',
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_create_client_requires_valid_email(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/clients', [
                'name' => 'Test Client',
                'email' => 'invalid-email',
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_create_client_requires_unique_email_per_business(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/clients', [
                'name' => 'Duplicate Email Client',
                'email' => 'client@test.com', // Already exists
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_create_client_allows_duplicate_email_across_businesses(): void
    {
        $response = $this->actingAs($this->otherBusinessAdmin)
            ->post('/clients', [
                'name' => 'Other Business Client',
                'email' => 'client@test.com', // Same email, different business
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'client@test.com',
            'primary_business_id' => $this->otherBusiness->id,
        ]);
    }

    public function test_create_client_requires_unique_phone_per_business(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/clients', [
                'name' => 'Duplicate Phone Client',
                'email' => 'unique@test.com',
                'phone' => '+1234567890', // Already exists
            ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_create_client_allows_duplicate_phone_across_businesses(): void
    {
        $response = $this->actingAs($this->otherBusinessAdmin)
            ->post('/clients', [
                'name' => 'Other Business Client',
                'email' => 'other@test.com',
                'phone' => '+1234567890', // Same phone, different business
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'phone' => '+1234567890',
            'primary_business_id' => $this->otherBusiness->id,
        ]);
    }

    public function test_create_client_validates_avatar_url_format(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/clients', [
                'name' => 'Test Client',
                'email' => 'avatar@test.com',
                'avatar_url' => 'not-a-url',
            ]);

        $response->assertSessionHasErrors('avatar_url');
    }

    public function test_business_admin_can_view_client_profile(): void
    {
        // Create test data
        Stamp::factory()->count(5)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/clients/{$this->client->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Show')
            ->has('client', fn ($item) => $item
                ->where('id', $this->client->id)
                ->where('name', 'Test Client')
                ->where('email', 'client@test.com')
                ->etc()
            )
            ->has('loyalty_progress', fn ($progress) => $progress
                ->has('current_stamps')
                ->has('stamps_required')
                ->has('progress_percentage')
                ->has('stamps_until_reward')
                ->etc()
            )
            ->has('recent_appointments')
            ->has('recent_stamps')
        );
    }

    public function test_employee_can_view_client_profile(): void
    {
        $this->linkEmployeeToClient();

        $response = $this->actingAs($this->employee)
            ->get("/clients/{$this->client->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Show')
            ->has('client')
            ->has('loyalty_progress')
        );
    }

    public function test_client_can_view_own_profile(): void
    {
        $response = $this->actingAs($this->client)
            ->get("/clients/{$this->client->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Show')
            ->where('client.id', $this->client->id)
        );
    }

    public function test_other_business_admin_cannot_view_client_profile(): void
    {
        $response = $this->actingAs($this->otherBusinessAdmin)
            ->get("/clients/{$this->client->id}");

        $response->assertNotFound();
    }

    public function test_client_cannot_view_other_client_profile(): void
    {
        $otherClient = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->client)
            ->get("/clients/{$otherClient->id}");

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_clients_index(): void
    {
        $response = $this->get('/clients');
        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_access_create_client_page(): void
    {
        $response = $this->get('/clients/create');
        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_create_client(): void
    {
        $response = $this->post('/clients', [
            'name' => 'Test',
            'email' => 'test@test.com',
        ]);
        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_view_client_profile(): void
    {
        $response = $this->get("/clients/{$this->client->id}");
        $response->assertRedirect('/login');
    }

    public function test_loyalty_progress_calculates_correctly(): void
    {
        // Create 3 stamps
        Stamp::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/clients/{$this->client->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Show')
            ->has('loyalty_progress', fn ($progress) => $progress
                ->where('current_stamps', 3)
                ->where('stamps_required', 10)
                ->where('progress_percentage', 30)
                ->where('stamps_until_reward', 7)
                ->where('can_redeem', false)
                ->etc()
            )
        );
    }

    public function test_loyalty_progress_shows_redeemable_when_stamps_met(): void
    {
        // Create 10 stamps (meets requirement)
        Stamp::factory()->count(10)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/clients/{$this->client->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Show')
            ->has('loyalty_progress', fn ($progress) => $progress
                ->where('current_stamps', 10)
                ->where('stamps_required', 10)
                ->where('progress_percentage', 100)
                ->where('stamps_until_reward', 0)
                ->where('can_redeem', true)
                ->etc()
            )
        );
    }

    public function test_client_show_displays_recent_appointments(): void
    {
        // Create multiple appointments
        Appointment::factory()->count(15)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/clients/{$this->client->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Show')
            ->has('recent_appointments', 10) // Should limit to 10
        );
    }

    public function test_clients_index_includes_appointments_count(): void
    {
        Appointment::factory()->count(5)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/clients');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data.0', fn ($item) => $item
                ->where('id', $this->client->id)
                ->where('appointments_count', 5)
                ->etc()
            )
        );
    }

    public function test_clients_index_includes_stamps_count(): void
    {
        Stamp::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/clients');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data.0', fn ($item) => $item
                ->where('id', $this->client->id)
                ->where('stamps_count', 3)
                ->etc()
            )
        );
    }

    public function test_employee_only_sees_assigned_clients(): void
    {
        $this->linkEmployeeToClient();

        // Create another client NOT assigned to this employee
        $unassignedClient = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->employee)
            ->get('/clients');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Index')
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $this->client->id)
        );
    }

    public function test_employee_cannot_view_unassigned_client_profile(): void
    {
        $this->linkEmployeeToClient();

        $unassignedClient = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($this->employee)
            ->get("/clients/{$unassignedClient->id}");

        $response->assertForbidden();
    }

    public function test_employee_cannot_create_client(): void
    {
        $response = $this->actingAs($this->employee)
            ->get('/clients/create');

        $response->assertForbidden();
    }

    public function test_clients_index_returns_can_permissions(): void
    {
        // Business admin can create
        $response = $this->actingAs($this->businessAdmin)
            ->get('/clients');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.create', true)
        );

        // Employee cannot create
        $this->linkEmployeeToClient();

        $response = $this->actingAs($this->employee)
            ->get('/clients');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.create', false)
        );
    }
}
