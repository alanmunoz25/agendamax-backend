<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayrollDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // super_admin without a business context must pass business_id explicitly
        // to avoid ambiguous null-filtered queries that return empty data silently.
        $businessId = $user->isSuperAdmin()
            ? (int) $request->query('business_id', 0)
            : $user->business_id;

        abort_unless(
            $businessId > 0,
            422,
            'super_admin debe especificar business_id para acceder al dashboard de nómina.'
        );

        $totalPaidThisYear = (string) PayrollRecord::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'paid')
            ->whereYear('paid_at', now()->year)
            ->sum('gross_total');

        $currentPeriod = PayrollPeriod::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'open')
            ->orderByDesc('starts_on')
            ->first();

        $currentPeriodTotal = $currentPeriod
            ? (string) PayrollRecord::withoutGlobalScopes()
                ->where('payroll_period_id', $currentPeriod->id)
                ->sum('gross_total')
            : '0.00';

        $activeEmployeesCount = Employee::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->count();

        $kpis = [
            'total_paid_this_year' => $totalPaidThisYear,
            'current_period_total' => $currentPeriodTotal,
            'current_period_status' => $currentPeriod?->status ?? null,
            'current_period_label' => $currentPeriod
                ? Carbon::parse($currentPeriod->starts_on)->translatedFormat('F Y')
                : null,
            'active_employees_count' => $activeEmployeesCount,
            'has_periods' => PayrollPeriod::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->exists(),
        ];

        return Inertia::render('Payroll/Dashboard', [
            'kpis' => $kpis,
            'monthly_series' => Inertia::defer(fn () => $this->monthlySeries($businessId)),
            'employee_distribution' => Inertia::defer(fn () => $this->employeeDistribution($businessId)),
            'recent_paid' => Inertia::defer(fn () => $this->recentPaid($businessId)),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function monthlySeries(int $businessId): array
    {
        $cutoff = now()->subMonths(12)->startOfMonth()->toDateString();

        return PayrollRecord::withoutGlobalScopes()
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_records.payroll_period_id')
            ->where('payroll_records.business_id', $businessId)
            ->where('payroll_periods.status', 'closed')
            ->where('payroll_records.status', 'paid')
            ->where('payroll_periods.starts_on', '>=', $cutoff)
            ->groupBy(DB::raw("DATE_FORMAT(payroll_periods.starts_on, '%Y-%m')"), 'payroll_periods.starts_on')
            ->orderBy('payroll_periods.starts_on')
            ->limit(12)
            ->get([
                DB::raw("DATE_FORMAT(payroll_periods.starts_on, '%Y-%m') as month"),
                DB::raw('SUM(payroll_records.gross_total) as total'),
                'payroll_periods.starts_on',
            ])
            ->map(fn ($row) => [
                'month' => $row->month,
                'label' => Carbon::parse($row->starts_on)->translatedFormat('M Y'),
                'total' => (string) $row->total,
            ])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employeeDistribution(int $businessId): array
    {
        $lastClosed = PayrollPeriod::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'closed')
            ->orderByDesc('starts_on')
            ->first();

        if (! $lastClosed) {
            return [];
        }

        $records = PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $lastClosed->id)
            ->where('status', 'paid')
            ->with('employee.user')
            ->orderByDesc('gross_total')
            ->limit(10)
            ->get();

        $grandTotal = $records->sum('gross_total');

        return $records->map(fn ($r) => [
            'employee_id' => $r->employee_id,
            'name' => $r->employee?->user?->name ?? "Employee #{$r->employee_id}",
            'total' => (string) $r->gross_total,
            'pct' => $grandTotal > 0 ? round((float) $r->gross_total / (float) $grandTotal * 100, 1) : 0,
        ])->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentPaid(int $businessId): array
    {
        return PayrollRecord::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'paid')
            ->with(['employee.user', 'period'])
            ->orderByDesc('paid_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'record_id' => $r->id,
                'employee_name' => $r->employee?->user?->name ?? "Employee #{$r->employee_id}",
                'gross_total' => (string) $r->gross_total,
                'period_label' => $r->period
                    ? Carbon::parse($r->period->starts_on)->translatedFormat('F Y')
                    : '—',
                'paid_at' => $r->paid_at?->toDateString(),
                'period_id' => $r->payroll_period_id,
            ])
            ->toArray();
    }
}
