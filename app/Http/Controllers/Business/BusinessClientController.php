<?php

declare(strict_types=1);

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\BlockClientRequest;
use App\Http\Requests\Business\UnblockClientRequest;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class BusinessClientController extends Controller
{
    /**
     * Block a client's enrollment in the given business.
     *
     * Returns 404 if the client is not enrolled.
     * Returns 422 (idempotent) if the client is already blocked — original reason is preserved.
     */
    public function block(BlockClientRequest $request, Business $business, User $user): JsonResponse
    {
        $enrolledBusiness = $user->businesses()->where('business_id', $business->id)->first();

        if (! $enrolledBusiness) {
            return response()->json([
                'message' => 'Client is not enrolled in this business.',
            ], 404);
        }

        $pivot = $enrolledBusiness->pivot;

        if ($pivot->status === 'blocked') {
            return response()->json([
                'message' => 'Client is already blocked.',
                'data' => [],
            ], 422);
        }

        $user->businesses()->updateExistingPivot($business->id, [
            'status' => 'blocked',
            'blocked_at' => now(),
            'blocked_by_user_id' => auth()->id(),
            'blocked_reason' => $request->reason,
        ]);

        // Reload the pivot to reflect the updated values.
        $reloaded = $user->businesses()->where('business_id', $business->id)->first();
        $updatedPivot = $reloaded->pivot;

        return response()->json([
            'message' => 'Client blocked successfully.',
            'data' => [
                'user_id' => $user->id,
                'business_id' => $business->id,
                'status' => $updatedPivot->status,
                'blocked_at' => $updatedPivot->blocked_at,
                'blocked_by_user_id' => $updatedPivot->blocked_by_user_id,
                'blocked_reason' => $updatedPivot->blocked_reason,
            ],
        ]);
    }

    /**
     * Unblock a client's enrollment in the given business.
     *
     * Returns 404 if the client is not blocked.
     */
    public function unblock(UnblockClientRequest $request, Business $business, User $user): JsonResponse
    {
        $enrolledBusiness = $user->businesses()->where('business_id', $business->id)->first();

        if (! $enrolledBusiness || $enrolledBusiness->pivot->status !== 'blocked') {
            return response()->json([
                'message' => 'Client is not blocked in this business.',
            ], 404);
        }

        $user->businesses()->updateExistingPivot($business->id, [
            'status' => 'active',
            'blocked_at' => null,
            'blocked_by_user_id' => null,
            'blocked_reason' => null,
        ]);

        return response()->json([
            'message' => 'Client unblocked successfully.',
            'data' => [
                'user_id' => $user->id,
                'business_id' => $business->id,
                'status' => 'active',
                'blocked_at' => null,
                'blocked_by_user_id' => null,
                'blocked_reason' => null,
            ],
        ]);
    }
}
