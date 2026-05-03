<?php

declare(strict_types=1);

namespace App\Http\Controllers\ElectronicInvoice;

use App\Http\Controllers\Controller;
use App\Models\Ecf;
use App\Models\NcfRango;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $businessId = $user->business_id;

        $feConfig = $user->business?->feConfig;

        if (! $feConfig || ! $feConfig->activo) {
            return Inertia::render('ElectronicInvoice/Dashboard', [
                'config' => null,
                'kpis' => null,
                'alerts' => [],
                'recent_ecfs' => [],
            ]);
        }

        $today = now()->toDateString();

        $kpis = [
            'ecfs_today' => Ecf::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->whereDate('fecha_emision', $today)
                ->count(),
            'accepted_this_month' => Ecf::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('status', 'accepted')
                ->whereMonth('fecha_emision', now()->month)
                ->whereYear('fecha_emision', now()->year)
                ->count(),
            'in_process' => Ecf::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->whereIn('status', ['draft', 'signed', 'sent'])
                ->count(),
            'rejected_this_month' => Ecf::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('status', 'rejected')
                ->whereMonth('fecha_emision', now()->month)
                ->whereYear('fecha_emision', now()->year)
                ->count(),
        ];

        $alerts = [];

        // Certificate expiry alert
        if ($feConfig->fecha_vigencia_cert !== null) {
            $daysUntilExpiry = now()->diffInDays($feConfig->fecha_vigencia_cert, false);
            if ($daysUntilExpiry <= 0) {
                $alerts[] = [
                    'type' => 'cert_expired',
                    'message' => 'Certificado digital VENCIDO. No puedes emitir e-CFs.',
                    'expired_at' => $feConfig->fecha_vigencia_cert->toDateString(),
                ];
            } elseif ($daysUntilExpiry <= 30) {
                $alerts[] = [
                    'type' => 'cert_expiring',
                    'days_remaining' => (int) $daysUntilExpiry,
                    'expires_at' => $feConfig->fecha_vigencia_cert->toDateString(),
                ];
            }
        }

        // Low sequence alerts
        $sequences = NcfRango::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->get();

        foreach ($sequences as $seq) {
            $available = max(0, $seq->secuencia_hasta - $seq->proximo_secuencial + 1);
            if ($available <= 50) {
                $alerts[] = [
                    'type' => 'low_sequence',
                    'tipo' => $seq->tipo_ecf,
                    'available' => $available,
                ];
            }
        }

        $recentEcfs = Ecf::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'numero_ecf', 'tipo', 'rnc_comprador', 'razon_social_comprador', 'monto_total', 'status', 'fecha_emision']);

        return Inertia::render('ElectronicInvoice/Dashboard', [
            'config' => [
                'ambiente' => $feConfig->ambiente,
                'activo' => $feConfig->activo,
                'cert_expiry' => $feConfig->fecha_vigencia_cert?->toDateString(),
            ],
            'kpis' => $kpis,
            'alerts' => $alerts,
            'recent_ecfs' => $recentEcfs,
        ]);
    }
}
