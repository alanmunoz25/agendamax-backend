<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StampResource;
use App\Models\Appointment;
use App\Models\Stamp;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function progress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
        ]);

        $user = Auth::user();
        if (! $user) {
            abort(401, 'Authentication required');
        }

        if (! $this->isClientOfBusiness($user->id, (int) $data['business_id']) && $user->role !== 'super_admin') {
            return response()->json([
                'message' => 'Client is not linked to this business',
            ], Response::HTTP_FORBIDDEN);
        }

        $progress = $this->loyaltyService->getProgress($user->id, (int) $data['business_id']);

        return response()->json($progress);
    }

    public function stamps(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
        ]);

        $user = Auth::user();
        if (! $user) {
            abort(401, 'Authentication required');
        }

        if (! $this->isClientOfBusiness($user->id, (int) $data['business_id']) && $user->role !== 'super_admin') {
            abort(Response::HTTP_FORBIDDEN, 'Client is not linked to this business');
        }

        $stamps = Stamp::where('business_id', $data['business_id'])
            ->where('client_id', $user->id)
            ->orderByDesc('earned_at')
            ->get();

        return StampResource::collection($stamps);
    }

    private function isClientOfBusiness(int $clientId, int $businessId): bool
    {
        return Appointment::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->exists();
    }
}
