<?php

namespace App\Http\Controllers\Api\CampaignOwner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationController;
use App\Http\Requests\CampaignOwner\SubmitAdvertRequest;
use App\Models\AdvertSubmission;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class AdvertSubmissionController extends Controller
{
    public function submit(SubmitAdvertRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {

                // 1️⃣ Get campaign automatically using owner_id
                $campaign = Campaign::where('owner_id', $request->user_id)
                    ->orderBy('created_at', 'asc')
                    ->first();

                if (!$campaign) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'No campaign found for this user.'
                    ], 404);
                }

                // 2️⃣ (HOOK) Subscription check
                // TODO: enforce subscription limits here

                // 3️⃣ Store original image
                $imageFile = $request->file('image');
                $imageName = time() . '_' . Str::random(8) . '.' . $imageFile->getClientOriginalExtension();
                $imageFile->move(public_path('storage/submissions'), $imageName);

                // 4️⃣ Store optional video
                $videoPath = null;
                if ($request->hasFile('video')) {
                    $videoFile = $request->file('video');
                    $videoName = time() . '_' . Str::random(8) . '.' . $videoFile->getClientOriginalExtension();
                    $videoFile->move(public_path('storage/submissions'), $videoName);
                    $videoPath = 'submissions/' . $videoName;
                }

                // 5️⃣ Create advert submission
                $submission = AdvertSubmission::create([
                    'campaign_id' => $campaign->id,
                    'submitted_by' => $request->user_id,
                    'capital_invested' => $request->capital_invested,
                    'name' => $request->name,
                    'description' => $request->description,
                    'target_audience' => $request->target_audience
                        ? json_decode($request->target_audience, true)
                        : null,
                    'original_image_path' => 'submissions/' . $imageName,
                    'original_video_path' => $videoPath,
                    'status' => 'PENDING_DESIGN',
                ]);

                $user = User::find($request->user_id);

                // 6️⃣ Send notifications
                DB::afterCommit(function () use ($campaign, $submission, $user) {

                    // Admins
                    app(NotificationController::class)->notifyRoles(new Request([
                        'roles' => ['admin'],
                        'title' => 'New Advert Submission from ' . $user->fullname . ' (Admin Review)',
                        'message' => "Please review the submission for campaign: {$campaign->name}.",
                        'type' => 'info',
                        'send_push' => true,
                        'data' => ['submission_id' => $submission->id, 'action_type' => 'admin_review'],
                    ]));

                    // Designers
                    app(NotificationController::class)->notifyRoles(new Request([
                        'roles' => ['designer'],
                        'title' => 'New Design Task',
                        'message' => "New submission waiting design for campaign: {$campaign->name}.",
                        'type' => 'info',
                        'send_push' => true,
                        'data' => ['submission_id' => $submission->id, 'action_type' => 'designer_task'],
                    ]));
                });



                return response()->json([
                    'ok' => true,
                    'message' => 'Advert submitted successfully. Awaiting design.',
                    'data' => [
                        'submission' => $submission,
                        'campaign' => $campaign,
                    ]
                ], 201);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to submit advert.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, string $userId)
    {
        try {

            $campaignIds = Campaign::where('owner_id', $userId)->pluck('id');

            if ($campaignIds->isEmpty()) {
                return response()->json([
                    'ok' => true,
                    'data' => [],
                    'meta' => [
                        'total' => 0
                    ]
                ], 200);
            }

            // 3️⃣ Filters
            $status = $request->query('status');
            $perPage = (int) $request->query('per_page', 10);

            // 4️⃣ Fetch submissions
            $query = AdvertSubmission::whereIn('campaign_id', $campaignIds)
                ->orderBy('created_at', 'desc');

            if ($status) {
                $query->where('status', $status);
            }

            $submissions = $query->paginate($perPage);

            return response()->json([
                'ok' => true,
                'data' => $submissions->items(),
                'meta' => [
                    'current_page' => $submissions->currentPage(),
                    'last_page' => $submissions->lastPage(),
                    'per_page' => $submissions->perPage(),
                    'total' => $submissions->total(),
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch advert submissions.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
