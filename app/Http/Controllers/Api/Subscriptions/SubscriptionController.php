<?php

namespace App\Http\Controllers\Api\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = SubscriptionPlan::all();

        return response()->json([
            'ok' => true,
            'data' => $subscriptions,
            'message' => 'Subscription plans retrieved successfully.',
        ]);
    }

    
}
