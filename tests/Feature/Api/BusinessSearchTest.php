<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_businesses_by_name(): void
    {
        Business::factory()->create(['name' => 'Barber Shop Pro', 'status' => 'active']);
        Business::factory()->create(['name' => 'Nail Salon Lux', 'status' => 'active']);

        $response = $this->getJson('/api/v1/businesses/search?q=Barber');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Barber Shop Pro');
    }

    public function test_search_requires_at_least_two_characters(): void
    {
        $response = $this->getJson('/api/v1/businesses/search?q=B');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_only_returns_active_businesses(): void
    {
        Business::factory()->create(['name' => 'Active Barbershop', 'status' => 'active']);
        Business::factory()->inactive()->create(['name' => 'Inactive Barbershop']);

        $response = $this->getJson('/api/v1/businesses/search?q=Barbershop');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Barbershop');
    }

    public function test_search_returns_empty_for_no_matches(): void
    {
        Business::factory()->create(['name' => 'Barber Shop', 'status' => 'active']);

        $response = $this->getJson('/api/v1/businesses/search?q=Nonexistent');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_search_limits_results_to_twenty(): void
    {
        Business::factory()->count(25)->create([
            'name' => 'Test Business',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/businesses/search?q=Test');

        $response->assertStatus(200)
            ->assertJsonCount(20, 'data');
    }
}
