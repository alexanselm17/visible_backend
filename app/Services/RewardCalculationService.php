<?php

namespace App\Services;

use App\Models\RewardPlan;
use App\Models\RewardPlanMetric;
use Illuminate\Validation\ValidationException;

class RewardCalculationService
{
    public const BASIS_POINTS = 10000;

    public function multiplierFor(RewardPlanMetric $planMetric, float $measuredValue): int
    {
        $tier = $planMetric->tiers
            ->sortByDesc(fn ($candidate) => (float) $candidate->minimum_value)
            ->first(function ($candidate) use ($measuredValue) {
                return $measuredValue >= (float) $candidate->minimum_value
                    && ($candidate->maximum_value === null
                        || $measuredValue <= (float) $candidate->maximum_value);
            });

        $multiplier = $tier ? (int) $tier->multiplier_basis_points : 0;

        return max(
            (int) $planMetric->minimum_basis_points,
            min((int) $planMetric->maximum_basis_points, $multiplier)
        );
    }

    /**
     * @param  array<int, array{code: string, multiplier_basis_points: int, weight_basis_points: int}>  $metrics
     */
    public function calculate(int $maximumAmountMinor, string $method, array $metrics): array
    {
        if ($maximumAmountMinor < 0) {
            throw ValidationException::withMessages([
                'maximum_amount' => 'The maximum reward cannot be negative.',
            ]);
        }

        if ($metrics === []) {
            return [
                'amount_minor' => 0,
                'combined_multiplier_basis_points' => 0,
            ];
        }

        return match ($method) {
            RewardPlan::MULTIPLY => $this->multiply($maximumAmountMinor, $metrics),
            RewardPlan::WEIGHTED_AVERAGE => $this->weightedAverage($maximumAmountMinor, $metrics),
            default => throw ValidationException::withMessages([
                'calculation_method' => "Unsupported reward calculation method: {$method}.",
            ]),
        };
    }

    private function multiply(int $maximumAmountMinor, array $metrics): array
    {
        $amount = $maximumAmountMinor;
        $combined = self::BASIS_POINTS;

        foreach ($metrics as $metric) {
            $basisPoints = max(0, min(self::BASIS_POINTS, (int) $metric['multiplier_basis_points']));
            $amount = $this->applyBasisPoints($amount, $basisPoints);
            $combined = $this->applyBasisPoints($combined, $basisPoints);
        }

        return [
            'amount_minor' => min($maximumAmountMinor, $amount),
            'combined_multiplier_basis_points' => min(self::BASIS_POINTS, $combined),
        ];
    }

    private function weightedAverage(int $maximumAmountMinor, array $metrics): array
    {
        $totalWeight = array_sum(array_map(
            fn ($metric) => max(0, (int) $metric['weight_basis_points']),
            $metrics
        ));

        if ($totalWeight < 1) {
            throw ValidationException::withMessages([
                'metrics' => 'Weighted-average reward plans must have a positive total weight.',
            ]);
        }

        $weightedTotal = array_sum(array_map(
            fn ($metric) => max(0, min(self::BASIS_POINTS, (int) $metric['multiplier_basis_points']))
                * max(0, (int) $metric['weight_basis_points']),
            $metrics
        ));

        $combined = intdiv($weightedTotal + intdiv($totalWeight, 2), $totalWeight);

        return [
            'amount_minor' => $this->applyBasisPoints($maximumAmountMinor, $combined),
            'combined_multiplier_basis_points' => $combined,
        ];
    }

    private function applyBasisPoints(int $amount, int $basisPoints): int
    {
        return intdiv(($amount * $basisPoints) + intdiv(self::BASIS_POINTS, 2), self::BASIS_POINTS);
    }
}
