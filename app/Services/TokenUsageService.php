<?php

namespace App\Services;

use App\Models\AdvertSubmission;
use App\Models\TokenType;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TokenUsageService
{
    /**
     * Calculate the total token cost of an advert.
     *
     * Formula:
     *   media_units × reach_units = tokens_required
     *
     * VIDEO media_units = ceil(video_seconds / seconds_per_token)
     * IMAGE/TEXT media_units = 1
     * reach_units = ceil(target_reach / people_per_token)
     */
    public function quote(
        TokenType $tokenType,
        int $targetReach,
        ?int $videoDurationSeconds = null,
        ?int $secondsPerTokenOverride = null,
        ?int $peoplePerTokenOverride = null
    ): array {
        if ($targetReach < 1) {
            throw ValidationException::withMessages([
                'target_reach' => 'Target reach must be at least one person.',
            ]);
        }

        $peoplePerToken = $peoplePerTokenOverride
            ?: (int) $tokenType->people_per_token;

        if ($peoplePerToken < 1) {
            throw new RuntimeException("{$tokenType->code} people-per-token pricing is not configured.");
        }

        $mediaUnits = 1;
        $secondsPerToken = null;

        if ($tokenType->media_type === TokenType::VIDEO) {
            if (!$videoDurationSeconds || $videoDurationSeconds < 1) {
                throw ValidationException::withMessages([
                    'video_duration_seconds' => 'Video duration is required for a video advert.',
                ]);
            }

            if (
                $tokenType->max_video_duration_seconds
                && $videoDurationSeconds > (int) $tokenType->max_video_duration_seconds
            ) {
                throw ValidationException::withMessages([
                    'video_duration_seconds' => "Video duration cannot exceed {$tokenType->max_video_duration_seconds} seconds.",
                ]);
            }

            $secondsPerToken = $secondsPerTokenOverride
                ?: (int) $tokenType->seconds_per_token;

            if ($secondsPerToken < 1) {
                throw new RuntimeException('Gold token video-duration pricing is not configured.');
            }

            $mediaUnits = (int) ceil($videoDurationSeconds / $secondsPerToken);
        }

        $reachUnits = (int) ceil($targetReach / $peoplePerToken);
        $tokensRequired = $mediaUnits * $reachUnits;

        return [
            'token_type' => $tokenType->code,
            'media_type' => $tokenType->media_type,
            'target_reach' => $targetReach,
            'video_duration_seconds' => $tokenType->media_type === TokenType::VIDEO
                ? $videoDurationSeconds
                : null,
            'seconds_per_token' => $secondsPerToken,
            'people_per_token' => $peoplePerToken,
            'media_units' => $mediaUnits,
            'reach_units' => $reachUnits,
            'tokens_required' => $tokensRequired,
        ];
    }

    public function quoteByMedia(
        string $mediaType,
        int $targetReach,
        ?int $videoDurationSeconds = null
    ): array {
        $tokenType = TokenType::where('media_type', strtoupper($mediaType))
            ->where('is_active', true)
            ->firstOrFail();

        return $this->quote(
            $tokenType,
            $targetReach,
            $videoDurationSeconds
        );
    }

    /**
     * Recalculate final usage with the pricing rules captured when the advert
     * was submitted. Admin pricing changes therefore do not affect an advert
     * that is already in the approval/design workflow.
     */
    public function forSubmission(
        AdvertSubmission $submission,
        ?int $videoDurationSeconds = null
    ): array {
        $submission->loadMissing('tokenType');

        if (!$submission->tokenType) {
            throw new RuntimeException('Token type is missing from this advert submission.');
        }

        $duration = $submission->tokenType->media_type === TokenType::VIDEO
            ? ($videoDurationSeconds
                ?: $submission->final_video_duration_seconds
                ?: $submission->video_duration_seconds)
            : null;

        return $this->quote(
            $submission->tokenType,
            (int) $submission->target_reach,
            $duration ? (int) $duration : null,
            $submission->seconds_per_token_snapshot
                ? (int) $submission->seconds_per_token_snapshot
                : null,
            $submission->people_per_token_snapshot
                ? (int) $submission->people_per_token_snapshot
                : null
        );
    }
}
