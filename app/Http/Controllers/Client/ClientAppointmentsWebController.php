<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClientAppointmentsWebController extends Controller
{
    /**
     * Display the cross-business appointment history for the authenticated client.
     */
    public function index(): Response
    {
        $user = Auth::user();

        if ($user->role !== 'client') {
            abort(403);
        }

        $appointments = Appointment::withoutGlobalScopes()
            ->where('client_id', $user->id)
            ->with(['business', 'service', 'employee.user', 'services'])
            ->orderBy('scheduled_at', 'desc')
            ->get();

        $userBusinesses = $user->businesses()
            ->wherePivotIn('status', ['active', 'blocked'])
            ->get()
            ->keyBy('id');

        $grouped = $appointments->groupBy('business_id');

        $appointmentsGrouped = $grouped->map(function ($businessAppointments, $businessId) use ($userBusinesses) {
            $firstAppointment = $businessAppointments->first();
            $business = $firstAppointment->business;

            $enrolledBusiness = $userBusinesses->get($businessId);
            $isBlocked = $enrolledBusiness?->pivot?->status === 'blocked';

            return [
                'business' => $business ? [
                    'id' => $business->id,
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'logo_url' => $business->logo_url,
                ] : null,
                'appointments' => $businessAppointments->map(function (Appointment $appt) {
                    return [
                        'id' => $appt->id,
                        'scheduled_at' => $appt->scheduled_at?->toISOString(),
                        'scheduled_until' => $appt->scheduled_until?->toISOString(),
                        'status' => $appt->status,
                        'notes' => $appt->notes,
                        'final_price' => $appt->final_price,
                        'service' => $appt->service ? [
                            'id' => $appt->service->id,
                            'name' => $appt->service->name,
                            'price' => $appt->service->price,
                            'duration' => $appt->service->duration,
                        ] : null,
                        'employee' => $appt->employee ? [
                            'id' => $appt->employee->id,
                            'name' => $appt->employee->user?->name,
                        ] : null,
                        'services' => $appt->services->map(fn ($s) => [
                            'id' => $s->id,
                            'name' => $s->name,
                            'price' => $s->price,
                        ])->values(),
                    ];
                })->values(),
                'total_count' => $businessAppointments->count(),
                'is_blocked' => $isBlocked,
            ];
        })->values();

        return Inertia::render('Client/MyAppointments', [
            'appointments_grouped' => $appointmentsGrouped,
        ]);
    }
}
