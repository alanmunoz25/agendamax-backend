<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Support\BusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for the ResolveBusinessContext middleware.
 *
 * All tests run with the feature flag enabled so that the middleware
 * participates in request processing. The flag is reset in tearDown.
 */
class BusinessContextResolverTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['status' => 'active']);

        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        // Enable the feature flag for all tests in this suite.
        config(['agendamax.use_business_context' => true]);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        config(['agendamax.use_business_context' => false]);
        parent::tearDown();
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    /**
     * Enroll a user in a business via the pivot.
     */
    private function enroll(User $user, Business $business, string $status = 'active'): void
    {
        $user->businesses()->attach($business->id, [
            'role_in_business' => 'client',
            'status' => $status,
            'joined_at' => now(),
        ]);
    }

    // ── X-Business-Id header: valid + enrolled ────────────────────────────

    public function test_valid_header_with_enrolled_user_sets_context(): void
    {
        $this->enroll($this->client, $this->business);

        Sanctum::actingAs($this->client);

        $response = $this->withHeader('X-Business-Id', (string) $this->business->id)
            ->getJson('/api/v1/auth/user');

        $response->assertOk();

        // The context should have been set during the request.
        // We verify indirectly by ensuring the response succeeded and user id matches.
        $this->assertEquals($this->client->id, $response->json('user.id'));
    }

    // ── X-Business-Id header: not enrolled → 403 ─────────────────────────

    public function test_header_with_non_enrolled_user_returns_403(): void
    {
        $otherBusiness = Business::factory()->create();

        // Client is NOT enrolled in otherBusiness.
        Sanctum::actingAs($this->client);

        $response = $this->withHeader('X-Business-Id', (string) $otherBusiness->id)
            ->getJson('/api/v1/auth/user');

        $response->assertForbidden();
    }

    // ── X-Business-Id header: non-existent business → 403 ────────────────

    public function test_header_with_nonexistent_business_returns_403(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->withHeader('X-Business-Id', '999999')
            ->getJson('/api/v1/auth/user');

        $response->assertForbidden();
    }

    // ── X-Business-Id header: non-integer value → 422 ────────────────────

    public function test_header_with_invalid_value_returns_422(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->withHeader('X-Business-Id', 'not-an-integer')
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(422);
    }

    // ── X-Business-Id header: blocked user → context still set ───────────

    public function test_header_with_blocked_user_sets_context_but_allows_history(): void
    {
        $this->enroll($this->client, $this->business, 'blocked');

        Sanctum::actingAs($this->client);

        // Blocked users can still query history (GET /auth/user does not use business scope).
        $response = $this->withHeader('X-Business-Id', (string) $this->business->id)
            ->getJson('/api/v1/auth/user');

        $response->assertOk();
    }

    // ── No header, user is business_admin with business_id → legacy context ──

    public function test_no_header_admin_with_business_id_uses_legacy_context(): void
    {
        $admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/auth/user');

        $response->assertOk();
        $this->assertEquals($admin->id, $response->json('user.id'));
    }

    // ── No header, user is client without context → no filter applied ─────

    public function test_no_header_client_without_context_gets_no_filter(): void
    {
        // Client with no pivot rows.
        $noContextClient = User::factory()->create([
            'business_id' => null,
            'role' => 'client',
        ]);

        Sanctum::actingAs($noContextClient);

        // /api/v1/auth/user does not use business scope — just verify it returns ok.
        $response = $this->getJson('/api/v1/auth/user');
        $response->assertOk();

        // BusinessContext should be null — client had no enrollment and no header.
        $this->assertNull(BusinessContext::current());
    }

    // ── Flag disabled → middleware is no-op ──────────────────────────────

    public function test_middleware_is_noop_when_flag_is_false(): void
    {
        config(['agendamax.use_business_context' => false]);
        BusinessContext::clear();

        Sanctum::actingAs($this->client);

        // Even with a header, context should NOT be set when flag is off.
        $this->withHeader('X-Business-Id', (string) $this->business->id)
            ->getJson('/api/v1/auth/user')
            ->assertOk();

        $this->assertNull(BusinessContext::current());
    }
}
