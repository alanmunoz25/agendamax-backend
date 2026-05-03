<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Jobs\EmitEcfJob;
use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PosFcmHookTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $cashier;

    private Employee $employee;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['pos_commissions_enabled' => false]);

        $this->cashier = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
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
            'price' => '850.00',
            'is_active' => true,
        ]);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'appointment_id' => null,
            'client_name' => 'Test Client',
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '850.00',
                    'qty' => 1,
                    'employee_id' => $this->employee->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '18',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '1003.00'],
            ],
            'ecf_requested' => false,
        ], $overrides);
    }

    public function test_ecf_job_dispatched_when_toggle_on_and_fe_config_active(): void
    {
        Bus::fake();

        // Business has active fe config
        BusinessFeConfig::factory()->create([
            'business_id' => $this->business->id,
            'activo' => true,
        ]);

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), $this->basePayload([
            'ecf_requested' => true,
            'ecf_type' => 'consumidor_final',
        ]));

        Bus::assertDispatched(EmitEcfJob::class);
    }

    public function test_ecf_job_not_dispatched_when_toggle_off(): void
    {
        Bus::fake();

        BusinessFeConfig::factory()->create([
            'business_id' => $this->business->id,
            'activo' => true,
        ]);

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), $this->basePayload([
            'ecf_requested' => false,
        ]));

        Bus::assertNotDispatched(EmitEcfJob::class);
    }

    public function test_ecf_job_dispatched_regardless_of_fe_config_when_toggle_on(): void
    {
        // The job dispatch is driven by the user toggle, not the fe config
        // (fe config controls whether the switch is shown in the UI, not the backend dispatch)
        Bus::fake();

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), $this->basePayload([
            'ecf_requested' => true,
            'ecf_type' => 'consumidor_final',
        ]));

        Bus::assertDispatched(EmitEcfJob::class);
    }

    public function test_ecf_status_set_to_pending_when_ecf_requested(): void
    {
        Bus::fake();

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), $this->basePayload([
            'ecf_requested' => true,
            'ecf_type' => 'consumidor_final',
        ]));

        $this->assertDatabaseHas('pos_tickets', [
            'business_id' => $this->business->id,
            'ecf_requested' => true,
            'ecf_status' => 'pending',
        ]);
    }

    public function test_ecf_status_set_to_na_when_ecf_not_requested(): void
    {
        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), $this->basePayload([
            'ecf_requested' => false,
        ]));

        $this->assertDatabaseHas('pos_tickets', [
            'business_id' => $this->business->id,
            'ecf_requested' => false,
            'ecf_status' => 'na',
        ]);
    }

    public function test_ecf_type_stored_correctly(): void
    {
        Bus::fake();

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), $this->basePayload([
            'ecf_requested' => true,
            'ecf_type' => 'credito_fiscal',
        ]));

        $this->assertDatabaseHas('pos_tickets', [
            'business_id' => $this->business->id,
            'ecf_type' => 'credito_fiscal',
        ]);
    }
}
