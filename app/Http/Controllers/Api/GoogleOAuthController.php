<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleOAuthController extends Controller
{
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events',
    ];

    public function redirect(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Authenticated user is not linked to an employee record.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $scopes = config('services.google.scopes', self::SCOPES);
        $redirectUrl = Socialite::driver('google')
            ->stateless()
            ->scopes($scopes)
            ->with([
                'access_type' => config('services.google.access_type', 'offline'),
                'prompt' => config('services.google.approval_prompt', 'consent select_account'),
            ])
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'redirect_url' => $redirectUrl,
        ]);
    }

    public function handleCallback(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Authenticated user is not linked to an employee record.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();
        } catch (Throwable $exception) {
            Log::warning('Google OAuth callback failed', [
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to authenticate with Google. Please try again.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $googleAccount = GoogleAccount::firstOrNew([
            'employee_id' => $employee->id,
        ]);

        $googleAccount->google_user_id = $googleUser->getId();
        $googleAccount->email = $googleUser->getEmail() ?? $googleAccount->email;
        $googleAccount->access_token = $this->encryptValue($googleUser->token ?? '');
        $googleAccount->refresh_token = $this->encryptValue($googleUser->refreshToken ?? $googleAccount->refresh_token);
        $googleAccount->expires_at = $googleUser->expiresIn ? now()->addSeconds((int) $googleUser->expiresIn) : null;
        $googleAccount->sync_enabled = true;
        $googleAccount->calendar_id ??= 'primary';
        $googleAccount->save();

        return response()->json([
            'message' => 'Google account connected successfully.',
            'google_account' => [
                'email' => $googleAccount->email,
                'google_user_id' => $googleAccount->google_user_id,
                'sync_enabled' => $googleAccount->sync_enabled,
                'expires_at' => optional($googleAccount->expires_at)->toISOString(),
            ],
        ]);
    }

    private function encryptValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Crypt::encryptString($value);
    }
}
