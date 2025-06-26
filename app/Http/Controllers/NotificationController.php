<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Notification;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
            ]
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
            'type' => 'nullable|in:system,security,info,warning,success,error',
            'is_read' => 'nullable|boolean',
        ]);

        $query = Notification::where('user_id', $request->user_id)
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        if ($request->has('is_read')) {
            if ($request->is_read) {
                $query->read();
            } else {
                $query->unread();
            }
        }

        $notifications = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'notifications' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'has_more' => $notifications->hasMorePages(),
            ],
            'unread_count' => Notification::where('user_id', $request->user_id)->unread()->count(),
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
                $firebase = new FirebaseService();
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
        $firebase = new FirebaseService();
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
}
