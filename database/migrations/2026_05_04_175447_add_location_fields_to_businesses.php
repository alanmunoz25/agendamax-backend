<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('sector', 80)->nullable()->after('status');
            $table->string('province', 80)->nullable()->after('sector');
            $table->char('country', 2)->default('DO')->after('province');
            $table->decimal('latitude', 10, 7)->nullable()->after('country');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        if ($driver === 'mariadb' || $driver === 'mysql') {
            // Generated column: longitude is X (first), latitude is Y (second) per MariaDB convention.
            // MariaDB 10.11 does not support the two-argument ST_SRID(); use GeomFromText with CONCAT.
            DB::statement("ALTER TABLE businesses ADD COLUMN location POINT GENERATED ALWAYS AS (IF(latitude IS NOT NULL AND longitude IS NOT NULL, GeomFromText(CONCAT('POINT(', longitude, ' ', latitude, ')'), 4326), NULL)) STORED");

            // FULLTEXT index for full-text business search.
            DB::statement('ALTER TABLE businesses ADD FULLTEXT INDEX businesses_name_description_ft (name, description)');

            // Composite index for province + sector geo-filter queries.
            DB::statement('ALTER TABLE businesses ADD INDEX businesses_province_sector_idx (province, sector)');

            // Note: MariaDB requires spatial indexes to be on NOT NULL columns, so a spatial index
            // on the nullable `location` column is not possible. The discover() method uses a
            // bounding-box pre-filter on the indexed latitude/longitude columns instead.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mariadb' || $driver === 'mysql') {
            DB::statement('ALTER TABLE businesses DROP INDEX businesses_province_sector_idx');
            DB::statement('ALTER TABLE businesses DROP INDEX businesses_name_description_ft');
        }

        Schema::table('businesses', function (Blueprint $table) use ($driver): void {
            if ($driver === 'mariadb' || $driver === 'mysql') {
                $table->dropColumn('location');
            }

            $table->dropColumn(['sector', 'province', 'country', 'latitude', 'longitude']);
        });
    }
};
