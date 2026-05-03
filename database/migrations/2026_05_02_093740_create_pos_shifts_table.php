<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cashier_id');
            $table->foreign('cashier_id')->references('id')->on('users')->restrictOnDelete();
            $table->date('shift_date');
            $table->time('opened_at')->nullable();
            $table->time('closed_at')->nullable();
            $table->decimal('opening_cash', 10, 2)->default(0.00);
            $table->decimal('closing_cash_counted', 10, 2)->default(0.00);
            $table->decimal('closing_cash_expected', 10, 2)->default(0.00);
            $table->decimal('cash_difference', 10, 2)->default(0.00);
            $table->text('difference_reason')->nullable();
            $table->integer('tickets_count')->default(0);
            $table->decimal('total_sales', 10, 2)->default(0.00);
            $table->decimal('total_tips', 10, 2)->default(0.00);
            $table->decimal('cash_sales', 10, 2)->default(0.00);
            $table->decimal('card_sales', 10, 2)->default(0.00);
            $table->decimal('transfer_sales', 10, 2)->default(0.00);
            $table->string('pdf_path', 255)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'cashier_id', 'shift_date'], 'pos_shifts_business_cashier_date_unique');
            $table->index(['business_id', 'shift_date'], 'pos_shifts_business_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};
