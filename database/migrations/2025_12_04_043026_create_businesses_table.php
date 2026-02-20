<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('invitation_code', 20)->unique();
            $table->unsignedInteger('loyalty_stamps_required')->default(10);
            $table->text('loyalty_reward_description')->nullable();
            $table->string('status')->default('active');
            $table->string('timezone')->default('UTC');
            $table->timestamps();

            $table->index('invitation_code');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
