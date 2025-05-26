<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
  public function updateFcmToken(Request $request)
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


    // Send a notification to all users
   public function notifyAllUsers($title, $body)
{
    $tokens = User::whereNotNull('fcm_token')->pluck('fcm_token')->toArray();

    $firebase = new FirebaseService();

    foreach ($tokens as $token) {
        try {
            $firebase->sendToDevice($token, $title, $body);
        } catch (\Exception $e) {
            // Handle failures
            \Log::error("FCM error: " . $e->getMessage());
        }
    }
}


// public function notifyAllUsers($title, $body)
// {
//     $tokens = User::whereHas('role', function ($query) {
//             $query->where('slug', '!=', 'admin');
//         })
//         ->whereNotNull('fcm_token')
//         ->pluck('fcm_token')
//         ->toArray();

//     $firebase = new FirebaseService();

//     foreach ($tokens as $token) {
//         try {
//             $firebase->sendToDevice($token, $title, $body);
//         } catch (\Exception $e) {
//             \Log::error("FCM error for token [$token]: " . $e->getMessage());
//         }
//     }
// }
}
