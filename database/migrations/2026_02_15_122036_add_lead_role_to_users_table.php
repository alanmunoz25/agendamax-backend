<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'business_admin', 'employee', 'client', 'lead') DEFAULT 'client'");
        }
        // SQLite: no-op — text columns accept any value
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            // First update any leads back to client
            DB::table('users')->where('role', 'lead')->update(['role' => 'client']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'business_admin', 'employee', 'client') DEFAULT 'client'");
        }
    }
};
