<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * TD-038: Verify the composite index on payroll_periods for analytics queries.
 */
class PayrollPeriodsIndexTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_payroll_periods_composite_index_exists(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: verify via sqlite_master
            $result = DB::select(
                "SELECT name FROM sqlite_master WHERE type='index' AND name='payroll_periods_business_status_starts_idx'"
            );
            $this->assertNotEmpty(
                $result,
                "Index 'payroll_periods_business_status_starts_idx' not found in SQLite"
            );
        } else {
            // MariaDB / MySQL
            $indexes = DB::select(
                "SHOW INDEX FROM payroll_periods WHERE Key_name = 'payroll_periods_business_status_starts_idx'"
            );
            $this->assertNotEmpty(
                $indexes,
                "Index 'payroll_periods_business_status_starts_idx' not found in MariaDB/MySQL"
            );
        }
    }

    /** @test */
    public function test_payroll_periods_table_has_required_indexed_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('payroll_periods', 'business_id'));
        $this->assertTrue(Schema::hasColumn('payroll_periods', 'status'));
        $this->assertTrue(Schema::hasColumn('payroll_periods', 'starts_on'));
    }
}
