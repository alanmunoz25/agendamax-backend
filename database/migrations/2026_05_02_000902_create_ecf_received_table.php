<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecf_received', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('rnc_emisor', 11);
            $table->string('razon_social_emisor', 150)->nullable();
            $table->string('nombre_comercial_emisor', 150)->nullable();
            $table->string('correo_emisor', 100)->nullable();
            $table->string('numero_ecf', 19)->comment('eNCF del proveedor');
            $table->string('tipo', 2)->nullable();
            $table->date('fecha_emision')->nullable();
            $table->decimal('monto_total', 14, 2)->nullable();
            $table->decimal('itbis_total', 14, 2)->nullable();
            $table->string('xml_path', 500)->nullable()->comment('XML original recibido del proveedor');
            $table->string('xml_arecf_path', 500)->nullable()->comment('ARECF generado y firmado');
            $table->string('status', 20)->default('pending')->comment('pending, accepted, rejected');
            $table->string('codigo_motivo', 10)->nullable();
            $table->timestamp('arecf_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'numero_ecf']);
            $table->index('business_id');
            $table->index('rnc_emisor');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecf_received');
    }
};
