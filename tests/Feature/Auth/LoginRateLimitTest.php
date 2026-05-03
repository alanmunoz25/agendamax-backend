<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear all rate limiters before each test to prevent bleed
        RateLimiter::clear('throttle:5|1');
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('throttle:5|1');

        parent::tearDown();
    }

    public function test_login_throttled_after_5_attempts_per_minute(): void
    {
        // Make 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'nonexistent@test.com',
                'password' => 'wrong-password',
            ]);
        }

        // 6th attempt should be throttled
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_login_throttle_resets_after_window(): void
    {
        // Use a unique email to ensure a fresh rate-limit key separate from
        // other tests running in the same process.
        $email = 'unique-reset-test@test.com';

        // Make 5 failed attempts to exhaust the limit
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ]);
        }

        // 6th attempt triggers 429
        $throttled = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);
        $throttled->assertStatus(429);

        // Flush cache entirely to simulate the throttle window expiring
        \Illuminate\Support\Facades\Cache::flush();

        // After reset, a new attempt should not be throttled (may still fail auth, but not 429)
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        // Should be 422 (validation/auth failure) not 429 (throttled)
        $this->assertNotEquals(429, $response->status());
    }

    public function test_register_throttled(): void
    {
        $business = Business::factory()->create();

        // Make 5 registration attempts (some may fail for other validation reasons)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', [
                'name' => 'Test User',
                'email' => "test{$i}@example.com",
                'password' => 'password123',
                'business_id' => $business->id,
            ]);
        }

        // 6th attempt should be throttled
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test99@example.com',
            'password' => 'password123',
            'business_id' => $business->id,
        ]);

        $response->assertStatus(429);
    }
}
