<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stamps', function (Blueprint $table) {
            // Add appointment_id column
            $table->foreignId('appointment_id')->nullable()->after('client_id')->constrained()->cascadeOnDelete();

            // Make visit_id nullable since stamps can be earned via appointments OR visits
            $table->foreignId('visit_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stamps', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropColumn('appointment_id');

            // Revert visit_id to non-nullable
            $table->foreignId('visit_id')->nullable(false)->change();
        });
    }
};
