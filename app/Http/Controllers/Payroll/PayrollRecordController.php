<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Events\Payroll\PayrollAdjustmentCreated;
use App\Events\Payroll\PayrollPeriodClosed;
use App\Events\Payroll\PayrollRecordPaid;
use App\Events\Payroll\PayrollRecordVoided;
use App\Exceptions\Payroll\InvalidPayrollTransitionException;
use App\Exceptions\Payroll\NoOpenPeriodForCompensationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\MarkPayrollRecordPaidRequest;
use App\Http\Requests\Payroll\VoidPayrollRecordRequest;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PayrollRecordController extends Controller
{
    public function __construct(
        private readonly PayrollService $payrollService
    ) {}

    public function markPaid(MarkPayrollRecordPaidRequest $request, PayrollRecord $record): RedirectResponse
    {
        $user = Auth::user();

        try {
            $this->payrollService->markPaid($record, $user, $request->validated());

            $record->refresh()->load('period');
            event(new PayrollRecordPaid($record));

            if ($record->period->status === 'closed') {
                event(new PayrollPeriodClosed($record->period));
            }

            return back()->with('success', 'Pago registrado.');
        } catch (InvalidPayrollTransitionException $e) {
            return back()->withErrors(['record' => 'Este record ya no está en estado Aprobado. Recarga la página.']);
        }
    }

    public function void(VoidPayrollRecordRequest $request, PayrollRecord $record): RedirectResponse
    {
        $user = Auth::user();
        $wasPaid = $record->status === 'paid';
        $periodWasOpen = PayrollPeriod::withoutGlobalScopes()
            ->where('id', $record->payroll_period_id)
            ->value('status') === 'open';

        try {
            $this->payrollService->void($record, $user, $request->validated('reason'));

            $record->refresh()->load('period');
            event(new PayrollRecordVoided($record));

            if ($wasPaid) {
                $compensation = PayrollAdjustment::withoutGlobalScopes()
                    ->where('employee_id', $record->employee_id)
                    ->where('reason', 'Void compensation: payroll_record #'.$record->id)
                    ->with(['period'])
                    ->latest()
                    ->first();

                if ($compensation) {
                    event(new PayrollAdjustmentCreated($compensation));
                }
            }

            // Only dispatch PayrollPeriodClosed if the period JUST auto-closed in this operation
            if ($periodWasOpen && $record->period->status === 'closed') {
                event(new PayrollPeriodClosed($record->period));
            }

            return back()->with('success', 'Record anulado.');
        } catch (InvalidPayrollTransitionException $e) {
            return back()->withErrors(['record' => $e->getMessage()]);
        } catch (NoOpenPeriodForCompensationException) {
            return back()->withErrors(['record' => 'No existe un período abierto después de este. Crea el siguiente período antes de anular este pago.']);
        }
    }
}
