<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Exceptions\Pos\TicketAlreadyExistsException;
use App\Exceptions\Pos\TicketNotVoidableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StorePosTicketRequest;
use App\Http\Requests\Pos\VoidPosTicketRequest;
use App\Models\PosTicket;
use App\Services\PosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PosTicketController extends Controller
{
    public function __construct(
        private readonly PosService $posService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // super_admin must explicitly supply business_id to avoid leaking data across all tenants.
        $businessId = $user->isSuperAdmin()
            ? (int) $request->query('business_id', 0)
            : $user->business_id;

        abort_unless(
            $businessId > 0,
            422,
            'super_admin debe especificar business_id para acceder a los tickets.'
        );

        $filters = $request->only(['search', 'method', 'ecf_status', 'date']);

        $tickets = PosTicket::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->with(['employee.user', 'payments', 'items.employee.user'])
            ->when($filters['search'] ?? null, function ($q, $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['method'] ?? null, function ($q, $method): void {
                $q->whereHas('payments', fn ($p) => $p->where('method', $method));
            })
            ->when($filters['ecf_status'] ?? null, fn ($q, $ecfStatus) => $q->where('ecf_status', $ecfStatus))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', $date))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        // Derive the unique list of collaborating employees per ticket from line items,
        // so the tickets list can show all employees who worked on a multi-service ticket.
        $ticketsData = $tickets->through(function (PosTicket $ticket): array {
            $employees = $ticket->items
                ->filter(fn ($item) => $item->employee !== null)
                ->map(fn ($item) => [
                    'id' => $item->employee->id,
                    'name' => $item->employee->user->name,
                ])
                ->unique('id')
                ->values()
                ->all();

            return array_merge($ticket->toArray(), ['employees' => $employees]);
        });

        return Inertia::render('Pos/Tickets/Index', [
            'tickets' => $ticketsData,
            'filters' => $filters,
        ]);
    }

    public function store(StorePosTicketRequest $request): RedirectResponse
    {
        $user = Auth::user();

        try {
            $this->posService->createTicket($request->validated(), $user);
        } catch (TicketAlreadyExistsException $e) {
            return redirect()->back()->withErrors(['appointment_id' => $e->getMessage()]);
        }

        return redirect()->route('pos.index')->with('success', 'Cobro registrado correctamente.');
    }

    public function show(PosTicket $ticket): Response
    {
        $ticket->loadMissing(['items', 'payments', 'appointment', 'employee.user', 'cashier']);

        return Inertia::render('Pos/Tickets/Show', [
            'ticket' => $ticket,
            'can' => [
                'void' => $ticket->status === 'paid',
            ],
        ]);
    }

    public function void(VoidPosTicketRequest $request, PosTicket $ticket): RedirectResponse
    {
        $user = Auth::user();

        try {
            $this->posService->voidTicket($ticket, $request->validated('reason'), $user);
        } catch (TicketNotVoidableException $e) {
            return redirect()->back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()->route('pos.tickets.show', $ticket)
            ->with('success', 'Ticket anulado correctamente.');
    }
}
