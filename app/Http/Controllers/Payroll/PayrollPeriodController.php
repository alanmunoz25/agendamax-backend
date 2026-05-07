<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Events\Payroll\PayrollRecordApproved;
use App\Exceptions\Payroll\InvalidPayrollTransitionException;
use App\Exceptions\Payroll\PeriodNotOpenException;
use App\Exceptions\Payroll\PeriodOverlapException;
use App\Exceptions\Payroll\RecordsAlreadyGeneratedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\CreatePayrollPeriodRequest;
use App\Models\CommissionRecord;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Tip;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollPeriodController extends Controller
{
    public function __construct(
        private readonly PayrollService $payrollService
    ) {}

    public function index(): Response
    {
        $user = Auth::user();
        $business = $user->business;

        $periods = PayrollPeriod::query()
            ->withCount([
                'payrollRecords as records_count',
                'payrollRecords as paid_count' => fn ($q) => $q->where('status', 'paid'),
            ])
            ->orderBy('starts_on', 'desc')
            ->paginate(15);

        $summary = [
            'open_count' => PayrollPeriod::where('status', 'open')->count(),
            'pending_payment_count' => PayrollPeriod::where('status', 'open')
                ->whereHas('payrollRecords', fn ($q) => $q->where('status', 'approved'))
                ->count(),
            'total_pending' => (string) PayrollRecord::whereHas('period', fn ($q) => $q->where('status', 'open'))
                ->whereIn('status', ['draft', 'approved'])
                ->sum('gross_total'),
            'active_employees' => Employee::where('is_active', true)->count(),
        ];

        return Inertia::render('Payroll/Periods/Index', [
            'periods' => $periods,
            'summary' => $summary,
            'filters' => request()->only(['search', 'status', 'year_month']),
        ]);
    }

    public function store(CreatePayrollPeriodRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $business = $user->business;

        try {
            $period = $this->payrollService->createPeriod(
                $business,
                Carbon::parse($request->validated('start')),
                Carbon::parse($request->validated('end')),
                $user,
            );

            $name = Carbon::parse($period->starts_on)->translatedFormat('F Y');

            return redirect()->route('payroll.periods.index')
                ->with('success', "Período {$name} creado.");
        } catch (PeriodOverlapException $e) {
            return back()->withErrors(['start' => 'Este rango se solapa con un período existente.']);
        } catch (LockTimeoutException) {
            return back()->withErrors(['start' => 'Operación en progreso. Otro administrador está creando un período. Intenta de nuevo en un momento.']);
        }
    }

    public function show(PayrollPeriod $period): Response
    {
        $period->load(['payrollRecords.employee.user']);

        $records = $period->payrollRecords()
            ->with(['employee.user'])
            ->get();

        // Eager load commissions, tips, adjustments per record (keyed by employee_id)
        $employeeIds = $records->pluck('employee_id')->unique()->all();

        $commissionsByEmployee = ! empty($employeeIds)
            ? CommissionRecord::withoutGlobalScopes()
                ->where('payroll_period_id', $period->id)
                ->whereIn('employee_id', $employeeIds)
                ->with(['appointment', 'service'])
                ->get()
                ->groupBy('employee_id')
            : collect();

        $tipsByEmployee = ! empty($employeeIds)
            ? Tip::withoutGlobalScopes()
                ->where('payroll_period_id', $period->id)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->groupBy('employee_id')
            : collect();

        $adjustmentsByEmployee = ! empty($employeeIds)
            ? PayrollAdjustment::withoutGlobalScopes()
                ->where('payroll_period_id', $period->id)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->groupBy('employee_id')
            : collect();

        $enrichedRecords = $records->map(function (PayrollRecord $record) use ($commissionsByEmployee, $tipsByEmployee, $adjustmentsByEmployee) {
            return array_merge($record->toArray(), [
                'commissions' => ($commissionsByEmployee[$record->employee_id] ?? collect())->values()->toArray(),
                'tips' => ($tipsByEmployee[$record->employee_id] ?? collect())->values()->toArray(),
                'adjustments' => ($adjustmentsByEmployee[$record->employee_id] ?? collect())->values()->toArray(),
            ]);
        });

        // CommissionRecords already assigned to this period but whose employees have no PayrollRecord yet.
        // This happens when auto-payroll ran but no manual "Generate Records" step has been done yet.
        // Exposing these allows the UI to show a warning and surface pending commissions.
        $pendingCommissionsCount = CommissionRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->whereNotIn('employee_id', $employeeIds)
            ->count();

        // Find the next open period after this one (for void-from-paid compensation)
        $nextOpenPeriod = PayrollPeriod::withoutGlobalScopes()
            ->where('business_id', $period->business_id)
            ->where('status', 'open')
            ->where('starts_on', '>', $period->ends_on->toDateString())
            ->orderBy('starts_on')
            ->select(['id', 'starts_on', 'ends_on'])
            ->first();

        $periodSummary = [
            'total_gross' => (string) $records->sum('gross_total'),
            'draft_count' => $records->where('status', 'draft')->count(),
            'approved_count' => $records->where('status', 'approved')->count(),
            'paid_count' => $records->where('status', 'paid')->count(),
            'voided_count' => $records->where('status', 'voided')->count(),
        ];

        $allDraft = $records->isNotEmpty() && $records->every(fn ($r) => $r->status === 'draft');

        // Allow generating records if: period is open AND either no records exist OR there are
        // pending commissions that were auto-assigned but records haven't been generated yet.
        $canGenerate = $period->status === 'open'
            && ($records->isEmpty() || $pendingCommissionsCount > 0);

        $can = [
            'generate' => $canGenerate,
            'approve_all' => $period->status === 'open' && $allDraft,
            'add_adjustment' => $period->status === 'open',
        ];

        $employees = Employee::where('is_active', true)->with('user')->get();

        return Inertia::render('Payroll/Periods/Show', [
            'period' => array_merge($period->toArray(), ['has_records' => $records->isNotEmpty()]),
            'next_open_period' => $nextOpenPeriod,
            'records' => $enrichedRecords->values(),
            'employees' => $employees,
            'period_summary' => $periodSummary,
            'can' => $can,
            'pending_commissions_count' => $pendingCommissionsCount,
        ]);
    }

    public function generate(PayrollPeriod $period): RedirectResponse
    {
        $this->authorize('generate', $period);

        $user = Auth::user();

        try {
            $records = $this->payrollService->generateRecords($period, $user);

            return back()->with('success', "Se generaron {$records->count()} records.");
        } catch (RecordsAlreadyGeneratedException) {
            return back()->with('info', 'Los records ya fueron generados.');
        } catch (PeriodNotOpenException) {
            return back()->withErrors(['period' => 'El período ya no está abierto.']);
        }
    }

    public function approve(PayrollPeriod $period): RedirectResponse
    {
        $this->authorize('update', $period);

        $user = Auth::user();

        try {
            $draftIds = PayrollRecord::withoutGlobalScopes()
                ->where('payroll_period_id', $period->id)
                ->where('status', 'draft')
                ->pluck('id');

            $this->payrollService->approve($period, $user);

            PayrollRecord::withoutGlobalScopes()
                ->whereIn('id', $draftIds)
                ->with(['period'])
                ->each(fn (PayrollRecord $record) => event(new PayrollRecordApproved($record)));

            return back()->with('success', 'Todos los records fueron aprobados.');
        } catch (InvalidPayrollTransitionException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }
    }

    public function employee(PayrollPeriod $period, Employee $employee): Response
    {
        $record = PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->with(['employee.user'])
            ->firstOrFail();

        $commissions = CommissionRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->with(['appointment', 'service'])
            ->get();

        $tips = Tip::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->get();

        $adjustments = PayrollAdjustment::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->with(['creator'])
            ->get();

        // Build transitions from audit fields
        $transitions = [];
        if ($record->created_at) {
            $transitions[] = ['from' => null, 'to' => 'draft', 'at' => $record->created_at->toIso8601String(), 'by_name' => '—'];
        }
        if ($record->approved_at) {
            $transitions[] = ['from' => 'draft', 'to' => 'approved', 'at' => $record->approved_at->toIso8601String(), 'by_name' => $record->approver?->name ?? '—'];
        }
        if ($record->paid_at) {
            $transitions[] = ['from' => 'approved', 'to' => 'paid', 'at' => $record->paid_at->toIso8601String(), 'by_name' => $record->payer?->name ?? '—'];
        }
        if ($record->voided_at) {
            $transitions[] = ['from' => $record->paid_at ? 'paid' : ($record->approved_at ? 'approved' : 'draft'), 'to' => 'voided', 'at' => $record->voided_at->toIso8601String(), 'by_name' => $record->voider?->name ?? '—'];
        }

        return Inertia::render('Payroll/Periods/Employee', [
            'period' => $period,
            'record' => array_merge($record->toArray(), [
                'commissions' => $commissions->map(function (CommissionRecord $c) use ($period) {
                    return array_merge($c->toArray(), [
                        'is_retroactive' => $c->appointment && $c->appointment->scheduled_at < $period->starts_on,
                    ]);
                })->values()->toArray(),
                'tips' => $tips->toArray(),
                'adjustments' => $adjustments->toArray(),
            ]),
            'transitions' => $transitions,
        ]);
    }

    public function export(PayrollPeriod $period): StreamedResponse
    {
        $records = PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->with('employee.user')
            ->orderBy('employee_id')
            ->get();

        $statusLabels = [
            'draft' => 'Borrador',
            'approved' => 'Aprobado',
            'paid' => 'Pagado',
            'voided' => 'Anulado',
        ];
        $paymentMethodLabels = [
            'cash' => 'Efectivo',
            'bank_transfer' => 'Transferencia bancaria',
            'check' => 'Cheque',
        ];

        $slug = Str::slug(
            Carbon::parse($period->starts_on)->translatedFormat('F Y')
        );
        $date = now()->toDateString();
        $filename = "nomina-{$slug}-{$date}.csv";

        return response()->stream(function () use ($records, $statusLabels, $paymentMethodLabels): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Empleado', 'Base Salary', 'Comisiones', 'Tips', 'Ajustes',
                'Bruto Total', 'Estado', 'Método de Pago', 'Referencia de Pago', 'Pagado el',
            ]);

            foreach ($records as $r) {
                fputcsv($handle, [
                    $r->employee?->user?->name ?? "Employee #{$r->employee_id}",
                    number_format((float) $r->base_salary_snapshot, 2, '.', ''),
                    number_format((float) $r->commissions_total, 2, '.', ''),
                    number_format((float) $r->tips_total, 2, '.', ''),
                    number_format((float) $r->adjustments_total, 2, '.', ''),
                    number_format((float) $r->gross_total, 2, '.', ''),
                    $statusLabels[$r->status] ?? $r->status,
                    $paymentMethodLabels[$r->payment_method] ?? ($r->payment_method ?? ''),
                    $r->payment_reference ?? '',
                    $r->paid_at?->toDateString() ?? '',
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
