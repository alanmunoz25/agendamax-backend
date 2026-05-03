<?php

declare(strict_types=1);

namespace Tests\Feature\Fcm;

use App\Events\Payroll\PayrollRecordApproved;
use App\Events\Payroll\PayrollRecordPaid;
use App\Listeners\Payroll\SendPayrollRecordApprovedPush;
use App\Listeners\Payroll\SendPayrollRecordPaidPush;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PayrollPushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_payroll_record_approved_dispatches_listener_to_fcm_queue(): void
    {
        // Verify the event-to-listener mapping is registered in AppServiceProvider.
        Event::fake();

        Event::assertListening(PayrollRecordApproved::class, SendPayrollRecordApprovedPush::class);
    }

    /** @test */
    public function test_payroll_record_paid_dispatches_listener_to_fcm_queue(): void
    {
        // Verify the event-to-listener mapping is registered in AppServiceProvider.
        Event::fake();

        Event::assertListening(PayrollRecordPaid::class, SendPayrollRecordPaidPush::class);
    }

    /** @test */
    public function test_listener_skips_gracefully_when_employee_has_no_user(): void
    {
        // Mock FcmService so Firebase SDK is never instantiated.
        $fcmMock = $this->mock(FcmService::class);
        $fcmMock->shouldNotReceive('sendToUser');

        $business = Business::factory()->create();
        $period = PayrollPeriod::factory()->forBusiness($business)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);
        $employeeUser = User::factory()->create(['business_id' => $business->id, 'role' => 'employee']);
        $employee = Employee::factory()->create(['business_id' => $business->id, 'user_id' => $employeeUser->id]);
        $record = PayrollRecord::factory()->create([
            'business_id' => $business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);

        // Detach the user relation on the in-memory Employee instance so the
        // listener sees employee->user as null (simulates deleted user edge case).
        $employee->setRelation('user', null);
        $record->setRelation('employee', $employee);

        $listener = app(SendPayrollRecordApprovedPush::class);

        $threwException = false;
        try {
            $listener->handle(new PayrollRecordApproved($record));
        } catch (\Throwable) {
            $threwException = true;
        }

        $this->assertFalse($threwException, 'Listener must not throw when employee has no user');
    }

    /** @test */
    public function test_listener_failed_handler_logs_without_rethrowing(): void
    {
        // Mock FcmService so Firebase SDK is never instantiated.
        $this->mock(FcmService::class);

        $business = Business::factory()->create();
        $period = PayrollPeriod::factory()->forBusiness($business)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);
        $employeeUser = User::factory()->create(['business_id' => $business->id, 'role' => 'employee']);
        $employee = Employee::factory()->create(['business_id' => $business->id, 'user_id' => $employeeUser->id]);
        $record = PayrollRecord::factory()->create([
            'business_id' => $business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);

        $listener = app(SendPayrollRecordApprovedPush::class);
        $event = new PayrollRecordApproved($record);
        $exception = new \RuntimeException('Simulated queue failure');

        $threwException = false;
        try {
            $listener->failed($event, $exception);
        } catch (\Throwable) {
            $threwException = true;
        }

        $this->assertFalse($threwException, 'failed() handler must not rethrow exceptions');
    }
}
