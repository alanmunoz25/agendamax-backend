<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return Inertia::render('dashboard', [
                'stats' => [
                    'total_businesses' => Business::count(),
                    'total_users' => User::count(),
                    'active_businesses' => Business::where('status', 'active')->count(),
                    'recent_businesses' => Business::latest()->take(5)->get(['id', 'name', 'status', 'created_at']),
                ],
            ]);
        }

        return Inertia::render('dashboard', [
            'stats' => [
                'today_appointments' => Appointment::whereDate('scheduled_at', today())->count(),
                'total_clients' => User::where('primary_business_id', $user->primary_business_id)->where('role', 'client')->count(),
                'active_employees' => Employee::where('is_active', true)->count(),
                'total_services' => Service::count(),
            ],
        ]);
    }
}
