<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $requiredPermission)
    {
        $authHeader = $request->header('Authorization');

        if (! $authHeader) {
            Log::warning('Unauthorized access attempt: No token provided.', [
                'ip' => $request->ip(),
                'route' => $request->path(),
            ]);

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'Unauthorized. No token provided.',
            ], 401);
        }

        $tokenString = preg_replace('/^Bearer\s+/i', '', $authHeader);
        $token = PersonalAccessToken::findToken($tokenString);

        if (! $token) {
            Log::warning('Unauthorized access attempt: Invalid or expired token.', [
                'ip' => $request->ip(),
                'route' => $request->path(),
            ]);

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $user = $token->tokenable;

        if (! $user) {
            Log::error('Token does not map to a valid user.', [
                'ip' => $request->ip(),
                'route' => $request->path(),
            ]);

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'Unauthorized. User not found.',
            ], 401);
        }

        // Set authenticated user for the request lifecycle
        Auth::setUser($user);

        // ✅ Permission check (IMPORTANT)
        $hasPermission = $user->permissions()
            ->where('slug', $requiredPermission)
            ->exists();

        if (! $hasPermission) {
            Log::warning('Forbidden: Missing permission.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'required_permission' => $requiredPermission,
                'route' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'Forbidden. You do not have permission to perform this action.',
                'required_permission' => $requiredPermission,
            ], 403);
        }

        return $next($request);
    }
}
