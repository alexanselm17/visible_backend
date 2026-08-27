<?php

namespace Database\Seeders;

use App\Models\RewardMetric;
use App\Models\RewardMetricTier;
use App\Models\RewardPlan;
use App\Models\RewardPlanMetric;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerformanceRewardPlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $existing = RewardPlan::where('name', 'Standard Performance Rewards')
                ->where('version', 1)
                ->first();

            if ($existing) {
                return;
            }

            $plan = RewardPlan::create([
                'name' => 'Standard Performance Rewards',
                'version' => 1,
                'calculation_method' => RewardPlan::MULTIPLY,
                'monthly_maximum_minor' => 500000,
                'currency' => 'KES',
                'is_active' => ! RewardPlan::where('is_active', true)->exists(),
                'effective_from' => '2026-08-01 00:00:00',
                'settings' => [
                    'description' => 'Views, consistency and qualified conversion multipliers are multiplied.',
                    'closure_grace_hours' => 24,
                ],
            ]);

            $this->addMetric(
                $plan,
                RewardMetric::VIEWS,
                'Verified views',
                1,
                3334,
                ['view_source' => 'second_verified_screenshot'],
                [
                    [50, 500, 1000],
                    [501, 1000, 2000],
                    [1001, 1500, 3000],
                    [1501, 2000, 4000],
                    [2001, 2500, 5000],
                    [2501, 3000, 6000],
                    [3001, 3500, 7000],
                    [3501, 4000, 8000],
                    [4001, 4500, 9000],
                    [4501, null, 10000],
                ]
            );

            $this->addMetric(
                $plan,
                RewardMetric::CONSISTENCY,
                'Advert consistency',
                2,
                3333,
                ['minimum_available_hours' => 18],
                [
                    [0.0001, 10, 1000],
                    [10.0001, 20, 2000],
                    [20.0001, 30, 3000],
                    [30.0001, 40, 4000],
                    [40.0001, 50, 5000],
                    [50.0001, 60, 6000],
                    [60.0001, 70, 7000],
                    [70.0001, 80, 8000],
                    [80.0001, 99.9999, 9000],
                    [100, null, 10000],
                ]
            );

            $this->addMetric(
                $plan,
                RewardMetric::CONVERSION,
                'Qualified referrals',
                3,
                3333,
                ['qualification' => 'referred_user_completed_first_verified_advert'],
                [
                    [1, 1, 1000],
                    [2, 2, 2000],
                    [3, 3, 3000],
                    [4, 4, 4000],
                    [5, 5, 5000],
                    [6, 6, 6000],
                    [7, 7, 7000],
                    [8, 8, 8000],
                    [9, 9, 9000],
                    [10, null, 10000],
                ]
            );
        });
    }

    private function addMetric(
        RewardPlan $plan,
        string $code,
        string $name,
        int $sortOrder,
        int $weightBasisPoints,
        array $settings,
        array $tiers
    ): void {
        $metric = RewardMetric::updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'evaluator_key' => $code,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );
        $planMetric = RewardPlanMetric::create([
            'reward_plan_id' => $plan->id,
            'reward_metric_id' => $metric->id,
            'weight_basis_points' => $weightBasisPoints,
            'minimum_basis_points' => 0,
            'maximum_basis_points' => 10000,
            'settings' => $settings,
        ]);

        foreach ($tiers as $index => [$minimum, $maximum, $multiplier]) {
            RewardMetricTier::create([
                'reward_plan_metric_id' => $planMetric->id,
                'minimum_value' => $minimum,
                'maximum_value' => $maximum,
                'multiplier_basis_points' => $multiplier,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
