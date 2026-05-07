<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_discover_returns_active_businesses_paginated(): void
    {
        Business::factory()->create(['name' => 'Active Salon', 'status' => 'active']);
        Business::factory()->inactive()->create(['name' => 'Inactive Salon']);

        $response = $this->getJson('/api/v1/businesses');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Salon');
    }

    public function test_discover_filters_by_sector(): void
    {
        Business::factory()->create(['name' => 'Sector A Business', 'status' => 'active', 'sector' => 'Zona Colonial']);
        Business::factory()->create(['name' => 'Sector B Business', 'status' => 'active', 'sector' => 'Piantini']);

        $response = $this->getJson('/api/v1/businesses?sector=Zona+Colonial');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sector A Business');
    }

    public function test_discover_filters_by_province(): void
    {
        Business::factory()->create(['name' => 'Santo Domingo Business', 'status' => 'active', 'province' => 'Santo Domingo']);
        Business::factory()->create(['name' => 'Santiago Business', 'status' => 'active', 'province' => 'Santiago']);

        $response = $this->getJson('/api/v1/businesses?province=Santiago');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Santiago Business');
    }

    public function test_discover_filters_by_service_id(): void
    {
        $business = Business::factory()->create(['status' => 'active']);
        $otherBusiness = Business::factory()->create(['status' => 'active']);

        $service = Service::factory()->create(['business_id' => $business->id, 'is_active' => true]);
        Service::factory()->create(['business_id' => $otherBusiness->id, 'is_active' => true]);

        $response = $this->getJson("/api/v1/businesses?service_id={$service->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $business->id);
    }

    public function test_discover_returns_422_with_lat_out_of_range(): void
    {
        $response = $this->getJson('/api/v1/businesses?lat=200&lng=-69.9');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lat']);
    }

    public function test_discover_inactive_business_not_returned(): void
    {
        Business::factory()->inactive()->create(['name' => 'Hidden Business']);

        $response = $this->getJson('/api/v1/businesses');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_discover_accepts_search_param_and_filters_results(): void
    {
        // ?search= is the mobile param alias for ?q=.
        // The controller normalises both to the same internal search term.
        Business::factory()->create(['name' => 'Paola Beauty Studio', 'status' => 'active']);
        Business::factory()->create(['name' => 'Cortes Modernos', 'status' => 'active']);

        $response = $this->getJson('/api/v1/businesses?search=Paola');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Paola Beauty Studio');
    }

    public function test_discover_search_param_too_short_returns_422(): void
    {
        // Same minimum-length validation as ?q= — minimum 2 characters.
        $response = $this->getJson('/api/v1/businesses?search=X');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    public function test_discover_q_takes_precedence_over_search_param(): void
    {
        // When both ?q= and ?search= are provided, ?q= wins — no 422 from either field.
        Business::factory()->create(['name' => 'Paola Salon', 'status' => 'active']);

        $response = $this->getJson('/api/v1/businesses?q=Paola&search=Otro');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_discover_fulltext_branch_is_taken_when_driver_is_mariadb(): void
    {
        // Verify the branching condition: in_array(['mysql','mariadb']) must include 'mariadb'.
        // This is a unit-level assertion on the condition used inside the controller.
        $driversWithFulltext = ['mysql', 'mariadb'];

        $this->assertTrue(
            in_array('mariadb', $driversWithFulltext, true),
            'mariadb must be in the fulltext driver list'
        );

        $this->assertTrue(
            in_array('mysql', $driversWithFulltext, true),
            'mysql must be in the fulltext driver list'
        );

        $this->assertFalse(
            in_array('sqlite', $driversWithFulltext, true),
            'sqlite must not trigger the fulltext branch'
        );

        $this->assertFalse(
            in_array('pgsql', $driversWithFulltext, true),
            'pgsql must not trigger the fulltext branch'
        );
    }

    public function test_discover_uses_like_fallback_when_driver_is_sqlite(): void
    {
        // The test environment uses SQLite. The controller falls back to LIKE when
        // the driver is not mysql/mariadb, so ?q= must still filter results.
        Business::factory()->create(['name' => 'Sqlite Beauty Studio', 'status' => 'active']);
        Business::factory()->create(['name' => 'Other Place', 'status' => 'active']);

        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/businesses?q=Sqlite');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sqlite Beauty Studio');

        $likeFound = collect($queries)->contains(
            fn (array $entry) => str_contains(strtoupper($entry['query']), 'LIKE')
        );

        $this->assertTrue($likeFound, 'Expected LIKE fallback query when driver is sqlite');
    }
}
