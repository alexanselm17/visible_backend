<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class EnsureCampaignOwner
{
    public function handle(Request $request, Closure $next)
    {
        $userId = $request->input('user_id') ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'ok' => false,
                'message' => 'user_id is required.'
            ], 422);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid user.'
            ], 404);
        }

        $roleSlug = $user->role->slug ?? null;

        // Allow campaign owners and admins
        if (!in_array($roleSlug, ['campaign_owner', 'admin'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized. Campaign owner or admin role required.'
            ], 403);
        }

        // Attach user to request so controllers can reuse it
        $request->attributes->set('campaign_owner', $user);

        return $next($request);
    }
}
