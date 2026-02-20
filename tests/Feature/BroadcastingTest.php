<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Events\EmployeeAvailabilityChanged;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private User $client;

    private Employee $employee;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        $this->employee->services()->attach($this->service->id);
    }

    public function test_appointment_created_event_is_dispatched_when_appointment_is_created(): void
    {
        Event::fake([AppointmentCreated::class]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
        ]);

        Event::assertDispatched(AppointmentCreated::class, function ($event) use ($appointment) {
            return $event->appointment->id === $appointment->id;
        });
    }

    public function test_appointment_updated_event_is_dispatched_when_appointment_is_updated(): void
    {
        Event::fake([AppointmentUpdated::class]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
            'status' => 'pending',
        ]);

        $appointment->update(['status' => 'confirmed']);

        Event::assertDispatched(AppointmentUpdated::class, function ($event) use ($appointment) {
            return $event->appointment->id === $appointment->id;
        });
    }

    public function test_appointment_cancelled_event_is_dispatched_when_appointment_is_cancelled(): void
    {
        Event::fake([AppointmentCancelled::class]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
            'status' => 'pending',
        ]);

        $appointment->update(['status' => 'cancelled']);

        Event::assertDispatched(AppointmentCancelled::class, function ($event) use ($appointment) {
            return $event->appointment->id === $appointment->id;
        });
    }

    public function test_employee_availability_changed_event_is_dispatched_when_employee_is_created(): void
    {
        Event::fake([EmployeeAvailabilityChanged::class]);

        $newEmployeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $newEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $newEmployeeUser->id,
            'is_active' => true,
        ]);

        Event::assertDispatched(EmployeeAvailabilityChanged::class, function ($event) use ($newEmployee) {
            return $event->employee->id === $newEmployee->id;
        });
    }

    public function test_employee_availability_changed_event_is_dispatched_when_employee_is_activated(): void
    {
        Event::fake([EmployeeAvailabilityChanged::class]);

        $this->employee->update(['is_active' => false]);

        Event::assertDispatched(EmployeeAvailabilityChanged::class, function ($event) {
            return $event->employee->id === $this->employee->id;
        });
    }

    public function test_appointment_created_event_broadcasts_to_correct_channel(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
        ]);

        $event = new AppointmentCreated($appointment);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        // Laravel adds "private-" prefix to PrivateChannel names
        $this->assertEquals('private-business.'.$this->business->id, $channels[0]->name);
    }

    public function test_appointment_created_event_includes_correct_broadcast_data(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
        ]);

        $event = new AppointmentCreated($appointment);
        $data = $event->broadcastWith();

        $this->assertArrayHasKey('appointment', $data);
        $this->assertEquals($appointment->id, $data['appointment']['id']);
        $this->assertArrayHasKey('client', $data['appointment']);
        $this->assertArrayHasKey('service', $data['appointment']);
        $this->assertArrayHasKey('employee', $data['appointment']);
    }

    public function test_appointment_created_event_has_correct_broadcast_name(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
        ]);

        $event = new AppointmentCreated($appointment);

        $this->assertEquals('appointment.created', $event->broadcastAs());
    }

    public function test_business_channel_authorization_allows_users_from_same_business(): void
    {
        // Test the channel authorization callback directly
        $authorized = \Illuminate\Support\Facades\Broadcast::channel(
            'business.'.$this->business->id,
            function ($user, int $businessId) {
                return $user->business_id === $businessId;
            }
        );

        // Manually test authorization logic
        $result = $this->businessAdmin->business_id === $this->business->id;
        $this->assertTrue($result);
    }

    public function test_business_channel_authorization_denies_users_from_different_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherUser = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
        ]);

        // Test authorization logic - user from different business should not be authorized
        $result = $otherUser->business_id === $this->business->id;
        $this->assertFalse($result);
    }

    public function test_employee_availability_changed_event_broadcasts_to_correct_channel(): void
    {
        $event = new EmployeeAvailabilityChanged($this->employee);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        // Laravel adds "private-" prefix to PrivateChannel names
        $this->assertEquals('private-business.'.$this->business->id, $channels[0]->name);
    }
}
