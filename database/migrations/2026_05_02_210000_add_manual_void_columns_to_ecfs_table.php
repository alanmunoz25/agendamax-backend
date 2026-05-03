<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecfs', function (Blueprint $table): void {
            $table->string('manual_void_ncf', 19)->nullable()->after('error_message')
                ->comment('NCF de la NC tipo 34 emitida manualmente en portal DGII');
            $table->text('manual_void_reason')->nullable()->after('manual_void_ncf');
            $table->timestamp('voided_at')->nullable()->after('manual_void_reason');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ecfs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['manual_void_ncf', 'manual_void_reason', 'voided_at']);
        });
    }
};
