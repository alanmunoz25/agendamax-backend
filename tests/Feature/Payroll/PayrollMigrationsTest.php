<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * AgendaMax Payroll Phase 1 — verify all migration tables and critical columns exist.
 */
class PayrollMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointments_table_has_payroll_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('appointments', 'completed_at'));
        $this->assertTrue(Schema::hasColumn('appointments', 'final_price'));
    }

    public function test_employees_table_has_base_salary_column(): void
    {
        $this->assertTrue(Schema::hasColumn('employees', 'base_salary'));
    }

    public function test_commission_rules_table_exists_with_critical_columns(): void
    {
        $this->assertTrue(Schema::hasTable('commission_rules'));

        $criticalColumns = [
            'id', 'business_id', 'employee_id', 'service_id',
            'type', 'value', 'priority', 'is_active',
            'effective_from', 'effective_until',
        ];

        foreach ($criticalColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('commission_rules', $column),
                "commission_rules.{$column} is missing"
            );
        }
    }

    public function test_payroll_periods_table_exists_with_critical_columns(): void
    {
        $this->assertTrue(Schema::hasTable('payroll_periods'));

        $criticalColumns = [
            'id', 'business_id', 'starts_on', 'ends_on',
            'status', 'closed_at', 'closed_by',
        ];

        foreach ($criticalColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('payroll_periods', $column),
                "payroll_periods.{$column} is missing"
            );
        }
    }

    public function test_commission_records_table_exists_with_critical_columns(): void
    {
        $this->assertTrue(Schema::hasTable('commission_records'));

        $criticalColumns = [
            'id', 'business_id', 'appointment_id', 'appointment_service_id',
            'employee_id', 'service_id', 'commission_rule_id', 'payroll_period_id',
            'service_price_snapshot', 'rule_type_snapshot', 'rule_value_snapshot',
            'commission_amount', 'status', 'generated_at', 'locked_at', 'paid_at',
        ];

        foreach ($criticalColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('commission_records', $column),
                "commission_records.{$column} is missing"
            );
        }
    }

    public function test_payroll_records_table_exists_with_critical_columns(): void
    {
        $this->assertTrue(Schema::hasTable('payroll_records'));

        $criticalColumns = [
            'id', 'business_id', 'payroll_period_id', 'employee_id',
            'base_salary_snapshot', 'commissions_total', 'tips_total',
            'adjustments_total', 'gross_total', 'status',
            'approved_at', 'approved_by', 'paid_at', 'paid_by',
            'payment_method', 'payment_reference',
            'voided_at', 'voided_by', 'void_reason', 'snapshot_payload',
        ];

        foreach ($criticalColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('payroll_records', $column),
                "payroll_records.{$column} is missing"
            );
        }
    }

    public function test_tips_table_exists_with_critical_columns(): void
    {
        $this->assertTrue(Schema::hasTable('tips'));

        $criticalColumns = [
            'id', 'business_id', 'appointment_id', 'employee_id',
            'payroll_period_id', 'amount', 'payment_method', 'notes', 'received_at',
        ];

        foreach ($criticalColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('tips', $column),
                "tips.{$column} is missing"
            );
        }
    }

    public function test_payroll_adjustments_table_exists_with_critical_columns(): void
    {
        $this->assertTrue(Schema::hasTable('payroll_adjustments'));

        $criticalColumns = [
            'id', 'business_id', 'payroll_period_id', 'employee_id',
            'related_commission_record_id', 'related_appointment_id',
            'type', 'amount', 'reason', 'description', 'created_by',
        ];

        foreach ($criticalColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('payroll_adjustments', $column),
                "payroll_adjustments.{$column} is missing"
            );
        }
    }

    public function test_appointment_services_has_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('appointment_services', 'id'));
    }
}
