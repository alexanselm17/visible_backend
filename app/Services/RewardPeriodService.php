<?php

namespace App\Services;

use App\Models\AdvertImages;
use App\Models\RewardLedgerEntry;
use App\Models\RewardPayout;
use App\Models\RewardPayoutPreference;
use App\Models\RewardPeriod;
use App\Models\RewardPeriodMetric;
use App\Models\RewardPlan;
use App\Models\RewardReferralQualification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RewardPeriodService
{
    public function __construct(
        private readonly RewardCalculationService $calculator,
        private readonly RewardMetricEvaluationService $metricEvaluator
    ) {}

    public function activePlan(?Carbon $at = null): ?RewardPlan
    {
        $at ??= Carbon::now(config('rewards.timezone'));

        return RewardPlan::query()
            ->where('is_active', true)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at) {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', $at);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();
    }

    public function recordAdvertCompletion(string $userId, AdvertImages $advert): bool
    {
        $now = Carbon::now(config('rewards.timezone'));
        $plan = $this->activePlan($now);

        if (! $plan) {
            throw ValidationException::withMessages([
                'reward_plan' => 'No active performance reward plan is configured.',
            ]);
        }

        $this->qualifyReferral($userId, $advert->id, $now);
        $period = $this->currentOrCreate($userId, $now, $plan);
        $this->calculate($period);

        return true;
    }

    public function currentOrCreate(
        string $userId,
        ?Carbon $at = null,
        ?RewardPlan $plan = null
    ): RewardPeriod {
        $at ??= Carbon::now(config('rewards.timezone'));
        $plan ??= $this->activePlan($at);

        if (! $plan) {
            throw ValidationException::withMessages([
                'reward_plan' => 'No active performance reward plan is configured.',
            ]);
        }

        $frequency = $this->frequencyFor($userId, $at);
        [$startsAt, $endsAt] = $this->periodBounds($at, $frequency);

        return RewardPeriod::firstOrCreate(
            [
                'user_id' => $userId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ],
            [
                'reward_plan_id' => $plan->id,
                'frequency' => $frequency,
                'maximum_amount_minor' => $this->periodMaximum($plan, $startsAt, $endsAt, $frequency),
                'currency' => $plan->currency,
                'calculation_method' => $plan->calculation_method,
                'status' => RewardPeriod::OPEN,
            ]
        );
    }

    public function calculate(RewardPeriod $period): RewardPeriod
    {
        return DB::transaction(function () use ($period) {
            $lockedPeriod = RewardPeriod::lockForUpdate()->findOrFail($period->id);

            if ($lockedPeriod->status !== RewardPeriod::OPEN) {
                return $lockedPeriod->load(['metrics.metric', 'plan']);
            }

            $plan = RewardPlan::with(['planMetrics.metric', 'planMetrics.tiers'])
                ->findOrFail($lockedPeriod->reward_plan_id);
            $calculatedMetrics = [];
            $snapshotMetrics = [];

            foreach ($plan->planMetrics->filter(fn ($item) => $item->metric?->is_active) as $planMetric) {
                $measurement = $this->metricEvaluator->evaluate($lockedPeriod, $planMetric);
                $multiplier = $this->calculator->multiplierFor($planMetric, (float) $measurement['value']);

                RewardPeriodMetric::updateOrCreate(
                    [
                        'reward_period_id' => $lockedPeriod->id,
                        'reward_metric_id' => $planMetric->reward_metric_id,
                    ],
                    [
                        'measured_value' => $measurement['value'],
                        'multiplier_basis_points' => $multiplier,
                        'evidence' => $measurement['evidence'],
                    ]
                );

                $calculatedMetrics[] = [
                    'code' => $planMetric->metric->code,
                    'multiplier_basis_points' => $multiplier,
                    'weight_basis_points' => (int) $planMetric->weight_basis_points,
                ];
                $snapshotMetrics[] = [
                    'code' => $planMetric->metric->code,
                    'name' => $planMetric->metric->name,
                    'measured_value' => (float) $measurement['value'],
                    'multiplier_basis_points' => $multiplier,
                    'multiplier' => $this->basisPointsToDecimal($multiplier),
                    'evidence' => $measurement['evidence'],
                ];
            }

            $result = $this->calculator->calculate(
                (int) $lockedPeriod->maximum_amount_minor,
                $lockedPeriod->calculation_method,
                $calculatedMetrics
            );

            $lockedPeriod->combined_multiplier_basis_points = $result['combined_multiplier_basis_points'];
            $lockedPeriod->calculated_amount_minor = $result['amount_minor'];
            $lockedPeriod->calculation_snapshot = [
                'reward_plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'version' => $plan->version,
                    'calculation_method' => $plan->calculation_method,
                ],
                'maximum_amount_minor' => (int) $lockedPeriod->maximum_amount_minor,
                'metrics' => $snapshotMetrics,
                'combined_multiplier_basis_points' => $result['combined_multiplier_basis_points'],
                'calculated_amount_minor' => $result['amount_minor'],
            ];
            $lockedPeriod->calculated_at = now();
            $lockedPeriod->save();

            return $lockedPeriod->fresh(['metrics.metric', 'plan']);
        });
    }

    public function lock(RewardPeriod $period): RewardPeriod
    {
        $this->calculate($period);

        return DB::transaction(function () use ($period) {
            $lockedPeriod = RewardPeriod::lockForUpdate()->findOrFail($period->id);

            if ($lockedPeriod->status !== RewardPeriod::OPEN) {
                return $lockedPeriod->load(['metrics.metric', 'payout', 'plan']);
            }

            $lockedPeriod->locked_at = now();

            if ((int) $lockedPeriod->calculated_amount_minor === 0) {
                $lockedPeriod->status = RewardPeriod::PAID;
                $lockedPeriod->paid_at = now();
                $lockedPeriod->save();

                return $lockedPeriod->fresh(['metrics.metric', 'payout', 'plan']);
            }

            RewardLedgerEntry::firstOrCreate(
                ['idempotency_key' => "reward-period:{$lockedPeriod->id}:earning"],
                [
                    'user_id' => $lockedPeriod->user_id,
                    'reward_period_id' => $lockedPeriod->id,
                    'type' => RewardLedgerEntry::EARNING,
                    'amount_minor' => (int) $lockedPeriod->calculated_amount_minor,
                    'currency' => $lockedPeriod->currency,
                    'description' => 'Locked performance reward earning.',
                    'metadata' => $lockedPeriod->calculation_snapshot,
                ]
            );

            RewardPayout::firstOrCreate(
                ['reward_period_id' => $lockedPeriod->id],
                [
                    'user_id' => $lockedPeriod->user_id,
                    'amount_minor' => (int) $lockedPeriod->calculated_amount_minor,
                    'currency' => $lockedPeriod->currency,
                    'status' => RewardPayout::PENDING,
                    'idempotency_key' => (string) Str::uuid(),
                ]
            );

            $lockedPeriod->status = RewardPeriod::PAYMENT_PENDING;
            $lockedPeriod->save();

            return $lockedPeriod->fresh(['metrics.metric', 'payout', 'plan']);
        });
    }

    public function closeEndedPeriods(?Carbon $now = null): int
    {
        $now ??= Carbon::now(config('rewards.timezone'));
        $cutoff = $now->copy()->subHours((int) config('rewards.closure_grace_hours', 24));
        $closed = 0;

        do {
            $periods = RewardPeriod::where('status', RewardPeriod::OPEN)
                ->where('ends_at', '<=', $cutoff)
                ->orderBy('ends_at')
                ->limit(100)
                ->get();

            foreach ($periods as $period) {
                $this->lock($period);
                $closed++;
            }
        } while ($periods->isNotEmpty());

        return $closed;
    }

    public function summary(string $userId): array
    {
        $plan = $this->activePlan();
        $current = $plan ? $this->calculate($this->currentOrCreate($userId, null, $plan)) : null;
        $history = RewardPeriod::query()
            ->where('user_id', $userId)
            ->where('id', '!=', $current?->id)
            ->with(['metrics.metric', 'payout', 'plan'])
            ->latest('starts_at')
            ->limit(12)
            ->get();
        $balanceMinor = (int) RewardLedgerEntry::where('user_id', $userId)->sum('amount_minor');

        return [
            'current_period' => $current ? $this->serializePeriod($current) : null,
            'history' => $history->map(fn ($period) => $this->serializePeriod($period))->values(),
            'ledger_balance_minor' => $balanceMinor,
            'ledger_balance' => $this->minorToMoney($balanceMinor),
            'currency' => $current?->currency ?? $plan?->currency ?? 'KES',
            'payout_frequency' => $this->frequencyFor($userId, Carbon::now(config('rewards.timezone'))),
        ];
    }

    public function setPayoutFrequency(string $userId, string $frequency): RewardPayoutPreference
    {
        if (! in_array($frequency, [RewardPayoutPreference::WEEKLY, RewardPayoutPreference::MONTHLY], true)) {
            throw ValidationException::withMessages([
                'frequency' => 'Payout frequency must be weekly or monthly.',
            ]);
        }

        $now = Carbon::now(config('rewards.timezone'));
        $openPeriod = RewardPeriod::where('user_id', $userId)
            ->where('status', RewardPeriod::OPEN)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->first();
        $effectiveFrom = $openPeriod
            ? Carbon::parse($openPeriod->ends_at)->addSecond()
            : $now;

        return RewardPayoutPreference::updateOrCreate(
            ['user_id' => $userId, 'effective_from' => $effectiveFrom],
            ['frequency' => $frequency]
        );
    }

    public function frequencyFor(string $userId, Carbon $at): string
    {
        return RewardPayoutPreference::query()
            ->where('user_id', $userId)
            ->where('effective_from', '<=', $at)
            ->latest('effective_from')
            ->value('frequency')
            ?? config('rewards.default_frequency', RewardPayoutPreference::MONTHLY);
    }

    private function periodBounds(Carbon $at, string $frequency): array
    {
        $local = $at->copy()->timezone(config('rewards.timezone'));

        if ($frequency === RewardPayoutPreference::MONTHLY) {
            return [$local->copy()->startOfMonth(), $local->copy()->endOfMonth()];
        }

        $start = $local->copy()->startOfWeek(Carbon::MONDAY);
        $end = $local->copy()->endOfWeek(Carbon::SUNDAY);

        if ($start->month !== $local->month) {
            $start = $local->copy()->startOfMonth();
        }

        if ($end->month !== $local->month) {
            $end = $local->copy()->endOfMonth();
        }

        return [$start, $end];
    }

    private function periodMaximum(
        RewardPlan $plan,
        Carbon $startsAt,
        Carbon $endsAt,
        string $frequency
    ): int {
        if ($frequency === RewardPayoutPreference::MONTHLY) {
            return (int) $plan->monthly_maximum_minor;
        }

        $daysInMonth = $startsAt->daysInMonth;
        $monthlyMaximum = (int) $plan->monthly_maximum_minor;
        $amountThroughEnd = (int) round(
            ($monthlyMaximum * $endsAt->day) / $daysInMonth,
            0,
            PHP_ROUND_HALF_UP
        );
        $amountBeforeStart = (int) round(
            ($monthlyMaximum * ($startsAt->day - 1)) / $daysInMonth,
            0,
            PHP_ROUND_HALF_UP
        );

        return $amountThroughEnd - $amountBeforeStart;
    }

    private function qualifyReferral(string $referredUserId, string $advertId, Carbon $qualifiedAt): void
    {
        $referredUser = User::find($referredUserId);

        if (! $referredUser || ! $referredUser->referal_code) {
            return;
        }

        $referrer = User::where('my_code', $referredUser->referal_code)
            ->where('id', '!=', $referredUserId)
            ->first();

        if (! $referrer) {
            return;
        }

        RewardReferralQualification::firstOrCreate(
            ['referred_user_id' => $referredUserId],
            [
                'referrer_user_id' => $referrer->id,
                'qualifying_advert_id' => $advertId,
                'qualified_at' => $qualifiedAt,
                'evidence' => ['qualification' => 'second_verified_screenshot'],
            ]
        );
    }

    private function serializePeriod(RewardPeriod $period): array
    {
        $period->loadMissing(['metrics.metric', 'payout', 'plan']);

        return [
            'id' => $period->id,
            'frequency' => $period->frequency,
            'starts_at' => $period->starts_at,
            'ends_at' => $period->ends_at,
            'status' => $period->status,
            'reward_plan' => [
                'name' => $period->plan?->name,
                'version' => $period->plan?->version,
                'calculation_method' => $period->calculation_method,
            ],
            'maximum_amount_minor' => (int) $period->maximum_amount_minor,
            'maximum_amount' => $this->minorToMoney((int) $period->maximum_amount_minor),
            'combined_multiplier' => $this->basisPointsToDecimal((int) $period->combined_multiplier_basis_points),
            'calculated_amount_minor' => (int) $period->calculated_amount_minor,
            'calculated_amount' => $this->minorToMoney((int) $period->calculated_amount_minor),
            'currency' => $period->currency,
            'metrics' => $period->metrics->map(fn ($metric) => [
                'code' => $metric->metric?->code,
                'name' => $metric->metric?->name,
                'measured_value' => (float) $metric->measured_value,
                'multiplier' => $this->basisPointsToDecimal((int) $metric->multiplier_basis_points),
                'evidence' => $metric->evidence,
            ])->values(),
            'payout' => $period->payout ? [
                'id' => $period->payout->id,
                'status' => $period->payout->status,
                'provider' => $period->payout->provider,
                'payment_reference' => $period->payout->payment_reference,
                'paid_at' => $period->payout->paid_at,
            ] : null,
        ];
    }

    private function basisPointsToDecimal(int $basisPoints): string
    {
        return number_format($basisPoints / RewardCalculationService::BASIS_POINTS, 4, '.', '');
    }

    private function minorToMoney(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2, '.', '');
    }
}
