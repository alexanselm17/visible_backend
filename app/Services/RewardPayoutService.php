<?php

namespace App\Services;

use App\Models\RewardLedgerEntry;
use App\Models\RewardPayout;
use App\Models\RewardPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RewardPayoutService
{
    public function confirm(
        string $payoutId,
        string $provider,
        string $paymentReference,
        string $processedBy,
        array $metadata = []
    ): RewardPayout {
        return DB::transaction(function () use (
            $payoutId,
            $provider,
            $paymentReference,
            $processedBy,
            $metadata
        ) {
            $payout = RewardPayout::lockForUpdate()->findOrFail($payoutId);
            $period = RewardPeriod::lockForUpdate()->findOrFail($payout->reward_period_id);
            User::whereKey($payout->user_id)->lockForUpdate()->firstOrFail();

            if ($payout->user_id === $processedBy) {
                throw ValidationException::withMessages([
                    'payout' => 'A user cannot confirm their own performance reward payout.',
                ]);
            }

            if ($payout->status === RewardPayout::PAID) {
                if ($payout->payment_reference !== $paymentReference) {
                    throw ValidationException::withMessages([
                        'payment_reference' => 'This payout was already paid with another reference.',
                    ]);
                }

                return $payout->load('period');
            }

            if (! in_array($payout->status, [RewardPayout::PENDING, RewardPayout::FAILED, RewardPayout::PROCESSING], true)) {
                throw ValidationException::withMessages([
                    'payout' => "Payout status {$payout->status} cannot be confirmed.",
                ]);
            }

            if (RewardPayout::where('payment_reference', $paymentReference)
                ->where('id', '!=', $payout->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'payment_reference' => 'This payment reference has already been used.',
                ]);
            }

            $this->guardMonthlyMaximum($payout, $period);

            $payout->status = RewardPayout::PAID;
            $payout->provider = $provider;
            $payout->payment_reference = $paymentReference;
            $payout->processed_by = $processedBy;
            $payout->paid_at = now();
            $payout->failed_at = null;
            $payout->failure_reason = null;
            $payout->metadata = $metadata;
            $payout->save();

            RewardLedgerEntry::firstOrCreate(
                ['idempotency_key' => "reward-payout:{$payout->id}:payment"],
                [
                    'user_id' => $payout->user_id,
                    'reward_period_id' => $period->id,
                    'reward_payout_id' => $payout->id,
                    'type' => RewardLedgerEntry::PAYMENT,
                    'amount_minor' => -((int) $payout->amount_minor),
                    'currency' => $payout->currency,
                    'description' => "Performance reward paid through {$provider}.",
                    'metadata' => ['payment_reference' => $paymentReference],
                ]
            );

            $period->status = RewardPeriod::PAID;
            $period->paid_at = $payout->paid_at;
            $period->save();

            return $payout->fresh('period');
        });
    }

    public function fail(string $payoutId, string $reason, string $processedBy): RewardPayout
    {
        return DB::transaction(function () use ($payoutId, $reason, $processedBy) {
            $payout = RewardPayout::lockForUpdate()->findOrFail($payoutId);

            if ($payout->status === RewardPayout::PAID) {
                throw ValidationException::withMessages([
                    'payout' => 'A paid payout cannot be marked as failed.',
                ]);
            }

            $payout->status = RewardPayout::FAILED;
            $payout->failure_reason = $reason;
            $payout->processed_by = $processedBy;
            $payout->failed_at = now();
            $payout->save();

            return $payout->fresh('period');
        });
    }

    private function guardMonthlyMaximum(RewardPayout $payout, RewardPeriod $period): void
    {
        $month = Carbon::parse($period->starts_at)->timezone(config('rewards.timezone'));
        $paidThisMonth = (int) RewardPayout::query()
            ->join('reward_periods', 'reward_payouts.reward_period_id', '=', 'reward_periods.id')
            ->where('reward_payouts.user_id', $payout->user_id)
            ->where('reward_payouts.status', RewardPayout::PAID)
            ->where('reward_payouts.id', '!=', $payout->id)
            ->whereBetween('reward_periods.starts_at', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ])
            ->sum('reward_payouts.amount_minor');

        $monthlyMaximum = (int) $period->plan()->value('monthly_maximum_minor');

        if ($paidThisMonth + (int) $payout->amount_minor > $monthlyMaximum) {
            throw ValidationException::withMessages([
                'payout' => 'This payment would exceed the user monthly reward maximum.',
            ]);
        }
    }
}
