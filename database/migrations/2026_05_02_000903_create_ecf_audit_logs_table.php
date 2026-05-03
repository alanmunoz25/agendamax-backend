<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecf_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('ecf_id')->nullable()->constrained('ecfs')->nullOnDelete();
            $table->string('action', 80)->comment('get_seed, sign_seed, validate_seed, generate_xml, sign_xml, send_ecf, poll_status, receive_ecf, etc.');
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index(['business_id', 'action']);
            $table->index('ecf_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecf_audit_logs');
    }
};
