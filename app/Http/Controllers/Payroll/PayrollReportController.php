<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayrollReportController extends Controller
{
    public function byService(Request $request): Response
    {
        $businessId = Auth::user()->business_id;

        $from = $request->input('from');
        $to = $request->input('to');
        $periodId = $request->input('period_id');

        if (! $from && ! $to && ! $periodId) {
            $from = now()->subDays(30)->toDateString();
            $to = now()->toDateString();
        }

        $query = DB::table('commission_records')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'commission_records.payroll_period_id')
            ->join('services', 'services.id', '=', 'commission_records.service_id')
            ->where('commission_records.business_id', $businessId);

        if ($periodId) {
            $query->where('commission_records.payroll_period_id', $periodId);
        } else {
            if ($from) {
                $query->where('payroll_periods.starts_on', '>=', $from);
            }
            if ($to) {
                $query->where('payroll_periods.ends_on', '<=', $to);
            }
        }

        $rows = $query
            ->groupBy('commission_records.service_id', 'services.name')
            ->orderByDesc(DB::raw('SUM(commission_records.commission_amount)'))
            ->limit(50)
            ->get([
                'commission_records.service_id',
                'services.name as service_name',
                DB::raw('COUNT(*) as commissions_count'),
                DB::raw('SUM(commission_records.commission_amount) as commissions_total'),
                DB::raw('AVG(commission_records.service_price_snapshot) as avg_price'),
            ]);

        $grandTotal = $rows->sum('commissions_total');

        $report = $rows->map(fn ($r) => [
            'service_id' => $r->service_id,
            'service_name' => $r->service_name,
            'commissions_count' => (int) $r->commissions_count,
            'commissions_total' => number_format((float) $r->commissions_total, 2, '.', ''),
            'avg_price' => number_format((float) $r->avg_price, 2, '.', ''),
            'pct_of_total' => $grandTotal > 0
                ? round((float) $r->commissions_total / (float) $grandTotal * 100, 1)
                : 0,
        ])->toArray();

        $periodsForFilter = PayrollPeriod::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => Carbon::parse($p->starts_on)->translatedFormat('F Y'),
            ])
            ->toArray();

        return Inertia::render('Payroll/Reports/ByService', [
            'report' => $report,
            'summary' => [
                'grand_total' => number_format((float) $grandTotal, 2, '.', ''),
                'date_from' => $from,
                'date_to' => $to,
            ],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'period_id' => $periodId,
            ],
            'periods_for_filter' => $periodsForFilter,
        ]);
    }
}
