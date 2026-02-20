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
        Schema::table('users', function (Blueprint $table) {
            // Drop global unique constraint on email
            $table->dropUnique(['email']);

            // Add business fields
            $table->foreignId('business_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->enum('role', ['super_admin', 'business_admin', 'employee', 'client'])->default('client')->after('business_id');
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar_url')->nullable()->after('phone');
            $table->string('push_token')->nullable()->after('avatar_url');

            // Business-scoped unique constraints
            $table->unique(['business_id', 'email'], 'users_business_email_unique');
            $table->unique(['business_id', 'phone'], 'users_business_phone_unique');

            $table->index('business_id');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop business-scoped unique constraints
            $table->dropUnique('users_business_email_unique');
            $table->dropUnique('users_business_phone_unique');

            $table->dropForeign(['business_id']);
            $table->dropIndex(['business_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['business_id', 'role', 'phone', 'avatar_url', 'push_token']);

            // Restore global unique constraint on email
            $table->unique('email');
        });
    }
};
