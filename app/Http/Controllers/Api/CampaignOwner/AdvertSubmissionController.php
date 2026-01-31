<?php

namespace App\Http\Controllers\Api\CampaignOwner;

use App\Enums\AdvertSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationController;
use App\Http\Requests\CampaignOwner\SubmitAdvertRequest;
use App\Models\AdvertSubmission;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Requests\CampaignOwner\UploadFinalDesignRequest;
use App\Http\Requests\CampaignOwner\RolloutSubmissionRequest;
use App\Models\AdvertImages;
use Illuminate\Http\JsonResponse;




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
                    'status' => AdvertSubmissionStatus::PENDING_APPROVAL,
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

    public function indexAll(Request $request)
    {
        try {
            $status  = $request->query('status');
            $perPage = (int) $request->query('per_page', 15);
            $perPage = max(1, min(100, $perPage));

            $query = AdvertSubmission::query()
                ->with([
                    'user',
                    'campaign',
                ])
                ->orderBy('created_at', 'desc');

            if (!empty($status)) {
                $query->where('status', $status);
            }

            $submissions = $query->paginate($perPage);

            // add absolute URLs (optional but useful)
            $items = collect($submissions->items())->map(function ($s) {
                $s->original_image_url = $s->original_image_path
                    ? asset('storage/' . $s->original_image_path)
                    : null;

                $s->original_video_url = $s->original_video_path
                    ? asset('storage/' . $s->original_video_path)
                    : null;

                return $s;
            });

            return response()->json([
                'ok' => true,
                'data' => $items,
                'meta' => [
                    'current_page' => $submissions->currentPage(),
                    'last_page'    => $submissions->lastPage(),
                    'per_page'     => $submissions->perPage(),
                    'total'        => $submissions->total(),
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch all advert submissions.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }



    public function uploadFinalDesign(UploadFinalDesignRequest $request, string $submissionId)
    {
        try {
            $submission = AdvertSubmission::with(['user', 'campaign'])->findOrFail($submissionId);

            if ($submission->status !== AdvertSubmissionStatus::PENDING_DESIGN) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Only PENDING_DESIGN submissions can be completed.',
                ], 422);
            }

            if (!$request->hasFile('final_image') && !$request->hasFile('final_video')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Please upload at least final_image or final_video.',
                ], 422);
            }

            if ($request->hasFile('final_image')) {
                $img = $request->file('final_image');
                $imgName = time() . '_' . Str::random(8) . '.' . $img->getClientOriginalExtension();
                $img->move(public_path('storage/submissions/final'), $imgName);
                $submission->final_image_path = 'submissions/final/' . $imgName;
            }

            if ($request->hasFile('final_video')) {
                $vid = $request->file('final_video');
                $vidName = time() . '_' . Str::random(8) . '.' . $vid->getClientOriginalExtension();
                $vid->move(public_path('storage/submissions/final'), $vidName);
                $submission->final_video_path = 'submissions/final/' . $vidName;
            }

            if ($request->filled('designer_id')) {
                $submission->designed_by = $request->designer_id;
            }

            $submission->status = AdvertSubmissionStatus::DESIGN_DONE;
            $submission->save();

            DB::afterCommit(function () use ($submission) {
                $campaignOwnerId = optional($submission->campaign)->owner_id;

                if ($campaignOwnerId) {
                    app(NotificationController::class)->notifyRoles(new Request([
                        'roles' => ['admin', 'campaign_owner'],
                        'user_ids' => [$campaignOwnerId],
                        'title' => 'Design Completed',
                        'message' => "Your advert design is ready for campaign: {$submission->campaign->name}.",
                        'type' => 'success',
                        'send_push' => true,
                        'data' => [
                            'submission_id' => $submission->id,
                            'action_type' => 'design_done'
                        ],
                    ]));
                }
            });

            $submission->final_image_url = $submission->final_image_path ? asset('storage/' . $submission->final_image_path) : null;
            $submission->final_video_url = $submission->final_video_path ? asset('storage/' . $submission->final_video_path) : null;

            return response()->json([
                'ok' => true,
                'message' => 'Final design uploaded successfully.',
                'data' => $submission,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to upload final design.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, string $submissionId)
    {
        try {
            $submission = AdvertSubmission::with(['campaign', 'user'])->findOrFail($submissionId);

            if ($submission->status !== AdvertSubmissionStatus::PENDING_APPROVAL) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Only PENDING_APPROVAL submissions can be approved.',
                ], 422);
            }

            $submission->status = AdvertSubmissionStatus::PENDING_DESIGN;
            $submission->save();

            DB::afterCommit(function () use ($submission) {
                app(NotificationController::class)->notifyRoles(new Request([
                    'roles' => ['designer'],
                    'title' => 'New Design Task',
                    'message' => "New submission waiting design for campaign: {$submission->campaign->name}.",
                    'type' => 'info',
                    'send_push' => true,
                    'data' => ['submission_id' => $submission->id, 'action_type' => 'designer_task'],
                ]));
            });

            return response()->json([
                'ok' => true,
                'message' => 'Submission approved. Now waiting for design.',
                'data' => $submission,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to approve submission.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, string $submissionId)
    {
        try {
            $submission = AdvertSubmission::findOrFail($submissionId);


            if ($submission->status !== AdvertSubmissionStatus::PENDING_APPROVAL) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Only PENDING_APPROVAL submissions can be rejected.',
                ], 422);
            }

            $submission->status = AdvertSubmissionStatus::REJECTED;
            $submission->save();

            DB::afterCommit(function () use ($submission) {
                app(NotificationController::class)->notifyRoles(new Request([
                    'roles' => ['campaign_owner'],
                    'title' => 'Submission Rejected',
                    'message' => "Submission rejected for campaign: {$submission->campaign->name}.",
                    'type' => 'warning',
                    'send_push' => true,
                    'data' => ['submission_id' => $submission->id, 'action_type' => 'rejected'],
                ]));
            });

            return response()->json([
                'ok' => true,
                'message' => 'Submission rejected.',
                'data' => $submission,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to reject submission.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }



    public function rolloutPost(Request $request, string $submissionId)
    {
        try {
            $submission = AdvertSubmission::findOrFail($submissionId);

            if ($submission->status !== AdvertSubmissionStatus::DESIGN_DONE) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Only PUBLISHED submissions can be rolled out (posted).',
                ], 422);
            }

            $submission->status = AdvertSubmissionStatus::PUBLISHED;
            $submission->save();

            DB::afterCommit(function () use ($submission) {
                app(NotificationController::class)->notifyRoles(new Request([
                    'roles' => ['campaign_owner'],
                    'title' => 'Submission Rolled Out (Posted)',
                    'message' => "Submission rolled out (posted) for campaign: {$submission->campaign->name}.",
                    'type' => 'info',
                    'send_push' => true,
                    'data' => ['submission_id' => $submission->id, 'action_type' => 'rolled_out'],
                ]));
            });

            return response()->json([
                'ok' => true,
                'message' => 'Submission rolled out (posted).',
                'data' => $submission,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to roll out (post) submission.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function rolloutSubmission(RolloutSubmissionRequest $request, string $submissionId)
    {
        try {
            return DB::transaction(function () use ($request, $submissionId) {

                $submission = AdvertSubmission::with(['campaign'])->findOrFail($submissionId);

                // Must be published
                if ($submission->status !== AdvertSubmissionStatus::DESIGN_DONE) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Only PUBLISHED submissions can be rolled out (posted).',
                        'status' => $submission->status,
                    ], 422);
                }

                // Must have final media
                if (empty($submission->final_image_path) && empty($submission->final_video_path)) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Final design media missing. Upload final image/video first.',
                    ], 422);
                }

                $campaign = $submission->campaign;

                // Reward priority:
                // 1) request.reward
                // 2) campaign.reward
                $reward = $request->filled('reward') ? $request->reward : $campaign->reward;

                // Create advert post (AdvertImages)
                $advert = new AdvertImages();
                $advert->campaign_id      = $campaign->id;
                $advert->submission_id = $submission->id;

                // Final designed media
                $advert->image_path       = $submission->final_image_path;
                $advert->video_path       = $submission->final_video_path;

                // From submission
                $advert->name             = $submission->name;
                $advert->description      = $submission->description;
                $advert->target_audience  = $submission->target_audience;

                // From rollout request
                $advert->category         = $request->category;
                $advert->badge            = $request->badge;
                $advert->valid_until      = $request->valid_until;
                $advert->capacity         = $request->capacity;
                $advert->reward           = $reward;
                $advert->capital_invested = $request->capital_invested;

                $advert->selling_price    = 0;

                $advert->save();

                // Update submission status -> POSTED
                $submission->status = AdvertSubmissionStatus::PUBLISHED;
                $submission->save();

                // Notify users
                $title = '📢 New Product posted!';
                $body  = "🔥 {$advert->name} is now live. Post it to your WhatsApp Status and earn ksh.{$advert->reward}";

                $notifReq = new Request([
                    'title' => $title,
                    'message' => $body,
                    'type' => 'info',
                    'send_push' => true,
                    'data' => [
                        'advert_id' => $advert->id,
                        'campaign_id' => $campaign->id,
                        'submission_id' => $submission->id,
                        'action_type' => 'rollout_posted',
                    ],
                ]);

                // app(NotificationController::class)->notifyAllUsers($notifReq);

                return response()->json([
                    'ok' => true,
                    'message' => 'Rolled out successfully. Submission is now POSTED.',
                    'data' => [
                        'submission' => $submission,
                        'advert' => $advert,
                    ],
                ], 200);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to roll out submission.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function dashboard(Request $request, string $userId): JsonResponse
    {
        // 1) Validate user exists
        $campaignOwner = User::where('id', $userId)->firstOrFail();

        // Optional: ensure user is campaign_owner role
        if (! $campaignOwner->hasRole('campaign_owner')) {
            return response()->json([
                'ok' => false,
                'message' => 'User is not a campaign owner',
            ], 403);
        }

        /**
         * 2) ACTIVE SUBSCRIPTION
         */
        $subscription = DB::table('user_subscriptions as us')
            ->join('subscription_plans as sp', 'sp.id', '=', 'us.plan_id')
            ->where('us.user_id', $userId)
            ->where('us.status', 'ACTIVE')
            ->where('us.starts_at', '<=', now())
            ->where('us.ends_at', '>=', now())
            ->select(
                'us.id as subscription_id',
                'us.starts_at',
                'us.ends_at',
                'us.auto_renew',
                'sp.code',
                'sp.name',
                'sp.billing_period',
                'sp.limits',
                'sp.features'
            )
            ->first();

        if ($subscription) {
            $subscription->limits = is_string($subscription->limits) ? json_decode($subscription->limits, true) : $subscription->limits;
            $subscription->features = is_string($subscription->features) ? json_decode($subscription->features, true) : $subscription->features;
        }

        /**
         * 3) Campaign IDs owned by this user
         * ✅ campaigns.owner_id exists in your codebase
         */
        $campaignIds = DB::table('campaigns')
            ->where('owner_id', $userId)
            ->pluck('id');

        if ($campaignIds->isEmpty()) {
            return response()->json([
                'ok' => true,
                'data' => [
                    'campaign_owner' => [
                        'id' => $campaignOwner->id,
                        'fullname' => $campaignOwner->fullname,
                        'phone' => $campaignOwner->phone,
                        'email' => $campaignOwner->email,
                    ],
                    'subscription' => $subscription,
                    'adverts' => [
                        'total' => 0,
                        'active' => 0,
                        'inactive' => 0,
                    ],
                    'submissions' => [
                        'total' => 0,
                        'pending' => 0,
                        'approved' => 0,
                        'rejected' => 0,
                        'completed' => 0,
                    ],
                    'top_active_adverts' => [],
                ],
            ]);
        }

        /**
         * 4) ADVERT STATS (adverts under these campaigns)
         * ✅ FIX: no owner_id/status on advert_images
         * Active = valid_until >= now()
         */
        $advertStats = DB::table('advert_images')
            ->whereIn('campaign_id', $campaignIds)
            ->selectRaw("
                COUNT(*) as total_adverts,
                SUM(CASE WHEN valid_until IS NOT NULL AND valid_until >= NOW() THEN 1 ELSE 0 END) as active_adverts,
                SUM(CASE WHEN valid_until IS NULL OR valid_until < NOW() THEN 1 ELSE 0 END) as inactive_adverts
            ")
            ->first();

        /**
         * 5) SUBMISSION STATS (submissions for those campaigns)
         */
        $submissionStats = DB::table('advert_submissions')
            ->whereIn('campaign_id', $campaignIds)
            ->selectRaw("
                COUNT(*) as total_submissions,
                SUM(status = 'PENDING') as pending,
                SUM(status = 'APPROVED') as approved,
                SUM(status = 'REJECTED') as rejected,
                SUM(status IN ('ROLLED_OUT','ROLLOUT','COMPLETED')) as completed
            ")
            ->first();

        /**
         * 6) TOP ACTIVE ADVERTS BY VIEWS
         * Join screenshots -> advert_images and filter by campaign_id list
         */
        $topActiveAdverts = DB::table('screenshots as sc')
            ->join('advert_images as a', 'a.id', '=', 'sc.advert_id')
            ->whereIn('a.campaign_id', $campaignIds)
            ->whereNotNull('a.valid_until')
            ->where('a.valid_until', '>=', now())
            ->selectRaw("
                a.id,
                a.name,
                a.campaign_id,
                COUNT(sc.id) as screenshots_count,
                COUNT(DISTINCT sc.processed_by) as unique_posters,
                SUM(sc.views) as total_views
            ")
            ->groupBy('a.id', 'a.name', 'a.campaign_id')
            ->orderByDesc('total_views')
            ->limit(5)
            ->get();

        /**
         * 7) LAST 5 ADVERTS FROM HIS SUBMISSIONS
         * - get latest 5 submissions by this owner (campaign_id in owner's campaigns)
         * - attach advert_images row where submission_id = submission.id (if created)
         */
        $latestSubmittedAdverts = DB::table('advert_submissions as s')
            ->leftJoin('advert_images as a', 'a.submission_id', '=', 's.id')
            ->whereIn('s.campaign_id', $campaignIds)
            ->orderBy('s.created_at', 'desc')
            ->limit(5)
            ->get([
                's.id as submission_id',
                's.campaign_id',
                's.name as submission_name',
                's.status as submission_status',
                's.created_at as submitted_at',

                's.final_image_path',
                's.final_video_path',

                'a.id as advert_id',
                'a.name as advert_name',
                'a.image_path as advert_image_path',
                'a.valid_until',
                'a.capacity',
                'a.reward',
            ])
            ->map(function ($row) {
                // Add urls for submission media
                $row->final_image_url = $row->final_image_path ? asset('storage/' . $row->final_image_path) : null;
                $row->final_video_url = $row->final_video_path ? asset('storage/' . $row->final_video_path) : null;

                // Add url for advert image (if advert exists)
                $row->advert_image_url = $row->advert_image_path ? asset('storage/' . $row->advert_image_path) : null;

                // Active status derived from valid_until
                $row->advert_is_active = $row->valid_until ? (now()->lte(\Carbon\Carbon::parse($row->valid_until))) : false;

                return $row;
            });


        return response()->json([
            'ok' => true,
            'data' => [
                'campaign_owner' => [
                    'id' => $campaignOwner->id,
                    'fullname' => $campaignOwner->fullname,
                    'phone' => $campaignOwner->phone,
                    'email' => $campaignOwner->email,
                ],

                'subscription' => $subscription,

                'adverts' => [
                    'total' => (int) ($advertStats->total_adverts ?? 0),
                    'active' => (int) ($advertStats->active_adverts ?? 0),
                    'inactive' => (int) ($advertStats->inactive_adverts ?? 0),
                ],

                'submissions' => [
                    'total' => (int) ($submissionStats->total_submissions ?? 0),
                    'pending' => (int) ($submissionStats->pending ?? 0),
                    'approved' => (int) ($submissionStats->approved ?? 0),
                    'rejected' => (int) ($submissionStats->rejected ?? 0),
                    'completed' => (int) ($submissionStats->completed ?? 0),
                ],

                'top_active_adverts' => $topActiveAdverts,
                'latest_submitted_adverts' => $latestSubmittedAdverts,

            ],
        ]);
    }
}
