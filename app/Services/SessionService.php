<?php

namespace App\Services;

use App\Models\User;
use App\Models\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SessionService
{
    /**
     * Track user login and create/update session
     */
    public function trackLogin(User $user, string $sessionId = null): void
    {
        $sessionId = $sessionId ?? session()->getId();

        // Update user login status
        $user->update(['is_logged_in' => true]);

        // Update session table with user information
        DB::table('sessions')
            ->where('id', $sessionId)
            ->update([
                'user_id' => $user->id,
                'last_activity' => now()->timestamp,
            ]);

        // Log the login activity
        $this->logActivity($user, 'login', $sessionId);
    }

    /**
     * Track user logout and clean up session
     */
    public function trackLogout(User $user, string $sessionId = null): void
    {
        $sessionId = $sessionId ?? session()->getId();

        // Remove the session
        DB::table('sessions')->where('id', $sessionId)->delete();

        // Check if user has other active sessions
        $hasOtherSessions = $this->hasActiveSessions($user, $sessionId);

        // Update login status only if no other sessions exist
        if (!$hasOtherSessions) {
            $user->update(['is_logged_in' => false]);
        }

        // Log the logout activity
        $this->logActivity($user, 'logout', $sessionId);
    }

    /**
     * Force logout user from all sessions
     */
    public function forceLogoutFromAllSessions(User $user): int
    {
        // Delete all user sessions
        $deletedSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        // Update login status
        $user->update(['is_logged_in' => false]);

        // Log the forced logout
        $this->logActivity($user, 'force_logout_all');

        return $deletedSessions;
    }

    /**
     * Force logout user from specific session
     */
    public function forceLogoutFromSession(User $user, string $sessionId): bool
    {
        // Delete specific session
        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        // Check if user has other active sessions
        $hasOtherSessions = $this->hasActiveSessions($user, $sessionId);

        // Update login status if no other sessions exist
        if (!$hasOtherSessions) {
            $user->update(['is_logged_in' => false]);
        }

        if ($deleted) {
            $this->logActivity($user, 'force_logout', $sessionId);
        }

        return $deleted > 0;
    }

    /**
     * Get all active sessions for a user
     */
    public function getActiveSessions(User $user): \Illuminate\Support\Collection
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('last_activity', '>', now()->subMinutes(30)->timestamp)
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(function ($session) {
                $session->last_activity = Carbon::createFromTimestamp($session->last_activity);
                $session->browser_info = $this->parseBrowserInfo($session->user_agent);
                return $session;
            });
    }

    /**
     * Get session statistics for admin dashboard
     */
    public function getSessionStats(): array
    {
        $activeThreshold = now()->subMinutes(30)->timestamp;

        return [
            'total_active_sessions' => DB::table('sessions')
                ->where('last_activity', '>', $activeThreshold)
                ->count(),

            'unique_active_users' => DB::table('sessions')
                ->where('last_activity', '>', $activeThreshold)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),

            'guest_sessions' => DB::table('sessions')
                ->where('last_activity', '>', $activeThreshold)
                ->whereNull('user_id')
                ->count(),

            'total_sessions_today' => DB::table('sessions')
                ->where('last_activity', '>', now()->startOfDay()->timestamp)
                ->count(),
        ];
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions(): int
    {
        $expiredThreshold = now()->subDays(7)->timestamp; // Sessions older than 7 days

        return DB::table('sessions')
            ->where('last_activity', '<', $expiredThreshold)
            ->delete();
    }

    /**
     * Check if user has active sessions excluding a specific session
     */
    protected function hasActiveSessions(User $user, string $excludeSessionId = null): bool
    {
        $query = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('last_activity', '>', now()->subMinutes(30)->timestamp);

        if ($excludeSessionId) {
            $query->where('id', '!=', $excludeSessionId);
        }

        return $query->exists();
    }

    /**
     * Parse browser information from user agent
     */
    protected function parseBrowserInfo(?string $userAgent): array
    {
        if (!$userAgent) {
            return ['browser' => 'Unknown', 'platform' => 'Unknown'];
        }

        $browser = 'Unknown';
        $platform = 'Unknown';

        // Browser detection
        if (preg_match('/Chrome\/(\d+)/i', $userAgent, $matches)) {
            $browser = 'Chrome ' . $matches[1];
        } elseif (preg_match('/Firefox\/(\d+)/i', $userAgent, $matches)) {
            $browser = 'Firefox ' . $matches[1];
        } elseif (preg_match('/Safari\/(\d+)/i', $userAgent, $matches)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge\/(\d+)/i', $userAgent, $matches)) {
            $browser = 'Edge ' . $matches[1];
        }

        // Platform detection
        if (preg_match('/Windows NT (\d+\.\d+)/i', $userAgent, $matches)) {
            $platform = 'Windows ' . $matches[1];
        } elseif (preg_match('/Mac OS X (\d+[._]\d+)/i', $userAgent, $matches)) {
            $platform = 'macOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/Android (\d+)/i', $userAgent, $matches)) {
            $platform = 'Android ' . $matches[1];
        } elseif (preg_match('/iPhone OS (\d+[._]\d+)/i', $userAgent, $matches)) {
            $platform = 'iOS ' . str_replace('_', '.', $matches[1]);
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'is_mobile' => preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent) ? true : false,
        ];
    }

    /**
     * Log session activity (optional - you can implement your own logging)
     */
    protected function logActivity(User $user, string $action, string $sessionId = null): void
    {
        // You can implement activity logging here
        // For example, create an ActivityLog model or use Laravel's built-in logging

        \Log::info("Session activity: {$action}", [
            'user_id' => $user->id,
            'username' => $user->username,
            'session_id' => $sessionId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Get concurrent sessions limit (you can customize this)
     */
    public function getConcurrentSessionsLimit(User $user): int
    {
        // You can customize this based on user role or subscription
        if ($user->isAdmin() || $user->isDeveloper()) {
            return 10; // Admins can have more sessions
        }

        return 3; // Regular users limited to 3 concurrent sessions
    }

    /**
     * Enforce concurrent sessions limit
     */
    public function enforceConcurrentSessionsLimit(User $user): void
    {
        $limit = $this->getConcurrentSessionsLimit($user);
        $activeSessions = $this->getActiveSessions($user);

        if ($activeSessions->count() > $limit) {
            // Remove oldest sessions
            $sessionsToRemove = $activeSessions->skip($limit - 1);

            foreach ($sessionsToRemove as $session) {
                DB::table('sessions')->where('id', $session->id)->delete();
            }

            $this->logActivity($user, 'session_limit_enforced');
        }
    }
}
