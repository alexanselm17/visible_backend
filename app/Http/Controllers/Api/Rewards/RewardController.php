<?php

namespace App\Http\Controllers\Api\Rewards;

use App\Http\Controllers\Controller;
use App\Services\RewardPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardController extends Controller
{
    public function summary(Request $request, RewardPeriodService $rewards): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $rewards->summary($request->user()->id),
        ]);
    }

    public function updatePayoutFrequency(Request $request, RewardPeriodService $rewards): JsonResponse
    {
        $validated = $request->validate([
            'frequency' => ['required', 'string', 'in:weekly,monthly'],
        ]);
        $preference = $rewards->setPayoutFrequency($request->user()->id, $validated['frequency']);

        return response()->json([
            'ok' => true,
            'message' => 'Payout frequency saved. An open reward period keeps its original frequency.',
            'data' => $preference,
        ]);
    }
}
