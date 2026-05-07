<?php

declare(strict_types=1);

namespace Tests\Feature\Business;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlockedClientHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Business $businessA;

    private Business $businessB;

    private User $adminA;

    private User $client;

    private Service $serviceA;

    private Service $serviceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessA = Business::factory()->create(['name' => 'Business A']);
        $this->businessB = Business::factory()->create(['name' => 'Business B']);

        $this->adminA = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->businessA->id,
        ]);

        $this->client = User::factory()->create([
            'role' => 'client',
            'business_id' => $this->businessA->id,
        ]);

        $this->serviceA = Service::factory()->create([
            'business_id' => $this->businessA->id,
            'is_active' => true,
        ]);

        $this->serviceB = Service::factory()->create([
            'business_id' => $this->businessB->id,
            'is_active' => true,
        ]);

        // Enroll client as active in business A.
        DB::table('user_business')->insert([
            'user_id' => $this->client->id,
            'business_id' => $this->businessA->id,
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * A blocked client in business A can still view their historical
     * appointments via the admin Show page (the admin sees the client's history).
     */
    public function test_admin_can_view_historical_appointments_of_blocked_client(): void
    {
        // Create a historical appointment before the client was blocked.
        Appointment::factory()->create([
            'business_id' => $this->businessA->id,
            'client_id' => $this->client->id,
            'service_id' => $this->serviceA->id,
            'status' => 'completed',
            'scheduled_at' => now()->subDays(10),
        ]);

        // Block the client.
        DB::table('user_business')
            ->where('user_id', $this->client->id)
            ->where('business_id', $this->businessA->id)
            ->update([
                'status' => 'blocked',
                'blocked_at' => now(),
                'blocked_reason' => 'Multiple last-minute cancellations',
                'updated_at' => now(),
            ]);

        // Admin can still access the client's profile and see history.
        $response = $this->actingAs($this->adminA)
            ->get("/clients/{$this->client->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Show')
            ->has('recent_appointments', 1)
            ->where('client.pivot_status', 'blocked')
        );
    }

    /**
     * A blocked client in business A cannot create a new appointment in A
     * because canBookIn() returns false.
     */
    public function test_blocked_client_cannot_create_appointment_in_blocked_business(): void
    {
        // Block the client in business A.
        DB::table('user_business')
            ->where('user_id', $this->client->id)
            ->where('business_id', $this->businessA->id)
            ->update([
                'status' => 'blocked',
                'blocked_at' => now(),
                'blocked_reason' => 'Misbehaving client should not book',
                'updated_at' => now(),
            ]);

        // Reload fresh from DB to ensure no cached state.
        $freshClient = User::find($this->client->id);
        $freshBusinessA = Business::find($this->businessA->id);

        $this->assertFalse(
            $freshClient->canBookIn($freshBusinessA),
            'A blocked client must not be able to book in the same business.'
        );
    }

    /**
     * A client blocked in A but active in B can still book in B.
     */
    public function test_client_blocked_in_a_can_still_book_in_b(): void
    {
        // Enroll client as active in business B.
        DB::table('user_business')->insert([
            'user_id' => $this->client->id,
            'business_id' => $this->businessB->id,
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Block the client in business A.
        DB::table('user_business')
            ->where('user_id', $this->client->id)
            ->where('business_id', $this->businessA->id)
            ->update([
                'status' => 'blocked',
                'blocked_at' => now(),
                'blocked_reason' => 'Blocked only in business A',
                'updated_at' => now(),
            ]);

        $freshClient = User::find($this->client->id);
        $freshBusinessA = Business::find($this->businessA->id);
        $freshBusinessB = Business::find($this->businessB->id);

        $this->assertFalse(
            $freshClient->canBookIn($freshBusinessA),
            'Client should not be able to book in business A where they are blocked.'
        );

        $this->assertTrue(
            $freshClient->canBookIn($freshBusinessB),
            'Client should still be able to book in business B where they are active.'
        );
    }
}
