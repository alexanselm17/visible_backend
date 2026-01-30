<?php

namespace App\Http\Controllers\Api\Subscriptions;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q'); // optional search

        $plansQuery = DB::table('subscription_plans')
            ->select('id', 'code', 'name', 'billing_period', 'price', 'limits', 'features', 'created_at', 'updated_at')
            ->orderByRaw("FIELD(code, 'FREE', 'STARTER', 'PRO')");

        if ($q) {
            $plansQuery->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        $plans = $plansQuery->get()->map(function ($p) {
            // Ensure JSON comes back as objects/arrays (not strings)
            $p->limits = is_string($p->limits) ? json_decode($p->limits, true) : $p->limits;
            $p->features = is_string($p->features) ? json_decode($p->features, true) : $p->features;
            return $p;
        });

        return response()->json([
            'ok' => true,
            'data' => $plans,
            'meta' => [
                'total' => $plans->count(),
            ],
        ]);
    }

    // GET /api/subscription-plans/{code}
    public function show(string $code): JsonResponse
    {
        $plan = DB::table('subscription_plans')
            ->select('id', 'code', 'name', 'billing_period', 'price', 'limits', 'features', 'created_at', 'updated_at')
            ->where('code', strtoupper($code))
            ->first();

        if (!$plan) {
            return response()->json([
                'ok' => false,
                'message' => 'Plan not found',
            ], 404);
        }

        $plan->limits = is_string($plan->limits) ? json_decode($plan->limits, true) : $plan->limits;
        $plan->features = is_string($plan->features) ? json_decode($plan->features, true) : $plan->features;

        return response()->json([
            'ok' => true,
            'data' => $plan,
        ]);
    }
}
