<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Employee;

use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TD-037: Verify the /employee/payroll/history endpoint does not suffer from N+1 queries.
 */
class PayrollHistoryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $employeeUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'email_verified_at' => now(),
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $this->employeeUser->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_history_endpoint_does_not_n_plus_one(): void
    {
        $adminUser = User::factory()->create(['business_id' => $this->business->id]);

        // Create 5 closed periods each with a paid record, commissions, tips, and adjustments
        for ($i = 1; $i <= 5; $i++) {
            $period = PayrollPeriod::factory()->forBusiness($this->business)->closed()->create([
                'starts_on' => "2026-0{$i}-01",
                'ends_on' => "2026-0{$i}-28",
            ]);

            $record = PayrollRecord::factory()->paid()->create([
                'business_id' => $this->business->id,
                'payroll_period_id' => $period->id,
                'employee_id' => $this->employee->id,
            ]);

            // Commission record for this employee/period
            CommissionRecord::factory()
                ->inPeriod($period)
                ->paid()
                ->create([
                    'business_id' => $this->business->id,
                    'employee_id' => $this->employee->id,
                ]);

            // Tip for this employee/period
            Tip::factory()
                ->inPeriod($period)
                ->create([
                    'business_id' => $this->business->id,
                    'employee_id' => $this->employee->id,
                    'amount' => '50.00',
                ]);

            // Adjustment for this employee/period
            PayrollAdjustment::factory()->create([
                'business_id' => $this->business->id,
                'payroll_period_id' => $period->id,
                'employee_id' => $this->employee->id,
                'type' => 'credit',
                'amount' => '100.00',
                'reason' => 'Test bonus',
                'created_by' => $adminUser->id,
            ]);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/employee/payroll/history');

        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        /**
         * Baseline: 14 queries (1 main + 1 count + 4 eager-loaded relations × N records).
         * Threshold 16 = baseline + 2 (margin for legit growth).
         * If this exceeds, suspect N+1 regression in EmployeePayrollRecordResource
         * or controller eager loads.
         */
        $this->assertLessThan(16, $queryCount,
            "Expected <16 queries, got {$queryCount} (N+1 regression). Baseline: 14.");
    }
}
