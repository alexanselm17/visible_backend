<?php

namespace App\Http\Controllers\Api\Subscriptions;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Str;
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

    public function buy(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code' => 'required|string|exists:subscription_plans,code',
            'user_id' => 'required|uuid|exists:users,id',
            'auto_renew' => 'sometimes|boolean',
        ]);

        $authUser = $request->user();

        $targetUserId = $authUser->id;

        if ($request->filled('user_id')) {
            if (!$authUser->hasRole('ADMIN')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'You are not allowed to buy a plan for another user',
                ], 403);
            }

            $targetUserId = $request->user_id;
        }

        $plan = DB::table('subscription_plans')
            ->where('code', strtoupper($request->plan_code))
            ->first();

        DB::beginTransaction();

        try {
            DB::table('user_subscriptions')
                ->where('user_id', $targetUserId)
                ->where('status', 'ACTIVE')
                ->update([
                    'status' => 'EXPIRED',
                    'updated_at' => now(),
                ]);

            $startsAt = now();
            $endsAt = match ($plan->billing_period) {
                'WEEK'  => now()->addWeek(),
                'MONTH' => now()->addMonth(),
                'YEAR'  => now()->addYear(),
            };

            DB::table('user_subscriptions')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $targetUserId,
                'plan_id' => $plan->id,
                'status' => 'ACTIVE',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'auto_renew' => $request->boolean('auto_renew'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Subscription activated successfully',
                'data' => [
                    'user_id' => $targetUserId,
                    'plan' => $plan->code,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => 'Failed to activate subscription',
            ], 500);
        }
    }


    /**
     * Get current user subscription
     */
    public function mySubscription(Request $request): JsonResponse
    {
        $subscription = DB::table('user_subscriptions as us')
            ->join('subscription_plans as sp', 'sp.id', '=', 'us.plan_id')
            ->where('us.user_id', $request->user()->id)
            ->where('us.status', 'ACTIVE')
            ->select(
                'us.id',
                'us.status',
                'us.starts_at',
                'us.ends_at',
                'us.auto_renew',
                'sp.code',
                'sp.name',
                'sp.billing_period',
                'sp.price',
                'sp.limits',
                'sp.features'
            )
            ->first();

        if (!$subscription) {
            return response()->json([
                'ok' => true,
                'data' => null,
                'message' => 'No active subscription',
            ]);
        }

        $subscription->limits = json_decode($subscription->limits, true);
        $subscription->features = json_decode($subscription->features, true);

        return response()->json([
            'ok' => true,
            'data' => $subscription,
        ]);
    }
}
