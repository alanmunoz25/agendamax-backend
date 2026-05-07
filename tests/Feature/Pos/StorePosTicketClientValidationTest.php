<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorePosTicketClientValidationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $cashier;

    private Employee $employee;

    private Service $service;

    /** @var array<string, mixed> */
    private array $basePayload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

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
            'price' => 500,
            'is_active' => true,
        ]);

        $this->basePayload = [
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '500.00',
                    'qty' => 1,
                    'employee_id' => null,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '18',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '590.00'],
            ],
            'ecf_requested' => false,
            'notes' => null,
        ];
    }

    /**
     * Insert a row in the user_business pivot table for a given user.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function enrollUserInBusiness(User $user, Business $business, array $overrides = []): void
    {
        DB::table('user_business')->insertOrIgnore(array_merge([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_client_enrolled_via_pivot_with_active_status_is_accepted(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->enrollUserInBusiness($client, $this->business, ['status' => 'active']);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), array_merge(
            $this->basePayload,
            ['client_id' => $client->id],
        ));

        $response->assertSessionDoesntHaveErrors('client_id');
    }

    public function test_client_enrolled_with_blocked_status_is_accepted_for_billing(): void
    {
        // Blocked clients can still be billed for past services.
        $client = User::factory()->create(['role' => 'client']);

        $this->enrollUserInBusiness($client, $this->business, ['status' => 'blocked']);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), array_merge(
            $this->basePayload,
            ['client_id' => $client->id],
        ));

        $response->assertSessionDoesntHaveErrors('client_id');
    }

    public function test_client_enrolled_with_left_status_is_rejected(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->enrollUserInBusiness($client, $this->business, ['status' => 'left']);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), array_merge(
            $this->basePayload,
            ['client_id' => $client->id],
        ));

        $response->assertSessionHasErrors('client_id');
    }

    public function test_client_with_different_primary_business_but_not_enrolled_in_pivot_is_rejected(): void
    {
        $otherBusiness = Business::factory()->create();

        // This client belongs to a different primary_business_id and has NO pivot entry
        // for $this->business — should be rejected.
        $client = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'client',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), array_merge(
            $this->basePayload,
            ['client_id' => $client->id],
        ));

        $response->assertSessionHasErrors('client_id');
    }

    public function test_client_with_null_primary_business_but_enrolled_in_pivot_is_accepted(): void
    {
        // Client created without a primary_business_id (global client)
        // but explicitly enrolled in this business via the pivot.
        $client = User::factory()->create([
            'role' => 'client',
            'primary_business_id' => null,
        ]);

        $this->enrollUserInBusiness($client, $this->business, ['status' => 'active']);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), array_merge(
            $this->basePayload,
            ['client_id' => $client->id],
        ));

        $response->assertSessionDoesntHaveErrors('client_id');
    }

    public function test_null_client_id_is_accepted_for_walk_in_ticket(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), array_merge(
            $this->basePayload,
            ['client_id' => null],
        ));

        $response->assertSessionDoesntHaveErrors('client_id');
    }
}
