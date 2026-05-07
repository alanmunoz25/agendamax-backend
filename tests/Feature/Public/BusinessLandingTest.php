<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessLandingTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'slug' => 'test-salon',
            'status' => 'active',
        ]);
    }

    public function test_public_business_landing_renders_for_active_business(): void
    {
        $response = $this->get('/negocio/test-salon');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/BusinessLanding')
            ->has('business')
            ->has('services')
            ->has('employees')
            ->has('categories')
        );
    }

    public function test_public_business_landing_returns_404_for_inactive_business(): void
    {
        Business::factory()->inactive()->create([
            'slug' => 'inactive-salon',
        ]);

        $response = $this->get('/negocio/inactive-salon');

        $response->assertNotFound();
    }

    public function test_public_business_landing_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->get('/negocio/does-not-exist-xyz');

        $response->assertNotFound();
    }

    public function test_public_business_landing_does_not_expose_employee_email(): void
    {
        $user = User::factory()->create([
            'email' => 'secret-employee@example.com',
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->get('/negocio/test-salon');

        $response->assertOk();
        $this->assertStringNotContainsString(
            'secret-employee@example.com',
            $response->getContent(),
        );
    }

    public function test_public_business_landing_includes_services_employees_categories(): void
    {
        ServiceCategory::factory()->create([
            'business_id' => $this->business->id,
        ]);

        Service::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->get('/negocio/test-salon');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/BusinessLanding')
            ->has('services', 3)
            ->has('employees', 1)
            ->has('categories', 1)
        );
    }

    public function test_public_business_landing_only_shows_active_services(): void
    {
        Service::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => false,
        ]);

        $response = $this->get('/negocio/test-salon');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('services', 2)
        );
    }

    public function test_public_business_landing_only_shows_active_employees(): void
    {
        $activeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $activeUser->id,
            'is_active' => true,
        ]);

        $inactiveUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        Employee::factory()->inactive()->create([
            'business_id' => $this->business->id,
            'user_id' => $inactiveUser->id,
        ]);

        $response = $this->get('/negocio/test-salon');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('employees', 1)
        );
    }
}
