<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Business;
use App\Models\Stamp;
use App\Models\User;
use App\Models\Visit;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoyaltyServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoyaltyService $loyaltyService;

    private Business $business;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loyaltyService = new LoyaltyService;

        // Create business with custom loyalty settings
        $this->business = Business::factory()->create([
            'loyalty_stamps_required' => 5,
            'loyalty_reward_description' => 'Free haircut',
        ]);

        // Create client
        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
        ]);
    }

    public function test_add_stamp_creates_stamp_for_verified_visit(): void
    {
        $visit = Visit::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'verified_at' => now(),
            'stamp_awarded' => false,
        ]);

        $stamp = $this->loyaltyService->addStamp($visit);

        $this->assertInstanceOf(Stamp::class, $stamp);
        $this->assertEquals($visit->id, $stamp->visit_id);
        $this->assertEquals($this->client->id, $stamp->client_id);
        $this->assertEquals($this->business->id, $stamp->business_id);
        $this->assertNotNull($stamp->earned_at);
        $this->assertNull($stamp->redeemed_at);

        // Verify visit was updated
        $visit->refresh();
        $this->assertTrue($visit->stamp_awarded);
    }

    public function test_add_stamp_throws_exception_if_stamp_already_awarded(): void
    {
        $visit = Visit::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'verified_at' => now(),
            'stamp_awarded' => true,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stamp already awarded for this visit');

        $this->loyaltyService->addStamp($visit);
    }

    public function test_add_stamp_is_transactional(): void
    {
        // Create a visit
        $visit = Visit::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'verified_at' => now(),
            'stamp_awarded' => false,
        ]);

        // Add stamp
        $stamp = $this->loyaltyService->addStamp($visit);

        // Verify both stamp and visit were updated atomically
        $this->assertDatabaseHas('stamps', ['id' => $stamp->id]);
        $this->assertDatabaseHas('visits', ['id' => $visit->id, 'stamp_awarded' => true]);
    }

    public function test_get_progress_returns_correct_progress_data(): void
    {
        // Create 3 stamps (need 5 for reward)
        Stamp::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        $progress = $this->loyaltyService->getProgress($this->client->id, $this->business->id);

        $this->assertEquals(3, $progress['current_stamps']);
        $this->assertEquals(5, $progress['stamps_required']);
        $this->assertEquals(60.0, $progress['progress_percentage']);
        $this->assertEquals(2, $progress['stamps_until_reward']);
        $this->assertEquals('Free haircut', $progress['reward_description']);
        $this->assertFalse($progress['can_redeem']);
    }

    public function test_get_progress_at_threshold(): void
    {
        // Create exactly 5 stamps (threshold)
        Stamp::factory()->count(5)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        $progress = $this->loyaltyService->getProgress($this->client->id, $this->business->id);

        $this->assertEquals(5, $progress['current_stamps']);
        $this->assertEquals(5, $progress['stamps_required']);
        $this->assertEquals(100.0, $progress['progress_percentage']);
        $this->assertEquals(0, $progress['stamps_until_reward']);
        $this->assertTrue($progress['can_redeem']);
    }

    public function test_get_progress_above_threshold(): void
    {
        // Create 7 stamps (above threshold of 5)
        Stamp::factory()->count(7)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        $progress = $this->loyaltyService->getProgress($this->client->id, $this->business->id);

        $this->assertEquals(7, $progress['current_stamps']);
        $this->assertEquals(5, $progress['stamps_required']);
        $this->assertEquals(100.0, $progress['progress_percentage']); // Capped at 100%
        $this->assertEquals(0, $progress['stamps_until_reward']);
        $this->assertTrue($progress['can_redeem']);
    }

    public function test_get_progress_excludes_redeemed_stamps(): void
    {
        // Create 5 unredeemed stamps
        Stamp::factory()->count(5)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        // Create 3 redeemed stamps (should not count)
        Stamp::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => now(),
        ]);

        $progress = $this->loyaltyService->getProgress($this->client->id, $this->business->id);

        $this->assertEquals(5, $progress['current_stamps']);
    }

    public function test_check_reward_eligibility_returns_true_when_eligible(): void
    {
        Stamp::factory()->count(5)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        $eligible = $this->loyaltyService->checkRewardEligibility($this->client->id, $this->business->id);

        $this->assertTrue($eligible);
    }

    public function test_check_reward_eligibility_returns_false_when_not_eligible(): void
    {
        Stamp::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        $eligible = $this->loyaltyService->checkRewardEligibility($this->client->id, $this->business->id);

        $this->assertFalse($eligible);
    }

    public function test_redeem_reward_marks_stamps_as_redeemed(): void
    {
        // Create 7 stamps
        Stamp::factory()->count(7)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        $result = $this->loyaltyService->redeemReward($this->client->id, $this->business->id);

        // Verify result
        $this->assertEquals(5, $result['stamps_redeemed']);
        $this->assertEquals(2, $result['remaining_stamps']); // 7 - 5 = 2
        $this->assertNotNull($result['redeemed_at']);
        $this->assertEquals('Free haircut', $result['reward_description']);

        // Verify database
        $redeemedStamps = Stamp::where('business_id', $this->business->id)
            ->where('client_id', $this->client->id)
            ->whereNotNull('redeemed_at')
            ->count();

        $this->assertEquals(5, $redeemedStamps);

        $unredeemedStamps = Stamp::where('business_id', $this->business->id)
            ->where('client_id', $this->client->id)
            ->whereNull('redeemed_at')
            ->count();

        $this->assertEquals(2, $unredeemedStamps);
    }

    public function test_redeem_reward_throws_exception_when_not_eligible(): void
    {
        // Create only 3 stamps (need 5)
        Stamp::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Client does not have enough stamps to redeem a reward');

        $this->loyaltyService->redeemReward($this->client->id, $this->business->id);
    }

    public function test_redeem_reward_uses_oldest_stamps_first(): void
    {
        // Create stamps at different times
        $oldStamp = Stamp::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'earned_at' => now()->subDays(5),
            'redeemed_at' => null,
        ]);

        $middleStamps = Stamp::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'earned_at' => now()->subDays(3),
            'redeemed_at' => null,
        ]);

        $newStamp = Stamp::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'earned_at' => now()->subDay(),
            'redeemed_at' => null,
        ]);

        $recentStamp = Stamp::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'earned_at' => now(),
            'redeemed_at' => null,
        ]);

        $this->loyaltyService->redeemReward($this->client->id, $this->business->id);

        // Verify oldest 5 stamps were redeemed
        $oldStamp->refresh();
        $newStamp->refresh();
        $recentStamp->refresh();

        $this->assertNotNull($oldStamp->redeemed_at);
        $this->assertNotNull($newStamp->redeemed_at);
        $this->assertNull($recentStamp->redeemed_at); // Should remain unredeemed
    }

    public function test_get_history_returns_stamps_ordered_by_earned_date(): void
    {
        // Create stamps at different times
        Stamp::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
        ]);

        $history = $this->loyaltyService->getHistory($this->client->id, $this->business->id);

        $this->assertCount(3, $history);

        // Verify descending order
        $previousEarnedAt = null;
        foreach ($history as $stamp) {
            if ($previousEarnedAt) {
                $this->assertLessThanOrEqual($previousEarnedAt, $stamp->earned_at);
            }
            $previousEarnedAt = $stamp->earned_at;
        }
    }

    public function test_multi_tenant_isolation(): void
    {
        // Create another business and client
        $otherBusiness = Business::factory()->create([
            'loyalty_stamps_required' => 5,
        ]);

        $otherClient = User::factory()->create([
            'business_id' => $otherBusiness->id,
        ]);

        // Create stamps for both businesses
        Stamp::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        Stamp::factory()->count(5)->create([
            'business_id' => $otherBusiness->id,
            'client_id' => $otherClient->id,
            'redeemed_at' => null,
        ]);

        // Verify progress is isolated
        $progress1 = $this->loyaltyService->getProgress($this->client->id, $this->business->id);
        $progress2 = $this->loyaltyService->getProgress($otherClient->id, $otherBusiness->id);

        $this->assertEquals(3, $progress1['current_stamps']);
        $this->assertEquals(5, $progress2['current_stamps']);

        // Verify eligibility is isolated
        $this->assertFalse($this->loyaltyService->checkRewardEligibility($this->client->id, $this->business->id));
        $this->assertTrue($this->loyaltyService->checkRewardEligibility($otherClient->id, $otherBusiness->id));
    }

    public function test_get_auth_user_progress_returns_progress_for_authenticated_user(): void
    {
        Auth::login($this->client);

        Stamp::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        $progress = $this->loyaltyService->getAuthUserProgress();

        $this->assertEquals(3, $progress['current_stamps']);
        $this->assertEquals(5, $progress['stamps_required']);
    }

    public function test_get_auth_user_progress_throws_exception_when_not_authenticated(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User must be authenticated and belong to a business');

        $this->loyaltyService->getAuthUserProgress();
    }

    public function test_redeem_auth_user_reward_redeems_for_authenticated_user(): void
    {
        Auth::login($this->client);

        Stamp::factory()->count(5)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'redeemed_at' => null,
        ]);

        $result = $this->loyaltyService->redeemAuthUserReward();

        $this->assertEquals(5, $result['stamps_redeemed']);
    }

    public function test_redeem_auth_user_reward_throws_exception_when_not_authenticated(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User must be authenticated and belong to a business');

        $this->loyaltyService->redeemAuthUserReward();
    }
}
