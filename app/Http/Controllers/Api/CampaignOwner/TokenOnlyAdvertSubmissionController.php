<?php

namespace App\Http\Controllers\Api\CampaignOwner;

use App\Enums\AdvertSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CampaignOwner\RolloutSubmissionRequest;
use App\Http\Requests\CampaignOwner\SubmitAdvertRequest;
use App\Http\Requests\CampaignOwner\UploadFinalDesignRequest;
use App\Http\Requests\RejectSubmissionRequest;
use App\Models\AdvertImages;
use App\Models\AdvertSubmission;
use App\Models\AdvertSubmissionMedia;
use App\Models\Campaign;
use App\Models\TokenType;
use App\Services\TokenService;
use App\Services\TokenUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TokenOnlyAdvertSubmissionController extends Controller
{
    public function submit(
        SubmitAdvertRequest $request,
        TokenService $tokens,
        TokenUsageService $usage
    ): JsonResponse {
        $storedFiles = [];

        try {
            $result = DB::transaction(function () use ($request, $tokens, $usage, &$storedFiles) {
                $userId = $request->user()->id;
                $campaign = Campaign::where('owner_id', $userId)->oldest()->first();

                if (!$campaign) {
                    throw ValidationException::withMessages([
                        'campaign' => 'No campaign found for this campaign owner.',
                    ]);
                }

                $tokenType = $tokens->getTypeForMedia($request->type);
                $targetReach = (int) $request->target_reach;
                $duration = $request->type === TokenType::VIDEO
                    ? (int) $request->video_duration_seconds
                    : null;

                $quote = $usage->quote($tokenType, $targetReach, $duration);

                $submission = AdvertSubmission::create([
                    'campaign_id' => $campaign->id,
                    'submitted_by' => $userId,
                    // Legacy DB column. The token-only flow never charges credits/money here.
                    'capital_invested' => 0,
                    'name' => $request->name,
                    'description' => $request->description,
                    'target_audience' => $request->target_audience
                        ? json_decode($request->target_audience, true)
                        : null,
                    'target_reach' => $targetReach,
                    'status' => AdvertSubmissionStatus::PENDING_APPROVAL,
                    'type' => $request->type,
                    'token_type_id' => $tokenType->id,
                    'tokens_reserved' => $quote['tokens_required'],
                    'media_units' => $quote['media_units'],
                    'reach_units' => $quote['reach_units'],
                    'people_per_token_snapshot' => $quote['people_per_token'],
                    'seconds_per_token_snapshot' => $quote['seconds_per_token'],
                    'video_duration_seconds' => $duration,
                ]);

                $tokens->reserveForSubmission(
                    $userId,
                    $submission,
                    $tokenType,
                    $quote['tokens_required']
                );

                $this->storeOriginalMedia($request, $submission, $storedFiles);

                return [
                    'submission' => $submission->fresh([
                        'campaign',
                        'user',
                        'media',
                        'tokenType',
                        'tokenHold',
                    ]),
                    'token_usage' => $quote,
                ];
            });

            return response()->json([
                'ok' => true,
                'message' => 'Advert submitted successfully. Required tokens have been reserved.',
                'data' => $result,
            ], 201);
        } catch (ValidationException $e) {
            $this->cleanup($storedFiles);
            $insufficient = isset($e->errors()['tokens']);

            return response()->json([
                'ok' => false,
                'message' => $insufficient
                    ? 'Insufficient token balance for this advert and target reach.'
                    : 'Advert submission validation failed.',
                'errors' => $e->errors(),
            ], $insufficient ? 402 : 422);
        } catch (Throwable $e) {
            $this->cleanup($storedFiles);

            return response()->json([
                'ok' => false,
                'message' => 'Failed to submit advert.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, string $submissionId): JsonResponse
    {
        $this->manager($request);

        try {
            $submission = DB::transaction(function () use ($request, $submissionId) {
                $submission = AdvertSubmission::lockForUpdate()->findOrFail($submissionId);

                if ($submission->status !== AdvertSubmissionStatus::PENDING_APPROVAL) {
                    throw ValidationException::withMessages([
                        'status' => 'Only PENDING_APPROVAL submissions can be approved.',
                    ]);
                }

                $submission->status = $submission->type === TokenType::TEXT
                    ? AdvertSubmissionStatus::DESIGN_DONE
                    : AdvertSubmissionStatus::PENDING_DESIGN;
                $submission->reviewed_by = $request->user()->id;
                $submission->reviewed_at = now();
                $submission->save();

                return $submission;
            });

            return response()->json([
                'ok' => true,
                'message' => $submission->type === TokenType::TEXT
                    ? 'Text submission approved and ready for rollout.'
                    : 'Submission approved and waiting for design.',
                'data' => $submission,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
    }

    public function uploadFinalDesign(
        UploadFinalDesignRequest $request,
        string $submissionId,
        TokenUsageService $usage
    ): JsonResponse {
        $this->designer($request);
        $storedFiles = [];

        try {
            $result = DB::transaction(function () use ($request, $submissionId, $usage, &$storedFiles) {
                $submission = AdvertSubmission::with('tokenType')
                    ->lockForUpdate()
                    ->findOrFail($submissionId);

                if ($submission->status !== AdvertSubmissionStatus::PENDING_DESIGN) {
                    throw ValidationException::withMessages([
                        'status' => 'Only PENDING_DESIGN submissions can receive a final design.',
                    ]);
                }

                if ($submission->type === TokenType::IMAGE && !$request->hasFile('final_image')) {
                    throw ValidationException::withMessages([
                        'final_image' => 'An image campaign requires a final image.',
                    ]);
                }

                if ($submission->type === TokenType::VIDEO && !$request->hasFile('final_video')) {
                    throw ValidationException::withMessages([
                        'final_video' => 'A video campaign requires a final video.',
                    ]);
                }

                if ($request->hasFile('final_image')) {
                    $submission->final_image_path = $this->storeFile(
                        $request->file('final_image'),
                        'submissions/final',
                        $storedFiles
                    );
                }

                if ($request->hasFile('final_video')) {
                    $submission->final_video_path = $this->storeFile(
                        $request->file('final_video'),
                        'submissions/final',
                        $storedFiles
                    );
                    $submission->final_thumbnail_path = $this->storeFile(
                        $request->file('thumbnail_image'),
                        'submissions/final/thumbnails',
                        $storedFiles
                    );
                    $submission->final_video_duration_seconds = (int) $request->final_video_duration_seconds;
                }

                $finalQuote = $usage->forSubmission(
                    $submission,
                    $submission->type === TokenType::VIDEO
                        ? (int) $submission->final_video_duration_seconds
                        : null
                );

                $submission->media_units = $finalQuote['media_units'];
                $submission->reach_units = $finalQuote['reach_units'];
                $submission->designed_by = $request->designer_id;
                $submission->designed_at = now();
                $submission->status = AdvertSubmissionStatus::DESIGN_DONE;
                $submission->save();

                return [
                    'submission' => $submission->fresh('tokenType'),
                    'token_usage' => $finalQuote,
                    'tokens_reserved' => (int) $submission->tokens_reserved,
                    'additional_tokens_needed_at_rollout' => max(
                        0,
                        $finalQuote['tokens_required'] - (int) $submission->tokens_reserved
                    ),
                ];
            });

            return response()->json([
                'ok' => true,
                'message' => 'Final design uploaded successfully.',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            $this->cleanup($storedFiles);
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        } catch (Throwable $e) {
            $this->cleanup($storedFiles);
            return response()->json([
                'ok' => false,
                'message' => 'Failed to upload final design.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reject(
        RejectSubmissionRequest $request,
        string $submissionId,
        TokenService $tokens
    ): JsonResponse {
        $this->manager($request);

        try {
            $submission = DB::transaction(function () use ($request, $submissionId, $tokens) {
                $submission = AdvertSubmission::lockForUpdate()->findOrFail($submissionId);

                if (in_array($submission->status, [
                    AdvertSubmissionStatus::PUBLISHED,
                    AdvertSubmissionStatus::REJECTED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'This submission can no longer be rejected.',
                    ]);
                }

                $submission->status = AdvertSubmissionStatus::REJECTED;
                $submission->rejection_reason = $request->reason;
                $submission->reviewed_by = $request->user()->id;
                $submission->reviewed_at = now();
                $submission->save();

                $tokens->releaseSubmission(
                    $submission,
                    'Tokens released because the advert submission was rejected'
                );

                return $submission->fresh(['tokenType', 'tokenHold']);
            });

            return response()->json([
                'ok' => true,
                'message' => 'Submission rejected. Reserved tokens were returned to the wallet.',
                'data' => $submission,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
    }

    public function rolloutSubmission(
        RolloutSubmissionRequest $request,
        string $submissionId,
        TokenService $tokens,
        TokenUsageService $usage
    ): JsonResponse {
        $this->manager($request);

        try {
            $result = DB::transaction(function () use ($request, $submissionId, $tokens, $usage) {
                $submission = AdvertSubmission::with(['campaign', 'tokenType'])
                    ->lockForUpdate()
                    ->findOrFail($submissionId);

                if ($submission->status !== AdvertSubmissionStatus::DESIGN_DONE) {
                    throw ValidationException::withMessages([
                        'status' => 'Only DESIGN_DONE submissions can be rolled out.',
                    ]);
                }

                if ($submission->type === TokenType::IMAGE && !$submission->final_image_path) {
                    throw ValidationException::withMessages(['media' => 'Final image is missing.']);
                }

                if ($submission->type === TokenType::VIDEO && !$submission->final_video_path) {
                    throw ValidationException::withMessages(['media' => 'Final video is missing.']);
                }

                $reward = $request->filled('reward')
                    ? (float) $request->reward
                    : (float) $submission->campaign->reward;

                if ($reward <= 0) {
                    throw ValidationException::withMessages([
                        'reward' => 'Reward must be greater than zero.',
                    ]);
                }

                $finalQuote = $usage->forSubmission($submission);

                $tokens->settleSubmission(
                    $submission,
                    $finalQuote['tokens_required']
                );

                $advert = new AdvertImages();
                $advert->campaign_id = $submission->campaign_id;
                $advert->submission_id = $submission->id;
                $advert->image_path = $submission->final_image_path;
                $advert->video_path = $submission->final_video_path;
                $advert->name = $submission->name;
                $advert->target_audience = $submission->target_audience;
                $advert->type = $submission->type;
                $advert->category = $request->category ?: 'General';
                $advert->badge = $request->badge;
                $advert->valid_until = $request->valid_until;
                $advert->reward = $reward;
                $advert->description = $request->description;
                // Legacy DB column retained for schema compatibility; credits are not used.
                $advert->capital_invested = 0;
                $advert->capacity = (int) $submission->target_reach;
                $advert->selling_price = 0;
                $advert->save();

                $submission->status = AdvertSubmissionStatus::PUBLISHED;
                $submission->tokens_spent = $finalQuote['tokens_required'];
                $submission->media_units = $finalQuote['media_units'];
                $submission->reach_units = $finalQuote['reach_units'];
                $submission->save();

                return [
                    'submission' => $submission->fresh(['tokenType', 'tokenHold']),
                    'advert' => $advert,
                    'token_usage' => $finalQuote,
                ];
            });

            return response()->json([
                'ok' => true,
                'message' => 'Advert rolled out successfully. Tokens have been settled.',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            $insufficient = isset($e->errors()['tokens']);

            return response()->json([
                'ok' => false,
                'message' => $insufficient
                    ? 'Additional tokens are required before this advert can be rolled out.'
                    : 'Unable to roll out submission.',
                'errors' => $e->errors(),
            ], $insufficient ? 402 : 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to roll out submission.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dashboard(
        Request $request,
        string $userId,
        TokenService $tokens
    ): JsonResponse {
        $response = app(AdvertSubmissionController::class)->dashboard($request, $userId);
        $payload = $response->getData(true);

        if (($payload['ok'] ?? false) && isset($payload['data'])) {
            unset($payload['data']['subscription']);
            unset($payload['data']['campaign_credits']);
            $payload['data']['tokens'] = $tokens->walletSummary($userId);
        }

        return response()->json($payload, $response->getStatusCode());
    }

    private function storeOriginalMedia(
        SubmitAdvertRequest $request,
        AdvertSubmission $submission,
        array &$storedFiles
    ): void {
        $sort = 0;

        foreach ($request->file('images', []) as $file) {
            AdvertSubmissionMedia::create([
                'submission_id' => $submission->id,
                'type' => TokenType::IMAGE,
                'path' => $this->storeFile($file, 'submissions/original/images', $storedFiles),
                'original_name' => $file->getClientOriginalName(),
                'sort_order' => $sort++,
            ]);
        }

        foreach ($request->file('videos', []) as $file) {
            AdvertSubmissionMedia::create([
                'submission_id' => $submission->id,
                'type' => TokenType::VIDEO,
                'path' => $this->storeFile($file, 'submissions/original/videos', $storedFiles),
                'original_name' => $file->getClientOriginalName(),
                'sort_order' => $sort++,
            ]);
        }
    }

    private function storeFile($file, string $folder, array &$storedFiles): string
    {
        $directory = public_path('storage/' . $folder);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $safe = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $file->getClientOriginalName());
        $name = time() . '_' . Str::random(8) . '_' . $safe;
        $file->move($directory, $name);

        $storedFiles[] = $directory . DIRECTORY_SEPARATOR . $name;

        return $folder . '/' . $name;
    }

    private function manager(Request $request): void
    {
        $user = $request->user();

        if (!$user || (!$user->isAdmin() && !$user->isDeveloper() && !$user->isManager())) {
            abort(403, 'Not authorized to manage campaign submissions.');
        }
    }

    private function designer(Request $request): void
    {
        $user = $request->user();

        if (!$user || (
            !$user->hasRole('designer')
            && !$user->isAdmin()
            && !$user->isDeveloper()
            && !$user->isManager()
        )) {
            abort(403, 'Not authorized to upload final designs.');
        }
    }

    private function cleanup(array $storedFiles): void
    {
        foreach ($storedFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
