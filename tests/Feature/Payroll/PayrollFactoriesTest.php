<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\Tip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 1 — verify every new factory creates valid records with correct business_id.
 */
class PayrollFactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_rule_factory_creates_record_with_business_id(): void
    {
        $business = Business::factory()->create();
        $rule = CommissionRule::factory()->create(['business_id' => $business->id]);

        $this->assertDatabaseHas('commission_rules', ['id' => $rule->id, 'business_id' => $business->id]);
    }

    public function test_commission_rule_factory_percentage_state(): void
    {
        $rule = CommissionRule::factory()->percentage(15.0)->create();

        $this->assertSame('percentage', $rule->type);
        $this->assertEquals('15.00', $rule->value);
    }

    public function test_commission_rule_factory_fixed_state(): void
    {
        $rule = CommissionRule::factory()->fixed(20.0)->create();

        $this->assertSame('fixed', $rule->type);
        $this->assertEquals('20.00', $rule->value);
    }

    public function test_commission_rule_factory_inactive_state(): void
    {
        $rule = CommissionRule::factory()->inactive()->create();

        $this->assertFalse($rule->is_active);
    }

    public function test_commission_rule_factory_expired_state(): void
    {
        $rule = CommissionRule::factory()->expired()->create();

        $this->assertNotNull($rule->effective_until);
        $this->assertTrue($rule->effective_until->isPast());
    }

    public function test_payroll_period_factory_creates_record_with_business_id(): void
    {
        $business = Business::factory()->create();
        $period = PayrollPeriod::factory()->forBusiness($business)->create();

        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id, 'business_id' => $business->id]);
    }

    public function test_payroll_period_factory_open_state(): void
    {
        $period = PayrollPeriod::factory()->open()->create();

        $this->assertSame('open', $period->status);
    }

    public function test_payroll_period_factory_closed_state(): void
    {
        $period = PayrollPeriod::factory()->closed()->create();

        $this->assertSame('closed', $period->status);
        $this->assertNotNull($period->closed_at);
    }

    public function test_payroll_record_factory_creates_draft_by_default(): void
    {
        $record = PayrollRecord::factory()->create();

        $this->assertSame('draft', $record->status);
        $this->assertDatabaseHas('payroll_records', ['id' => $record->id]);
    }

    public function test_payroll_record_factory_approved_state(): void
    {
        $record = PayrollRecord::factory()->approved()->create();

        $this->assertSame('approved', $record->status);
        $this->assertNotNull($record->approved_at);
    }

    public function test_payroll_record_factory_paid_state(): void
    {
        $record = PayrollRecord::factory()->paid()->create();

        $this->assertSame('paid', $record->status);
        $this->assertNotNull($record->paid_at);
    }

    public function test_payroll_record_factory_voided_state(): void
    {
        $record = PayrollRecord::factory()->voided()->create();

        $this->assertSame('voided', $record->status);
        $this->assertNotNull($record->voided_at);
    }

    public function test_tip_factory_creates_record_with_business_id(): void
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $tip = Tip::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertDatabaseHas('tips', ['id' => $tip->id, 'business_id' => $business->id]);
    }

    public function test_tip_factory_cash_state(): void
    {
        $tip = Tip::factory()->cash()->create();

        $this->assertSame('cash', $tip->payment_method);
    }

    public function test_tip_factory_card_state(): void
    {
        $tip = Tip::factory()->card()->create();

        $this->assertSame('card', $tip->payment_method);
    }

    public function test_payroll_adjustment_factory_creates_record_with_business_id(): void
    {
        $business = Business::factory()->create();
        $period = PayrollPeriod::factory()->forBusiness($business)->create();
        $adjustment = PayrollAdjustment::factory()->forPeriod($period)->create([
            'business_id' => $business->id,
        ]);

        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $adjustment->id,
            'business_id' => $business->id,
        ]);
    }

    public function test_payroll_adjustment_factory_credit_state(): void
    {
        $adjustment = PayrollAdjustment::factory()->credit(100.0)->create();

        $this->assertSame('credit', $adjustment->type);
        $this->assertEquals('100.00', $adjustment->amount);
    }

    public function test_payroll_adjustment_factory_debit_state(): void
    {
        $adjustment = PayrollAdjustment::factory()->debit(75.0)->create();

        $this->assertSame('debit', $adjustment->type);
        $this->assertEquals('75.00', $adjustment->amount);
    }
}
