<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user (mobile client).
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $businessId = $request->resolveBusinessId();

        // role and business_id are excluded from $fillable to prevent mass-assignment.
        // Trusted controller action uses forceFill to assign them explicitly.
        $user = new User;
        $user->fill([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'phone' => $request->phone,
        ]);
        $user->forceFill([
            'role' => 'client',
            'business_id' => $businessId,
        ])->save();

        $user->load('business');

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Login user and return Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Get authenticated user details.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('business');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update authenticated user's profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return response()->json([
            'data' => new UserResource($user->fresh()),
            'message' => 'Perfil actualizado.',
        ]);
    }

    /**
     * Update push notification token.
     */
    public function updatePushToken(Request $request): JsonResponse
    {
        $request->validate([
            'push_token' => ['required', 'string'],
        ]);

        $request->user()->update([
            'push_token' => $request->push_token,
        ]);

        return response()->json([
            'message' => 'Push token updated successfully',
        ]);
    }

    /**
     * Send a 6-digit password reset code via email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $throttleKey = 'forgot-password:'.Str::lower($request->input('email'));

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json(['message' => "Demasiados intentos. Intenta en {$seconds} segundos."], 429);
        }

        RateLimiter::hit($throttleKey, 600);

        $user = User::where('email', $request->input('email'))->first();

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_codes')->upsert([
                'email' => $user->email,
                'code' => Hash::make($code),
                'expires_at' => now()->addMinutes(15),
                'attempts' => 0,
                'created_at' => now(),
            ], ['email'], ['code', 'expires_at', 'attempts', 'created_at']);

            Mail::to($user->email)->queue(new PasswordResetCodeMail($code));
        }

        return response()->json(['message' => 'Si el email existe, recibirás un código en breve.']);
    }

    /**
     * Reset user password using a 6-digit code.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $entry = DB::table('password_reset_codes')
            ->where('email', $request->input('email'))
            ->first();

        if (! $entry || Carbon::parse($entry->expires_at)->isPast()) {
            return response()->json(['message' => 'Código inválido o expirado.'], 422);
        }

        if ($entry->attempts >= 5) {
            return response()->json(['message' => 'Demasiados intentos. Solicita un nuevo código.'], 429);
        }

        if (! Hash::check($request->input('code'), $entry->code)) {
            DB::table('password_reset_codes')
                ->where('email', $request->input('email'))
                ->increment('attempts');

            return response()->json(['message' => 'Código inválido o expirado.'], 422);
        }

        $user = User::where('email', $request->input('email'))->firstOrFail();
        $user->forceFill(['password' => Hash::make($request->input('password'))])->save();

        DB::table('password_reset_codes')->where('email', $request->input('email'))->delete();
        $user->tokens()->delete();

        return response()->json(['message' => 'Contraseña actualizada. Inicia sesión.']);
    }
}
