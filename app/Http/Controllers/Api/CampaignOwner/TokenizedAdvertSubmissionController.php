<?php

namespace App\Http\Controllers\Api\CampaignOwner;

use App\Enums\AdvertSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CampaignOwner\RolloutSubmissionRequest;
use App\Http\Requests\CampaignOwner\SubmitAdvertRequest;
use App\Http\Requests\CampaignOwner\UploadFinalDesignRequest;
use App\Http\Requests\RejectSubmissionRequest;
use App\Models\{AdvertImages, AdvertSubmission, AdvertSubmissionMedia, Campaign, TokenType, Wallet};
use App\Services\{TokenService, WalletEscrowService};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TokenizedAdvertSubmissionController extends Controller
{
    private const CREDIT_VALUE = 500;

    public function submit(SubmitAdvertRequest $r, WalletEscrowService $credits, TokenService $tokens): JsonResponse
    {
        $files = [];
        try {
            $data = DB::transaction(function () use ($r, $credits, $tokens, &$files) {
                $uid = $r->user()->id;
                $campaign = Campaign::where('owner_id', $uid)->oldest()->first();
                if (!$campaign) throw ValidationException::withMessages(['campaign' => 'No campaign found for this campaign owner.']);

                $type = $tokens->getTypeForMedia($r->type);
                $duration = $r->type === TokenType::VIDEO ? (int) $r->video_duration_seconds : null;
                $needed = $tokens->requiredTokens($type, $duration);
                $creditAmount = (float) $r->credits;

                $submission = AdvertSubmission::create([
                    'campaign_id' => $campaign->id,
                    'submitted_by' => $uid,
                    'capital_invested' => $creditAmount * self::CREDIT_VALUE,
                    'name' => $r->name,
                    'description' => $r->description,
                    'target_audience' => $r->target_audience ? json_decode($r->target_audience, true) : null,
                    'status' => AdvertSubmissionStatus::PENDING_APPROVAL,
                    'type' => $r->type,
                    'token_type_id' => $type->id,
                    'tokens_reserved' => $needed,
                    'video_duration_seconds' => $duration,
                ]);

                $tokens->reserveForSubmission($uid, $submission, $type, $needed);
                $credits->lockCreditsForCampaign($uid, $submission->id, $creditAmount);
                $this->storeSources($r, $submission, $files);

                return [
                    'submission' => $submission->fresh(['campaign', 'user', 'media', 'tokenType', 'tokenHold']),
                    'token_charge' => ['token_type' => $type->code, 'tokens_reserved' => $needed, 'video_duration_seconds' => $duration],
                ];
            });

            return response()->json(['ok' => true, 'message' => 'Advert submitted. Campaign credits and media tokens are reserved.', 'data' => $data], 201);
        } catch (ValidationException $e) {
            $this->cleanup($files);
            return response()->json(['ok' => false, 'message' => isset($e->errors()['tokens']) ? 'Insufficient media token balance.' : 'Advert submission validation failed.', 'errors' => $e->errors()], isset($e->errors()['tokens']) ? 402 : 422);
        } catch (Throwable $e) {
            $this->cleanup($files);
            $balance = str_contains(strtolower($e->getMessage()), 'insufficient');
            return response()->json(['ok' => false, 'message' => $balance ? 'Insufficient campaign credit balance.' : 'Failed to submit advert.', 'error' => $e->getMessage()], $balance ? 402 : 500);
        }
    }

    public function approve(Request $r, string $id): JsonResponse
    {
        $this->manager($r);
        try {
            $s = DB::transaction(function () use ($r, $id) {
                $s = AdvertSubmission::lockForUpdate()->findOrFail($id);
                if ($s->status !== AdvertSubmissionStatus::PENDING_APPROVAL) throw ValidationException::withMessages(['status' => 'Only PENDING_APPROVAL submissions can be approved.']);
                $s->status = $s->type === TokenType::TEXT ? AdvertSubmissionStatus::DESIGN_DONE : AdvertSubmissionStatus::PENDING_DESIGN;
                $s->reviewed_by = $r->user()->id;
                $s->reviewed_at = now();
                $s->save();
                return $s;
            });
            return response()->json(['ok' => true, 'message' => $s->type === TokenType::TEXT ? 'Text submission approved and ready for rollout.' : 'Submission approved and waiting for design.', 'data' => $s]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
    }

    public function uploadFinalDesign(UploadFinalDesignRequest $r, string $id, TokenService $tokens): JsonResponse
    {
        $this->designer($r);
        $files = [];
        try {
            $data = DB::transaction(function () use ($r, $id, $tokens, &$files) {
                $s = AdvertSubmission::with('tokenType')->lockForUpdate()->findOrFail($id);
                if ($s->status !== AdvertSubmissionStatus::PENDING_DESIGN) throw ValidationException::withMessages(['status' => 'Only PENDING_DESIGN submissions can receive a final design.']);
                if ($s->type === TokenType::IMAGE && !$r->hasFile('final_image')) throw ValidationException::withMessages(['final_image' => 'An image campaign requires a final image.']);
                if ($s->type === TokenType::VIDEO && !$r->hasFile('final_video')) throw ValidationException::withMessages(['final_video' => 'A video campaign requires a final video.']);

                if ($r->hasFile('final_image')) $s->final_image_path = $this->store($r->file('final_image'), 'submissions/final', $files);
                if ($r->hasFile('final_video')) {
                    $s->final_video_path = $this->store($r->file('final_video'), 'submissions/final', $files);
                    $s->final_thumbnail_path = $this->store($r->file('thumbnail_image'), 'submissions/final/thumbnails', $files);
                    $s->final_video_duration_seconds = (int) $r->final_video_duration_seconds;
                }
                $s->designed_by = $r->designer_id;
                $s->designed_at = now();
                $s->status = AdvertSubmissionStatus::DESIGN_DONE;
                $s->save();

                $actual = $s->tokenType ? $tokens->requiredTokens($s->tokenType, $s->type === TokenType::VIDEO ? $s->final_video_duration_seconds : null) : 0;
                return ['submission' => $s->fresh('tokenType'), 'actual_tokens_required' => $actual, 'tokens_reserved' => (int) $s->tokens_reserved];
            });
            return response()->json(['ok' => true, 'message' => 'Final design uploaded successfully.', 'data' => $data]);
        } catch (ValidationException $e) {
            $this->cleanup($files);
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        } catch (Throwable $e) {
            $this->cleanup($files);
            return response()->json(['ok' => false, 'message' => 'Failed to upload final design.', 'error' => $e->getMessage()], 500);
        }
    }

    public function reject(RejectSubmissionRequest $r, string $id, TokenService $tokens, WalletEscrowService $credits): JsonResponse
    {
        $this->manager($r);
        try {
            $s = DB::transaction(function () use ($r, $id, $tokens, $credits) {
                $s = AdvertSubmission::lockForUpdate()->findOrFail($id);
                if (in_array($s->status, [AdvertSubmissionStatus::PUBLISHED, AdvertSubmissionStatus::REJECTED], true)) throw ValidationException::withMessages(['status' => 'This submission can no longer be rejected.']);
                $s->status = AdvertSubmissionStatus::REJECTED;
                $s->rejection_reason = $r->reason;
                $s->reviewed_by = $r->user()->id;
                $s->reviewed_at = now();
                $s->save();
                if ($s->token_type_id) $tokens->releaseSubmission($s, 'Media tokens released because the submission was rejected');
                $credits->cancelCampaignHoldBySubmission($s->id, 'Campaign credits released because the submission was rejected');
                return $s->fresh(['tokenType', 'tokenHold']);
            });
            return response()->json(['ok' => true, 'message' => 'Submission rejected. Reserved tokens and campaign credits were released.', 'data' => $s]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
    }

    public function rolloutSubmission(RolloutSubmissionRequest $r, string $id, TokenService $tokens, WalletEscrowService $credits): JsonResponse
    {
        $this->manager($r);
        try {
            $data = DB::transaction(function () use ($r, $id, $tokens, $credits) {
                $s = AdvertSubmission::with(['campaign', 'tokenType'])->lockForUpdate()->findOrFail($id);
                if ($s->status !== AdvertSubmissionStatus::DESIGN_DONE) throw ValidationException::withMessages(['status' => 'Only DESIGN_DONE submissions can be rolled out.']);
                if ($s->type === TokenType::IMAGE && !$s->final_image_path) throw ValidationException::withMessages(['media' => 'Final image is missing.']);
                if ($s->type === TokenType::VIDEO && !$s->final_video_path) throw ValidationException::withMessages(['media' => 'Final video is missing.']);

                $reward = $r->filled('reward') ? (float) $r->reward : (float) $s->campaign->reward;
                if ($reward <= 0) throw ValidationException::withMessages(['reward' => 'Reward must be greater than zero.']);
                $campaignCredits = (float) $s->capital_invested / self::CREDIT_VALUE;
                $duration = $s->type === TokenType::VIDEO ? (int) ($s->final_video_duration_seconds ?: $s->video_duration_seconds) : null;
                $actualTokens = $s->tokenType ? $tokens->requiredTokens($s->tokenType, $duration) : 0;

                if ($s->tokenType) $tokens->settleSubmission($s, $actualTokens);
                $credits->settleCampaignHoldBySubmission($s->id, $campaignCredits);

                $a = new AdvertImages();
                $a->campaign_id = $s->campaign_id;
                $a->submission_id = $s->id;
                $a->image_path = $s->final_image_path;
                $a->video_path = $s->final_video_path;
                $a->name = $s->name;
                $a->target_audience = $s->target_audience;
                $a->type = $s->type;
                $a->category = $r->category ?: 'General';
                $a->badge = $r->badge;
                $a->valid_until = $r->valid_until;
                $a->reward = $reward;
                $a->description = $r->description;
                $a->capital_invested = $s->capital_invested;
                $a->capacity = (int) floor($campaignCredits);
                $a->selling_price = 0;
                $a->save();

                $s->status = AdvertSubmissionStatus::PUBLISHED;
                $s->tokens_spent = $actualTokens;
                $s->save();
                return ['submission' => $s->fresh(['tokenType', 'tokenHold']), 'advert' => $a, 'token_charge' => $s->tokenType ? ['token_type' => $s->tokenType->code, 'tokens_spent' => $actualTokens, 'video_duration_seconds' => $duration] : null];
            });
            return response()->json(['ok' => true, 'message' => 'Rolled out successfully. Tokens and campaign credits were settled.', 'data' => $data]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => isset($e->errors()['tokens']) ? 'Additional media tokens are required before rollout.' : 'Unable to roll out submission.', 'errors' => $e->errors()], isset($e->errors()['tokens']) ? 402 : 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Failed to roll out submission.', 'error' => $e->getMessage()], 500);
        }
    }

    public function dashboard(Request $r, string $userId, TokenService $tokens): JsonResponse
    {
        $response = app(AdvertSubmissionController::class)->dashboard($r, $userId);
        $payload = $response->getData(true);
        if (($payload['ok'] ?? false) && isset($payload['data'])) {
            unset($payload['data']['subscription']);
            $payload['data']['media_tokens'] = $tokens->walletSummary($userId);
            $w = Wallet::where('user_id', $userId)->first();
            $payload['data']['campaign_credits'] = ['balance' => $w ? (float) $w->balance : 0, 'locked_balance' => $w ? (float) $w->locked_balance : 0, 'total_balance' => $w ? (float) $w->total_balance : 0];
        }
        return response()->json($payload, $response->getStatusCode());
    }

    private function storeSources(SubmitAdvertRequest $r, AdvertSubmission $s, array &$files): void
    {
        $sort = 0;
        foreach ($r->file('images', []) as $f) AdvertSubmissionMedia::create(['submission_id' => $s->id, 'type' => 'IMAGE', 'path' => $this->store($f, 'submissions/original/images', $files), 'original_name' => $f->getClientOriginalName(), 'sort_order' => $sort++]);
        foreach ($r->file('videos', []) as $f) AdvertSubmissionMedia::create(['submission_id' => $s->id, 'type' => 'VIDEO', 'path' => $this->store($f, 'submissions/original/videos', $files), 'original_name' => $f->getClientOriginalName(), 'sort_order' => $sort++]);
    }

    private function store($file, string $folder, array &$files): string
    {
        $dir = public_path('storage/' . $folder);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $safe = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $file->getClientOriginalName());
        $name = time() . '_' . Str::random(8) . '_' . $safe;
        $file->move($dir, $name);
        $files[] = $dir . DIRECTORY_SEPARATOR . $name;
        return $folder . '/' . $name;
    }

    private function manager(Request $r): void
    {
        $u = $r->user();
        if (!$u || (!$u->isAdmin() && !$u->isDeveloper() && !$u->isManager())) abort(403, 'Not authorized to manage campaign submissions.');
    }

    private function designer(Request $r): void
    {
        $u = $r->user();
        if (!$u || (!$u->hasRole('designer') && !$u->isAdmin() && !$u->isDeveloper() && !$u->isManager())) abort(403, 'Not authorized to upload final designs.');
    }

    private function cleanup(array $files): void
    {
        foreach ($files as $f) if (is_file($f)) @unlink($f);
    }
}
