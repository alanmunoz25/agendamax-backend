<?php

declare(strict_types=1);

namespace App\Http\Controllers\ElectronicInvoice;

use App\Http\Controllers\Controller;
use App\Models\EcfAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $businessId = $user->business_id;

        $query = EcfAuditLog::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at');

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($ecfId = $request->input('ecf_id')) {
            $query->where('ecf_id', $ecfId);
        }

        $logs = $query->paginate(50)->withQueryString();

        $actions = EcfAuditLog::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->select('action')
            ->distinct()
            ->pluck('action');

        return Inertia::render('ElectronicInvoice/Audit/Index', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => $request->only(['action', 'ecf_id']),
        ]);
    }
}
