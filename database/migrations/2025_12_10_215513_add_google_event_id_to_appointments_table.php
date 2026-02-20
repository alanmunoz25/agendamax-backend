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
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('google_event_id')->nullable()->after('cancellation_reason');
            $table->timestamp('google_synced_at')->nullable()->after('google_event_id');

            $table->index('google_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['google_event_id']);
            $table->dropColumn(['google_event_id', 'google_synced_at']);
        });
    }
};
