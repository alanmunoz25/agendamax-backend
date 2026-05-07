<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PosShift;
use App\Models\PosTicket;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // super_admin without an active business context must explicitly supply
        // business_id to avoid leaking data across all tenants via scope bypass.
        $businessId = $user->isSuperAdmin()
            ? (int) $request->query('business_id', 0)
            : $user->business_id;

        abort_unless(
            $businessId > 0,
            422,
            'super_admin debe especificar business_id para acceder al POS.'
        );

        $business = Business::find($businessId);
        abort_unless($business !== null, 404, 'Business not found.');

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Show today's appointments AND recently-billed appointments from the last 2 days
        // so that an appointment scheduled yesterday but billed today remains visible.
        $todayAppointments = Appointment::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where(function ($q) use ($today, $yesterday): void {
                $q->whereDate('scheduled_at', $today)
                    ->orWhere(function ($q2) use ($today, $yesterday): void {
                        // Recently billed (has a ticket) from yesterday or today
                        $q2->whereNotNull('ticket_id')
                            ->whereDate('scheduled_at', '>=', $yesterday)
                            ->whereDate('scheduled_at', '<', $today);
                    });
            })
            ->with(['client', 'employee.user', 'services', 'ticket'])
            ->orderBy('scheduled_at')
            ->get();

        $collectedCount = $todayAppointments->filter(fn ($a) => $a->ticket_id !== null)->count();
        $uncollectedCount = $todayAppointments
            ->filter(fn ($a) => $a->ticket_id === null && $a->status !== 'cancelled')
            ->count();

        $totalSalesToday = PosTicket::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'paid')
            ->whereDate('created_at', $today)
            ->sum('total');

        $hasOpenShift = PosShift::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('cashier_id', $user->id)
            ->whereDate('shift_date', $today)
            ->exists();

        return Inertia::render('Pos/Index', [
            'today_appointments' => $todayAppointments,
            'service_categories' => ServiceCategory::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->orderBy('name')
                ->get(),
            'employees_for_walkin' => Employee::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->with('user')
                ->get(),
            'today_summary' => [
                'total_tickets' => $todayAppointments->count(),
                'uncollected_count' => $uncollectedCount,
                'collected_count' => $collectedCount,
                'total_sales_today' => (string) $totalSalesToday,
            ],
            'has_open_shift' => $hasOpenShift,
            'ecf_enabled' => $business->feConfig?->activo ?? false,
            'services_catalog' => Inertia::defer(fn () => Service::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->with('serviceCategory')
                ->orderBy('name')
                ->get()),
            'products_catalog' => Inertia::defer(fn () => Product::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->active()
                ->orderBy('name')
                ->get()),
        ]);
    }
}
