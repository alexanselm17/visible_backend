<?php

namespace App\Http\Controllers\Api\Rewards;

use App\Http\Controllers\Controller;
use App\Models\RewardMetric;
use App\Models\RewardMetricTier;
use App\Models\RewardPayout;
use App\Models\RewardPlan;
use App\Models\RewardPlanMetric;
use App\Services\RewardPayoutService;
use App\Services\RewardPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RewardAdminController extends Controller
{
    public function plans(Request $request): JsonResponse
    {
        $this->ensureRewardAdmin($request);

        return response()->json([
            'ok' => true,
            'data' => RewardPlan::with(['planMetrics.metric', 'planMetrics.tiers'])
                ->orderByDesc('effective_from')
                ->orderByDesc('version')
                ->get(),
        ]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $this->ensureRewardAdmin($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
            'calculation_method' => [
                'required',
                Rule::in([RewardPlan::MULTIPLY, RewardPlan::WEIGHTED_AVERAGE]),
            ],
            'monthly_maximum' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
            'settings' => ['sometimes', 'array'],
            'metrics' => ['required', 'array', 'min:1'],
            'metrics.*.code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'metrics.*.name' => ['required', 'string', 'max:255'],
            'metrics.*.evaluator_key' => ['required', Rule::in([
                RewardMetric::VIEWS,
                RewardMetric::CONSISTENCY,
                RewardMetric::CONVERSION,
            ])],
            'metrics.*.weight' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'metrics.*.minimum_multiplier' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'metrics.*.maximum_multiplier' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'metrics.*.settings' => ['sometimes', 'array'],
            'metrics.*.tiers' => ['required', 'array', 'min:1'],
            'metrics.*.tiers.*.minimum' => ['required', 'numeric', 'min:0'],
            'metrics.*.tiers.*.maximum' => ['nullable', 'numeric', 'gte:metrics.*.tiers.*.minimum'],
            'metrics.*.tiers.*.multiplier' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);
        $this->validateMetricConfiguration($validated['metrics']);

        $plan = DB::transaction(function () use ($validated) {
            $plan = RewardPlan::create([
                'name' => $validated['name'],
                'version' => $validated['version'],
                'calculation_method' => $validated['calculation_method'],
                'monthly_maximum_minor' => $this->moneyToMinor($validated['monthly_maximum']),
                'currency' => strtoupper($validated['currency']),
                'effective_from' => $validated['effective_from'],
                'effective_until' => $validated['effective_until'] ?? null,
                'is_active' => false,
                'settings' => $validated['settings'] ?? null,
            ]);

            foreach ($validated['metrics'] as $metricIndex => $metricData) {
                $metric = RewardMetric::updateOrCreate(
                    ['code' => $metricData['code']],
                    [
                        'name' => $metricData['name'],
                        'evaluator_key' => $metricData['evaluator_key'],
                        'is_active' => true,
                        'sort_order' => $metricIndex + 1,
                    ]
                );
                $planMetric = RewardPlanMetric::create([
                    'reward_plan_id' => $plan->id,
                    'reward_metric_id' => $metric->id,
                    'weight_basis_points' => $this->multiplierToBasisPoints($metricData['weight'] ?? 1),
                    'minimum_basis_points' => $this->multiplierToBasisPoints($metricData['minimum_multiplier'] ?? 0),
                    'maximum_basis_points' => $this->multiplierToBasisPoints($metricData['maximum_multiplier'] ?? 1),
                    'settings' => $metricData['settings'] ?? null,
                ]);

                foreach ($metricData['tiers'] as $tierIndex => $tier) {
                    RewardMetricTier::create([
                        'reward_plan_metric_id' => $planMetric->id,
                        'minimum_value' => $tier['minimum'],
                        'maximum_value' => $tier['maximum'] ?? null,
                        'multiplier_basis_points' => $this->multiplierToBasisPoints($tier['multiplier']),
                        'sort_order' => $tierIndex + 1,
                    ]);
                }
            }

            return $plan;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Reward plan version created. Activate it when it is ready to take effect.',
            'data' => $plan->load(['planMetrics.metric', 'planMetrics.tiers']),
        ], 201);
    }

    public function activatePlan(Request $request, string $planId): JsonResponse
    {
        $this->ensureRewardAdmin($request);

        $plan = DB::transaction(function () use ($planId) {
            $plan = RewardPlan::lockForUpdate()->findOrFail($planId);
            $plan->is_active = true;
            $plan->save();

            return $plan;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Reward plan activated. Existing periods keep their saved plan version.',
            'data' => $plan,
        ]);
    }

    public function closeEndedPeriods(Request $request, RewardPeriodService $rewards): JsonResponse
    {
        $this->ensureRewardAdmin($request);
        $closed = $rewards->closeEndedPeriods();

        return response()->json([
            'ok' => true,
            'message' => "{$closed} ended reward period(s) calculated and locked.",
            'data' => ['closed_periods' => $closed],
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $this->ensureRewardAdmin($request);
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([
                RewardPayout::PENDING,
                RewardPayout::PROCESSING,
                RewardPayout::PAID,
                RewardPayout::FAILED,
            ])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $payouts = RewardPayout::query()
            ->with(['period.metrics.metric'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'ok' => true,
            'data' => $payouts->items(),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    public function confirmPayout(
        Request $request,
        string $payoutId,
        RewardPayoutService $payouts
    ): JsonResponse {
        $this->ensureRewardAdmin($request);
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:100'],
            'payment_reference' => ['required', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $payout = $payouts->confirm(
            $payoutId,
            $validated['provider'],
            $validated['payment_reference'],
            $request->user()->id,
            $validated['metadata'] ?? []
        );

        return response()->json([
            'ok' => true,
            'message' => 'Payout confirmed. The paid period is preserved and the next period starts from zero.',
            'data' => $payout,
        ]);
    }

    public function failPayout(
        Request $request,
        string $payoutId,
        RewardPayoutService $payouts
    ): JsonResponse {
        $this->ensureRewardAdmin($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $payout = $payouts->fail($payoutId, $validated['reason'], $request->user()->id);

        return response()->json([
            'ok' => true,
            'message' => 'Payout marked as failed. Its locked calculation remains available for retry.',
            'data' => $payout,
        ]);
    }

    private function ensureRewardAdmin(Request $request): void
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->isDeveloper())) {
            abort(403, 'You are not authorized to manage reward plans or payouts.');
        }
    }

    private function moneyToMinor(int|float|string $amount): int
    {
        return (int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function multiplierToBasisPoints(int|float|string $multiplier): int
    {
        return (int) round(((float) $multiplier) * 10000, 0, PHP_ROUND_HALF_UP);
    }

    private function validateMetricConfiguration(array $metrics): void
    {
        foreach ($metrics as $metricIndex => $metric) {
            $minimum = (float) ($metric['minimum_multiplier'] ?? 0);
            $maximum = (float) ($metric['maximum_multiplier'] ?? 1);

            if ($minimum > $maximum) {
                throw ValidationException::withMessages([
                    "metrics.{$metricIndex}.minimum_multiplier" => 'The minimum multiplier cannot exceed the maximum multiplier.',
                ]);
            }

            $tiers = collect($metric['tiers'])->sortBy(fn ($tier) => (float) $tier['minimum'])->values();
            $previousMaximum = null;
            $previousMultiplier = -1.0;

            foreach ($tiers as $tierIndex => $tier) {
                $tierMinimum = (float) $tier['minimum'];
                $tierMaximum = array_key_exists('maximum', $tier) && $tier['maximum'] !== null
                    ? (float) $tier['maximum']
                    : null;
                $tierMultiplier = (float) $tier['multiplier'];

                if ($tierIndex > 0 && $previousMaximum === null) {
                    throw ValidationException::withMessages([
                        "metrics.{$metricIndex}.tiers" => 'An open-ended range must be the final tier.',
                    ]);
                }

                if ($previousMaximum !== null && $tierMinimum <= $previousMaximum) {
                    throw ValidationException::withMessages([
                        "metrics.{$metricIndex}.tiers" => 'Metric ranges cannot overlap.',
                    ]);
                }

                if ($tierMultiplier < $previousMultiplier) {
                    throw ValidationException::withMessages([
                        "metrics.{$metricIndex}.tiers" => 'Multiplier values must not decrease as measured performance increases.',
                    ]);
                }

                $previousMaximum = $tierMaximum;
                $previousMultiplier = $tierMultiplier;
            }
        }
    }
}
