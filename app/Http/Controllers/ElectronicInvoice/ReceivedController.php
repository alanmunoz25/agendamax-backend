<?php

declare(strict_types=1);

namespace App\Http\Controllers\ElectronicInvoice;

use App\Http\Controllers\Controller;
use App\Models\EcfReceived;
use App\Services\ElectronicInvoice\RecepcionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReceivedController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $businessId = $user->business_id;
        $feConfig = $user->business?->feConfig;

        $query = EcfReceived::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('numero_ecf', 'like', "%{$search}%")
                    ->orWhere('rnc_emisor', 'like', "%{$search}%")
                    ->orWhere('razon_social_emisor', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $received = $query->paginate(20)->withQueryString();

        return Inertia::render('ElectronicInvoice/Received/Index', [
            'received' => $received,
            'config' => $feConfig ? [
                'ambiente' => $feConfig->ambiente,
            ] : null,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(EcfReceived $received): Response
    {
        $user = Auth::user();

        abort_if($received->business_id !== $user->business_id, 403);

        $feConfig = $user->business?->feConfig;

        $xmlContent = null;
        if ($received->xml_path && Storage::exists($received->xml_path)) {
            $xmlContent = Storage::get($received->xml_path);
        }

        $arecfContent = null;
        if ($received->xml_arecf_path && Storage::exists($received->xml_arecf_path)) {
            $arecfContent = Storage::get($received->xml_arecf_path);
        }

        return Inertia::render('ElectronicInvoice/Received/Show', [
            'received' => [
                'id' => $received->id,
                'rnc_emisor' => $received->rnc_emisor,
                'razon_social_emisor' => $received->razon_social_emisor,
                'numero_ecf' => $received->numero_ecf,
                'tipo' => $received->tipo,
                'fecha_emision' => $received->fecha_emision?->toDateString(),
                'monto_total' => $received->monto_total,
                'itbis_total' => $received->itbis_total,
                'status' => $received->status,
                'arecf_sent_at' => $received->arecf_sent_at?->toISOString(),
                'xml_path' => $received->xml_path,
                'xml_content' => $xmlContent,
                'arecf_content' => $arecfContent,
            ],
            'config' => [
                'ambiente' => $feConfig?->ambiente ?? 'TestECF',
            ],
            'can' => [
                'approve' => $received->status === 'pending',
                'reject' => $received->status === 'pending',
            ],
        ]);
    }

    public function approve(EcfReceived $received): RedirectResponse
    {
        $user = Auth::user();

        abort_if($received->business_id !== $user->business_id, 403);

        if ($received->status !== 'pending') {
            return back()->with('error', 'Solo se pueden aprobar e-CFs recibidos pendientes.');
        }

        $business = $user->business;
        $feConfig = $business?->feConfig;

        if (! $feConfig) {
            return back()->with('error', 'No hay configuración de facturación electrónica.');
        }

        return DB::transaction(function () use ($received, $business, $feConfig, $user): RedirectResponse {
            $service = new RecepcionService($business, $feConfig);
            $service->enviarAcecfAprobacion($received, [
                'comentario' => 'Aprobado',
                'usuario' => $user->id,
            ]);

            $received->status = 'accepted';
            $received->save();

            Log::info('EcfReceived approved + ACECF sent', [
                'ecf_received_id' => $received->id,
                'business_id' => $received->business_id,
                'approved_by' => $user->id,
            ]);

            return redirect()->route('electronic-invoice.received.show', $received)
                ->with('success', 'e-CF aprobado y ACECF enviado.');
        });
    }

    public function reject(Request $request, EcfReceived $received): RedirectResponse
    {
        $user = Auth::user();

        abort_if($received->business_id !== $user->business_id, 403);

        if ($received->status !== 'pending') {
            return back()->with('error', 'Solo se pueden rechazar e-CFs recibidos pendientes.');
        }

        $validated = $request->validate([
            'codigo_rechazo' => ['required', 'integer', 'min:1', 'max:4'],
            'razon' => ['nullable', 'string', 'max:500'],
        ]);

        $business = $user->business;
        $feConfig = $business?->feConfig;

        if (! $feConfig) {
            return back()->with('error', 'No hay configuración de facturación electrónica.');
        }

        $service = new RecepcionService($business, $feConfig);

        if ($received->xml_path && Storage::exists($received->xml_path)) {
            $xmlContent = Storage::get($received->xml_path);
            $service->receive(
                $xmlContent,
                1,
                (string) $validated['codigo_rechazo']
            );
        } else {
            $received->status = 'rejected';
            $received->codigo_motivo = (string) $validated['codigo_rechazo'];
            $received->arecf_sent_at = now();
            $received->save();
        }

        return redirect()->route('electronic-invoice.received.show', $received)
            ->with('success', 'e-CF rechazado.');
    }
}
