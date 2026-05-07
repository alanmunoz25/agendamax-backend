<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies that the create_user_business_table migration backfill
 * inserts exactly one pivot row per user that has a business_id,
 * and that re-running the backfill SQL is idempotent (no duplicates).
 *
 * Because RefreshDatabase rolls back and re-migrates between tests,
 * the migration runs fresh for every test in this class.
 */
class MigrationBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_one_row_per_user_with_business_id(): void
    {
        $business = Business::factory()->create();

        // Users with a business_id — should appear in pivot.
        User::factory()->count(3)->create([
            'business_id' => $business->id,
            'role' => 'client',
        ]);
        User::factory()->create([
            'business_id' => $business->id,
            'role' => 'business_admin',
        ]);
        User::factory()->create([
            'business_id' => $business->id,
            'role' => 'employee',
        ]);

        // Users without a business_id — should NOT appear in pivot.
        User::factory()->count(2)->create(['business_id' => null, 'role' => 'client']);

        $usersWithBusiness = User::query()
            ->whereNotNull('primary_business_id')
            ->count();

        // The migration already ran via RefreshDatabase, but these users were
        // created AFTER the migration, so we need to manually run the backfill
        // SQL to test it in isolation.
        $this->runBackfill();

        $pivotCount = DB::table('user_business')->count();

        $this->assertEquals($usersWithBusiness, $pivotCount);
    }

    public function test_backfill_maps_roles_correctly(): void
    {
        $business = Business::factory()->create();

        User::factory()->create(['business_id' => $business->id, 'role' => 'business_admin']);
        User::factory()->create(['business_id' => $business->id, 'role' => 'super_admin']);
        User::factory()->create(['business_id' => $business->id, 'role' => 'employee']);
        User::factory()->create(['business_id' => $business->id, 'role' => 'client']);
        User::factory()->create(['business_id' => $business->id, 'role' => 'lead']);

        // Clear any pre-existing pivot rows from setUp (user factory creates users).
        DB::table('user_business')->delete();

        $this->runBackfill();

        $adminRows = DB::table('user_business')
            ->where('role_in_business', 'admin')
            ->count();

        $employeeRows = DB::table('user_business')
            ->where('role_in_business', 'employee')
            ->count();

        $clientRows = DB::table('user_business')
            ->where('role_in_business', 'client')
            ->count();

        // business_admin + super_admin → 'admin'
        $this->assertEquals(2, $adminRows);
        // employee → 'employee'
        $this->assertEquals(1, $employeeRows);
        // client + lead → 'client'
        $this->assertEquals(2, $clientRows);
    }

    public function test_backfill_is_idempotent(): void
    {
        $business = Business::factory()->create();

        User::factory()->count(3)->create([
            'business_id' => $business->id,
            'role' => 'client',
        ]);

        DB::table('user_business')->delete();

        // Run backfill twice.
        $this->runBackfill();
        $countAfterFirst = DB::table('user_business')->count();

        $this->runBackfill();
        $countAfterSecond = DB::table('user_business')->count();

        $this->assertEquals($countAfterFirst, $countAfterSecond, 'Backfill should be idempotent — no duplicate rows on second run.');
    }

    public function test_users_without_business_id_are_not_backfilled(): void
    {
        User::factory()->count(5)->create(['business_id' => null, 'role' => 'client']);

        DB::table('user_business')->delete();

        $this->runBackfill();

        $this->assertEquals(0, DB::table('user_business')->count());
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    /**
     * Execute the backfill SQL in a driver-agnostic way.
     * Mirrors the logic in the migration's up() method.
     */
    private function runBackfill(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("
                INSERT OR IGNORE INTO user_business
                    (user_id, business_id, role_in_business, status, joined_at, created_at, updated_at)
                SELECT
                    id,
                    primary_business_id,
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
                WHERE primary_business_id IS NOT NULL
            ");
        } else {
            DB::statement("
                INSERT INTO user_business
                    (user_id, business_id, role_in_business, status, joined_at, created_at, updated_at)
                SELECT
                    id,
                    primary_business_id,
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
                WHERE primary_business_id IS NOT NULL
                ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
            ");
        }
    }
}
