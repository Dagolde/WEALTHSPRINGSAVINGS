<?php

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use App\Models\Contribution;
use App\Models\GroupMember;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PayoutService
{
    /**
     * Verify if a user is eligible to receive payout for a given day.
     *
     * @param Group $group The group for which to verify payout eligibility
     * @param User $user The user to verify eligibility for
     * @param Carbon|string $payoutDay The payout day to verify
     * @return bool True if eligible, throws exception if not
     * @throws \Exception If any eligibility condition is not met
     */
    public function verifyPayoutEligibility(Group $group, User $user, Carbon|string $payoutDay): bool
    {
        // Convert string to Carbon if needed
        if (is_string($payoutDay)) {
            $payoutDay = Carbon::parse($payoutDay);
        }

        // Check 1: Group must be in 'active' status
        if (!$group->isActive()) {
            throw new \Exception(
                "Group is not active. Current status: {$group->status}"
            );
        }

        // Check 2: User must be a member of the group
        $groupMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$groupMember) {
            throw new \Exception(
                "User is not a member of this group"
            );
        }

        // Check 3: User must not have already received payout for this day
        if ($groupMember->has_received_payout) {
            throw new \Exception(
                "User has already received payout for this group"
            );
        }

        // Check 4: All group members must have contributed on the payout day
        $totalMembers = $group->total_members;
        $successfulContributions = Contribution::where('group_id', $group->id)
            ->whereDate('contribution_date', $payoutDay)
            ->where('payment_status', 'successful')
            ->distinct('user_id')
            ->count('user_id');

        if ($successfulContributions < $totalMembers) {
            throw new \Exception(
                "Not all group members have contributed for this day. " .
                "Contributions received: {$successfulContributions}/{$totalMembers}"
            );
        }

        Log::info('Payout eligibility verified', [
            'group_id' => $group->id,
            'user_id' => $user->id,
            'payout_day' => $payoutDay->toDateString(),
        ]);

        return true;
    }

    /**
     * Get list of group members eligible for payout on a given day.
     *
     * @param Group $group The group to check
     * @param Carbon|string $payoutDay The payout day to check
     * @return \Illuminate\Database\Eloquent\Collection Collection of eligible GroupMember records
     */
    public function getPayoutEligibleMembers(Group $group, Carbon|string $payoutDay)
    {
        // Convert string to Carbon if needed
        if (is_string($payoutDay)) {
            $payoutDay = Carbon::parse($payoutDay);
        }

        // Get all group members who haven't received payout yet
        $eligibleMembers = GroupMember::where('group_id', $group->id)
            ->where('has_received_payout', false)
            ->get();

        // Filter to only those whose groups have all contributions for the day
        $totalMembers = $group->total_members;
        $successfulContributions = Contribution::where('group_id', $group->id)
            ->whereDate('contribution_date', $payoutDay)
            ->where('payment_status', 'successful')
            ->distinct('user_id')
            ->count('user_id');

        // If not all members have contributed, return empty collection
        if ($successfulContributions < $totalMembers) {
            return collect();
        }

        // Return members who are eligible (haven't received payout and group is active)
        if ($group->isActive()) {
            return $eligibleMembers;
        }

        return collect();
    }

    /**
     * Calculate the daily payout amount for a group.
     *
     * The payout amount is calculated as: contribution_amount × total_members
     *
     * @param Group $group The group for which to calculate payout
     * @return float The calculated payout amount with 2 decimal places
     * @throws \Exception If group has invalid contribution_amount or total_members
     */
    public function calculateDailyPayout(Group $group): float
    {
        // Validate group has valid contribution_amount
        if (!$group->contribution_amount || $group->contribution_amount <= 0) {
            throw new \Exception(
                "Group has invalid contribution amount: {$group->contribution_amount}"
            );
        }

        // Validate group has valid total_members
        if (!$group->total_members || $group->total_members <= 0) {
            throw new \Exception(
                "Group has invalid total members: {$group->total_members}"
            );
        }

        // Calculate payout amount: contribution_amount × total_members
        $payoutAmount = $group->contribution_amount * $group->total_members;

        // Return with 2 decimal places
        return round($payoutAmount, 2);
    }

    /**
     * Process a payout for a user in a group.
     *
     * This method:
     * 1. Verifies payout eligibility
     * 2. Calculates payout amount
     * 3. Creates Payout record
     * 4. Credits user's wallet
     * 5. Updates GroupMember.has_received_payout to true
     * 6. Updates payout status to 'successful'
     * 7. Handles failures gracefully
     * 8. Uses database transactions for atomicity
     *
     * @param \App\Models\Payout $payout The payout record to process
     * @param Group $group The group for which to process payout
     * @param User $user The user to receive the payout
     * @return \App\Models\Payout The created/updated Payout record
     * @throws \Exception If payout processing fails
     */
    public function processPayout(\App\Models\Payout $payout, Group $group, User $user): \App\Models\Payout
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($payout, $group, $user) {
            try {
                // Step 1: Verify payout eligibility
                $this->verifyPayoutEligibility($group, $user, $payout->payout_date);

                // Step 2: Calculate payout amount
                $payoutAmount = $this->calculateDailyPayout($group);

                // Step 3: Update payout record with calculated amount
                $payout->update([
                    'amount' => $payoutAmount,
                    'status' => 'processing',
                ]);

                // Step 4: Credit user's wallet using WalletService
                $walletService = new WalletService();
                $walletService->creditWallet(
                    $user,
                    $payoutAmount,
                    "Payout from group: {$group->name}",
                    [
                        'group_id' => $group->id,
                        'payout_id' => $payout->id,
                    ]
                );

                // Step 5: Update GroupMember.has_received_payout to true
                $groupMember = GroupMember::where('group_id', $group->id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (!$groupMember) {
                    throw new \Exception('Group member not found');
                }

                $groupMember->update([
                    'has_received_payout' => true,
                    'payout_received_at' => now(),
                ]);

                // Step 6: Update payout status to 'successful'
                $payout->markAsSuccessful();

                Log::info('Payout processed successfully', [
                    'payout_id' => $payout->id,
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'amount' => $payoutAmount,
                ]);

                return $payout->fresh();

            } catch (\Exception $e) {
                // Step 7: Handle failures gracefully - mark payout as failed with reason
                $payout->markAsFailed($e->getMessage());

                Log::error('Payout processing failed', [
                    'payout_id' => $payout->id,
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Retry a failed payout.
     *
     * This method:
     * 1. Checks that payout status is 'failed'
     * 2. Resets payout to 'pending' status
     * 3. Clears failure_reason
     * 4. Clears processed_at
     * 5. Resets GroupMember.has_received_payout to false
     * 6. Clears GroupMember.payout_received_at
     * 7. Returns updated payout
     *
     * @param \App\Models\Payout $payout The failed payout to retry
     * @return \App\Models\Payout The updated payout record
     * @throws \Exception If payout is not in failed status
     */
    public function retryFailedPayout(\App\Models\Payout $payout): \App\Models\Payout
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($payout) {
            // Check 1: Verify payout status is 'failed'
            if ($payout->status !== 'failed') {
                throw new \Exception(
                    "Payout cannot be retried. Current status: {$payout->status}. Only failed payouts can be retried."
                );
            }

            // Step 2: Reset payout to 'pending' status
            $payout->update([
                'status' => 'pending',
                'failure_reason' => null,
                'processed_at' => null,
            ]);

            // Step 3: Reset GroupMember.has_received_payout to false
            $groupMember = GroupMember::where('group_id', $payout->group_id)
                ->where('user_id', $payout->user_id)
                ->lockForUpdate()
                ->first();

            if ($groupMember) {
                $groupMember->update([
                    'has_received_payout' => false,
                    'payout_received_at' => null,
                ]);
            }

            Log::info('Payout retry initiated', [
                'payout_id' => $payout->id,
                'group_id' => $payout->group_id,
                'user_id' => $payout->user_id,
            ]);

            return $payout->fresh();
        });
    }
}
