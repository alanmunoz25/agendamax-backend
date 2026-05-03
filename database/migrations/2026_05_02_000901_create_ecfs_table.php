<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->unsignedBigInteger('pos_ticket_id')->nullable()->comment('FK a pos_tickets (módulo POS futuro)');
            $table->string('numero_ecf', 19)->unique()->comment('eNCF completo: E310000000001');
            $table->string('tipo', 2)->comment('31, 32, 33, 34, 41, 43, 44, 45, 46, 47');
            $table->string('rnc_comprador', 11)->nullable();
            $table->string('razon_social_comprador', 150)->nullable();
            $table->string('nombre_comprador', 150)->nullable()->comment('Para clientes walk-in sin RNC');
            $table->date('fecha_emision');
            $table->decimal('monto_total', 14, 2);
            $table->decimal('itbis_total', 14, 2)->default('0.00');
            $table->decimal('monto_gravado', 14, 2)->default('0.00');
            $table->string('status', 20)->default('draft')->comment('draft, signed, sent, accepted, rejected, contingency');
            $table->string('track_id', 100)->nullable()->comment('TrackID de DGII para consulta de estado');
            $table->string('xml_path', 500)->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index('status');
            $table->index('track_id');
            $table->index('tipo');
            $table->index(['business_id', 'fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecfs');
    }
};
