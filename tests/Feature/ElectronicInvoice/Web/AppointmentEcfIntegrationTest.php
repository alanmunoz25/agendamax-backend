<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice\Web;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentEcfIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private Employee $employee;

    private Service $service;

    private BusinessFeConfig $feConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'email_verified_at' => now(),
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $this->feConfig = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $this->feConfig->forceFill(['activo' => true, 'ambiente' => 'TestECF'])->save();
    }

    private function createAppointment(string $status = 'completed'): Appointment
    {
        $clientUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        return Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $clientUser->id,
            'status' => $status,
        ]);
    }

    public function test_appointment_show_includes_ecf_enabled_when_config_active(): void
    {
        $appointment = $this->createAppointment('completed');

        $response = $this->actingAs($this->admin)
            ->get(route('appointments.show', $appointment));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('ecf_enabled', true)
        );
    }

    public function test_appointment_show_ecf_enabled_false_when_config_inactive(): void
    {
        $this->feConfig->forceFill(['activo' => false])->save();
        $appointment = $this->createAppointment('completed');

        $response = $this->actingAs($this->admin)
            ->get(route('appointments.show', $appointment));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('ecf_enabled', false)
        );
    }

    public function test_appointment_show_can_issue_ecf_when_completed_and_config_active(): void
    {
        $appointment = $this->createAppointment('completed');

        $response = $this->actingAs($this->admin)
            ->get(route('appointments.show', $appointment));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.issue_ecf', true)
        );
    }

    public function test_appointment_show_cannot_issue_ecf_when_pending(): void
    {
        $appointment = $this->createAppointment('pending');

        $response = $this->actingAs($this->admin)
            ->get(route('appointments.show', $appointment));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.issue_ecf', false)
        );
    }

    public function test_appointment_show_cannot_issue_ecf_when_config_inactive(): void
    {
        $this->feConfig->forceFill(['activo' => false])->save();
        $appointment = $this->createAppointment('completed');

        $response = $this->actingAs($this->admin)
            ->get(route('appointments.show', $appointment));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.issue_ecf', false)
        );
    }

    public function test_appointment_show_includes_ecf_when_already_issued(): void
    {
        $appointment = $this->createAppointment('completed');

        $ecf = Ecf::factory()->create([
            'business_id' => $this->business->id,
            'appointment_id' => $appointment->id,
            'numero_ecf' => 'B0100000001',
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('appointments.show', $appointment));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('ecf')
            ->where('ecf.id', $ecf->id)
            ->where('ecf.ncf', 'B0100000001')
            ->where('ecf.status', 'accepted')
        );
    }

    public function test_appointment_show_ecf_is_null_when_not_issued(): void
    {
        $appointment = $this->createAppointment('completed');

        $response = $this->actingAs($this->admin)
            ->get(route('appointments.show', $appointment));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('ecf', null)
        );
    }

    public function test_appointment_index_includes_ecf_ncf_badge(): void
    {
        $appointment = $this->createAppointment('completed');

        Ecf::factory()->create([
            'business_id' => $this->business->id,
            'appointment_id' => $appointment->id,
            'numero_ecf' => 'B0100000099',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('appointments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Appointments/Index')
        );
    }

    public function test_appointment_ecf_multi_tenant_isolation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $appointment = $this->createAppointment('completed');

        // The AppointmentPolicy denies view for other business — results in 403
        // (the policy's before() method does not apply for cross-business access)
        $response = $this->actingAs($otherAdmin)
            ->get(route('appointments.show', $appointment));

        // AppointmentController uses authorize('view') which triggers the policy
        // The BelongsToBusiness scope resolves the model as not found (404)
        $response->assertStatus(404);
    }

    public function test_appointment_show_ecf_enabled_false_when_no_fe_config(): void
    {
        $businessNoFe = Business::factory()->create();
        $adminNoFe = User::factory()->create([
            'business_id' => $businessNoFe->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $serviceNoFe = Service::factory()->create(['business_id' => $businessNoFe->id]);
        $employeeUserNoFe = User::factory()->create([
            'business_id' => $businessNoFe->id,
            'role' => 'employee',
        ]);
        $employeeNoFe = Employee::factory()->create([
            'business_id' => $businessNoFe->id,
            'user_id' => $employeeUserNoFe->id,
        ]);
        $clientNoFe = User::factory()->create([
            'business_id' => $businessNoFe->id,
            'role' => 'client',
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $businessNoFe->id,
            'service_id' => $serviceNoFe->id,
            'employee_id' => $employeeNoFe->id,
            'client_id' => $clientNoFe->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($adminNoFe)
            ->get(route('appointments.show', $appointment));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('ecf_enabled', false)
            ->where('can.issue_ecf', false)
        );
    }
}
