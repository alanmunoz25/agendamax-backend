<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentProviderInterface;
use App\Events\Payroll\PayrollAdjustmentCreated;
use App\Events\Payroll\PayrollRecordApproved;
use App\Events\Payroll\PayrollRecordPaid;
use App\Events\Payroll\PayrollRecordVoided;
use App\Listeners\Payroll\SendPayrollAdjustmentCreatedPush;
use App\Listeners\Payroll\SendPayrollRecordApprovedPush;
use App\Listeners\Payroll\SendPayrollRecordPaidPush;
use App\Listeners\Payroll\SendPayrollRecordVoidedPush;
use App\Models\Appointment;
use App\Models\BusinessFeConfig;
use App\Models\CommissionRule;
use App\Models\Ecf;
use App\Models\EcfReceived;
use App\Models\Employee;
use App\Models\NcfRango;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\PosShift;
use App\Models\PosTicket;
use App\Observers\AppointmentObserver;
use App\Observers\EmployeeObserver;
use App\Policies\ClientEnrollmentPolicy;
use App\Policies\ElectronicInvoice\BusinessFeConfigPolicy;
use App\Policies\ElectronicInvoice\EcfPolicy;
use App\Policies\ElectronicInvoice\EcfReceivedPolicy;
use App\Policies\ElectronicInvoice\NcfRangoPolicy;
use App\Policies\Payroll\CommissionRulePolicy;
use App\Policies\Payroll\PayrollAdjustmentPolicy;
use App\Policies\Payroll\PayrollPeriodPolicy;
use App\Policies\Payroll\PayrollRecordPolicy;
use App\Policies\Pos\PosShiftPolicy;
use App\Policies\Pos\PosTicketPolicy;
use App\Services\Payment\StubPaymentProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        // Payroll policies (sub-namespace not auto-discovered by Laravel)
        Gate::policy(PayrollPeriod::class, PayrollPeriodPolicy::class);
        Gate::policy(PayrollRecord::class, PayrollRecordPolicy::class);
        Gate::policy(PayrollAdjustment::class, PayrollAdjustmentPolicy::class);
        Gate::policy(CommissionRule::class, CommissionRulePolicy::class);

        // Electronic Invoice policies (sub-namespace not auto-discovered by Laravel)
        Gate::policy(Ecf::class, EcfPolicy::class);
        Gate::policy(BusinessFeConfig::class, BusinessFeConfigPolicy::class);
        Gate::policy(NcfRango::class, NcfRangoPolicy::class);
        Gate::policy(EcfReceived::class, EcfReceivedPolicy::class);

        // POS policies (sub-namespace not auto-discovered by Laravel)
        Gate::policy(PosTicket::class, PosTicketPolicy::class);
        Gate::policy(PosShift::class, PosShiftPolicy::class);

        // Client enrollment gates (block/unblock — not tied to a single model)
        $clientEnrollmentPolicy = new ClientEnrollmentPolicy;
        Gate::define('block-client', fn ($actor, $target, $business) => $clientEnrollmentPolicy->block($actor, $target, $business));
        Gate::define('unblock-client', fn ($actor, $target, $business) => $clientEnrollmentPolicy->unblock($actor, $target, $business));

        // FCM push notification listeners for payroll events
        Event::listen(PayrollRecordApproved::class, SendPayrollRecordApprovedPush::class);
        Event::listen(PayrollRecordPaid::class, SendPayrollRecordPaidPush::class);
        Event::listen(PayrollRecordVoided::class, SendPayrollRecordVoidedPush::class);
        Event::listen(PayrollAdjustmentCreated::class, SendPayrollAdjustmentCreatedPush::class);
    }
}
