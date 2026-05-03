<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ncf_rangos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedTinyInteger('tipo_ecf')->comment('31, 32, 33, 34, 41, 43, 44, 45, 46, 47');
            $table->string('numero_solicitud', 20)->nullable()->comment('Número de solicitud ante la DGII');
            $table->string('numero_autorizacion', 20)->nullable()->comment('Número de resolución/autorización DGII');
            $table->unsignedInteger('secuencia_desde')->comment('Inicio del rango autorizado');
            $table->unsignedInteger('secuencia_hasta')->comment('Fin del rango autorizado');
            $table->unsignedInteger('proximo_secuencial')->comment('Próximo número a usar');
            $table->date('fecha_vencimiento')->nullable()->comment('Fecha de vencimiento de la autorización DGII');
            $table->enum('status', ['active', 'expired', 'exhausted'])->default('active');
            $table->timestamps();

            $table->index(['business_id', 'tipo_ecf']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ncf_rangos');
    }
};
