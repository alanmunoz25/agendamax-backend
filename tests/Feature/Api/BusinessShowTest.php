<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessShowTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // show() — numeric ID route
    // -------------------------------------------------------------------------

    public function test_show_by_id_returns_200_for_active_business(): void
    {
        $business = Business::factory()->create(['status' => 'active']);

        $response = $this->getJson("/api/v1/businesses/{$business->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $business->id)
            ->assertJsonPath('data.slug', $business->slug)
            ->assertJsonPath('data.name', $business->name)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'invitation_code',
                    'services',
                    'employees',
                    'categories',
                ],
            ]);
    }

    public function test_show_by_id_returns_404_for_inactive_business(): void
    {
        $business = Business::factory()->inactive()->create();

        $response = $this->getJson("/api/v1/businesses/{$business->id}");

        $response->assertStatus(404);
    }

    public function test_show_by_id_returns_404_for_nonexistent_id(): void
    {
        $response = $this->getJson('/api/v1/businesses/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Route disambiguation — numeric vs alphanumeric must NOT cross
    // -------------------------------------------------------------------------

    public function test_numeric_id_route_resolves_to_show_not_invitation_code(): void
    {
        $business = Business::factory()->create(['status' => 'active']);

        // A purely numeric path segment must hit show(), not showByInvitationCode().
        // We verify by checking the response contains the full public profile shape
        // (services, employees, categories) that only show() / showBySlug() return.
        $response = $this->getJson("/api/v1/businesses/{$business->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['services', 'employees', 'categories']]);
    }

    public function test_alphanumeric_invitation_code_resolves_to_show_by_invitation_code(): void
    {
        $business = Business::factory()->create([
            'status' => 'active',
            'invitation_code' => 'EIVE3FX9',
        ]);

        // An alphanumeric segment must hit showByInvitationCode(), NOT show().
        $response = $this->getJson('/api/v1/businesses/EIVE3FX9');

        $response->assertStatus(200)
            ->assertJsonPath('data.invitation_code', 'EIVE3FX9');
    }

    // -------------------------------------------------------------------------
    // by-slug — confirm route still works after route reorder
    // -------------------------------------------------------------------------

    public function test_by_slug_returns_200_after_route_reorder(): void
    {
        $business = Business::factory()->create([
            'status' => 'active',
            'slug' => 'paomakeup-beauty-salon',
        ]);

        $response = $this->getJson('/api/v1/businesses/by-slug/paomakeup-beauty-salon');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'paomakeup-beauty-salon');
    }

    // -------------------------------------------------------------------------
    // Legacy invitation-code route — must not be broken
    // -------------------------------------------------------------------------

    public function test_invitation_code_route_still_returns_200_for_active_business(): void
    {
        $business = Business::factory()->create([
            'status' => 'active',
            'invitation_code' => 'ABC12345',
        ]);

        $response = $this->getJson('/api/v1/businesses/ABC12345');

        $response->assertStatus(200)
            ->assertJsonPath('data.invitation_code', 'ABC12345');
    }

    public function test_invitation_code_route_returns_404_for_inactive_business(): void
    {
        $business = Business::factory()->inactive()->create([
            'invitation_code' => 'INACT123',
        ]);

        $response = $this->getJson('/api/v1/businesses/INACT123');

        $response->assertStatus(404);
    }
}
