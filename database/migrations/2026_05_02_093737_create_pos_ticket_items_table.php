<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_ticket_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', ['service', 'product']);
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('name', 200);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedTinyInteger('qty')->default(1);
            $table->decimal('line_total', 10, 2);
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('appointment_service_id')->nullable();
            $table->foreign('appointment_service_id')->references('id')->on('appointment_services')->nullOnDelete();
            $table->timestamps();

            $table->index('pos_ticket_id', 'pos_ticket_items_ticket_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_ticket_items');
    }
};
