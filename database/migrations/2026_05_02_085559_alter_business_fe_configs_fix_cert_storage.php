<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_fe_configs', function (Blueprint $table) {
            $table->dropColumn('cert_path');
            $table->renameColumn('cert_password', 'password_certificado');
            $table->longText('certificado_digital')->nullable()->after('actividad_economica')
                ->comment('Base64 del P12 convertido a AES-256-CBC');
            $table->boolean('certificado_convertido')->default(false)->after('certificado_digital');
        });
    }

    public function down(): void
    {
        Schema::table('business_fe_configs', function (Blueprint $table) {
            $table->dropColumn(['certificado_digital', 'certificado_convertido']);
            $table->renameColumn('password_certificado', 'cert_password');
            $table->string('cert_path', 500)->nullable()
                ->comment('Ruta relativa a storage/app/certificates/business_{id}/cert.p12');
        });
    }
};
