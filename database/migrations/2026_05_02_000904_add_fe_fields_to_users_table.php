<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rnc', 11)->nullable()->after('phone');
            $table->string('razon_social_fe', 150)->nullable()->after('rnc');
            $table->string('direccion_fiscal', 250)->nullable()->after('razon_social_fe');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rnc', 'razon_social_fe', 'direccion_fiscal']);
        });
    }
};
