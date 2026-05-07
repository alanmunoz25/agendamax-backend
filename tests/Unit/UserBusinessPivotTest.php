<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Pivots\UserBusiness;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the UserBusiness pivot model and User helper methods.
 */
class UserBusinessPivotTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->user = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);
    }

    // ── Pivot creation and retrieval ─────────────────────────────────────

    public function test_pivot_row_can_be_created_and_retrieved(): void
    {
        $joinedAt = now()->subDays(10);

        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => $joinedAt,
        ]);

        $pivot = $this->user->businesses()
            ->wherePivot('business_id', $this->business->id)
            ->first()
            ?->pivot;

        $this->assertNotNull($pivot);
        $this->assertInstanceOf(UserBusiness::class, $pivot);
        $this->assertEquals('client', $pivot->role_in_business);
        $this->assertEquals('active', $pivot->status);
    }

    // ── Datetime casts ────────────────────────────────────────────────────

    public function test_joined_at_is_cast_to_carbon(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $pivot = $this->user->businesses()->first()?->pivot;

        $this->assertInstanceOf(Carbon::class, $pivot->joined_at);
    }

    public function test_blocked_at_is_cast_to_carbon_when_set(): void
    {
        $blockedAt = now()->subHours(2);

        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'blocked',
            'joined_at' => now()->subDays(5),
            'blocked_at' => $blockedAt,
        ]);

        $pivot = $this->user->businesses()->first()?->pivot;

        $this->assertInstanceOf(Carbon::class, $pivot->blocked_at);
    }

    public function test_blocked_at_is_null_when_not_set(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $pivot = $this->user->businesses()->first()?->pivot;

        $this->assertNull($pivot->blocked_at);
    }

    // ── Pivot helper methods ──────────────────────────────────────────────

    public function test_is_active_returns_true_for_active_status(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $pivot = $this->user->businesses()->first()?->pivot;

        $this->assertTrue($pivot->isActive());
        $this->assertFalse($pivot->isBlocked());
        $this->assertFalse($pivot->isLeft());
    }

    public function test_is_blocked_returns_true_for_blocked_status(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'blocked',
            'joined_at' => now(),
        ]);

        $pivot = $this->user->businesses()->first()?->pivot;

        $this->assertFalse($pivot->isActive());
        $this->assertTrue($pivot->isBlocked());
        $this->assertFalse($pivot->isLeft());
    }

    public function test_is_left_returns_true_for_left_status(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'left',
            'joined_at' => now(),
        ]);

        $pivot = $this->user->businesses()->first()?->pivot;

        $this->assertFalse($pivot->isActive());
        $this->assertFalse($pivot->isBlocked());
        $this->assertTrue($pivot->isLeft());
    }

    // ── blocked_by relation ───────────────────────────────────────────────

    public function test_blocked_by_relation_resolves_to_user(): void
    {
        $blocker = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'blocked',
            'joined_at' => now()->subDays(5),
            'blocked_at' => now(),
            'blocked_by_user_id' => $blocker->id,
            'blocked_reason' => 'Test reason for blocking.',
        ]);

        $pivot = UserBusiness::query()
            ->where('user_id', $this->user->id)
            ->where('business_id', $this->business->id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertInstanceOf(User::class, $pivot->blockedBy);
        $this->assertEquals($blocker->id, $pivot->blockedBy->id);
    }

    // ── User::isEnrolledIn ────────────────────────────────────────────────

    public function test_is_enrolled_in_returns_true_for_active_enrollment(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->assertTrue($this->user->isEnrolledIn($this->business));
    }

    public function test_is_enrolled_in_returns_true_for_blocked_enrollment(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'blocked',
            'joined_at' => now(),
        ]);

        // Blocked is still considered enrolled (history must remain accessible).
        $this->assertTrue($this->user->isEnrolledIn($this->business));
    }

    public function test_is_enrolled_in_returns_false_for_left_status(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'left',
            'joined_at' => now(),
        ]);

        $this->assertFalse($this->user->isEnrolledIn($this->business));
    }

    public function test_is_enrolled_in_returns_false_with_no_pivot(): void
    {
        $otherBusiness = Business::factory()->create();

        $this->assertFalse($this->user->isEnrolledIn($otherBusiness));
    }

    // ── User::isBlockedIn ─────────────────────────────────────────────────

    public function test_is_blocked_in_returns_true_when_blocked(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'blocked',
            'joined_at' => now(),
        ]);

        $this->assertTrue($this->user->isBlockedIn($this->business));
    }

    public function test_is_blocked_in_returns_false_when_active(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->assertFalse($this->user->isBlockedIn($this->business));
    }

    // ── User::canBookIn ───────────────────────────────────────────────────

    public function test_can_book_in_returns_true_for_active_enrollment(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->assertTrue($this->user->canBookIn($this->business));
    }

    public function test_can_book_in_returns_false_when_blocked(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'blocked',
            'joined_at' => now(),
        ]);

        $this->assertFalse($this->user->canBookIn($this->business));
    }

    public function test_can_book_in_returns_false_with_no_enrollment(): void
    {
        $otherBusiness = Business::factory()->create();

        $this->assertFalse($this->user->canBookIn($otherBusiness));
    }

    // ── Business::members relation ────────────────────────────────────────

    public function test_business_members_relation_returns_enrolled_users(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $members = $this->business->members;

        $this->assertTrue($members->contains('id', $this->user->id));
    }

    public function test_business_members_pivot_has_expected_fields(): void
    {
        $this->user->businesses()->attach($this->business->id, [
            'role_in_business' => 'admin',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $member = $this->business->members->first();

        $this->assertEquals('admin', $member->pivot->role_in_business);
        $this->assertEquals('active', $member->pivot->status);
    }
}
