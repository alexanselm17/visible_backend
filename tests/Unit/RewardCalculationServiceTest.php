<?php

namespace Tests\Unit;

use App\Models\RewardMetricTier;
use App\Models\RewardPlan;
use App\Models\RewardPlanMetric;
use App\Services\RewardCalculationService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class RewardCalculationServiceTest extends TestCase
{
    public function test_it_multiplies_every_metric_against_the_period_maximum(): void
    {
        $result = (new RewardCalculationService)->calculate(500000, RewardPlan::MULTIPLY, [
            $this->metric('views', 8000),
            $this->metric('consistency', 9000),
            $this->metric('conversion', 5000),
        ]);

        $this->assertSame(180000, $result['amount_minor']);
        $this->assertSame(3600, $result['combined_multiplier_basis_points']);
    }

    public function test_full_multipliers_pay_the_maximum_and_zero_stops_payment(): void
    {
        $calculator = new RewardCalculationService;

        $full = $calculator->calculate(500000, RewardPlan::MULTIPLY, [
            $this->metric('views', 10000),
            $this->metric('consistency', 10000),
            $this->metric('conversion', 10000),
        ]);
        $zero = $calculator->calculate(500000, RewardPlan::MULTIPLY, [
            $this->metric('views', 10000),
            $this->metric('consistency', 0),
            $this->metric('conversion', 10000),
        ]);

        $this->assertSame(500000, $full['amount_minor']);
        $this->assertSame(0, $zero['amount_minor']);
    }

    public function test_calculation_method_can_be_changed_by_a_future_plan_version(): void
    {
        $result = (new RewardCalculationService)->calculate(500000, RewardPlan::WEIGHTED_AVERAGE, [
            $this->metric('views', 8000, 4000),
            $this->metric('consistency', 6000, 4000),
            $this->metric('conversion', 5000, 2000),
        ]);

        $this->assertSame(330000, $result['amount_minor']);
        $this->assertSame(6600, $result['combined_multiplier_basis_points']);
    }

    public function test_metric_ranges_choose_the_matching_multiplier(): void
    {
        $planMetric = new RewardPlanMetric([
            'minimum_basis_points' => 0,
            'maximum_basis_points' => 10000,
        ]);
        $planMetric->setRelation('tiers', new Collection([
            new RewardMetricTier([
                'minimum_value' => 50,
                'maximum_value' => 500,
                'multiplier_basis_points' => 1000,
            ]),
            new RewardMetricTier([
                'minimum_value' => 501,
                'maximum_value' => 1000,
                'multiplier_basis_points' => 2000,
            ]),
        ]));
        $calculator = new RewardCalculationService;

        $this->assertSame(0, $calculator->multiplierFor($planMetric, 49));
        $this->assertSame(1000, $calculator->multiplierFor($planMetric, 500));
        $this->assertSame(2000, $calculator->multiplierFor($planMetric, 501));
    }

    private function metric(string $code, int $multiplier, int $weight = 10000): array
    {
        return [
            'code' => $code,
            'multiplier_basis_points' => $multiplier,
            'weight_basis_points' => $weight,
        ];
    }
}
