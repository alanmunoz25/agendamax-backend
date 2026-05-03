<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\UpdateBaseSalaryRequest;
use App\Models\Employee;
use App\Models\PayrollRecord;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollEmployeeController extends Controller
{
    public function show(Employee $employee): Response
    {
        $businessId = Auth::user()->business_id;

        $totals = PayrollRecord::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('business_id', $businessId)
            ->selectRaw('
                SUM(gross_total) as gross_total_all_time,
                SUM(commissions_total) as commissions_total,
                SUM(tips_total) as tips_total,
                COUNT(*) as records_count
            ')
            ->first();

        $chartSeries = PayrollRecord::withoutGlobalScopes()
            ->where('payroll_records.employee_id', $employee->id)
            ->where('payroll_records.business_id', $businessId)
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_records.payroll_period_id')
            ->orderBy('payroll_periods.starts_on')
            ->get([
                'payroll_records.gross_total',
                'payroll_records.base_salary_snapshot',
                'payroll_periods.starts_on as period_starts_on',
            ])
            ->map(fn ($r) => [
                'month' => Carbon::parse($r->period_starts_on)->translatedFormat('M Y'),
                'gross' => (float) $r->gross_total,
                'base' => (float) $r->base_salary_snapshot,
            ])
            ->toArray();

        $records = PayrollRecord::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('business_id', $businessId)
            ->with('period')
            ->orderByDesc('created_at')
            ->paginate(15);

        $paginatedRecords = [
            'data' => $records->map(fn ($r) => [
                'record_id' => $r->id,
                'period_id' => $r->payroll_period_id,
                'period_label' => $r->period
                    ? Carbon::parse($r->period->starts_on)->translatedFormat('F Y')
                    : '—',
                'starts_on' => $r->period?->starts_on,
                'ends_on' => $r->period?->ends_on,
                'base_salary_snapshot' => (string) $r->base_salary_snapshot,
                'commissions_total' => (string) $r->commissions_total,
                'tips_total' => (string) $r->tips_total,
                'adjustments_total' => (string) $r->adjustments_total,
                'gross_total' => (string) $r->gross_total,
                'status' => $r->status,
            ]),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ];

        $employee->load('user');

        return Inertia::render('Payroll/Employees/Show', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user?->name,
                'role' => $employee->user?->role,
                'is_active' => $employee->is_active,
                'base_salary' => $employee->base_salary ? (string) $employee->base_salary : null,
            ],
            'totals' => [
                'gross_total_all_time' => (string) ($totals?->gross_total_all_time ?? '0.00'),
                'commissions_total' => (string) ($totals?->commissions_total ?? '0.00'),
                'tips_total' => (string) ($totals?->tips_total ?? '0.00'),
                'records_count' => (int) ($totals?->records_count ?? 0),
            ],
            'chart_series' => $chartSeries,
            'records' => $paginatedRecords,
        ]);
    }

    public function updateBaseSalary(UpdateBaseSalaryRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update(['base_salary' => $request->validated('base_salary')]);

        return back()->with('success', 'Salario base actualizado.');
    }

    public function export(Employee $employee): StreamedResponse
    {
        $businessId = Auth::user()->business_id;
        $employee->load('user');

        $records = PayrollRecord::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('business_id', $businessId)
            ->with('period')
            ->orderByDesc('created_at')
            ->get();

        $statusLabels = [
            'draft' => 'Borrador',
            'approved' => 'Aprobado',
            'paid' => 'Pagado',
            'voided' => 'Anulado',
            'open' => 'En curso',
            'closed' => 'Cerrado',
        ];

        $slug = Str::slug($employee->user?->name ?? "employee-{$employee->id}");
        $date = now()->toDateString();
        $filename = "empleado-{$slug}-historial-{$date}.csv";

        return response()->stream(function () use ($records, $statusLabels): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Período', 'Inicio', 'Fin', 'Base Salary', 'Comisiones', 'Tips', 'Ajustes', 'Bruto Total', 'Estado']);

            foreach ($records as $r) {
                $periodLabel = $r->period
                    ? Carbon::parse($r->period->starts_on)->translatedFormat('F Y')
                    : '—';
                $status = $r->period?->status === 'open'
                    ? 'En curso'
                    : ($statusLabels[$r->status] ?? $r->status);

                fputcsv($handle, [
                    $periodLabel,
                    $r->period?->starts_on ?? '',
                    $r->period?->ends_on ?? '',
                    number_format((float) $r->base_salary_snapshot, 2, '.', ''),
                    number_format((float) $r->commissions_total, 2, '.', ''),
                    number_format((float) $r->tips_total, 2, '.', ''),
                    number_format((float) $r->adjustments_total, 2, '.', ''),
                    number_format((float) $r->gross_total, 2, '.', ''),
                    $status,
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
