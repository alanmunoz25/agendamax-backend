<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyAppointmentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_client_can_access_my_appointments(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)->get('/mi-cuenta/citas');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Client/MyAppointments'));
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/mi-cuenta/citas');

        $response->assertRedirect('/login');
    }

    public function test_business_admin_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'business_admin']);

        $response = $this->actingAs($admin)->get('/mi-cuenta/citas');

        $response->assertForbidden();
    }

    public function test_appointments_grouped_prop_is_present(): void
    {
        $business = Business::factory()->create(['status' => 'active']);

        $client = User::factory()->create(['role' => 'client']);

        Appointment::factory()->create([
            'business_id' => $business->id,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($client)->get('/mi-cuenta/citas');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Client/MyAppointments')
            ->has('appointments_grouped')
        );
    }
}
