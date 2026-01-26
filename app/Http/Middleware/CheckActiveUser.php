<?php

namespace App\Http\Middleware;

use App\Models\AppVersion;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveUser
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Token not provided.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $personalAccessToken = PersonalAccessToken::findToken($token);

        if (! $personalAccessToken) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Invalid token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $personalAccessToken->tokenable;

        if (! $user || ! $user->is_active) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Account has already been deactivated or user not found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // ✅ Validate token expiration (24 hours)
        if (Carbon::parse($personalAccessToken->created_at)->addHours(24)->isPast()) {
            $personalAccessToken->delete();

            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Token has expired. Please log in again.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // ✅ Validate app version from token name
        $tokenName = $personalAccessToken->name;
        if (! str_starts_with($tokenName, 'api-token-v')) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Invalid token format.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userAppVersion = str_replace('api-token-v', '', $tokenName);

        $latestVersion = AppVersion::orderBy('created_at', 'desc')->first();
        if (! $latestVersion) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'No app version found.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (version_compare($userAppVersion, $latestVersion->versions, '!=')) {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => 'Your app is out of date. Please update to the latest version.',
                'latest_version' => $latestVersion->versions,
            ], Response::HTTP_FORBIDDEN);
        }

        // ✅ Role check (if provided)
        if (! empty($roles)) {
            $userRoleSlug = $user->role->slug ?? null;

            if (! $userRoleSlug || ! in_array($userRoleSlug, $roles)) {
                return response()->json([
                    'ok' => false,
                    'status' => 'failed',
                    'message' => 'Unauthorized. Insufficient privileges.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
