<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Exceptions\Payroll\PeriodOverlapException;
use App\Models\Business;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-3.1.4 — createPeriod Cache::lock tests.
 *
 * Verifies that the Cache::lock in createPeriod is correctly acquired and
 * released under success, overlap exception, and serial-call scenarios.
 *
 * Cross-process concurrency tests (pcntl_fork + shared atomic cache) are
 * deferred to T-3.1.8 and must run against MariaDB with a non-array cache driver.
 */
class PayrollCreatePeriodLockTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    private Business $business;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollService::class);

        $this->adminUser = User::factory()->create(['role' => 'super_admin']);
        $this->business = Business::factory()->create();
    }

    /**
     * After a successful createPeriod call the lock must be released so that
     * a subsequent call for the same business (with a non-overlapping range)
     * can also succeed without blocking or deadlocking.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_period_releases_lock_on_success(): void
    {
        $periodA = $this->service->createPeriod(
            $this->business,
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
            $this->adminUser
        );

        $this->assertInstanceOf(PayrollPeriod::class, $periodA);
        $this->assertEquals('open', $periodA->status);

        // Immediately calling createPeriod again with a different range must succeed,
        // proving that the lock was released in the finally block.
        $periodB = $this->service->createPeriod(
            $this->business,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30'),
            $this->adminUser
        );

        $this->assertInstanceOf(PayrollPeriod::class, $periodB);
        $this->assertEquals('open', $periodB->status);
        $this->assertNotEquals($periodA->id, $periodB->id);
    }

    /**
     * When an overlap exception is thrown inside the DB::transaction the lock
     * must still be released by the finally block, allowing a valid subsequent
     * call to succeed.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_period_releases_lock_on_overlap_exception(): void
    {
        // Create the initial period.
        $this->service->createPeriod(
            $this->business,
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
            $this->adminUser
        );

        // Attempt an overlapping range — must throw.
        try {
            $this->service->createPeriod(
                $this->business,
                Carbon::parse('2026-05-15'),
                Carbon::parse('2026-06-15'),
                $this->adminUser
            );
            $this->fail('Expected PeriodOverlapException was not thrown.');
        } catch (PeriodOverlapException) {
            // Expected — the lock must have been released in the finally block.
        }

        // A valid non-overlapping call immediately after must succeed,
        // demonstrating the lock was properly released despite the exception.
        $periodB = $this->service->createPeriod(
            $this->business,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30'),
            $this->adminUser
        );

        $this->assertInstanceOf(PayrollPeriod::class, $periodB);
        $this->assertEquals('open', $periodB->status);
    }

    /**
     * Five serial calls with non-overlapping ranges for the same business must
     * all succeed and produce five distinct open periods, with no lock leakage
     * between calls.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_period_serial_calls_for_same_business_succeed(): void
    {
        $months = [
            ['2026-01-01', '2026-01-31'],
            ['2026-02-01', '2026-02-28'],
            ['2026-03-01', '2026-03-31'],
            ['2026-04-01', '2026-04-30'],
            ['2026-05-01', '2026-05-31'],
        ];

        $created = [];

        foreach ($months as [$startStr, $endStr]) {
            $period = $this->service->createPeriod(
                $this->business,
                Carbon::parse($startStr),
                Carbon::parse($endStr),
                $this->adminUser
            );

            $this->assertInstanceOf(PayrollPeriod::class, $period);
            $this->assertEquals('open', $period->status);
            $this->assertEquals($this->business->id, $period->business_id);
            $created[] = $period->id;
        }

        // All five periods must have unique IDs (no duplicates or lock starvation).
        $this->assertCount(5, array_unique($created));

        $this->assertDatabaseCount('payroll_periods', 5);
    }

    /**
     * Locks for different businesses must be independent — creating periods for
     * business A and business B in sequence must both succeed without interference.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_period_locks_are_scoped_per_business(): void
    {
        $businessB = Business::factory()->create();

        $periodA = $this->service->createPeriod(
            $this->business,
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
            $this->adminUser
        );

        $periodB = $this->service->createPeriod(
            $businessB,
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
            $this->adminUser
        );

        $this->assertEquals($this->business->id, $periodA->business_id);
        $this->assertEquals($businessB->id, $periodB->business_id);
        $this->assertNotEquals($periodA->id, $periodB->id);
    }

    /**
     * Placeholder for the cross-process concurrency test that verifies Cache::lock
     * prevents two simultaneous createPeriod calls from inserting overlapping periods.
     *
     * Requirements:
     * - Shared atomic cache driver (Redis or database — NOT array).
     * - pcntl_fork or Symfony\Process to spawn a second OS process.
     * - MariaDB or MySQL (SQLite does not support cross-process row locks).
     *
     * This test is covered in T-3.1.8 (CI MariaDB job).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\Group('concurrency')]
    public function test_create_period_concurrent_does_not_produce_overlap(): void
    {
        if (! in_array(\Illuminate\Support\Facades\DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Requires MariaDB/MySQL for atomic row-level locks. Run with phpunit.mariadb.xml.');
        }

        if (config('cache.default') === 'array') {
            $this->markTestSkipped('Requires a non-array cache driver (e.g. database) for atomic cross-process locks.');
        }

        // TD-018: pcntl_fork / Symfony\Process implementation pending.
        // MariaDB CI is now active (phpunit.mariadb.xml). The fork test remains
        // the last open item before full concurrency coverage is complete.
        $this->markTestSkipped('pcntl_fork or Symfony\\Process concurrency harness not yet implemented (TD-018).');
    }
}
