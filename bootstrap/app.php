<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserHasBusiness;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\ResolveBusinessContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->prepend(RequestId::class);

        $middleware->alias([
            'business' => EnsureUserHasBusiness::class,
            'ensure-2fa' => \App\Http\Middleware\EnsureTwoFactorIsSetup::class,
        ]);

        // ResolveBusinessContext runs on every authenticated API request.
        // It is a no-op when agendamax.use_business_context is false, so
        // registering it globally here costs nothing in the default configuration.
        $middleware->api(append: [
            ResolveBusinessContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /**
         * Build a structured API error response.
         *
         * @param  array<string,mixed>  $error
         */
        $apiError = static function (string $code, string $message, int $status, array $fields = [], array $extra = []): \Illuminate\Http\JsonResponse {
            $body = ['error' => ['code' => $code, 'message' => $message]];

            if ($fields !== []) {
                $body['error']['fields'] = $fields;
            }

            if ($extra !== []) {
                $body = array_merge($body, $extra);
            }

            return response()->json($body, $status);
        };

        /**
         * Log with structured context (user_id, primary_business_id, request_id).
         *
         * @param  array<string,mixed>  $context
         */
        $logContext = static function (\Throwable $e, Request $request, array $context = []): array {
            $user = $request->user();

            return array_merge([
                'request_id' => app()->has('request_id') ? app('request_id') : null,
                'user_id' => $user?->id,
                'business_id' => $user?->primary_business_id,
                'route' => $request->route()?->getName() ?? $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'exception' => get_class($e),
            ], $context);
        };

        // ── ValidationException ──────────────────────────────────────────────
        $exceptions->render(function (ValidationException $e, Request $request): ?\Illuminate\Http\JsonResponse {
            if (! $request->expectsJson()) {
                return null; // Let Laravel handle web redirect
            }

            // Include both the structured error envelope and the top-level `errors`
            // key so that Laravel's assertJsonValidationErrors() helper works in tests
            // and mobile clients that expect the standard Laravel validation shape.
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'fields' => $e->errors(),
                ],
            ], 422);
        });

        // ── AccessDeniedHttpException (from AuthorizationException) ─────────
        // Laravel's prepareException() converts AuthorizationException into
        // AccessDeniedHttpException before our callbacks run, so we target that.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($apiError, $logContext): ?\Illuminate\Http\JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            Log::warning('Authorization failed', $logContext($e, $request, ['message' => $e->getMessage()]));

            return $apiError('FORBIDDEN', 'You are not authorized to perform this action.', 403);
        });

        // ── AuthenticationException ──────────────────────────────────────────
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($apiError): ?\Illuminate\Http\JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $apiError('UNAUTHENTICATED', 'Authentication required.', 401);
        });

        // ── ModelNotFoundException / NotFoundHttpException ───────────────────
        // Note: Laravel's prepareException() converts ModelNotFoundException to
        // NotFoundHttpException before render callbacks run. We detect it via previous.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($apiError): ?\Illuminate\Http\JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            $isModelNotFound = $e->getPrevious() instanceof ModelNotFoundException;
            $message = $isModelNotFound
                ? 'The requested resource was not found.'
                : 'The requested endpoint was not found.';

            return $apiError('NOT_FOUND', $message, 404);
        });

        // ── TooManyRequestsHttpException (from throttle middleware) ──────────
        // Note: abort(429) creates a generic HttpException, but the throttle
        // middleware throws TooManyRequestsHttpException. Both are handled here.
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) use ($apiError, $logContext): ?\Illuminate\Http\JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            Log::warning('Rate limit exceeded', $logContext($e, $request));

            return $apiError('RATE_LIMIT_EXCEEDED', 'Too many requests. Please slow down.', 429);
        });

        // ── DomainException ──────────────────────────────────────────────────
        $exceptions->render(function (\DomainException $e, Request $request) use ($apiError, $logContext): ?\Illuminate\Http\JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            Log::error('Domain exception', $logContext($e, $request, ['message' => $e->getMessage()]));

            $userMessage = app()->isProduction()
                ? 'A business rule violation occurred.'
                : $e->getMessage();

            return $apiError('DOMAIN_ERROR', $userMessage, 422);
        });

        // ── InvalidSignatureException ────────────────────────────────────────
        $exceptions->render(function (InvalidSignatureException $e, Request $request) use ($apiError): ?\Illuminate\Http\JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $apiError('INVALID_SIGNATURE', 'This link has expired or is invalid.', 403);
        });

        // ── Generic HttpException ────────────────────────────────────────────
        // Handles abort(4xx/5xx) calls, including abort(429) from manual rate limiting.
        $exceptions->render(function (HttpException $e, Request $request) use ($apiError, $logContext): ?\Illuminate\Http\JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            if ($e->getStatusCode() === 429) {
                Log::warning('Rate limit exceeded', $logContext($e, $request));

                return $apiError('RATE_LIMIT_EXCEEDED', 'Too many requests. Please slow down.', 429);
            }

            return $apiError('HTTP_ERROR', $e->getMessage() ?: 'An HTTP error occurred.', $e->getStatusCode());
        });

        // ── Catch-all unhandled exceptions ───────────────────────────────────
        $exceptions->render(function (\Throwable $e, Request $request) use ($apiError, $logContext): ?\Illuminate\Http\JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            Log::error('Unhandled exception', $logContext($e, $request, [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));

            $userMessage = app()->isProduction()
                ? 'An unexpected error occurred. Please try again later.'
                : $e->getMessage();

            return $apiError('SERVER_ERROR', $userMessage, 500);
        });

        // ── Suppress stack traces in production ──────────────────────────────
        if (app()->isProduction()) {
            $exceptions->dontReport([
                ValidationException::class,
                AuthenticationException::class,
                ModelNotFoundException::class,
                NotFoundHttpException::class,
                TooManyRequestsHttpException::class,
            ]);
        }
    })->create();
