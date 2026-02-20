<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Employee;
use App\Observers\AppointmentObserver;
use App\Observers\EmployeeObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        Appointment::observe(AppointmentObserver::class);
        Employee::observe(EmployeeObserver::class);
    }
}
