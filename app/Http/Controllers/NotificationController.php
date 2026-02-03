<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'fcm_token' => 'required|string',
        ]);

        $user = User::find($request->user_id);
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json([
            'message' => 'FCM token updated successfully',
            'user' => [
                'id' => $user->id,
                'fcm_token' => $user->fcm_token,
            ],
        ]);
    }

    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->per_page ?? 10;

        $notifications = Notification::where('user_id', $request->user_id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'notifications' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'has_more' => $notifications->hasMorePages(),
            ],
            'unread_count' => Notification::where('user_id', $request->user_id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'notification_id' => 'required|exists:notifications,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $notification = Notification::where('id', $request->notification_id)
            ->where('user_id', $request->user_id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification->fresh(),
        ]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $updated = Notification::where('user_id', $request->user_id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'updated_count' => $updated,
        ]);
    }

    /**
     * Delete a notification
     */
    public function deleteNotification(Request $request): JsonResponse
    {
        $request->validate([
            'notification_id' => 'required|exists:notifications,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $notification = Notification::where('id', $request->notification_id)
            ->where('user_id', $request->user_id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully',
        ]);
    }

    /**
     * Send notification to a specific user
     */
    public function sendToUser(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:system,security,info,warning,success,error',
            'data' => 'nullable|array',
            'send_push' => 'nullable|boolean',
        ]);

        $user = User::find($request->user_id);

        // Store notification in database
        $notification = Notification::create([
            'user_id' => $request->user_id,
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type ?? 'info',
            'data' => $request->data,
        ]);

        // Send push notification if requested and user has FCM token
        if (($request->send_push ?? true) && $user->fcm_token) {
            try {
                $firebase = new FirebaseService;
                $firebase->sendToDevice($user->fcm_token, $request->title, $request->message);
            } catch (\Exception $e) {
                \Log::error("FCM error for user [{$user->id}]: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Notification sent successfully',
            'notification' => $notification,
        ]);
    }

    /**
     * Send notification to all users
     */
    public function notifyAllUsers(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:system,security,info,warning,success,error',
            'data' => 'nullable|array',
            'send_push' => 'nullable|boolean',
            'exclude_admins' => 'nullable|boolean',
        ]);

        $userQuery = User::query();

        if ($request->exclude_admins ?? true) {
            $userQuery->whereHas('role', function ($query) {
                $query->where('slug', '!=', 'admin');
            });
        }

        $users = $userQuery->get();
        $firebase = new FirebaseService;
        $notifications = [];

        foreach ($users as $user) {
            $notification = Notification::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type ?? 'info',
                'data' => $request->data,
            ]);

            $notifications[] = $notification;

            if (($request->send_push ?? true) && $user->fcm_token) {
                try {
                    $firebase->sendToDevice($user->fcm_token, $request->title, $request->message);
                } catch (\Exception $e) {
                    \Log::error("FCM error for user [{$user->id}]: " . $e->getMessage());
                }
            }
        }

        return response()->json([
            'message' => 'Notifications sent to all users',
            'total_sent' => count($notifications),
            'notifications_created' => count($notifications),
        ]);
    }

    /**
     * Send notification to admins when a new account is created
     */
    public function notifyAdminsNewAccount(Request $request): JsonResponse
    {
        $request->validate([
            'new_user_id' => 'required|exists:users,id',
            'send_push' => 'nullable|boolean',
        ]);

        $newUser = User::with(['role', 'county', 'subCounty'])->find($request->new_user_id);

        // Get all admin users
        $admins = User::whereHas('role', function ($query) {
            $query->where('slug', 'admin');
        })->get();

        if ($admins->isEmpty()) {
            return response()->json([
                'message' => 'No admin users found',
                'notifications_sent' => 0,
            ]);
        }

        $firebase = new FirebaseService;
        $notifications = [];

        $title = 'New Account Registration';
        $message = "A new user account has been created: {$newUser->fullname} ({$newUser->username})";

        // Additional data for admins
        $notificationData = [
            'user_id' => $newUser->id,
            'user_fullname' => $newUser->fullname,
            'user_username' => $newUser->username,
            'user_email' => $newUser->email,
            'user_phone' => $newUser->phone,
            'user_role' => $newUser->role?->name,
            'user_county' => $newUser->county?->name,
            'user_subcounty' => $newUser->subCounty?->name,
            'created_at' => $newUser->created_at->toDateTimeString(),
            'action_type' => 'new_account_created',
        ];

        foreach ($admins as $admin) {
            $notification = Notification::create([
                'user_id' => $admin->id,
                'title' => $title,
                'message' => $message,
                'type' => 'system',
                'data' => $notificationData,
            ]);

            $notifications[] = $notification;

            if (($request->send_push ?? true) && $admin->fcm_token) {
                try {
                    $firebase->sendToDevice($admin->fcm_token, $title, $message);
                } catch (\Exception $e) {
                    \Log::error("FCM error for admin [{$admin->id}]: " . $e->getMessage());
                }
            }
        }

        return response()->json([
            'message' => 'New account notifications sent to admins',
            'notifications_sent' => count($notifications),
            'admins_notified' => $admins->count(),
            'new_user' => [
                'id' => $newUser->id,
                'fullname' => $newUser->fullname,
                'username' => $newUser->username,
            ],
        ]);
    }

    /**
     * Send notification to user when their account has been activated
     */
    public function notifyUserAccountActivated(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'send_push' => 'nullable|boolean',
        ]);

        $user = User::find($request->user_id);

        $title = 'Account Activated';
        $message = 'Congratulations! Your account has been successfully activated. You can now access all features of our platform.';

        $notificationData = [
            'action_type' => 'account_activated',
            'activated_at' => now()->toDateTimeString(),
            'account_status' => 'active',
        ];

        // Store notification in database
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => 'system',
            'data' => $notificationData,
        ]);

        // Send push notification if requested and user has FCM token
        if (($request->send_push ?? true) && $user->fcm_token) {
            try {
                $firebase = new FirebaseService;
                $firebase->sendToDevice($user->fcm_token, $title, $message);
            } catch (\Exception $e) {
                \Log::error("FCM error for user [{$user->id}]: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Account activation notification sent successfully',
            'notification' => $notification,
            'user' => [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'username' => $user->username,
            ],

        ]);
    }

    /**
     * Get notification statistics for a user
     */
    public function getNotificationStats(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = $request->user_id;

        $stats = [
            'total' => Notification::where('user_id', $userId)->count(),
            'unread' => Notification::where('user_id', $userId)->unread()->count(),
            'read' => Notification::where('user_id', $userId)->read()->count(),
            'by_type' => Notification::where('user_id', $userId)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
        ];

        return response()->json($stats);
    }

    /**
     * Send notification to user when their account has been deactivated due to fraud
     */
    public function notifyUserAccountFlaggedFraud(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'send_push' => 'nullable|boolean',
        ]);

        $user = User::find($request->user_id);

        $title = 'Account Deactivated';
        $message = 'Your account has been deactivated due to suspicious activity. If you believe this is a mistake, please contact support for assistance.';

        $notificationData = [
            'action_type' => 'account_flagged_fraud',
            'account_status' => 'deactivated',
            'flagged_at' => now()->toDateTimeString(),
        ];

        // Store notification in database
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => 'security',
            'data' => $notificationData,
        ]);

        if (($request->send_push ?? true) && $user->fcm_token) {
            try {
                $firebase = new FirebaseService;
                $firebase->sendToDevice($user->fcm_token, $title, $message);
            } catch (\Exception $e) {
                \Log::error("FCM error for user [{$user->id}]: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Fraud deactivation notification sent successfully',
            'notification' => $notification,
            'user' => [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'username' => $user->username,
            ],
        ]);
    }

    /**
     * Send notification to users with specific roles
     */
    public function notifyRoles(Request $request): JsonResponse
    {
        $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:system,security,info,warning,success,error',
            'data' => 'nullable|array',
            'send_push' => 'nullable|boolean',
        ]);

        $roles = $request->roles;

        $users = User::whereHas('role', function ($q) use ($roles) {
            $q->whereIn('slug', $roles);
        })->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' => 'No users found for the specified roles',
                'notifications_sent' => 0,
            ], 200);
        }

        $firebase = new FirebaseService;
        $created = 0;

        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type ?? 'info',
                'data' => $request->data,
            ]);

            $created++;

            if (($request->send_push ?? true) && $user->fcm_token) {
                try {
                    $firebase->sendToDevice($user->fcm_token, $request->title, $request->message);
                } catch (\Exception $e) {
                    Log::error("FCM error for role user [{$user->id}]: " . $e->getMessage());
                }
            }
        }

        return response()->json([
            'message' => 'Role notifications sent successfully',
            'notifications_sent' => $created,
            'roles' => $roles,
        ], 200);
    }


    public function notifyUser(Request $request): JsonResponse
    {
        $request->validate([
            'userId' => 'required|array|min:1',
            'userId.*' => 'required|integer|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:system,security,info,warning,success,error',
            'data' => 'nullable|array',
            'send_push' => 'nullable|boolean',
        ]);

        $userIds = $request->userId;

        $users = User::whereIn('id', $userIds)->get();
        if ($users->isEmpty()) {
            return response()->json([
                'message' => 'No users found for the specified user IDs',
                'notifications_sent' => 0,
            ], 200);
        }

        $firebase = new FirebaseService;
        $created = 0;

        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type ?? 'info',
                'data' => $request->data,
            ]);

            $created++;

            if (($request->send_push ?? true) && $user->fcm_token) {
                try {
                    $firebase->sendToDevice($user->fcm_token, $request->title, $request->message);
                } catch (\Exception $e) {
                    Log::error("FCM error for role user [{$user->id}]: " . $e->getMessage());
                }
            }
        }

        return response()->json([
            'message' => 'User notifications sent successfully',
            'notifications_sent' => $created,
            'user_ids' => $userIds,
        ], 200);
    }
}
