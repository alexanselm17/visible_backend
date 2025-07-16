<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\AdvertImages;
use App\Models\Screenshots;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendIncompleteScreenshotNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected User $user;
    protected AdvertImages $advert;
    protected int $hoursAfterFirst;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, AdvertImages $advert, int $hoursAfterFirst)
    {
        $this->user = $user;
        $this->advert = $advert;
        $this->hoursAfterFirst = $hoursAfterFirst;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if user has already completed all 5 screenshots
            $currentScreenshotCount = Screenshots::where('advert_id', $this->advert->id)
                ->where('processed_by', $this->user->id)
                ->count();

            // If user has completed all 5 screenshots, don't send notification
            if ($currentScreenshotCount >= 5) {
                Log::info("User {$this->user->id} has completed all screenshots for advert {$this->advert->id}. Skipping reminder.");
                return;
            }

            // Check if campaign is still valid (within 24 hours of first upload)
            $firstScreenshot = Screenshots::where('advert_id', $this->advert->id)
                ->where('processed_by', $this->user->id)
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$firstScreenshot) {
                Log::warning("No first screenshot found for user {$this->user->id} and advert {$this->advert->id}");
                return;
            }

            // Check if 24 hours have passed since first upload
            if ($firstScreenshot->created_at->addHours(24)->isPast()) {
                Log::info("Campaign expired for user {$this->user->id} and advert {$this->advert->id}");
                $this->sendExpirationNotification($currentScreenshotCount);
                return;
            }

            // Send reminder notification
            $this->sendReminderNotification($currentScreenshotCount);
        } catch (\Exception $e) {
            Log::error("Error in SendIncompleteScreenshotNotification job: " . $e->getMessage());
        }
    }

    /**
     * Send reminder notification
     */
    private function sendReminderNotification(int $currentCount): void
    {
        $remainingCount = 5 - $currentCount;
        $timeLeft = $this->getTimeLeftMessage();

        $title = "Complete Your Campaign! ⏰";
        $message = "You have {$remainingCount} screenshots remaining for '{$this->advert->name}'. {$timeLeft}";

        $this->sendNotification($title, $message, 'warning', [
            'advert_id' => $this->advert->id,
            'advert_name' => $this->advert->name,
            'screenshots_uploaded' => $currentCount,
            'screenshots_remaining' => $remainingCount,
            'action' => 'incomplete_reminder',
            'hours_after_first' => $this->hoursAfterFirst
        ]);
    }

    /**
     * Send expiration notification
     */
    private function sendExpirationNotification(int $currentCount): void
    {
        $title = "Campaign Expired 😔";
        $message = "Unfortunately, the 24-hour window for '{$this->advert->name}' has expired. You uploaded {$currentCount} out of 5 screenshots.";

        $this->sendNotification($title, $message, 'error', [
            'advert_id' => $this->advert->id,
            'advert_name' => $this->advert->name,
            'screenshots_uploaded' => $currentCount,
            'screenshots_required' => 5,
            'action' => 'campaign_expired'
        ]);
    }

    /**
     * Send notification via Firebase and store in database
     */
    private function sendNotification(string $title, string $message, string $type, array $data): void
    {
        try {
            // Send push notification if user has FCM token
            if ($this->user->fcm_token) {
                $firebase = new FirebaseService();
                $firebase->sendToDevice($this->user->fcm_token, $title, $message);
            }

            // Store notification in database
            \App\Models\Notification::create([
                'user_id' => $this->user->id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $data,
            ]);

            Log::info("Sent notification to user {$this->user->id} for advert {$this->advert->id}: {$title}");
        } catch (\Exception $e) {
            Log::error("Failed to send notification to user {$this->user->id}: " . $e->getMessage());
        }
    }

    /**
     * Get time left message based on hours after first upload
     */
    private function getTimeLeftMessage(): string
    {
        $hoursLeft = 24 - $this->hoursAfterFirst;

        if ($hoursLeft <= 1) {
            return "Only 1 hour left!";
        } elseif ($hoursLeft <= 4) {
            return "Only {$hoursLeft} hours left!";
        } else {
            return "You have {$hoursLeft} hours left.";
        }
    }
}
