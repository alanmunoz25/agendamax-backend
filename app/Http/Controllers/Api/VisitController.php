<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QRService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function __construct(
        private readonly QRService $qrService
    ) {}

    /**
     * Verify a QR code and create a visit record.
     */
    public function verifyQR(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        try {
            $visit = $this->qrService->verifyQR($validated['qr_code']);

            return response()->json([
                'message' => 'Visit verified successfully',
                'visit' => $visit,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'QR verification failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check if a QR code is valid without creating a visit.
     */
    public function checkQR(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $isValid = $this->qrService->isValidQR($validated['qr_code']);

        if ($isValid) {
            $expiration = $this->qrService->getQRExpiration($validated['qr_code']);

            return response()->json([
                'valid' => true,
                'expires_at' => $expiration?->toIso8601String(),
            ]);
        }

        return response()->json([
            'valid' => false,
            'message' => 'QR code is invalid or expired',
        ]);
    }
}
