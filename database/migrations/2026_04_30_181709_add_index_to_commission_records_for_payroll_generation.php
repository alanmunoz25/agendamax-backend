<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// AgendaMax Payroll Phase 3 — Wave 1 fix: composite index for efficient payroll generation queries.
// The existing index (business_id, employee_id, status) lacks created_at, forcing full scans
// on the whereBetween clause used in PayrollService::generateRecords().
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commission_records', function (Blueprint $table): void {
            $table->index(
                ['business_id', 'employee_id', 'status', 'created_at'],
                'commission_records_payroll_generation_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_records', function (Blueprint $table): void {
            $table->dropIndex('commission_records_payroll_generation_idx');
        });
    }
};
