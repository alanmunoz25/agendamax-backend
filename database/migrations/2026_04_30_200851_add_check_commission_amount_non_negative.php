<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AgendaMax Payroll Phase 3.1 — T-3.1.6 (DN-06)
 *
 * Adds a CHECK constraint enforcing commission_amount >= 0 at the database level.
 *
 * CHECK constraint is enforced at DB level on MySQL/MariaDB.
 * SQLite skip is acceptable because the test driver is array-cache only and tests run
 * with the in-service NegativeCommissionDetectedException defense as the primary guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite does not support ADD CONSTRAINT via ALTER TABLE.
            // The service-layer NegativeCommissionDetectedException provides equivalent protection in tests.
            return;
        }

        DB::statement(
            'ALTER TABLE commission_records ADD CONSTRAINT chk_commission_amount_nonneg CHECK (commission_amount >= 0)'
        );
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE commission_records DROP CONSTRAINT chk_commission_amount_nonneg');
    }
};
