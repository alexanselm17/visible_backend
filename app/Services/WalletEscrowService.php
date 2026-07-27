<?php

namespace App\Services;

use App\Models\CreditHold;
use App\Models\CreditPlan;
use App\Models\Wallet;
use Exception;
use Illuminate\Support\Facades\DB;

class WalletEscrowService
{
    /**
     * Lock credits when a campaign starts.
     */
    public function lockCreditsForCampaign(string $userId, string $campaignId, float $amountToLock): CreditHold
    {
        return DB::transaction(function () use ($userId, $campaignId, $amountToLock) {
            // lockForUpdate() prevents any other process from modifying this wallet until this transaction finishes
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $amountToLock) {
                throw new Exception('Insufficient available balance to launch this campaign.');
            }

            // Move balance to locked
            $wallet->balance -= $amountToLock;
            $wallet->locked_balance += $amountToLock;
            $wallet->save();

            // Create the Hold record
            return CreditHold::create([
                'wallet_id' => $wallet->id,
                'advert_submission_id' => $campaignId,
                'amount_locked' => $amountToLock,
                'status' => 'ACTIVE'
            ]);
        });
    }

    /**
     * Settle credits when a campaign finishes (full or partial completion).
     */
    public function settleCampaignHold(string $holdId, float $actualAmountSpent)
    {
        return DB::transaction(function () use ($holdId, $actualAmountSpent) {
            $hold = CreditHold::lockForUpdate()->findOrFail($holdId);
            $wallet = Wallet::where('id', $hold->wallet_id)->lockForUpdate()->firstOrFail();

            if ($hold->status !== 'ACTIVE') {
                throw new Exception('This hold has already been processed.');
            }

            $amountToRelease = $hold->amount_locked - $actualAmountSpent;

            // Remove the total locked amount from the wallet's escrow pool
            $wallet->locked_balance -= $hold->amount_locked;

            // If there's leftover credit, refund it to the available balance
            if ($amountToRelease > 0) {
                $wallet->balance += $amountToRelease;
                $hold->status = 'PARTIALLY_SETTLED';
            } else {
                $hold->status = 'SETTLED';
            }

            $wallet->save();

            // Record the actual spend in the immutable ledger
            if ($actualAmountSpent > 0) {
                $wallet->transactions()->create([
                    'type' => 'spend',
                    'amount' => $actualAmountSpent,
                    'description' => "Campaign completion deduction",
                    'reference_id' => $hold->campaign_id,
                    'reference_type' => 'App\Models\Campaign', // Adjust to your actual Campaign model namespace
                ]);
            }

            // Update the hold record
            $hold->amount_spent = $actualAmountSpent;
            $hold->amount_released = $amountToRelease;
            $hold->save();

            return $hold;
        });
    }


    public function fundWalletFromPlan(string $userId, string $planId, string $paymentReference)
    {
        return DB::transaction(function () use ($userId, $planId, $paymentReference) {
            $plan = CreditPlan::where('is_active', true)->findOrFail($planId);

            // Lock the wallet for updating
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            $totalCreditsToAdd = $plan->total_credits;

            // 1. Update the available balance
            $wallet->balance += $totalCreditsToAdd;
            $wallet->save();

            // 2. Log the immutable transaction
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
}
