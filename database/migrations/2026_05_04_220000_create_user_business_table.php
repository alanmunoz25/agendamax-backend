<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_business', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->enum('role_in_business', ['client', 'employee', 'admin']);
            $table->enum('status', ['active', 'left', 'blocked'])->default('active');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('blocked_at')->nullable();
            $table->foreignId('blocked_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('blocked_reason', 500)->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'business_id']);
            $table->index(['business_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        // Backfill existing users who already belong to a business.
        // Idempotent: safe to run again — duplicates are silently skipped.
        DB::transaction(function (): void {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                // SQLite supports INSERT OR IGNORE for idempotency.
                DB::statement("
                    INSERT OR IGNORE INTO user_business
                        (user_id, business_id, role_in_business, status, joined_at, created_at, updated_at)
                    SELECT
                        id,
                        business_id,
                        CASE
                            WHEN role IN ('business_admin', 'super_admin') THEN 'admin'
                            WHEN role = 'employee' THEN 'employee'
                            ELSE 'client'
                        END,
                        'active',
                        COALESCE(created_at, CURRENT_TIMESTAMP),
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    FROM users
                    WHERE business_id IS NOT NULL
                ");
            } else {
                // MariaDB / MySQL — ON DUPLICATE KEY UPDATE is idempotent thanks to the UNIQUE index.
                DB::statement("
                    INSERT INTO user_business
                        (user_id, business_id, role_in_business, status, joined_at, created_at, updated_at)
                    SELECT
                        id,
                        business_id,
                        CASE
                            WHEN role IN ('business_admin', 'super_admin') THEN 'admin'
                            WHEN role = 'employee' THEN 'employee'
                            ELSE 'client'
                        END,
                        'active',
                        COALESCE(created_at, NOW()),
                        NOW(),
                        NOW()
                    FROM users
                    WHERE business_id IS NOT NULL
                    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
                ");
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_business');
    }
};
