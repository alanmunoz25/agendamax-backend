<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['cash', 'card', 'transfer']);
            $table->decimal('amount', 10, 2);
            $table->string('reference', 100)->nullable();
            $table->decimal('cash_tendered', 10, 2)->nullable();
            $table->decimal('cash_change', 10, 2)->nullable();
            $table->timestamps();

            $table->index('pos_ticket_id', 'pos_payments_ticket_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
    }
};
