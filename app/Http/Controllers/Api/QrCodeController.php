<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QrCodeResource;
use App\Models\QrCode;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    public function index(): JsonResponse
    {
        $this->ensureAdmin();

        $codes = QrCode::orderByDesc('created_at')->get()->map(fn ($qr) => QrCodeResource::fromModel($qr));

        return response()->json($codes);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'reward_description' => ['required', 'string', 'max:255'],
            'stamps_required' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $code = (string) Str::uuid();
        $imagePath = $this->generateQrImage($code);

        $qrCode = QrCode::create([
            'business_id' => Auth::user()->business_id,
            'code' => $code,
            'type' => 'visit',
            'reward_description' => $data['reward_description'],
            'stamps_required' => $data['stamps_required'],
            'is_active' => $data['is_active'] ?? true,
            'image_path' => $imagePath,
        ]);

        return response()->json(QrCodeResource::fromModel($qrCode), 201);
    }

    public function show(QrCode $qrCode): JsonResponse
    {
        $this->ensureAdmin();
        $this->ensureSameBusiness($qrCode);

        return response()->json(QrCodeResource::fromModel($qrCode));
    }

    public function update(Request $request, QrCode $qrCode): JsonResponse
    {
        $this->ensureAdmin();
        $this->ensureSameBusiness($qrCode);

        $data = $request->validate([
            'reward_description' => ['sometimes', 'string', 'max:255'],
            'stamps_required' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $qrCode->update($data);

        return response()->json(QrCodeResource::fromModel($qrCode->fresh()));
    }

    public function destroy(QrCode $qrCode): JsonResponse
    {
        $this->ensureAdmin();
        $this->ensureSameBusiness($qrCode);

        if ($qrCode->image_path) {
            Storage::disk('public')->delete($qrCode->image_path);
        }

        $qrCode->delete();

        return response()->json(['message' => 'QR code deleted']);
    }

    public function image(QrCode $qrCode)
    {
        $this->ensureAdmin();
        $this->ensureSameBusiness($qrCode);

        if (! $qrCode->image_path || ! Storage::disk('public')->exists($qrCode->image_path)) {
            abort(404, 'QR image not found');
        }

        return response()->file(Storage::disk('public')->path($qrCode->image_path));
    }

    private function ensureAdmin(): void
    {
        $user = Auth::user();

        if (! $user || ! in_array($user->role, ['business_admin', 'super_admin'], true)) {
            abort(403, 'Only admins can manage QR codes');
        }
    }

    private function ensureSameBusiness(QrCode $qrCode): void
    {
        $user = Auth::user();

        if ($user && $user->business_id !== $qrCode->business_id) {
            abort(403, 'Unauthorized QR access');
        }
    }

    private function generateQrImage(string $payload): string
    {
        $qrCode = Encoder::encode($payload, ErrorCorrectionLevel::L());
        $matrix = $qrCode->getMatrix();
        $moduleCount = $matrix->getWidth();
        $margin = 4;
        $scale = 10; // pixels per module
        $size = ($moduleCount + ($margin * 2)) * $scale;

        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < $moduleCount; $y++) {
            for ($x = 0; $x < $moduleCount; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $startX = ($x + $margin) * $scale;
                    $startY = ($y + $margin) * $scale;
                    imagefilledrectangle($image, $startX, $startY, $startX + $scale - 1, $startY + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $path = 'qr_codes/'.$payload.'.png';
        Storage::disk('public')->put($path, $png ?? '');

        return $path;
    }
}
