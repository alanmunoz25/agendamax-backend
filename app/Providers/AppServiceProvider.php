<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentProviderInterface;
use App\Models\Appointment;
use App\Models\Employee;
use App\Observers\AppointmentObserver;
use App\Observers\EmployeeObserver;
use App\Services\Payment\StubPaymentProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentProviderInterface::class, StubPaymentProvider::class);
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
