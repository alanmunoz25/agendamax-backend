<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number', 20);
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_name', 150)->nullable();
            $table->string('client_rnc', 20)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('cashier_id');
            $table->foreign('cashier_id')->references('id')->on('users')->restrictOnDelete();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('discount_pct', 5, 2)->nullable();
            $table->decimal('itbis_amount', 10, 2)->default(0.00);
            $table->decimal('itbis_pct', 5, 2)->default(18.00);
            $table->decimal('tip_amount', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2);
            $table->enum('status', ['open', 'paid', 'voided'])->default('open');
            $table->text('void_reason')->nullable();
            $table->datetime('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();
            $table->boolean('ecf_requested')->default(false);
            $table->enum('ecf_type', ['consumidor_final', 'credito_fiscal'])->nullable();
            $table->enum('ecf_status', ['na', 'pending', 'emitted', 'error', 'offline_pending'])->default('na');
            $table->string('ecf_ncf', 50)->nullable();
            $table->text('ecf_error_message')->nullable();
            $table->datetime('ecf_emitted_at')->nullable();
            $table->unsignedBigInteger('nc_ticket_id')->nullable();
            $table->foreign('nc_ticket_id')->references('id')->on('pos_tickets')->nullOnDelete();
            $table->boolean('is_offline')->default(false);
            $table->datetime('offline_created_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'ticket_number'], 'pos_tickets_ticket_number_business_unique');
            $table->unique('appointment_id', 'pos_tickets_appointment_id_unique');
            $table->index(['business_id', 'status'], 'pos_tickets_business_status_idx');
            $table->index(['business_id', 'cashier_id', 'created_at'], 'pos_tickets_business_cashier_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_tickets');
    }
};
