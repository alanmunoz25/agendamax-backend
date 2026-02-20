<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registered_user_gets_default_business_id(): void
    {
        $business = Business::factory()->create();

        config(['app.default_business_id' => $business->id]);

        $this->post(route('register.store'), [
            'name' => 'New Client',
            'email' => 'newclient@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'newclient@example.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals($business->id, $user->business_id);
        $this->assertEquals('client', $user->role);
    }

    public function test_registered_user_has_null_business_id_when_not_configured(): void
    {
        config(['app.default_business_id' => null]);

        $this->post(route('register.store'), [
            'name' => 'New User',
            'email' => 'notenancy@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'notenancy@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->business_id);
        $this->assertEquals('client', $user->role);
    }

    public function test_email_uniqueness_is_scoped_to_business(): void
    {
        $business = Business::factory()->create();

        config(['app.default_business_id' => $business->id]);

        // Create first user
        User::factory()->create([
            'email' => 'duplicate@example.com',
            'business_id' => $business->id,
        ]);

        // Try to register with same email in same business
        $response = $this->post(route('register.store'), [
            'name' => 'Duplicate User',
            'email' => 'duplicate@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
