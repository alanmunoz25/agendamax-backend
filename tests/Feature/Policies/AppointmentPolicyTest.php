<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Business $business1;

    private Business $business2;

    private User $superAdmin;

    private User $businessAdmin1;

    private User $businessAdmin2;

    private User $employee1;

    private User $client1;

    private User $client2;

    private Appointment $appointment1;

    private Appointment $appointment2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create businesses
        $this->business1 = Business::factory()->create(['name' => 'Business 1']);
        $this->business2 = Business::factory()->create(['name' => 'Business 2']);

        // Create users with different roles
        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->businessAdmin1 = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business1->id,
        ]);

        $this->businessAdmin2 = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business2->id,
        ]);

        $this->employee1 = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business1->id,
        ]);

        $this->client1 = User::factory()->create([
            'role' => 'client',
            'business_id' => $this->business1->id,
        ]);

        $this->client2 = User::factory()->create([
            'role' => 'client',
            'business_id' => $this->business2->id,
        ]);

        // Create services and employees
        $service1 = Service::factory()->create(['business_id' => $this->business1->id]);
        $service2 = Service::factory()->create(['business_id' => $this->business2->id]);

        $employee1Record = Employee::factory()->create([
            'business_id' => $this->business1->id,
            'user_id' => $this->employee1->id,
        ]);

        // Create appointments
        $this->appointment1 = Appointment::factory()->create([
            'business_id' => $this->business1->id,
            'client_id' => $this->client1->id,
            'service_id' => $service1->id,
            'employee_id' => $employee1Record->id,
        ]);

        $this->appointment2 = Appointment::factory()->create([
            'business_id' => $this->business2->id,
            'client_id' => $this->client2->id,
            'service_id' => $service2->id,
            'employee_id' => Employee::factory()->create(['business_id' => $this->business2->id])->id,
        ]);
    }

    public function test_super_admin_can_view_any_appointments(): void
    {
        $this->assertTrue($this->superAdmin->can('viewAny', Appointment::class));
    }

    public function test_business_admin_can_view_any_appointments_in_their_business(): void
    {
        $this->assertTrue($this->businessAdmin1->can('viewAny', Appointment::class));
    }

    public function test_employee_can_view_any_appointments_in_their_business(): void
    {
        $this->assertTrue($this->employee1->can('viewAny', Appointment::class));
    }

    public function test_client_can_view_any_appointments_in_their_business(): void
    {
        $this->assertTrue($this->client1->can('viewAny', Appointment::class));
    }

    public function test_super_admin_can_view_any_appointment(): void
    {
        $this->assertTrue($this->superAdmin->can('view', $this->appointment1));
        $this->assertTrue($this->superAdmin->can('view', $this->appointment2));
    }

    public function test_business_admin_can_view_appointments_in_their_business(): void
    {
        $this->assertTrue($this->businessAdmin1->can('view', $this->appointment1));
        $this->assertFalse($this->businessAdmin1->can('view', $this->appointment2));
    }

    public function test_employee_can_view_appointments_in_their_business(): void
    {
        $this->assertTrue($this->employee1->can('view', $this->appointment1));
        $this->assertFalse($this->employee1->can('view', $this->appointment2));
    }

    public function test_client_can_only_view_their_own_appointments(): void
    {
        $this->assertTrue($this->client1->can('view', $this->appointment1));
        $this->assertFalse($this->client1->can('view', $this->appointment2));
    }

    public function test_super_admin_can_create_appointments(): void
    {
        $this->assertTrue($this->superAdmin->can('create', Appointment::class));
    }

    public function test_business_admin_can_create_appointments(): void
    {
        $this->assertTrue($this->businessAdmin1->can('create', Appointment::class));
    }

    public function test_client_can_create_appointments(): void
    {
        $this->assertTrue($this->client1->can('create', Appointment::class));
    }

    public function test_employee_cannot_create_appointments(): void
    {
        $this->assertFalse($this->employee1->can('create', Appointment::class));
    }

    public function test_super_admin_can_update_any_appointment(): void
    {
        $this->assertTrue($this->superAdmin->can('update', $this->appointment1));
        $this->assertTrue($this->superAdmin->can('update', $this->appointment2));
    }

    public function test_business_admin_can_update_appointments_in_their_business(): void
    {
        $this->assertTrue($this->businessAdmin1->can('update', $this->appointment1));
        $this->assertFalse($this->businessAdmin1->can('update', $this->appointment2));
    }

    public function test_client_cannot_update_appointments(): void
    {
        $this->assertFalse($this->client1->can('update', $this->appointment1));
    }

    public function test_employee_cannot_update_appointments(): void
    {
        $this->assertFalse($this->employee1->can('update', $this->appointment1));
    }

    public function test_super_admin_can_delete_any_appointment(): void
    {
        $this->assertTrue($this->superAdmin->can('delete', $this->appointment1));
        $this->assertTrue($this->superAdmin->can('delete', $this->appointment2));
    }

    public function test_business_admin_can_delete_appointments_in_their_business(): void
    {
        $this->assertTrue($this->businessAdmin1->can('delete', $this->appointment1));
        $this->assertFalse($this->businessAdmin1->can('delete', $this->appointment2));
    }

    public function test_client_can_cancel_their_own_pending_appointments(): void
    {
        $this->appointment1->update(['status' => 'pending']);
        $this->assertTrue($this->client1->can('delete', $this->appointment1));
    }

    public function test_client_cannot_cancel_completed_appointments(): void
    {
        $this->appointment1->update(['status' => 'completed']);
        $this->assertFalse($this->client1->can('delete', $this->appointment1));
    }

    public function test_client_cannot_cancel_other_clients_appointments(): void
    {
        $this->assertFalse($this->client1->can('delete', $this->appointment2));
    }

    public function test_employee_cannot_delete_appointments(): void
    {
        $this->assertFalse($this->employee1->can('delete', $this->appointment1));
    }

    public function test_super_admin_can_restore_any_appointment(): void
    {
        $this->assertTrue($this->superAdmin->can('restore', $this->appointment1));
    }

    public function test_business_admin_can_restore_appointments_in_their_business(): void
    {
        $this->assertTrue($this->businessAdmin1->can('restore', $this->appointment1));
        $this->assertFalse($this->businessAdmin1->can('restore', $this->appointment2));
    }

    public function test_client_cannot_restore_appointments(): void
    {
        $this->assertFalse($this->client1->can('restore', $this->appointment1));
    }

    public function test_only_super_admin_can_force_delete_appointments(): void
    {
        $this->assertTrue($this->superAdmin->can('forceDelete', $this->appointment1));
        $this->assertFalse($this->businessAdmin1->can('forceDelete', $this->appointment1));
        $this->assertFalse($this->client1->can('forceDelete', $this->appointment1));
    }
}
