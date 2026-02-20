<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\Stamp;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class QrScanController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required_without:qr_code', 'string'],
            'qr_code' => ['required_without:code', 'string'],
        ]);

        $user = Auth::user();
        if (! $user) {
            abort(401, 'Authentication required');
        }

        $payload = $data['code'] ?? $data['qr_code'] ?? '';

        $qrCode = QrCode::withoutGlobalScopes()
            ->where('code', $payload)
            ->where('type', 'visit')
            ->firstOrFail();

        if ($user->role === 'client') {
            $isLinkedToBusiness = Appointment::withoutGlobalScopes()
                ->where('business_id', $qrCode->business_id)
                ->where('client_id', $user->id)
                ->exists();

            if (! $isLinkedToBusiness) {
                return response()->json([
                    'message' => 'QR code belongs to a different business for this client',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (! $qrCode->is_active) {
            return response()->json([
                'message' => 'QR code is inactive',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = DB::transaction(function () use ($qrCode, $user) {
            QrScan::create([
                'qr_code_id' => $qrCode->id,
                'business_id' => $qrCode->business_id,
                'client_id' => $user->id,
                'scanned_at' => now(),
            ]);

            $stampsToAward = max(1, $qrCode->stamps_required);
            $stamps = [];

            for ($i = 0; $i < $stampsToAward; $i++) {
                $stamps[] = Stamp::create([
                    'business_id' => $qrCode->business_id,
                    'client_id' => $user->id,
                    'earned_at' => now(),
                ]);
            }

            return $stamps;
        });

        Log::info('QR scan processed', [
            'qr_code_id' => $qrCode->id,
            'client_id' => $user->id,
            'stamps_awarded' => count($result),
        ]);

        $progress = $this->loyaltyService->getProgress($user->id, $qrCode->business_id);

        return response()->json([
            'message' => 'Scan recorded and stamps awarded',
            'stamps_awarded' => count($result),
            'loyalty_progress' => $progress,
        ], Response::HTTP_CREATED);
    }
}
