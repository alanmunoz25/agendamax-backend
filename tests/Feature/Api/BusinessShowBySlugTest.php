<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessShowBySlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_by_slug_returns_200_for_active_business(): void
    {
        $business = Business::factory()->create(['status' => 'active']);

        $response = $this->getJson("/api/v1/businesses/by-slug/{$business->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $business->id)
            ->assertJsonPath('data.slug', $business->slug)
            ->assertJsonPath('data.name', $business->name);
    }

    public function test_show_by_slug_returns_404_for_inactive_business(): void
    {
        $business = Business::factory()->inactive()->create();

        $response = $this->getJson("/api/v1/businesses/by-slug/{$business->slug}");

        $response->assertStatus(404);
    }

    public function test_show_by_slug_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->getJson('/api/v1/businesses/by-slug/this-slug-does-not-exist');

        $response->assertStatus(404);
    }

    public function test_show_by_slug_does_not_include_employee_email(): void
    {
        $business = Business::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['email' => 'employee@secret.com']);
        Employee::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/by-slug/{$business->slug}");

        $response->assertStatus(200);
        $this->assertStringNotContainsString('employee@secret.com', $response->content());
    }

    public function test_show_by_slug_includes_services_employees_categories(): void
    {
        $business = Business::factory()->create(['status' => 'active']);
        Service::factory()->create(['business_id' => $business->id, 'is_active' => true]);
        $user = User::factory()->create();
        Employee::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/by-slug/{$business->slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'services',
                    'employees',
                    'categories',
                ],
            ])
            ->assertJsonCount(1, 'data.services')
            ->assertJsonCount(1, 'data.employees');
    }
}
