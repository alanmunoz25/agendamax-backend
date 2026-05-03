<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollExportTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'name' => 'Test Employee',
            'email_verified_at' => now(),
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_it_exports_period_csv_with_correct_headers(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'base_salary_snapshot' => 1000,
            'commissions_total' => 500,
            'tips_total' => 50,
            'adjustments_total' => 0,
            'gross_total' => 1550,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/payroll/periods/{$period->id}/export");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Empleado', $content);
        $this->assertStringContainsString('Bruto Total', $content);
        $this->assertStringContainsString('Comisiones', $content);
    }

    /** @test */
    public function test_it_exports_period_csv_with_bom(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => 1000,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/payroll/periods/{$period->id}/export");

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
    }

    /** @test */
    public function test_it_exports_employee_history_csv(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => 2000,
            'commissions_total' => 500,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/payroll/employees/{$this->employee->id}/export");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Período', $content);
        $this->assertStringContainsString('Bruto Total', $content);
    }

    /** @test */
    public function test_period_export_includes_employee_name(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => 1500,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/payroll/periods/{$period->id}/export");

        $content = $response->streamedContent();
        $this->assertStringContainsString('Test Employee', $content);
    }

    /** @test */
    public function test_it_requires_authentication_for_period_export(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        $this->get("/payroll/periods/{$period->id}/export")->assertRedirect('/login');
    }

    /** @test */
    public function test_it_requires_authentication_for_employee_export(): void
    {
        $this->get("/payroll/employees/{$this->employee->id}/export")->assertRedirect('/login');
    }
}
