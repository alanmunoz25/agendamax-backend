<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\Stamp;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Add a stamp for a verified visit.
     *
     * @throws \Exception
     */
    public function addStamp(Visit $visit): Stamp
    {
        // Verify visit hasn't already received a stamp
        if ($visit->stamp_awarded) {
            throw new \Exception('Stamp already awarded for this visit');
        }

        return DB::transaction(function () use ($visit) {
            // Create stamp
            $stamp = Stamp::create([
                'business_id' => $visit->business_id,
                'client_id' => $visit->client_id,
                'visit_id' => $visit->id,
                'earned_at' => now(),
            ]);

            // Update visit to mark stamp as awarded
            $visit->update(['stamp_awarded' => true]);

            return $stamp->load(['client', 'visit']);
        });
    }

    /**
     * Get loyalty progress for a client.
     *
     * @return array{
     *     current_stamps: int,
     *     stamps_required: int,
     *     progress_percentage: float,
     *     stamps_until_reward: int,
     *     reward_description: string|null,
     *     can_redeem: bool
     * }
     */
    public function getProgress(int $clientId, int $businessId): array
    {
        $business = Business::findOrFail($businessId);

        // Count unredeemed stamps for this client
        $currentStamps = Stamp::where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->whereNull('redeemed_at')
            ->count();

        $stampsRequired = $business->loyalty_stamps_required ?? 10;
        $stampsUntilReward = max(0, $stampsRequired - $currentStamps);
        $progressPercentage = min(100, ($currentStamps / $stampsRequired) * 100);

        return [
            'current_stamps' => $currentStamps,
            'stamps_required' => $stampsRequired,
            'progress_percentage' => round($progressPercentage, 2),
            'stamps_until_reward' => $stampsUntilReward,
            'reward_description' => $business->loyalty_reward_description,
            'can_redeem' => $currentStamps >= $stampsRequired,
        ];
    }

    /**
     * Check if a client is eligible for reward redemption.
     */
    public function checkRewardEligibility(int $clientId, int $businessId): bool
    {
        $business = Business::findOrFail($businessId);
        $stampsRequired = $business->loyalty_stamps_required ?? 10;

        $currentStamps = Stamp::where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->whereNull('redeemed_at')
            ->count();

        return $currentStamps >= $stampsRequired;
    }

    /**
     * Redeem a reward for a client.
     *
     * @throws \Exception
     */
    public function redeemReward(int $clientId, int $businessId): array
    {
        // Verify eligibility
        if (! $this->checkRewardEligibility($clientId, $businessId)) {
            throw new \Exception('Client does not have enough stamps to redeem a reward');
        }

        $business = Business::findOrFail($businessId);
        $stampsRequired = $business->loyalty_stamps_required ?? 10;

        return DB::transaction(function () use ($clientId, $businessId, $stampsRequired) {
            // Get the oldest unredeemed stamps up to the required amount
            $stamps = Stamp::where('business_id', $businessId)
                ->where('client_id', $clientId)
                ->whereNull('redeemed_at')
                ->orderBy('earned_at', 'asc')
                ->limit($stampsRequired)
                ->get();

            // Mark stamps as redeemed
            $redeemedAt = now();
            foreach ($stamps as $stamp) {
                $stamp->update(['redeemed_at' => $redeemedAt]);
            }

            // Get updated progress
            $progress = $this->getProgress($clientId, $businessId);

            return [
                'stamps_redeemed' => $stamps->count(),
                'redeemed_at' => $redeemedAt->toIso8601String(),
                'remaining_stamps' => $progress['current_stamps'],
                'reward_description' => $progress['reward_description'],
            ];
        });
    }

    /**
     * Get loyalty history for a client.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getHistory(int $clientId, int $businessId)
    {
        return Stamp::where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->with(['visit.appointment', 'visit.employee.user'])
            ->orderBy('earned_at', 'desc')
            ->get();
    }

    /**
     * Get loyalty progress for the authenticated user.
     *
     * @return array{
     *     current_stamps: int,
     *     stamps_required: int,
     *     progress_percentage: float,
     *     stamps_until_reward: int,
     *     reward_description: string|null,
     *     can_redeem: bool
     * }
     */
    public function getAuthUserProgress(): array
    {
        $user = Auth::user();

        if (! $user || ! $user->business_id) {
            throw new \Exception('User must be authenticated and belong to a business');
        }

        return $this->getProgress($user->id, $user->business_id);
    }

    /**
     * Redeem a reward for the authenticated user.
     *
     * @throws \Exception
     */
    public function redeemAuthUserReward(): array
    {
        $user = Auth::user();

        if (! $user || ! $user->business_id) {
            throw new \Exception('User must be authenticated and belong to a business');
        }

        return $this->redeemReward($user->id, $user->business_id);
    }
}
