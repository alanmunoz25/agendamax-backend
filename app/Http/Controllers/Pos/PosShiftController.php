<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StorePosShiftRequest;
use App\Models\Employee;
use App\Models\PosShift;
use App\Services\PosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PosShiftController extends Controller
{
    public function __construct(
        private readonly PosService $posService
    ) {}

    public function create(): Response
    {
        $user = Auth::user();
        $today = now()->toDateString();

        $shiftSummary = $this->posService->calculateShiftSummary(
            $user->primary_business_id,
            $user->id,
            $today
        );

        $existingShift = PosShift::query()
            ->where('cashier_id', $user->id)
            ->whereDate('shift_date', $today)
            ->first();

        return Inertia::render('Pos/ShiftClose', [
            'cashier' => $user,
            'today' => $today,
            'shift_summary' => $shiftSummary,
            'existing_shift' => $existingShift,
            'employees' => Employee::query()->where('is_active', true)->with('user')->get(),
        ]);
    }

    public function store(StorePosShiftRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        $cashierId = (int) $data['cashier_id'];
        $shiftDate = $data['shift_date'];

        // Check for duplicate shift
        if (PosShift::query()->where('business_id', $user->primary_business_id)->where('cashier_id', $cashierId)->whereDate('shift_date', $shiftDate)->exists()) {
            return redirect()->back()->withErrors(['shift_date' => 'Ya existe un cierre registrado para este cajero en esta fecha.']);
        }

        $summary = $this->posService->calculateShiftSummary($user->primary_business_id, $cashierId, $shiftDate);

        $openingCash = (string) ($data['opening_cash'] ?? '0');
        $cashExpected = bcadd($openingCash, $summary['by_method']['cash'], 2);
        $cashCounted = (string) ($data['closing_cash_counted'] ?? '0');
        $cashDifference = bcsub($cashCounted, $cashExpected, 2);

        // Cashier-supplied fields go through fill(); server-calculated fields
        // (totals, cash_expected, difference, closed_at) use forceFill() to
        // bypass the mass-assignment guard and prevent submitted fabrication.
        $shift = new PosShift;
        $shift->fill([
            'business_id' => $user->primary_business_id,
            'cashier_id' => $cashierId,
            'shift_date' => $shiftDate,
            'opened_at' => $data['opened_at'] ?? null,
            'opening_cash' => $openingCash,
            'closing_cash_counted' => $cashCounted,
            'difference_reason' => $data['difference_reason'] ?? null,
        ]);
        $shift->forceFill([
            'closed_at' => $data['closed_at'] ?? now(),
            'closing_cash_expected' => $cashExpected,
            'cash_difference' => $cashDifference,
            'tickets_count' => $summary['tickets_count'],
            'total_sales' => $summary['total_sales'],
            'total_tips' => $summary['total_tips'],
            'cash_sales' => $summary['by_method']['cash'],
            'card_sales' => $summary['by_method']['card'],
            'transfer_sales' => $summary['by_method']['transfer'],
        ])->save();

        return redirect()->route('pos.index')
            ->with('success', 'Turno cerrado correctamente. Resumen guardado.');
    }
}
