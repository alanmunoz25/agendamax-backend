<?php

namespace Tests\Feature\Dashboard;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardClientCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_correct_client_count(): void
    {
        $business = Business::factory()->create();
        $admin = User::factory()->create([
            'role' => 'business_admin',
            'primary_business_id' => $business->id,
        ]);

        User::factory()->count(3)->create([
            'role' => 'client',
            'primary_business_id' => $business->id,
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('stats.total_clients', 3)
        );
    }
}
