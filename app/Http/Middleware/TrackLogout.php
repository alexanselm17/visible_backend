<?php

namespace App\Http\Middleware;

use App\Services\SessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TrackLogout
{
    protected SessionService $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Check if user is logging out
        if ($request->is('admin/logout') && Auth::check()) {
            $user = Auth::user();
            $sessionId = session()->getId();

            Log::info('User logout detected', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
            ]);

            // Track the logout
            $this->sessionService->trackLogout($user, $sessionId);
        }

        return $response;
    }
}
