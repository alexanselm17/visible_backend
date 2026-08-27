<?php

namespace App\Services;

use App\Models\AdvertImages;
use App\Models\RewardMetric;
use App\Models\RewardPeriod;
use App\Models\RewardPlanMetric;
use App\Models\RewardReferralQualification;
use App\Models\Screenshots;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class RewardMetricEvaluationService
{
    public function evaluate(RewardPeriod $period, RewardPlanMetric $planMetric): array
    {
        $evaluatorKey = $planMetric->metric->evaluator_key;

        return match ($evaluatorKey) {
            RewardMetric::VIEWS => $this->views($period),
            RewardMetric::CONSISTENCY => $this->consistency($period, $planMetric),
            RewardMetric::CONVERSION => $this->conversion($period),
            default => throw new RuntimeException("Unknown reward metric evaluator: {$evaluatorKey}."),
        };
    }

    private function views(RewardPeriod $period): array
    {
        $completed = Screenshots::query()
            ->selectRaw('advert_id, MAX(views) as views')
            ->where('processed_by', $period->user_id)
            ->where('number', 2)
            ->whereBetween('created_at', [$period->starts_at, $period->ends_at])
            ->groupBy('advert_id')
            ->get();

        return [
            'value' => (float) $completed->sum('views'),
            'evidence' => [
                'completed_adverts' => $completed->pluck('advert_id')->unique()->count(),
                'view_source' => 'second_verified_screenshot_only',
            ],
        ];
    }

    private function consistency(RewardPeriod $period, RewardPlanMetric $planMetric): array
    {
        $minimumAvailableHours = (int) data_get($planMetric->settings, 'minimum_available_hours', 18);
        $user = User::findOrFail($period->user_id);
        $eligibleStart = Carbon::parse($period->starts_at);

        if ($user->created_at && $user->created_at->greaterThan($eligibleStart)) {
            $eligibleStart = $user->created_at;
        }

        $latestEligiblePublication = Carbon::parse($period->ends_at)->subHours($minimumAvailableHours);
        $eligibleAdvertIds = AdvertImages::query()
            ->whereBetween('created_at', [$eligibleStart, $latestEligiblePublication])
            ->pluck('id');

        $eligibleCount = $eligibleAdvertIds->count();
        $completedCount = $eligibleCount === 0
            ? 0
            : Screenshots::query()
                ->where('processed_by', $period->user_id)
                ->where('number', 2)
                ->whereIn('advert_id', $eligibleAdvertIds)
                ->whereBetween('created_at', [$period->starts_at, $period->ends_at])
                ->distinct()
                ->count('advert_id');

        $percentage = $eligibleCount > 0
            ? round(($completedCount / $eligibleCount) * 100, 4)
            : 0.0;

        return [
            'value' => $percentage,
            'evidence' => [
                'eligible_adverts' => $eligibleCount,
                'completed_adverts' => $completedCount,
                'minimum_available_hours' => $minimumAvailableHours,
                'definition' => 'second_verified_screenshot_submitted_for_every_eligible_advert',
            ],
        ];
    }

    private function conversion(RewardPeriod $period): array
    {
        $qualified = RewardReferralQualification::query()
            ->where('referrer_user_id', $period->user_id)
            ->whereBetween('qualified_at', [$period->starts_at, $period->ends_at])
            ->count();

        return [
            'value' => (float) $qualified,
            'evidence' => [
                'qualified_referrals' => $qualified,
                'qualification' => 'referred_user_completed_first_verified_advert',
            ],
        ];
    }
}
