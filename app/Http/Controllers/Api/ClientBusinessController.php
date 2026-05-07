<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EnrollBusinessRequest;
use App\Http\Resources\ClientBusinessResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ClientBusinessController extends Controller
{
    /**
     * List all businesses the authenticated user is enrolled in (active or blocked).
     * Primary business comes first, then ordered by joined_at descending.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $businesses = $user->businesses()
            ->wherePivotIn('status', ['active', 'blocked'])
            ->get()
            ->sortByDesc(function (Business $business) use ($user) {
                $isPrimary = $user->business_id === $business->id ? 1 : 0;
                $joinedAt = $business->pivot->joined_at?->timestamp ?? 0;

                return [$isPrimary, $joinedAt];
            })
            ->values();

        return ClientBusinessResource::collection($businesses);
    }

    /**
     * Enroll the authenticated user in a business via invitation_code or business_slug.
     */
    public function store(EnrollBusinessRequest $request): JsonResponse
    {
        $user = $request->user();
        $business = $request->resolveBusiness();

        if (! $business) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['invitation_code' => ['The invitation code is invalid.']],
            ], 422);
        }

        $pivotRow = DB::table('user_business')
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->first();

        if ($pivotRow) {
            if ($pivotRow->status === 'blocked') {
                return response()->json([
                    'message' => 'You are blocked from this business and cannot re-enroll.',
                ], 403);
            }

            if ($pivotRow->status === 'active') {
                return response()->json([
                    'message' => 'Already enrolled',
                    'data' => [],
                ], 200);
            }

            // status === 'left' — re-activate the membership
            DB::table('user_business')
                ->where('user_id', $user->id)
                ->where('business_id', $business->id)
                ->update([
                    'status' => 'active',
                    'joined_at' => now(),
                    'blocked_at' => null,
                    'blocked_reason' => null,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('user_business')->insert([
                'user_id' => $user->id,
                'business_id' => $business->id,
                'role_in_business' => 'client',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Upgrade user from lead to client if needed
        if ($user->role === null || $user->role === 'lead') {
            $user->forceFill(['role' => 'client'])->save();
        }

        $user->load('businesses');
        $enrolledBusiness = $user->businesses()
            ->where('businesses.id', $business->id)
            ->first();

        return response()->json([
            'message' => 'Enrollment successful',
            'data' => new ClientBusinessResource($enrolledBusiness),
        ], 201);
    }

    /**
     * Leave (unenroll) the authenticated user from a business.
     */
    public function destroy(Request $request, Business $business): JsonResponse
    {
        $user = $request->user();

        $pivotRow = DB::table('user_business')
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->first();

        if (! $pivotRow) {
            return response()->json([
                'message' => 'Enrollment not found.',
            ], 404);
        }

        if ($pivotRow->status === 'left') {
            return response()->json([
                'message' => 'You have left the business successfully.',
            ], 200);
        }

        DB::table('user_business')
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->update([
                'status' => 'left',
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'You have left the business successfully.',
        ], 200);
    }
}
