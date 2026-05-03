<?php

declare(strict_types=1);

namespace App\Http\Controllers\ElectronicInvoice;

use App\Http\Controllers\Controller;
use App\Models\BusinessFeConfig;
use App\Models\NcfRango;
use App\Services\ElectronicInvoice\CertificateConversionService;
use App\Services\ElectronicInvoice\DgiiAuthService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use AuthorizesRequests;

    public function show(): Response
    {
        $user = Auth::user();
        $business = $user->business;
        $feConfig = $business?->feConfig;

        $sequences = NcfRango::withoutGlobalScopes()
            ->where('business_id', $user->business_id)
            ->orderBy('tipo_ecf')
            ->get()
            ->map(fn ($seq) => [
                'id' => $seq->id,
                'tipo' => $seq->tipo_ecf,
                'secuencia_desde' => $seq->secuencia_desde,
                'secuencia_hasta' => $seq->secuencia_hasta,
                'proximo_secuencial' => $seq->proximo_secuencial,
                'fecha_vencimiento' => $seq->fecha_vencimiento?->toDateString(),
                'status' => $seq->status,
                'available' => max(0, $seq->secuencia_hasta - $seq->proximo_secuencial + 1),
            ]);

        return Inertia::render('ElectronicInvoice/Settings', [
            'feConfig' => $feConfig ? [
                'id' => $feConfig->id,
                'rnc_emisor' => $feConfig->rnc_emisor,
                'razon_social' => $feConfig->razon_social,
                'nombre_comercial' => $feConfig->nombre_comercial,
                'direccion' => $feConfig->direccion,
                'municipio' => $feConfig->municipio,
                'provincia' => $feConfig->provincia,
                'telefono' => $feConfig->telefono,
                'email' => $feConfig->email,
                'actividad_economica' => $feConfig->actividad_economica,
                'ambiente' => $feConfig->ambiente,
                'activo' => $feConfig->activo,
                'certificado_convertido' => $feConfig->certificado_convertido,
                'fecha_vigencia_cert' => $feConfig->fecha_vigencia_cert?->toDateString(),
                'has_certificate' => $feConfig->hasCertificate(),
            ] : null,
            'sequences' => $sequences,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $business = $user->business;

        /** @var BusinessFeConfig $feConfig */
        $feConfig = BusinessFeConfig::firstOrNew(['business_id' => $business->id]);
        $this->authorize('update', $feConfig);

        $validated = $request->validate([
            'rnc_emisor' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'municipio' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'actividad_economica' => ['nullable', 'string', 'max:255'],
            'ambiente' => ['required', 'string', 'in:TestECF,CertECF,ECF'],
            'activo' => ['boolean'],
        ]);

        // forceFill is used here because ambiente and activo are excluded from $fillable
        // (guarded against mass assignment) but are intentionally writable by admins
        // via this explicit, policy-authorized controller action.
        $feConfig->forceFill($validated)->save();

        return back()->with('success', 'Configuración guardada.');
    }

    public function testConnectivity(): RedirectResponse
    {
        $user = Auth::user();
        $business = $user->business;
        $feConfig = $business?->feConfig;

        if (! $feConfig) {
            return back()->with('error', 'No hay configuración de facturación electrónica.');
        }

        try {
            $authService = new DgiiAuthService($business, $feConfig);
            $authService->getToken();

            return back()->with('success', 'Conectividad con DGII verificada correctamente.');
        } catch (\Throwable $e) {
            Log::error('DGII connectivity check failed', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error de conectividad con DGII. Verifica la configuración del certificado.');
        }
    }

    public function uploadCertificate(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $business = $user->business;

        /** @var BusinessFeConfig $feConfig */
        $feConfig = BusinessFeConfig::firstOrNew(['business_id' => $business->id]);
        $this->authorize('uploadCertificate', $feConfig);

        $validated = $request->validate([
            'certificate' => ['required', 'file', 'mimes:p12,pfx', 'max:2048'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $file = $request->file('certificate');

        // Ensure the record exists in the database before saving certificate data
        if (! $feConfig->exists) {
            $feConfig->save();
        }

        try {
            $converter = new CertificateConversionService;
            $convertedBase64 = $converter->convertAndEncode($file->getRealPath(), $validated['password']);

            $feConfig->certificado_digital = $convertedBase64;
            $feConfig->password_certificado = Crypt::encryptString($validated['password']);
            $feConfig->certificado_convertido = true;
            $feConfig->save();
        } catch (\Throwable $e) {
            Log::error('Certificate upload/conversion failed', [
                'business_id' => $user->business_id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'certificate' => 'No se pudo procesar el certificado. Verifica el archivo y la contraseña.',
            ]);
        }

        return back()->with('success', 'Certificado digital cargado correctamente.');
    }
}
