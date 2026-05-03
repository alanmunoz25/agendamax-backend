<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecf_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('tipo', 2)->comment('31, 32, 33, 34, 41, 43, 44, 45, 46, 47');
            $table->string('prefijo', 5)->comment('B01, B02, E31, etc.');
            $table->unsignedBigInteger('desde')->comment('Primer secuencial del rango');
            $table->unsignedBigInteger('hasta')->comment('Último secuencial del rango');
            $table->unsignedBigInteger('proximo_secuencial')->comment('Próximo número a usar');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('status', 20)->default('active')->comment('active, expired, exhausted');
            $table->timestamps();

            $table->unique(['business_id', 'tipo']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecf_sequences');
    }
};
