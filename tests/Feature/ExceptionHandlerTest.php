<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class ExceptionHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register temporary routes for exception testing
        Route::middleware('api')->prefix('test-exceptions')->group(function () {
            Route::get('/validation', fn () => validator(['email' => 'not-email'], ['email' => 'required|email'])->validate());
            Route::get('/authorization', fn () => throw new AuthorizationException('Not allowed.'));
            Route::get('/model-not-found', fn () => throw (new ModelNotFoundException)->setModel(User::class));
            Route::get('/not-found-http', fn () => abort(404, 'Endpoint missing.'));
            Route::get('/domain', fn () => throw new \DomainException('Cannot book in the past.'));
            Route::get('/throttle-middleware', fn () => throw new TooManyRequestsHttpException(60, 'Rate limited.'));
            Route::get('/throttle-abort', fn () => abort(429, 'Too many requests.'));
            Route::get('/server-error', fn () => throw new \RuntimeException('Something went wrong internally.'));
        });
    }

    // ── ValidationException ──────────────────────────────────────────────────

    public function test_validation_exception_returns_422_with_fields(): void
    {
        $response = $this->getJson('/test-exceptions/validation');

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['code', 'message', 'fields']])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.message', 'The given data was invalid.');
    }

    public function test_validation_exception_includes_field_errors(): void
    {
        $response = $this->getJson('/test-exceptions/validation');

        $response->assertStatus(422);
        $data = $response->json('error.fields');
        $this->assertArrayHasKey('email', $data);
    }

    // ── AuthorizationException → AccessDeniedHttpException ──────────────────

    public function test_authorization_exception_returns_403(): void
    {
        Log::spy();

        $response = $this->getJson('/test-exceptions/authorization');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'You are not authorized to perform this action.');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'Authorization failed'));
    }

    public function test_authorization_exception_does_not_expose_internal_message(): void
    {
        Log::spy();

        $response = $this->getJson('/test-exceptions/authorization');

        $response->assertStatus(403);
        $this->assertStringNotContainsString('Not allowed.', $response->content());
    }

    // ── ModelNotFoundException ───────────────────────────────────────────────

    public function test_model_not_found_returns_404(): void
    {
        $response = $this->getJson('/test-exceptions/model-not-found');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.message', 'The requested resource was not found.');
    }

    // ── NotFoundHttpException ────────────────────────────────────────────────

    public function test_404_http_exception_returns_structured_json(): void
    {
        $response = $this->getJson('/test-exceptions/not-found-http');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // ── DomainException ──────────────────────────────────────────────────────

    public function test_domain_exception_returns_422(): void
    {
        Log::spy();

        $response = $this->getJson('/test-exceptions/domain');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DOMAIN_ERROR');

        // Base framework may also log; assert at least our call was made.
        Log::shouldHaveReceived('error')
            ->atLeast()->times(1)
            ->withArgs(fn ($msg) => str_contains($msg, 'Domain exception'));
    }

    public function test_domain_exception_logs_with_context(): void
    {
        Log::spy();

        $this->getJson('/test-exceptions/domain');

        // Verify our structured context call happened (may be called multiple times)
        Log::shouldHaveReceived('error')
            ->atLeast()->times(1)
            ->withArgs(function ($message, $context) {
                return $message === 'Domain exception'
                    && array_key_exists('request_id', $context)
                    && array_key_exists('user_id', $context)
                    && array_key_exists('route', $context)
                    && array_key_exists('ip', $context);
            });
    }

    // ── TooManyRequestsHttpException (from throttle middleware) ──────────────

    public function test_throttle_middleware_exception_returns_429(): void
    {
        Log::spy();

        $response = $this->getJson('/test-exceptions/throttle-middleware');

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED')
            ->assertJsonPath('error.message', 'Too many requests. Please slow down.');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'Rate limit exceeded'));
    }

    public function test_abort_429_returns_rate_limit_code(): void
    {
        Log::spy();

        $response = $this->getJson('/test-exceptions/throttle-abort');

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    }

    // ── Generic server error ─────────────────────────────────────────────────

    public function test_unhandled_exception_returns_500(): void
    {
        Log::spy();

        $response = $this->getJson('/test-exceptions/server-error');

        $response->assertStatus(500)
            ->assertJsonPath('error.code', 'SERVER_ERROR');

        Log::shouldHaveReceived('error')
            ->atLeast()->times(1)
            ->withArgs(fn ($msg) => str_contains($msg, 'Unhandled exception'));
    }

    // ── X-Request-Id header ──────────────────────────────────────────────────

    public function test_response_includes_request_id_header(): void
    {
        $response = $this->getJson('/test-exceptions/validation');

        $this->assertTrue($response->headers->has('X-Request-Id'));
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_request_id_is_propagated_from_request_header(): void
    {
        $customId = 'my-custom-request-id-123';

        $response = $this->withHeader('X-Request-Id', $customId)
            ->getJson('/test-exceptions/validation');

        $this->assertSame($customId, $response->headers->get('X-Request-Id'));
    }

    // ── Non-JSON (web) requests fall through to default handler ─────────────

    public function test_validation_exception_on_web_redirects(): void
    {
        $response = $this->get('/test-exceptions/validation');

        // Web requests get redirected (302) not JSON 422
        $response->assertStatus(302);
    }
}
