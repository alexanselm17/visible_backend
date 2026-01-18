<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\AdvertImages;
use App\Models\Screenshots;
use App\Models\Notification;
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

    private const REQUIRED_SCREENSHOTS = 2;
    private const EXPIRY_HOURS = 24;
    private const REMINDER_HOURS = 18;

    protected User $user;
    protected AdvertImages $advert;
    protected int $hoursAfterFirst;

    public function __construct(User $user, AdvertImages $advert, int $hoursAfterFirst)
    {
        $this->user = $user;
        $this->advert = $advert;
        $this->hoursAfterFirst = $hoursAfterFirst;
    }

    public function handle(): void
    {
        try {
            // Safety: this job should only ever send at 18 hours
            if ($this->hoursAfterFirst !== self::REMINDER_HOURS) {
                Log::info("Skipping reminder: hoursAfterFirst={$this->hoursAfterFirst} (expected " . self::REMINDER_HOURS . ")");
                return;
            }

            // Prevent duplicates (queue retries, multiple schedules, etc.)
            $alreadySent = Notification::where('user_id', $this->user->id)
                ->where('data->action', 'incomplete_reminder')
                ->where('data->advert_id', $this->advert->id)
                ->where('data->hours_after_first', self::REMINDER_HOURS)
                ->exists();

            if ($alreadySent) {
                Log::info("Reminder already sent (18h) for user {$this->user->id} advert {$this->advert->id}. Skipping.");
                return;
            }

            // Count current screenshots
            $currentCount = Screenshots::where('advert_id', $this->advert->id)
                ->where('processed_by', $this->user->id)
                ->count();

            // Completed => do nothing
            if ($currentCount >= self::REQUIRED_SCREENSHOTS) {
                Log::info("User {$this->user->id} completed campaign for advert {$this->advert->id}. Skipping reminder.");
                return;
            }

            // First screenshot check (also used for expiry window)
            $firstScreenshot = Screenshots::where('advert_id', $this->advert->id)
                ->where('processed_by', $this->user->id)
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$firstScreenshot) {
                Log::warning("No first screenshot found for user {$this->user->id} and advert {$this->advert->id}");
                return;
            }

            // Expired (24 hours since first upload) => send expiry message (optional)
            if ($firstScreenshot->created_at->copy()->addHours(self::EXPIRY_HOURS)->isPast()) {
                Log::info("Campaign expired for user {$this->user->id} and advert {$this->advert->id}");
                $this->sendExpirationNotification($currentCount);
                return;
            }

            // Send the one and only reminder (at 18 hours)
            $this->sendReminderNotification($currentCount);
        } catch (\Throwable $e) {
            Log::error("Error in SendIncompleteScreenshotNotification job: " . $e->getMessage());
        }
    }

    private function sendReminderNotification(int $currentCount): void
    {
        $remaining = self::REQUIRED_SCREENSHOTS - $currentCount;

        $title = "Complete Your Campaign! ⏰";
        $message = "You have {$remaining} screenshot(s) remaining for '{$this->advert->name}'. Upload to complete your campaign.";

        $this->sendNotification($title, $message, 'warning', [
            'advert_id' => $this->advert->id,
            'advert_name' => $this->advert->name,
            'screenshots_uploaded' => $currentCount,
            'screenshots_remaining' => $remaining,
            'screenshots_required' => self::REQUIRED_SCREENSHOTS,
            'action' => 'incomplete_reminder',
            'hours_after_first' => self::REMINDER_HOURS,
        ]);
    }

    private function sendExpirationNotification(int $currentCount): void
    {
        $title = "Campaign Expired 😔";
        $message = "Unfortunately, the " . self::EXPIRY_HOURS . "-hour window for '{$this->advert->name}' has expired. You uploaded {$currentCount} out of " . self::REQUIRED_SCREENSHOTS . " screenshot(s).";

        $this->sendNotification($title, $message, 'error', [
            'advert_id' => $this->advert->id,
            'advert_name' => $this->advert->name,
            'screenshots_uploaded' => $currentCount,
            'screenshots_required' => self::REQUIRED_SCREENSHOTS,
            'action' => 'campaign_expired',
        ]);
    }

    private function sendNotification(string $title, string $message, string $type, array $data): void
    {
        try {
            if (!empty($this->user->fcm_token)) {
                $firebase = new FirebaseService();
                $firebase->sendToDevice($this->user->fcm_token, $title, $message);
            }

            Notification::create([
                'user_id' => $this->user->id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $data,
            ]);

            Log::info("Sent notification to user {$this->user->id} for advert {$this->advert->id}: {$title}");
        } catch (\Throwable $e) {
            Log::error("Failed to send notification to user {$this->user->id}: " . $e->getMessage());
        }
    }
}
