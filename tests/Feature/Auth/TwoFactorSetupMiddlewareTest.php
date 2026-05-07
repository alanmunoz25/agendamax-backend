<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureTwoFactorIsSetup;
use App\Models\Business;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Tests for the EnsureTwoFactorIsSetup middleware.
 *
 * Verifies that super_admin and business_admin users without confirmed 2FA
 * are redirected to the 2FA settings page, while clients and employees
 * are not affected.
 */
class TwoFactorSetupMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private EnsureTwoFactorIsSetup $middleware;

    private Closure $nextPassthrough;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();
        $this->middleware = new EnsureTwoFactorIsSetup;
        $this->nextPassthrough = fn ($request) => response('OK', 200);
    }

    public function test_super_admin_without_2fa_is_redirected_to_setup(): void
    {
        $superAdmin = User::factory()->withoutTwoFactor()->create([
            'role' => 'super_admin',
            'business_id' => null,
        ]);

        $request = Request::create(route('dashboard'), 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $superAdmin);

        $response = $this->middleware->handle($request, $this->nextPassthrough);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('two-factor', $response->headers->get('Location'));
    }

    public function test_business_admin_without_2fa_is_redirected_to_setup(): void
    {
        $admin = User::factory()->withoutTwoFactor()->create([
            'role' => 'business_admin',
            'business_id' => $this->business->id,
        ]);

        $request = Request::create(route('dashboard'), 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $admin);

        $response = $this->middleware->handle($request, $this->nextPassthrough);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('two-factor', $response->headers->get('Location'));
    }

    public function test_employee_without_2fa_passes_through(): void
    {
        $employee = User::factory()->withoutTwoFactor()->create([
            'role' => 'employee',
            'business_id' => $this->business->id,
        ]);

        $request = Request::create(route('dashboard'), 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $employee);

        $response = $this->middleware->handle($request, $this->nextPassthrough);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_client_without_2fa_passes_through(): void
    {
        $client = User::factory()->withoutTwoFactor()->create([
            'role' => 'client',
            'business_id' => $this->business->id,
        ]);

        $request = Request::create(route('dashboard'), 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $client);

        $response = $this->middleware->handle($request, $this->nextPassthrough);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_unauthenticated_request_passes_through(): void
    {
        $request = Request::create(route('dashboard'), 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => null);

        $response = $this->middleware->handle($request, $this->nextPassthrough);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_admin_accessing_2fa_settings_route_is_not_looped(): void
    {
        $admin = User::factory()->withoutTwoFactor()->create([
            'role' => 'business_admin',
            'business_id' => $this->business->id,
        ]);

        // Simulate a request to the two-factor.show route.
        $request = Request::create(route('two-factor.show'), 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $admin);

        // Must bind a real route so routeIs() works.
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle($request, $this->nextPassthrough);

        // Should pass through (200), not redirect.
        $this->assertEquals(200, $response->getStatusCode());
    }
}
