<?php

namespace App\Console\Commands;

use App\Models\Screenshots;
use App\Models\User;
use App\Models\AdvertImages;
use App\Services\FirebaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'campaigns:cleanup-expired';

    /**
     * The console command description.
     */
    protected $description = 'Send final notifications to users with incomplete campaigns that have expired';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting cleanup of expired campaigns...');

        // Get all first screenshots that are older than 24 hours
        $expiredFirstScreenshots = Screenshots::selectRaw('advert_id, processed_by, MIN(created_at) as first_upload')
            ->where('created_at', '<', now()->subHours(24))
            ->groupBy('advert_id', 'processed_by')
            ->get();

        $notificationsSent = 0;

        foreach ($expiredFirstScreenshots as $record) {
            // Count total screenshots for this user and advert
            $totalScreenshots = Screenshots::where('advert_id', $record->advert_id)
                ->where('processed_by', $record->processed_by)
                ->count();

            // Only process if user didn't complete all 5 screenshots
            if ($totalScreenshots < 2) {
                $user = User::find($record->processed_by);
                $advert = AdvertImages::find($record->advert_id);

                if ($user && $advert) {
                    // Check if we already sent an expiration notification
                    $existingNotification = \App\Models\Notification::where('user_id', $user->id)
                        ->where('data->advert_id', $advert->id)
                        ->where('data->action', 'campaign_expired')
                        ->exists();

                    if (!$existingNotification) {
                        $this->sendFinalExpirationNotification($user, $advert, $totalScreenshots);
                        $notificationsSent++;
                    }
                }
            }
        }

        $this->info("Cleanup completed. Sent {$notificationsSent} expiration notifications.");
        return 0;
    }

    /**
     * Send final expiration notification
     */
    private function sendFinalExpirationNotification(User $user, AdvertImages $advert, int $completedCount): void
    {
        try {
            $title = "Campaign Expired 😔";
            $message = "The 24-hour window for '{$advert->name}' has expired. You completed {$completedCount} out of 2 screenshots.";

            // Send push notification if user has FCM token
            if ($user->fcm_token) {
                $firebase = new FirebaseService();
                $firebase->sendToDevice($user->fcm_token, $title, $message);
            }

            // Store notification in database
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'type' => 'error',
                'data' => [
                    'advert_id' => $advert->id,
                    'advert_name' => $advert->name,
                    'screenshots_uploaded' => $completedCount,
                    'screenshots_required' => 2,
                    'action' => 'campaign_expired'
                ],
            ]);

            Log::info("Sent final expiration notification to user {$user->id} for advert {$advert->id}");
        } catch (\Exception $e) {
            Log::error("Failed to send final expiration notification to user {$user->id}: " . $e->getMessage());
        }
    }
}
