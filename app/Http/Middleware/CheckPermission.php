<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    
    public function handle(Request $request, Closure $next, $requiredPermission)
    {
        if (!$request->header('Authorization')) {
            Log::warning('Unauthorized access attempt: No token provided.', [
                'ip' => $request->ip(),
                'route' => $request->path(),
            ]);
            return response()->json([
                'message' => "Unauthorized. No token provided.",
                'error' => 'Unauthorized. No token provided.'
            ], 401);
        }

        $tokenString = str_replace('Bearer ', '', $request->header('Authorization'));
        $token = PersonalAccessToken::findToken($tokenString);

        if (!$token) {
            Log::warning('Unauthorized access attempt: Invalid or expired token.', [
                'token' => $tokenString,
                'ip' => $request->ip(),
                'route' => $request->path(),
            ]);
            return response()->json(['error' => 'Invalid or expired token.'], 401);
        }

        $user = $token->tokenable;

        if (!$user) {
            Log::error('Token does not map to a valid user.', [
                'token' => $tokenString,
                'ip' => $request->ip(),
                'route' => $request->path(),
            ]);
            return response()->json(['error' => 'Unauthorized. User not found.'], 401);
        }


        Auth::setUser($user);

        Log::info('User authenticated.', [
            'user_id' => $user->id,
            'email' => $user->email,
            'permissions' => $user->permissions()->pluck('slug')->toArray(),
            'requested_permission' => $requiredPermission,
            'route' => $request->path(),
            'ip' => $request->ip(),
        ]);


        Log::info('Permission granted.', [
            'user_id' => $user->id,
            'email' => $user->email,
            'requested_permission' => $requiredPermission,
            'route' => $request->path(),
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
