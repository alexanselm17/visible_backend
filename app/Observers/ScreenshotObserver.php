<?php

namespace App\Observers;

use App\Models\Screenshots;
use App\Models\AdvertImages;
use App\Models\User;
use App\Services\FirebaseService;
use App\Jobs\SendIncompleteScreenshotNotification;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;

class ScreenshotObserver
{
    /**
     * How many screenshots are required to mark the campaign as completed
     */
    private const REQUIRED_SCREENSHOTS = 2;

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
        $user   = $screenshot->user ?? User::find($screenshot->processed_by);

        if (!$advert || !$user) {
            return;
        }

        // Count total screenshots for this user and advert
        $totalScreenshots = Screenshots::where('advert_id', $advert->id)
            ->where('processed_by', $user->id)
            ->count();

        // If this is the first screenshot, schedule reminder notifications
        // (Use current $screenshot as the first timestamp to avoid extra query)
        if ($totalScreenshots === 1) {
            $this->scheduleReminderNotifications($user, $advert, $screenshot);
        }

        // If user has completed all required screenshots, send completion notification once
        if ($totalScreenshots >= self::REQUIRED_SCREENSHOTS) {
            $alreadySent = Notification::where('user_id', $user->id)
                ->where('data->action', 'campaign_completed')
                ->where('data->advert_id', $advert->id)
                ->exists();

            if (!$alreadySent) {
                $this->sendCompletionNotification($user, $advert);
            }
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
            // IMPORTANT: copy() to avoid mutating created_at
            $scheduledTime = $firstScreenshot->created_at->copy()->addHours($hours);

            // Only schedule if the time hasn't passed yet
            if ($scheduledTime->isFuture()) {
                SendIncompleteScreenshotNotification::dispatch($user, $advert, $hours)
                    ->delay($scheduledTime);

                Log::info("Scheduled reminder notification for user {$user->id} at {$description} for advert {$advert->id}");
            }
        }
    }

    /**
     * Send completion notification when user uploads all required screenshots
     */
    private function sendCompletionNotification(User $user, AdvertImages $advert): void
    {
        try {
            $firebase = new FirebaseService();

            $title = "Campaign Completed! 🎉";
            $message = "Congratulations! You've successfully uploaded all screenshots for '{$advert->name}'. Your reward is being processed.";

            if (!empty($user->fcm_token)) {
                $firebase->sendToDevice($user->fcm_token, $title, $message);
            }

            Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'type' => 'success',
                'data' => [
                    'advert_id' => $advert->id,
                    'advert_name' => $advert->name,
                    'reward' => $advert->reward,
                    'action' => 'campaign_completed',
                    'required_screenshots' => self::REQUIRED_SCREENSHOTS,
                ],
            ]);

            Log::info("Sent completion notification to user {$user->id} for advert {$advert->id}");
        } catch (\Throwable $e) {
            Log::error("Failed to send completion notification to user {$user->id}: " . $e->getMessage());
        }
    }
}
