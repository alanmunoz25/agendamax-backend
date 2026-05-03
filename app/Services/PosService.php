<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Pos\TicketAlreadyExistsException;
use App\Exceptions\Pos\TicketNotVoidableException;
use App\Jobs\EmitEcfJob;
use App\Models\Appointment;
use App\Models\PosPayment;
use App\Models\PosTicket;
use App\Models\PosTicketItem;
use App\Models\Service;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PosService
{
    public function __construct(
        private readonly CommissionService $commissionService
    ) {}

    /**
     * Create a new POS ticket from checkout data.
     * Handles appointment-driven and walk-in tickets.
     *
     * @param  array{
     *   appointment_id?: int|null,
     *   client_id?: int|null,
     *   client_name?: string|null,
     *   client_rnc?: string|null,
     *   employee_id?: int|null,
     *   items: array<int, array{type: string, item_id: int, name: string, unit_price: string, qty: int, employee_id?: int|null, appointment_service_id?: int|null}>,
     *   discount_amount: string,
     *   itbis_pct: string,
     *   tip_amount: string,
     *   payments: array<int, array{method: string, amount: string, reference?: string|null, cash_tendered?: string|null}>,
     *   ecf_requested: bool,
     *   ecf_type?: string|null,
     *   notes?: string|null
     * }  $data
     */
    public function createTicket(array $data, User $cashier): PosTicket
    {
        // super_admin has no implicit business — must supply business_id in $data.
        // Regular users fall back to their own business_id.
        $businessId = $data['business_id'] ?? $cashier->business_id;

        if ($businessId === null) {
            throw new \DomainException(
                'business_id is required. super_admin must specify it explicitly.'
            );
        }

        // Validate appointment if provided
        $appointment = null;
        if (! empty($data['appointment_id'])) {
            $appointment = Appointment::withoutGlobalScopes()
                ->where('id', $data['appointment_id'])
                ->where('business_id', $businessId)
                ->firstOrFail();

            if ($appointment->ticket_id !== null) {
                throw new TicketAlreadyExistsException(
                    "Appointment #{$appointment->id} already has ticket #{$appointment->ticket_id}."
                );
            }

            if ($appointment->status === 'cancelled') {
                throw new \InvalidArgumentException('Cannot create a ticket for a cancelled appointment.');
            }
        }

        return DB::transaction(function () use ($data, $cashier, $businessId, $appointment): PosTicket {
            // Calculate totals using BCMath for monetary precision
            $subtotal = '0.00';
            foreach ($data['items'] as $item) {
                $lineTotal = bcmul((string) $item['unit_price'], (string) $item['qty'], 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);
            }

            $discountAmount = bcadd((string) ($data['discount_amount'] ?? '0'), '0', 2);
            $subtotalAfterDiscount = bcsub($subtotal, $discountAmount, 2);
            if (bccomp($subtotalAfterDiscount, '0', 2) < 0) {
                $subtotalAfterDiscount = '0.00';
            }

            $itbisRate = bcdiv((string) ($data['itbis_pct'] ?? '18'), '100', 6);
            $itbisAmount = bcmul($subtotalAfterDiscount, $itbisRate, 2);

            $tipAmount = bcadd((string) ($data['tip_amount'] ?? '0'), '0', 2);

            $total = bcadd(
                bcadd($subtotalAfterDiscount, $itbisAmount, 2),
                $tipAmount,
                2
            );

            // Create ticket — status is set via forceFill since it is excluded from $fillable
            // to prevent mass assignment from untrusted input.
            $ticket = new PosTicket;
            $ticket->fill([
                'business_id' => $businessId,
                'ticket_number' => 'TKT-PENDING',
                'appointment_id' => $data['appointment_id'] ?? null,
                'client_id' => $data['client_id'] ?? null,
                'client_name' => $data['client_name'] ?? null,
                'client_rnc' => $data['client_rnc'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'cashier_id' => $cashier->id,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'discount_pct' => $discountAmount !== '0.00' && bccomp($subtotal, '0', 2) > 0
                    ? bcmul(bcdiv($discountAmount, $subtotal, 6), '100', 2)
                    : null,
                'itbis_amount' => $itbisAmount,
                'itbis_pct' => $data['itbis_pct'] ?? '18.00',
                'tip_amount' => $tipAmount,
                'total' => $total,
                'ecf_requested' => $data['ecf_requested'] ?? false,
                'ecf_type' => $data['ecf_type'] ?? null,
                'ecf_status' => $data['ecf_requested'] ? 'pending' : 'na',
                'notes' => $data['notes'] ?? null,
            ]);
            // Guarded transition fields — set explicitly from trusted service layer
            $ticket->forceFill(['status' => 'paid']);
            $ticket->save();

            // Assign readable ticket number using the inserted ID (no race condition)
            $year = now()->year;
            $ticket->update([
                'ticket_number' => PosTicket::generateTicketNumber($year, $ticket->id),
            ]);

            // Create line items
            foreach ($data['items'] as $item) {
                $lineTotal = bcmul((string) $item['unit_price'], (string) $item['qty'], 2);
                PosTicketItem::create([
                    'pos_ticket_id' => $ticket->id,
                    'item_type' => $item['type'],
                    'item_id' => $item['item_id'] ?? null,
                    'name' => $item['name'],
                    'unit_price' => $item['unit_price'],
                    'qty' => $item['qty'],
                    'line_total' => $lineTotal,
                    'employee_id' => $item['employee_id'] ?? null,
                    'appointment_service_id' => $item['appointment_service_id'] ?? null,
                ]);
            }

            // Create payment records
            foreach ($data['payments'] as $payment) {
                $cashChange = null;
                if ($payment['method'] === 'cash' && isset($payment['cash_tendered'])) {
                    $cashChange = bcsub((string) $payment['cash_tendered'], $total, 2);
                    if (bccomp($cashChange, '0', 2) < 0) {
                        $cashChange = '0.00';
                    }
                }

                PosPayment::create([
                    'pos_ticket_id' => $ticket->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'cash_tendered' => $payment['cash_tendered'] ?? null,
                    'cash_change' => $cashChange,
                ]);
            }

            // Create tip record if tip > 0 and there is an employee to assign it to
            if (bccomp($tipAmount, '0', 2) > 0 && ! empty($data['employee_id'])) {
                $firstPaymentMethod = $data['payments'][0]['method'] ?? 'cash';

                Tip::create([
                    'business_id' => $businessId,
                    'employee_id' => $data['employee_id'],
                    'appointment_id' => $data['appointment_id'] ?? null,
                    'amount' => $tipAmount,
                    'payment_method' => $firstPaymentMethod,
                    'received_at' => now(),
                ]);
            }

            // Link appointment and update its status
            if ($appointment !== null) {
                $appointment->update([
                    'status' => 'completed',
                    'completed_at' => $appointment->completed_at ?? now(),
                    'final_price' => $total,
                    'ticket_id' => $ticket->id,
                ]);

                // Generate commissions for appointment-driven tickets
                $this->commissionService->generateForAppointment($appointment->fresh());
            } else {
                // Walk-in: generate per-item commissions if business allows it
                $business = $cashier->business;
                if ($business !== null && $business->pos_commissions_enabled) {
                    foreach ($data['items'] as $item) {
                        if (
                            ($item['type'] ?? '') === 'service'
                            && ! empty($item['employee_id'])
                            && ! empty($item['item_id'])
                        ) {
                            $employee = \App\Models\Employee::withoutGlobalScopes()
                                ->where('id', $item['employee_id'])
                                ->where('business_id', $businessId)
                                ->first();

                            $service = Service::withoutGlobalScopes()
                                ->where('id', $item['item_id'])
                                ->where('business_id', $businessId)
                                ->first();

                            if ($employee !== null && $service !== null) {
                                $lineTotal = bcmul((string) $item['unit_price'], (string) $item['qty'], 2);
                                $this->commissionService->generateForServiceItem($employee, $service, $lineTotal, null);
                            }
                        }
                    }
                }
            }

            // Dispatch e-CF job (stub) if requested
            if (! empty($data['ecf_requested'])) {
                EmitEcfJob::dispatch($ticket->id);
            }

            return $ticket->load(['items', 'payments']);
        });
    }

    /**
     * Void a paid ticket. Restores appointment to collectable state if linked.
     *
     * @throws \DomainException when the ticket has an e-CF emitted (must void via DGII portal)
     * @throws TicketNotVoidableException when the ticket is not in a voidable state
     */
    public function voidTicket(PosTicket $ticket, string $reason, User $voidedBy): PosTicket
    {
        if ($ticket->status !== 'paid') {
            throw new TicketNotVoidableException(
                "Ticket #{$ticket->id} cannot be voided — current status: {$ticket->status}."
            );
        }

        // Guard (BLOCK-002): tickets with an e-CF emitted cannot be voided from POS in v1.0.
        // The business admin must issue a Nota de Crédito tipo 34 directly in the DGII portal
        // and then register the manual void from the e-CF detail page.
        if (! empty($ticket->ecf_ncf)) {
            throw new \DomainException(
                'Este ticket tiene e-CF emitido. Para anularlo, emite Nota de '
                .'Crédito tipo 34 directamente en el portal DGII y luego '
                .'registra la anulación manual desde el detalle del e-CF.'
            );
        }

        return DB::transaction(function () use ($ticket, $reason, $voidedBy): PosTicket {
            // These fields are guarded from mass assignment; forceFill is correct here
            // because this is the service layer with validated, authorized input.
            $ticket->forceFill([
                'status' => 'voided',
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by' => $voidedBy->id,
            ])->save();

            // Restore linked appointment so it can be checked out again
            if ($ticket->appointment_id !== null) {
                Appointment::withoutGlobalScopes()
                    ->where('id', $ticket->appointment_id)
                    ->update([
                        'status' => 'completed',
                        'ticket_id' => null,
                    ]);
            }

            return $ticket->fresh();
        });
    }

    /**
     * Calculate the shift summary for a given cashier on a given date.
     *
     * @return array{
     *   tickets_count: int,
     *   total_sales: string,
     *   total_tips: string,
     *   by_method: array{cash: string, card: string, transfer: string},
     *   cash_expected: string
     * }
     */
    public function calculateShiftSummary(int $businessId, int $cashierId, string $date): array
    {
        $tickets = PosTicket::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('cashier_id', $cashierId)
            ->where('status', 'paid')
            ->whereDate('created_at', $date)
            ->with('payments')
            ->get();

        $totalSales = '0.00';
        $totalTips = '0.00';
        $cashSales = '0.00';
        $cardSales = '0.00';
        $transferSales = '0.00';

        foreach ($tickets as $ticket) {
            $totalSales = bcadd($totalSales, (string) $ticket->total, 2);
            $totalTips = bcadd($totalTips, (string) $ticket->tip_amount, 2);

            foreach ($ticket->payments as $payment) {
                match ($payment->method) {
                    'cash' => $cashSales = bcadd($cashSales, (string) $payment->amount, 2),
                    'card' => $cardSales = bcadd($cardSales, (string) $payment->amount, 2),
                    'transfer' => $transferSales = bcadd($transferSales, (string) $payment->amount, 2),
                    default => null,
                };
            }
        }

        return [
            'tickets_count' => $tickets->count(),
            'total_sales' => $totalSales,
            'total_tips' => $totalTips,
            'by_method' => [
                'cash' => $cashSales,
                'card' => $cardSales,
                'transfer' => $transferSales,
            ],
            'cash_expected' => $cashSales,
        ];
    }
}
