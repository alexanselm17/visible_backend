<?php

namespace App\Observers;

use App\Models\Screenshots;
use App\Models\AdvertImages;
use App\Models\User;
use App\Services\FirebaseService;
use App\Jobs\SendIncompleteScreenshotNotification;
use Illuminate\Support\Facades\Log;

class ScreenshotObserver
{
    /**
     * Handle the Screenshots "created" event.
     */
    public function created(Screenshots $screenshot): void
    {
        $this->handleScreenshotUpload($screenshot);
    }

    /**
     * Handle screenshot upload and schedule notification if needed
     */
    private function handleScreenshotUpload(Screenshots $screenshot): void
    {
        // Get the advert and user
        $advert = $screenshot->advert ?? AdvertImages::find($screenshot->advert_id);
        $user = $screenshot->user ?? User::find($screenshot->processed_by);

        if (!$advert || !$user) {
            return;
        }

        // Count total screenshots for this user and advert
        $totalScreenshots = Screenshots::where('advert_id', $advert->id)
            ->where('processed_by', $user->id)
            ->count();

        // Get the first screenshot timestamp for this user and advert
        $firstScreenshot = Screenshots::where('advert_id', $advert->id)
            ->where('processed_by', $user->id)
            ->orderBy('created_at', 'asc')
            ->first();

        // If this is the first screenshot, schedule reminder notifications
        if ($totalScreenshots === 1) {
            $this->scheduleReminderNotifications($user, $advert, $firstScreenshot);
        }

        if ($totalScreenshots === 2) {
            $this->sendCompletionNotification($user, $advert);
        }
    }

    /**
     * Schedule reminder notifications for incomplete screenshots
     */
    private function scheduleReminderNotifications(User $user, AdvertImages $advert, Screenshots $firstScreenshot): void
    {
        $reminderIntervals = [
            18 => '18 hours',
        ];

        foreach ($reminderIntervals as $hours => $description) {
            $scheduledTime = $firstScreenshot->created_at->addHours($hours);

            if ($scheduledTime->isFuture()) {
                SendIncompleteScreenshotNotification::dispatch($user, $advert, $hours)
                    ->delay($scheduledTime);

                Log::info("Scheduled reminder notification for user {$user->id} at {$description} for advert {$advert->id}");
            }
        }
    }

    /**
     * Send completion notification when user uploads all 5 screenshots
     */
    private function sendCompletionNotification(User $user, AdvertImages $advert): void
    {
        try {
            $firebase = new FirebaseService();

            $title = "Campaign Completed! 🎉";
            $message = "Congratulations! You've successfully uploaded all 2 screenshots for '{$advert->name}'. Your reward is being processed.";

            // Send push notification if user has FCM token
            if ($user->fcm_token) {
                $firebase->sendToDevice($user->fcm_token, $title, $message);
            }

            // Store in database
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'type' => 'success',
                'data' => [
                    'advert_id' => $advert->id,
                    'advert_name' => $advert->name,
                    'reward' => $advert->reward,
                    'action' => 'campaign_completed'
                ],
            ]);

            Log::info("Sent completion notification to user {$user->id} for advert {$advert->id}");
        } catch (\Exception $e) {
            Log::error("Failed to send completion notification to user {$user->id}: " . $e->getMessage());
        }
    }
}
