<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\Payroll\EmployeeAdjustmentResource;
use App\Http\Resources\Payroll\EmployeePayrollRecordResource;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayrollController extends Controller
{
    /**
     * GET /v1/employee/payroll/current
     * Returns the current open period's record for the authenticated employee.
     */
    public function current(): JsonResponse
    {
        $employee = $this->resolveEmployee();

        $openPeriod = PayrollPeriod::withoutGlobalScopes()
            ->where('business_id', $employee->business_id)
            ->where('status', 'open')
            ->orderBy('starts_on', 'desc')
            ->first();

        if ($openPeriod === null) {
            return response()->json([
                'data' => null,
                'meta' => [
                    'has_current_period' => false,
                    'message' => 'No tienes un período de nómina activo en este momento.',
                ],
            ]);
        }

        $record = PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $openPeriod->id)
            ->where('employee_id', $employee->id)
            ->with([
                'period',
                'commissionRecords' => fn ($q) => $q->where('employee_id', $employee->id)->with(['appointment', 'service']),
                'tips' => fn ($q) => $q->where('employee_id', $employee->id),
                'adjustments' => fn ($q) => $q->where('employee_id', $employee->id),
            ])
            ->first();

        if ($record === null) {
            return response()->json([
                'data' => null,
                'meta' => [
                    'has_current_period' => true,
                    'has_record' => false,
                    'message' => 'El período está abierto pero aún no se han generado los records.',
                ],
            ]);
        }

        return response()->json([
            'data' => new EmployeePayrollRecordResource($record),
            'meta' => ['has_current_period' => true],
        ]);
    }

    /**
     * GET /v1/employee/payroll/history
     * Returns paginated history of past payroll records for the authenticated employee.
     */
    public function history(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();

        $perPage = min((int) $request->input('per_page', 10), 50);

        $records = PayrollRecord::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereHas('period', fn ($q) => $q->where('status', 'closed'))
            ->with([
                'period',
                'commissionRecords' => fn ($q) => $q->where('employee_id', $employee->id)->with(['appointment', 'service']),
                'tips' => fn ($q) => $q->where('employee_id', $employee->id),
                'adjustments' => fn ($q) => $q->where('employee_id', $employee->id),
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => EmployeePayrollRecordResource::collection($records)->resolve($request),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * GET /v1/employee/payroll/periods/{id}
     * Returns full detail of a specific payroll period record for the authenticated employee.
     *
     * @throws AuthorizationException
     */
    public function periodDetail(PayrollPeriod $period): JsonResponse
    {
        $employee = $this->resolveEmployee();

        $record = PayrollRecord::withoutGlobalScopes()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->with([
                'period',
                'commissionRecords' => fn ($q) => $q->where('employee_id', $employee->id)->with(['appointment', 'service']),
                'tips' => fn ($q) => $q->where('employee_id', $employee->id),
                'adjustments' => fn ($q) => $q->where('employee_id', $employee->id),
            ])
            ->first();

        if ($record === null) {
            abort(404, 'No tienes un record en este período.');
        }

        // Authorization: ensure record belongs to this employee
        if ($record->employee_id !== $employee->id) {
            throw new AuthorizationException;
        }

        return response()->json([
            'data' => new EmployeePayrollRecordResource($record),
        ]);
    }

    /**
     * GET /v1/employee/payroll/adjustments
     * Returns paginated adjustments for the authenticated employee.
     */
    public function adjustments(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();

        $query = PayrollAdjustment::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->with(['period'])
            ->orderByDesc('created_at');

        if ($periodId = $request->input('period_id')) {
            $query->where('payroll_period_id', (int) $periodId);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $adjustments = $query->paginate(10);

        return response()->json([
            'data' => EmployeeAdjustmentResource::collection($adjustments)->resolve($request),
            'meta' => [
                'current_page' => $adjustments->currentPage(),
                'last_page' => $adjustments->lastPage(),
                'per_page' => $adjustments->perPage(),
                'total' => $adjustments->total(),
            ],
        ]);
    }

    private function resolveEmployee(): Employee
    {
        $user = Auth::user();

        if (! $user->isEmployee()) {
            abort(403, 'Este endpoint solo está disponible para empleados.');
        }

        return Employee::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->firstOrFail();
    }
}
