<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->business = Business::factory()->create([
            'name' => 'Test Business',
        ]);
    }

    public function test_public_api_returns_active_promotions(): void
    {
        Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Active Promo',
            'is_active' => true,
            'expires_at' => null,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/promotions");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Active Promo');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'image_url', 'url', 'expires_at', 'is_active'],
            ],
        ]);
    }

    public function test_public_api_excludes_inactive_promotions(): void
    {
        Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Active Promo',
            'is_active' => true,
        ]);

        Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Inactive Promo',
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/promotions");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Active Promo');
    }

    public function test_public_api_excludes_expired_promotions(): void
    {
        Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Valid Promo',
            'is_active' => true,
            'expires_at' => now()->addWeek(),
        ]);

        Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Expired Promo',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/promotions");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Valid Promo');
    }

    public function test_public_api_includes_promotions_with_no_expiration(): void
    {
        Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'No Expiry',
            'is_active' => true,
            'expires_at' => null,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/promotions");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'No Expiry');
    }

    public function test_public_api_does_not_require_authentication(): void
    {
        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/promotions");

        $response->assertOk();
    }

    public function test_public_api_returns_404_for_nonexistent_business(): void
    {
        $response = $this->getJson('/api/v1/businesses/99999/promotions');

        $response->assertNotFound();
    }

    public function test_public_api_only_returns_promotions_for_specified_business(): void
    {
        $otherBusiness = Business::factory()->create();

        Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'My Promo',
            'is_active' => true,
        ]);

        Promotion::factory()->create([
            'business_id' => $otherBusiness->id,
            'title' => 'Other Promo',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/promotions");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'My Promo');
    }

    public function test_public_api_includes_today_expiring_promotions(): void
    {
        Promotion::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Expires Today',
            'is_active' => true,
            'expires_at' => today(),
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/promotions");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Expires Today');
    }
}
