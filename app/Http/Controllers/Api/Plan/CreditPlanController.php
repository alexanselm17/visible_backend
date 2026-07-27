<?php

namespace App\Http\Controllers\Api\Plan;

use App\Http\Controllers\Controller;
use App\Models\CreditPlan;
use Illuminate\Http\JsonResponse;

class CreditPlanController extends Controller
{
    /**
     * Retrieve all active credit plans for purchase.
     */
    public function index(): JsonResponse
    {
        try {
            $plans = CreditPlan::where('is_active', true)
                ->orderBy('price', 'asc')
                ->get();

            $plans->map(function ($plan) {
                $plan->total_credits = $plan->base_credits + $plan->bonus_credits;
                return $plan;
            });

            return response()->json([
                'ok' => true,
                'message' => 'Credit plans retrieved successfully.',
                'data' => [
                    'plans' => $plans
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to retrieve credit plans.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
