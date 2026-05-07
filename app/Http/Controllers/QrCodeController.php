<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\QrCodeResource;
use App\Models\QrCode;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class QrCodeController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', QrCode::class);

        $qrCodes = QrCodeResource::collection(
            QrCode::orderByDesc('created_at')->get()
        )->resolve();

        return Inertia::render('QrCodes/Index', [
            'qrCodes' => $qrCodes,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', QrCode::class);

        return Inertia::render('QrCodes/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', QrCode::class);

        $validated = $request->validate([
            'reward_description' => ['required', 'string', 'max:255'],
            'stamps_required' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $code = (string) Str::uuid();
        $imagePath = $this->generateQrImage($code);

        $qrCode = QrCode::create([
            'business_id' => $user->primary_business_id,
            'code' => $code,
            'type' => 'visit',
            'reward_description' => $validated['reward_description'],
            'stamps_required' => (int) $validated['stamps_required'],
            'is_active' => $validated['is_active'] ?? true,
            'image_path' => $imagePath,
        ]);

        return Redirect::route('qr-codes.show', $qrCode->id)
            ->with('success', 'QR code created successfully.');
    }

    public function view(QrCode $qrCode): Response
    {
        $this->authorize('view', $qrCode);

        return Inertia::render('QrCodes/Show', [
            'qrCode' => QrCodeResource::fromModel($qrCode),
        ]);
    }

    public function destroy(QrCode $qrCode): RedirectResponse
    {
        $this->authorize('delete', $qrCode);

        if ($qrCode->image_path) {
            Storage::disk('public')->delete($qrCode->image_path);
        }

        $qrCode->delete();

        return Redirect::route('qr-codes.index')->with('success', 'QR code deleted');
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
