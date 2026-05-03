<?php

declare(strict_types=1);

namespace App\Http\Controllers\ElectronicInvoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\ElectronicInvoice\RegisterManualVoidRequest;
use App\Http\Requests\ElectronicInvoice\StoreIssuedEcfRequest;
use App\Models\Ecf;
use App\Models\EcfAuditLog;
use App\Models\NcfRango;
use App\Models\Service;
use App\Services\ElectronicInvoice\ElectronicInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class IssuedController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $businessId = $user->business_id;
        $feConfig = $user->business?->feConfig;

        $query = Ecf::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('numero_ecf', 'like', "%{$search}%")
                    ->orWhere('rnc_comprador', 'like', "%{$search}%")
                    ->orWhere('razon_social_comprador', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($tipo = $request->input('tipo')) {
            $query->where('tipo', $tipo);
        }

        $ecfs = $query->paginate(20)->withQueryString();

        return Inertia::render('ElectronicInvoice/Issued/Index', [
            'ecfs' => $ecfs,
            'config' => $feConfig ? [
                'ambiente' => $feConfig->ambiente,
                'activo' => $feConfig->activo,
            ] : null,
            'filters' => $request->only(['search', 'status', 'tipo']),
        ]);
    }

    public function create(): Response
    {
        $user = Auth::user();
        $businessId = $user->business_id;

        $sequences = NcfRango::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->get()
            ->mapWithKeys(fn ($seq) => [
                (string) $seq->tipo_ecf => [
                    'available' => max(0, $seq->secuencia_hasta - $seq->proximo_secuencial + 1),
                ],
            ]);

        $services = Service::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price']);

        $feConfig = $user->business?->feConfig;

        return Inertia::render('ElectronicInvoice/Issued/Create', [
            'sequences' => $sequences,
            'services' => $services,
            'config' => [
                'ambiente' => $feConfig?->ambiente ?? 'TestECF',
            ],
        ]);
    }

    public function store(StoreIssuedEcfRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $business = $user->business;
        $feConfig = $business->feConfig;

        if (! $feConfig || ! $feConfig->activo) {
            return back()->with('error', 'La configuración de facturación electrónica no está activa.');
        }

        $validated = $request->validated();

        /** @var NcfRango|null $sequence */
        $sequence = NcfRango::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('tipo_ecf', $validated['tipo_ecf'])
            ->where('status', 'active')
            ->first();

        if (! $sequence) {
            return back()->withInput()->with('error', "No hay secuencias activas para el tipo {$validated['tipo_ecf']}.");
        }

        $numeroEcf = $sequence->assignNextSecuencial();

        $ecf = Ecf::create([
            'business_id' => $business->id,
            'numero_ecf' => $numeroEcf,
            'tipo' => $validated['tipo_ecf'],
            'rnc_comprador' => $validated['client_rnc'] ?? null,
            'razon_social_comprador' => $validated['client_name'] ?? null,
            'fecha_emision' => now()->toDateString(),
            'monto_total' => '0.00',
            'itbis_total' => '0.00',
            'monto_gravado' => '0.00',
            'status' => 'draft',
        ]);

        $items = array_map(fn ($line) => [
            'name' => $line['description'],
            'quantity' => (float) $line['qty'],
            'unit_price' => (float) $line['unit_price'],
            'discount' => (float) ($line['discount_pct'] ?? 0),
        ], $validated['items']);

        $options = [
            'tipo_pago' => $validated['tipo_pago'] ?? 'contado',
            'indicador_monto_gravado' => (string) ($validated['indicador_monto_gravado'] ?? '1'),
            'client_direccion' => $validated['client_direccion'] ?? null,
            'ecf_referencia' => $validated['ecf_referencia'] ?? null,
        ];

        $service = new ElectronicInvoiceService($business, $feConfig);
        $service->emit($ecf, $items, $options);

        return redirect()->route('electronic-invoice.issued.show', $ecf)
            ->with('success', "e-CF {$numeroEcf} emitido correctamente.");
    }

    public function show(Ecf $ecf): Response
    {
        $user = Auth::user();

        abort_if($ecf->business_id !== $user->business_id, 403);

        $ecf->load('auditLogs');

        $feConfig = $user->business?->feConfig;

        $xmlContent = null;
        if ($ecf->xml_path && Storage::exists($ecf->xml_path)) {
            $xmlContent = Storage::get($ecf->xml_path);
        }

        $ecfData = [
            'id' => $ecf->id,
            'numero_ecf' => $ecf->numero_ecf,
            'tipo_ecf' => (int) $ecf->tipo,
            'status' => $ecf->status,
            'track_id' => $ecf->track_id,
            'rnc_comprador' => $ecf->rnc_comprador,
            'razon_social_comprador' => $ecf->razon_social_comprador,
            'fecha_emision' => $ecf->fecha_emision?->toDateString(),
            'monto_total' => $ecf->monto_total,
            'itbis_total' => $ecf->itbis_total,
            'xml_path' => $ecf->xml_path,
            'xml_content' => $xmlContent,
            'error_message' => $ecf->error_message,
            'appointment_id' => $ecf->appointment_id,
            'created_at' => $ecf->created_at?->toISOString(),
            'manual_void_ncf' => $ecf->manual_void_ncf,
            'manual_void_reason' => $ecf->manual_void_reason,
            'voided_at' => $ecf->voided_at?->toISOString(),
        ];

        return Inertia::render('ElectronicInvoice/Issued/Show', [
            'ecf' => $ecfData,
            'items' => [],
            'audit_logs' => $ecf->auditLogs->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'created_at' => $log->created_at?->toISOString(),
                'payload' => $log->payload,
                'status_code' => $log->status_code,
                'error' => $log->error,
            ]),
            'config' => [
                'ambiente' => $feConfig?->ambiente ?? 'TestECF',
            ],
            'can' => [
                'emit_credit_note' => in_array($ecf->status, ['accepted', 'conditional_accepted'], true),
                'resend' => in_array($ecf->status, ['error', 'rejected'], true),
                'register_manual_void' => in_array($ecf->status, ['accepted', 'conditional_accepted'], true),
            ],
        ]);
    }

    public function creditNote(Ecf $ecf): RedirectResponse
    {
        $user = Auth::user();

        abort_if($ecf->business_id !== $user->business_id, 403);

        if (! in_array($ecf->status, ['accepted', 'conditional_accepted'], true)) {
            return back()->with('error', 'Solo se puede emitir nota de crédito sobre e-CFs aceptados.');
        }

        $business = $user->business;
        $feConfig = $business->feConfig;

        if (! $feConfig || ! $feConfig->activo) {
            return back()->with('error', 'La configuración de facturación electrónica no está activa.');
        }

        /** @var NcfRango|null $sequence */
        $sequence = NcfRango::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('tipo_ecf', 34)
            ->where('status', 'active')
            ->first();

        if (! $sequence) {
            return back()->with('error', 'No hay secuencias activas para Nota de Crédito (tipo 34).');
        }

        $numeroEcf = $sequence->assignNextSecuencial();

        $creditNote = Ecf::create([
            'business_id' => $business->id,
            'numero_ecf' => $numeroEcf,
            'tipo' => '34',
            'rnc_comprador' => $ecf->rnc_comprador,
            'razon_social_comprador' => $ecf->razon_social_comprador,
            'fecha_emision' => now()->toDateString(),
            'monto_total' => $ecf->monto_total,
            'itbis_total' => $ecf->itbis_total,
            'monto_gravado' => $ecf->monto_gravado,
            'status' => 'draft',
        ]);

        $items = [[
            'name' => "Nota de Crédito — Anulación {$ecf->numero_ecf}",
            'quantity' => 1.0,
            'unit_price' => (float) $ecf->monto_total,
            'discount' => 0.0,
        ]];

        $options = [
            'tipo_pago' => 'contado',
            'indicador_monto_gravado' => '1',
            'ecf_referencia' => $ecf->numero_ecf,
        ];

        $service = new ElectronicInvoiceService($business, $feConfig);
        $service->emit($creditNote, $items, $options);

        return redirect()->route('electronic-invoice.issued.show', $creditNote)
            ->with('success', "Nota de Crédito {$numeroEcf} emitida.");
    }

    /**
     * Register a manual void for an accepted e-CF.
     *
     * Used when the business has already issued a Nota de Crédito tipo 34 directly
     * in the DGII portal and wants to record it in the system (BLOCK-002 v1.0 flow).
     */
    public function registerManualVoid(Ecf $ecf, RegisterManualVoidRequest $request): RedirectResponse
    {
        $user = Auth::user();

        abort_if($ecf->business_id !== $user->business_id, 403);

        if (! in_array($ecf->status, ['accepted', 'conditional_accepted'], true)) {
            return back()->with('error', 'Solo se puede registrar anulación manual en e-CFs aceptados.');
        }

        return DB::transaction(function () use ($ecf, $request, $user): RedirectResponse {
            // forceFill — status and void fields excluded from $fillable (BLOCK-008 hardening)
            $ecf->forceFill([
                'status' => 'voided_manual',
                'manual_void_ncf' => $request->validated('manual_void_ncf'),
                'manual_void_reason' => $request->validated('reason'),
                'voided_at' => now(),
                'voided_by' => $user->id,
            ])->save();

            EcfAuditLog::create([
                'business_id' => $ecf->business_id,
                'ecf_id' => $ecf->id,
                'action' => 'manual_void_registered',
                'payload' => [
                    'manual_void_ncf' => $request->validated('manual_void_ncf'),
                    'reason' => $request->validated('reason'),
                    'registered_by' => $user->id,
                ],
                'response' => null,
                'status_code' => 200,
                'error' => null,
            ]);

            return redirect()->route('electronic-invoice.issued.show', $ecf)
                ->with('success', 'Anulación manual registrada.');
        });
    }

    public function resend(Ecf $ecf): RedirectResponse
    {
        $user = Auth::user();

        abort_if($ecf->business_id !== $user->business_id, 403);

        if (! in_array($ecf->status, ['error', 'rejected'], true)) {
            return back()->with('error', 'Solo se puede reenviar e-CFs con estado error o rechazado.');
        }

        $business = $user->business;
        $feConfig = $business->feConfig;

        if (! $feConfig || ! $feConfig->activo) {
            return back()->with('error', 'La configuración de facturación electrónica no está activa.');
        }

        $service = new ElectronicInvoiceService($business, $feConfig);
        $service->emit($ecf, [], []);

        return redirect()->route('electronic-invoice.issued.show', $ecf)
            ->with('success', 'e-CF reenviado a DGII.');
    }
}
