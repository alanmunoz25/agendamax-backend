<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Events\Payroll\PayrollAdjustmentCreated;
use App\Exceptions\Payroll\PeriodNotOpenException;
use App\Exceptions\Payroll\RecordAlreadyFinalizedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\AddPayrollAdjustmentRequest;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PayrollAdjustmentController extends Controller
{
    public function __construct(
        private readonly PayrollService $payrollService
    ) {}

    public function index(Request $request): Response
    {
        $businessId = Auth::user()->business_id;

        $employeeId = $request->input('employee_id');
        $periodId = $request->input('payroll_period_id');
        $type = $request->input('type');
        $from = $request->input('from');
        $to = $request->input('to');

        $query = PayrollAdjustment::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->with(['employee.user', 'period']);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        if ($periodId) {
            $query->where('payroll_period_id', $periodId);
        }
        if ($type) {
            $query->where('type', $type);
        }
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $adjustments = $query->orderByDesc('created_at')->paginate(20);

        $totalsQuery = PayrollAdjustment::withoutGlobalScopes()
            ->where('business_id', $businessId);

        if ($employeeId) {
            $totalsQuery->where('employee_id', $employeeId);
        }
        if ($periodId) {
            $totalsQuery->where('payroll_period_id', $periodId);
        }
        if ($type) {
            $totalsQuery->where('type', $type);
        }
        if ($from) {
            $totalsQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $totalsQuery->whereDate('created_at', '<=', $to);
        }

        $credits = (float) (clone $totalsQuery)->where('type', 'credit')->sum('amount');
        $debits = (float) (clone $totalsQuery)->where('type', 'debit')->sum('amount');

        $employeesForFilter = Employee::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->with('user')
            ->get()
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->user?->name ?? "Employee #{$e->id}"])
            ->toArray();

        $periodsForFilter = PayrollPeriod::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => Carbon::parse($p->starts_on)->translatedFormat('F Y'),
            ])
            ->toArray();

        return Inertia::render('Payroll/Adjustments/Index', [
            'adjustments' => [
                'data' => $adjustments->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'amount' => (string) $a->amount,
                    'signed_amount' => $a->type === 'debit'
                        ? '-'.number_format((float) $a->amount, 2, '.', '')
                        : '+'.number_format((float) $a->amount, 2, '.', ''),
                    'reason' => $a->reason,
                    'is_compensation' => $a->related_commission_record_id !== null,
                    'created_at' => $a->created_at?->toDateString(),
                    'employee' => $a->employee ? ['id' => $a->employee->id, 'name' => $a->employee->user?->name] : null,
                    'period' => $a->period ? [
                        'id' => $a->period->id,
                        'label' => Carbon::parse($a->period->starts_on)->translatedFormat('F Y'),
                    ] : null,
                    'origin_record_id' => $a->related_commission_record_id,
                ]),
                'meta' => [
                    'current_page' => $adjustments->currentPage(),
                    'last_page' => $adjustments->lastPage(),
                    'per_page' => $adjustments->perPage(),
                    'total' => $adjustments->total(),
                ],
            ],
            'totals' => [
                'credits' => number_format($credits, 2, '.', ''),
                'debits' => number_format($debits, 2, '.', ''),
                'net' => number_format($credits - $debits, 2, '.', ''),
            ],
            'filters' => [
                'employee_id' => $employeeId,
                'payroll_period_id' => $periodId,
                'type' => $type,
                'from' => $from,
                'to' => $to,
            ],
            'employees_for_filter' => $employeesForFilter,
            'periods_for_filter' => $periodsForFilter,
        ]);
    }

    public function store(AddPayrollAdjustmentRequest $request, PayrollPeriod $period): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $employee = Employee::findOrFail($validated['employee_id']);

        try {
            $this->payrollService->addAdjustment(
                $period,
                $employee,
                $validated['type'],
                (float) $validated['amount'],
                $validated['reason'],
                $user,
            );

            $adjustment = PayrollAdjustment::withoutGlobalScopes()
                ->where('payroll_period_id', $period->id)
                ->where('employee_id', $employee->id)
                ->with(['period'])
                ->latest()
                ->first();

            if ($adjustment) {
                event(new PayrollAdjustmentCreated($adjustment));
            }

            $label = $validated['type'] === 'debit' ? 'débito' : 'crédito';

            return back()->with('success', "Ajuste {$label} de \${$validated['amount']} aplicado.");
        } catch (PeriodNotOpenException) {
            return back()->withErrors(['period' => 'El período fue cerrado mientras editabas. Verifica el estado actual.']);
        } catch (RecordAlreadyFinalizedException) {
            return back()->withErrors(['employee_id' => 'El record de este empleado ya fue aprobado/pagado y no puede recibir ajustes directos.']);
        }
    }
}
