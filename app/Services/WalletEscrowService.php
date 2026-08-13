<?php

namespace App\Services;

use App\Models\AdvertSubmission;
use App\Models\CreditHold;
use App\Models\CreditPlan;
use App\Models\Wallet;
use Exception;
use Illuminate\Support\Facades\DB;

class WalletEscrowService
{
    /**
     * Lock campaign credits when a submission starts.
     */
    public function lockCreditsForCampaign(string $userId, string $submissionId, float $amountToLock): CreditHold
    {
        return DB::transaction(function () use ($userId, $submissionId, $amountToLock) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $amountToLock) {
                throw new Exception('Insufficient available campaign credit balance to launch this advert.');
            }

            $wallet->balance -= $amountToLock;
            $wallet->locked_balance += $amountToLock;
            $wallet->save();

            return CreditHold::create([
                'wallet_id' => $wallet->id,
                'advert_submission_id' => $submissionId,
                'amount_locked' => $amountToLock,
                'status' => 'ACTIVE',
            ]);
        });
    }

    /**
     * Settle a specific credit hold when a campaign finishes.
     */
    public function settleCampaignHold(string $holdId, float $actualAmountSpent): CreditHold
    {
        return DB::transaction(function () use ($holdId, $actualAmountSpent) {
            $hold = CreditHold::lockForUpdate()->findOrFail($holdId);

            return $this->settleLockedHold($hold, $actualAmountSpent);
        });
    }

    /**
     * Settle the active campaign-credit hold for a submission.
     */
    public function settleCampaignHoldBySubmission(string $submissionId, float $actualAmountSpent): ?CreditHold
    {
        return DB::transaction(function () use ($submissionId, $actualAmountSpent) {
            $hold = CreditHold::where('advert_submission_id', $submissionId)
                ->lockForUpdate()
                ->first();

            if (!$hold) {
                return null;
            }

            if ($hold->status !== 'ACTIVE') {
                return $hold;
            }

            return $this->settleLockedHold($hold, $actualAmountSpent);
        });
    }

    /**
     * Release campaign credits when a submission is rejected/cancelled before publication.
     */
    public function cancelCampaignHoldBySubmission(string $submissionId, string $reason = 'Advert submission cancelled'): ?CreditHold
    {
        return DB::transaction(function () use ($submissionId, $reason) {
            $hold = CreditHold::where('advert_submission_id', $submissionId)
                ->lockForUpdate()
                ->first();

            if (!$hold || $hold->status !== 'ACTIVE') {
                return $hold;
            }

            $wallet = Wallet::whereKey($hold->wallet_id)->lockForUpdate()->firstOrFail();
            $amount = (float) $hold->amount_locked;

            $wallet->locked_balance -= $amount;
            $wallet->balance += $amount;
            $wallet->save();

            $wallet->transactions()->create([
                'type' => 'refund',
                'amount' => $amount,
                'description' => $reason,
                'reference_id' => $hold->advert_submission_id,
                'reference_type' => AdvertSubmission::class,
            ]);

            $hold->amount_released = $amount;
            $hold->status = 'CANCELLED';
            $hold->save();

            return $hold;
        });
    }

    public function fundWalletFromPlan(string $userId, string $planId, string $paymentReference)
    {
        return DB::transaction(function () use ($userId, $planId, $paymentReference) {
            $plan = CreditPlan::where('is_active', true)->findOrFail($planId);

            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            $totalCreditsToAdd = $plan->total_credits;
            $wallet->balance += $totalCreditsToAdd;
            $wallet->save();

            $wallet->transactions()->create([
                'type' => 'deposit',
                'amount' => $totalCreditsToAdd,
                'description' => "Purchased {$plan->name} ({$plan->base_credits} base + {$plan->bonus_credits} bonus)",
                'reference_id' => $paymentReference,
                'reference_type' => 'payment_gateway_receipt',
            ]);

            return $wallet;
        });
    }

    private function settleLockedHold(CreditHold $hold, float $actualAmountSpent): CreditHold
    {
        if ($hold->status !== 'ACTIVE') {
            throw new Exception('This campaign credit hold has already been processed.');
        }

        $locked = (float) $hold->amount_locked;
        if ($actualAmountSpent < 0 || $actualAmountSpent > $locked) {
            throw new Exception('Actual campaign credit spend must be between zero and the locked amount.');
        }

        $wallet = Wallet::whereKey($hold->wallet_id)->lockForUpdate()->firstOrFail();
        $amountToRelease = $locked - $actualAmountSpent;

        $wallet->locked_balance -= $locked;

        if ($amountToRelease > 0) {
            $wallet->balance += $amountToRelease;
        }

        $wallet->save();

        if ($actualAmountSpent > 0) {
            $wallet->transactions()->create([
                'type' => 'spend',
                'amount' => $actualAmountSpent,
                'description' => 'Campaign completion deduction',
                'reference_id' => $hold->advert_submission_id,
                'reference_type' => AdvertSubmission::class,
            ]);
        }

        if ($amountToRelease > 0) {
            $wallet->transactions()->create([
                'type' => 'refund',
                'amount' => $amountToRelease,
                'description' => 'Unused campaign credits released after settlement',
                'reference_id' => $hold->advert_submission_id,
                'reference_type' => AdvertSubmission::class,
            ]);
        }

        $hold->amount_spent = $actualAmountSpent;
        $hold->amount_released = $amountToRelease;
        $hold->status = $amountToRelease > 0 ? 'PARTIALLY_SETTLED' : 'SETTLED';
        $hold->save();

        return $hold;
    }
}
