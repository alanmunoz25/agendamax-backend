<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add cert_encrypted_at timestamp used by the fe:encrypt-existing-certificates command
     * as an idempotence flag to track which rows have been re-encrypted with the
     * Laravel 'encrypted' cast.
     */
    public function up(): void
    {
        Schema::table('business_fe_configs', function (Blueprint $table): void {
            $table->timestamp('cert_encrypted_at')->nullable()->after('certificado_digital');
        });
    }

    public function down(): void
    {
        Schema::table('business_fe_configs', function (Blueprint $table): void {
            $table->dropColumn('cert_encrypted_at');
        });
    }
};
