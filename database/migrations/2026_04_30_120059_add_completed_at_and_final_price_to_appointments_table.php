<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// AgendaMax Payroll Phase 1 — M1: track completion timestamp and final invoiced price on appointments
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('status')->index();
            $table->decimal('final_price', 12, 2)->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['completed_at']);
            $table->dropColumn(['completed_at', 'final_price']);
        });
    }
};
