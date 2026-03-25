<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categories = DB::table('service_categories')->whereNull('slug')->get();

        foreach ($categories as $category) {
            DB::table('service_categories')
                ->where('id', $category->id)
                ->update(['slug' => Str::slug($category->name)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed — slugs are harmless
    }
};
