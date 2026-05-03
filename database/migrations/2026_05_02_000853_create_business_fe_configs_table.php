<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_fe_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('rnc_emisor', 11);
            $table->string('razon_social', 150);
            $table->string('nombre_comercial', 150)->nullable();
            $table->string('direccion', 250)->nullable();
            $table->string('municipio', 10)->nullable();
            $table->string('provincia', 10)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('actividad_economica', 10)->nullable();
            $table->string('cert_path', 500)->nullable()->comment('Ruta relativa a storage/app/certificates/business_{id}/cert.p12');
            $table->text('cert_password')->nullable()->comment('Password encriptado con Crypt::encryptString()');
            $table->string('ambiente', 10)->default('TestECF')->comment('TestECF, CertECF, ECF');
            $table->date('fecha_vigencia_cert')->nullable();
            $table->boolean('activo')->default(false);
            $table->timestamps();

            $table->unique('business_id');
            $table->index('rnc_emisor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_fe_configs');
    }
};
