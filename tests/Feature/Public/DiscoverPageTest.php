<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_discover_page_is_accessible_without_auth(): void
    {
        $response = $this->get('/buscar');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Public/Discover'));
    }

    public function test_discover_page_passes_required_props(): void
    {
        Business::factory()->count(3)->create(['status' => 'active']);

        $response = $this->get('/buscar');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Discover')
            ->has('businesses')
            ->has('sectors')
            ->has('provinces')
        );
    }

    public function test_discover_page_does_not_require_session(): void
    {
        // The /buscar route is fully public — no auth middleware applied.
        // Sending a request with no cookies / session must still return 200.
        $response = $this->get('/buscar');

        $response->assertOk();
    }
}
