<?php

namespace App\Http\Controllers\Api\CampaignOwner;

use App\Models\AdvertSubmission;
use App\Models\AdvertSubmissionMedia;
use App\Enums\AdvertSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationController;
use App\Http\Requests\CampaignOwner\SubmitAdvertRequest;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Requests\CampaignOwner\UploadFinalDesignRequest;
use App\Http\Requests\CampaignOwner\RolloutSubmissionRequest;
use App\Models\AdvertImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;





class AdvertSubmissionController extends Controller
{

    public function submit(SubmitAdvertRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {

                $campaign = Campaign::where('owner_id', $request->user_id)
                    ->orderBy('created_at', 'asc')
                    ->first();

                if (!$campaign) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'No campaign found for this user.'
                    ], 404);
                }

                // Must have at least 1 file
                $hasImages = $request->hasFile('images');
                $hasVideos = $request->hasFile('videos');
                if (!$hasImages && !$hasVideos) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Upload at least one image or video.',
                    ], 422);
                }

                // Create submission first (no original_* single fields anymore needed)
                $submission = AdvertSubmission::create([
                    'campaign_id' => $campaign->id,
                    'submitted_by' => $request->user_id,
                    'capital_invested' => $request->capital_invested,
                    'name' => $request->name,
                    'description' => $request->description,
                    'target_audience' => $request->target_audience
                        ? json_decode($request->target_audience, true)
                        : null,
                    'status' => AdvertSubmissionStatus::PENDING_APPROVAL,
                ]);

                $sort = 0;

                // Save multiple images
                if ($hasImages) {
                    foreach ($request->file('images') as $img) {
                        $original = $img->getClientOriginalName();
                        $safe = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $original);
                        $name = time() . '_' . Str::random(6) . '_' . $safe;

                        $img->move(public_path('storage/submissions/original/images'), $name);

                        AdvertSubmissionMedia::create([
                            'submission_id' => $submission->id,
                            'type' => 'IMAGE',
                            'path' => 'submissions/original/images/' . $name,
                            'original_name' => $original,
                            'sort_order' => $sort++,
                        ]);
                    }
                }

                // Save multiple videos
                if ($hasVideos) {
                    foreach ($request->file('videos') as $vid) {
                        $original = $vid->getClientOriginalName();
                        $safe = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $original);
                        $name = time() . '_' . Str::random(6) . '_' . $safe;

                        $vid->move(public_path('storage/submissions/original/videos'), $name);

                        AdvertSubmissionMedia::create([
                            'submission_id' => $submission->id,
                            'type' => 'VIDEO',
                            'path' => 'submissions/original/videos/' . $name,
                            'original_name' => $original,
                            'sort_order' => $sort++,
                        ]);
                    }
                }

                $submission->load(['campaign', 'user', 'media']);

                // notifications (same as your code)
                $user = User::find($request->user_id);
                DB::afterCommit(function () use ($campaign, $submission, $user) {
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
                    'message' => 'Advert submitted successfully. Pending approval.',
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
                    'meta' => ['total' => 0],
                ], 200);
            }

            $status  = $request->query('status');
            $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

            $query = AdvertSubmission::query()
                ->whereIn('campaign_id', $campaignIds)
                ->with([
                    'campaign',
                    'user',
                    'media',
                ])
                ->orderBy('created_at', 'desc');

            if (!empty($status)) {
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
                    'media',
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
                        'roles' => ['admin'],
                        'title' => 'Design Completed',
                        'message' => "Your advert design is ready for campaign: {$submission->campaign->name}.",
                        'type' => 'success',
                        'send_push' => true,
                        'data' => [
                            'submission_id' => $submission->id,
                            'action_type' => 'design_done'
                        ],
                    ]));

                    app(NotificationController::class)->notifyUser(new Request([
                        'userId' => [$submission->submitted_by],
                        'title' => 'Design Completed',
                        'message' => "Your advert design is ready for campaign: {$submission->name}.",
                        'type' => 'success',
                        'send_push' => true,
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
                app(NotificationController::class)->notifyUser(new Request([
                    'userId' => [$submission->submitted_by],
                    'title' => "Design for {$submission->name} submitted",
                    'message' => "Your submission was approved for advert: {$submission->name}.",
                    'type' => 'info',
                    'send_push' => true,
                    'data' => ['submission_id' => $submission->id, 'action_type' => 'approved_task'],
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
            $validator = Validator::make($request->all(), [
                'reason'  => 'required|string|min:5|max:1000',
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            $user = User::find($validated['user_id']);

            if (! $user || ! $user->hasRole('admin')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'You are not authorized to reject this submission.',
                ], 403);
            }

            $submission = AdvertSubmission::with('campaign')->findOrFail($submissionId);

            if ($submission->status !== AdvertSubmissionStatus::PENDING_APPROVAL) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Only PENDING_APPROVAL submissions can be rejected.',
                ], 422);
            }

            $submission->status = AdvertSubmissionStatus::REJECTED;
            $submission->rejection_reason = $validated['reason'];
            $submission->reviewed_by = $validated['user_id'];
            $submission->reviewed_at = now();
            $submission->save();

            DB::afterCommit(function () use ($submission) {
                app(NotificationController::class)->notifyUser(new Request([
                    'userId' => [$submission->submitted_by],
                    'title' => 'Submission Rejected',
                    'message' => "Your submission for '{$submission->campaign->name}' was rejected.\nReason: {$submission->rejection_reason}",
                    'type' => 'warning',
                    'send_push' => true,
                    'data' => [
                        'submission_id' => $submission->id,
                        'action_type' => 'rejected',
                    ],
                ]));
            });

            return response()->json([
                'ok' => true,
                'message' => 'Submission rejected successfully.',
                'data' => [
                    'submission_id' => $submission->id,
                    'status' => $submission->status,
                    'rejection_reason' => $submission->rejection_reason,
                ],
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
                app(NotificationController::class)->notifyUser(new Request([
                    'userId' => [$submission->submitted_by],
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
                $advert->target_audience  = $submission->target_audience;

                // From rollout request
                $advert->category         = $request->category;
                $advert->badge            = $request->badge;
                $advert->valid_until      = $request->valid_until;
                $advert->capacity         = $request->capacity;
                $advert->reward           = $reward;
                $advert->capital_invested = $request->capital_invested;
                $advert->description      = $request->description;

                $advert->selling_price    = 0;

                $advert->save();

                // Update submission status -> POSTED
                $submission->status = AdvertSubmissionStatus::PUBLISHED;
                $submission->save();


                DB::afterCommit(function () use ($submission, $advert) {
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
                            'submission_id' => $submission->id,
                            'action_type' => 'rollout_posted',
                        ],
                    ]);

                    // app(NotificationController::class)->notifyAllUsers($notifReq);

                    app(NotificationController::class)->notifyUser(new Request([
                        'userId' => [$submission->submitted_by],
                        'title' => 'Submission Rolled Out (Posted)',
                        'message' => "Submission rolled out (posted) for campaign: {$submission->name}.",
                        'type' => 'info',
                        'send_push' => true,
                        'data' => ['submission_id' => $submission->id, 'action_type' => 'rolled_out'],
                    ]));
                });

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

    public function pendingDesign(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);

        $query = AdvertSubmission::query()
            ->where('status', AdvertSubmissionStatus::PENDING_DESIGN)
            ->orderBy('created_at', 'desc');

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->query('campaign_id'));
        }

        if ($request->filled('submitted_by')) {
            $query->where('submitted_by', $request->query('submitted_by'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $query->with([
            'campaign',
            'user.campaignOwnerProfile.logos',
            'media',
        ]);

        $submissions = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => $submissions->items(),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
                'last_page' => $submissions->lastPage(),
            ],
        ], 200);
    }
}
