<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS Phase 6 — Make appointment_id nullable in tips and commission_records
 * to support walk-in tickets that have no associated appointment.
 * Also makes appointment_service_id nullable in commission_records for walk-in commissions.
 * Drops the unique constraint that prevented null appointment_service_id values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tips', function (Blueprint $table): void {
            $table->foreignId('appointment_id')->nullable()->change();
        });

        Schema::table('commission_records', function (Blueprint $table): void {
            $table->foreignId('appointment_id')->nullable()->change();
            // Drop the FK on appointment_service_id first (required by MariaDB before dropping the index)
            $table->dropForeign(['appointment_service_id']);
            $table->dropUnique('commission_records_line_employee_unique');
            $table->unsignedBigInteger('appointment_service_id')->nullable()->change();
            // Recreate the FK
            $table->foreign('appointment_service_id')->references('id')->on('appointment_services')->nullOnDelete();
            // Restore the unique index — in MariaDB, NULL values do not conflict, so walk-in records
            // (appointment_service_id=NULL) can coexist while appointment-linked records remain unique.
            $table->unique(['appointment_service_id', 'employee_id'], 'commission_records_line_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tips', function (Blueprint $table): void {
            $table->foreignId('appointment_id')->nullable(false)->change();
        });

        Schema::table('commission_records', function (Blueprint $table): void {
            $table->foreignId('appointment_id')->nullable(false)->change();
            $table->foreignId('appointment_service_id')->nullable(false)->constrained('appointment_services')->cascadeOnDelete()->change();
            $table->unique(['appointment_service_id', 'employee_id'], 'commission_records_line_employee_unique');
        });
    }
};
