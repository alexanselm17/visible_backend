<?php

namespace App\Repositories\Products;

use App\Exceptions\ScreenshotVerificationException;
use App\Http\Controllers\NotificationController;
use App\Http\Requests\ProductAdvertRequest;
use App\Http\Requests\RepostAdvertRequest;
use App\Http\Requests\StartCampaignRequest;
use App\Models\AdvertImages;
use App\Models\Campaign;
use App\Models\RewardLedgerEntry;
use App\Models\RewardPayout;
use App\Models\Screenshots;
use App\Models\User;
use App\Services\AdvertQrCodeService;
use App\Services\ImageDecoderService;
use App\Services\RewardPeriodService;
use App\Services\ScreenshotVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductRepository implements ProductRepositoryInterface
{
    public function updateAdvertProduct(ProductAdvertRequest $request, $advertId)
    {
        try {
            // Find the advert record to update
            $advert = AdvertImages::findOrFail($advertId);

            // Handle image update (optional)
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $originalImageName = $imageFile->getClientOriginalName();
                $sanitizedImageName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $originalImageName);
                $imageFilename = time() . '_' . $sanitizedImageName;
                $imageFile->move(public_path('storage/uploads'), $imageFilename);
                $advert->image_path = 'uploads/' . $imageFilename;
            }

            // Handle video update (optional)
            if ($request->hasFile('video')) {
                $videoFile = $request->file('video');
                $originalVideoName = $videoFile->getClientOriginalName();
                $sanitizedVideoName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $originalVideoName);
                $videoFilename = time() . '_' . $sanitizedVideoName;
                $videoFile->move(public_path('storage/uploads'), $videoFilename);
                $advert->video_path = 'uploads/' . $videoFilename;
            }

            // Update other fields
            $advert->category = $request->input('category', $advert->category);
            $advert->name = $request->input('name', $advert->name);
            $advert->description = $request->input('description', $advert->description);
            $advert->badge = $request->input('badge', $advert->badge);

            $advert->capital_invested = $request->input('capital_invested', $advert->capital_invested);
            $advert->valid_until = $request->input('valid_until', $advert->valid_until);
            $advert->capacity = $request->input('capacity', $advert->capacity);

            // Save updated advert
            $advert->save();

            return response()->json([
                'ok' => true,
                'status' => 'Success',
                'message' => 'Advert updated successfully!',
                'data' => $advert,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Advert not found.',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function updateCampaign(Request $request, $id)
    {
        try {
            // Find the campaign by ID
            $campaign = Campaign::findOrFail($id);

            // Update the campaign with validated input
            $campaign->update([
                'name' => $request->input('name', $campaign->name),
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Campaign updated successfully.',
                'data' => $campaign,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Campaign not found.',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to update campaign.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function startCampaigns(StartCampaignRequest $request)
    {
        try {
            // Create and save the campaign
            $campaign = Campaign::create([
                'name' => $request->input('name'),
                'owner_id' => $request->input('user_id'),

            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Campaign started successfully.',
                'data' => $campaign,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to start campaign.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCampaigns(Request $request)
    {
        try {
            $campaigns = Campaign::with('adverts.screenshots')->get();

            $campaigns = $campaigns->map(function ($campaign) {
                $completed = 0;
                $ongoing = 0;
                $now = Carbon::now('Africa/Nairobi');
                $threshold = $now->copy()->subDay();

                foreach ($campaign->adverts as $advert) {
                    foreach ($advert->screenshots->whereNotNull('processed_by')->groupBy('processed_by') as $screenshots) {
                        if ($screenshots->contains('number', 2)) {
                            $completed++;

                            continue;
                        }

                        $firstScreenshotTime = $screenshots->sortBy('created_at')->first()?->created_at;
                        if ($firstScreenshotTime && $firstScreenshotTime >= $threshold) {
                            $ongoing++;
                        }
                    }
                }

                $available = max(0, $campaign->adverts->sum('capacity') - ($completed + $ongoing));

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'completed' => $completed,
                    'ongoing' => $ongoing,
                    'available' => $available,
                    'created_at' => $campaign->created_at,
                ];
            });

            return response()->json([
                'ok' => true,
                'message' => 'Campaigns fetched successfully.',
                'data' => $campaigns,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch campaigns.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAdvertProducts(Request $request)
    {
        try {
            $userId = (string) $request->user()->id;
            $status = $request->query('status');

            if (! $status) {
                return response()->json([
                    'message' => 'Status is required in the query parameters.',
                ], 400);
            }

            $user = User::with(['county', 'subCounty'])->find($userId);
            if (! $user) {
                return response()->json([
                    'message' => 'User not found.',
                ], 404);
            }

            $adverts = AdvertImages::query();

            if ($status === 'available') {
                $adverts->where('advert_images.valid_until', '>', Carbon::now('Africa/Nairobi'));

                $adverts->whereDoesntHave('screenshots', function ($query) use ($userId) {
                    $query->where('processed_by', $userId);
                });

                $adverts->where(function ($query) use ($user) {
                    // 1. If target_audience is null, allow all records (target everyone)
                    $query->whereNull('target_audience')

                        // 2. If gender is set, filter by gender
                        ->orWhere(function ($subQuery) use ($user) {
                            if ($user->gender) {
                                $subQuery->whereJsonContains('target_audience->gender', $user->gender);
                            }
                        })

                        // 3. If county_id is set, filter by county
                        ->orWhere(function ($subQuery) use ($user) {
                            if ($user->county_id) {
                                $subQuery->whereJsonContains('target_audience->county_id', $user->county_id);
                            }
                        })

                        // 4. If subcounty_id is set, filter by subcounty
                        ->orWhere(function ($subQuery) use ($user) {
                            if ($user->subcounty_id) {
                                $subQuery->whereJsonContains('target_audience->subcounty_id', $user->subcounty_id);
                            } else {
                                // If subcounty is not provided, allow entries where subcounty is null
                                $subQuery->orWhereNull('target_audience->subcounty_id');
                            }
                        });
                });
            }

            if ($status === 'ongoing') {
                $ongoingThreshold = Carbon::now('Africa/Nairobi')->subDay()->toDateTimeString();

                $adverts->whereRaw('
                    (
                        SELECT MIN(created_at)
                        FROM screenshots
                        WHERE screenshots.advert_id = advert_images.id
                        AND screenshots.processed_by = ?
                    ) >= ?
                ', [$userId, $ongoingThreshold])
                    ->withCount(['screenshots as user_screenshot_count' => function ($query) use ($userId) {
                        $query->where('processed_by', $userId);
                    }])
                    ->having('user_screenshot_count', '<', 2);
            }

            if ($status === 'completed') {
                $adverts->whereHas('screenshots', function ($query) use ($userId) {
                    $query->where('processed_by', $userId)->where('number', 2);
                })
                    ->withCount(['screenshots as user_screenshot_count' => function ($query) use ($userId) {
                        $query->where('processed_by', $userId);
                    }])
                    ->orderBy('created_at', 'desc')
                    ->limit(20);
            }

            $adverts = $adverts
                ->with([
                    'screenshots' => function ($query) use ($userId) {
                        $query->where('processed_by', $userId)
                            ->orderBy('created_at', 'asc');
                    },
                    'campaign:id',
                ])
                ->paginate(10);

            return response()->json([
                'message' => 'Adverts retrieved successfully.',
                'data' => $adverts->through(function ($advert) use ($status) {
                    $imagePath = 'storage/' . $advert->image_path;
                    $screenshot = $advert->screenshots->first();
                    $screenshotPath = $screenshot?->screenshot ? 'storage/' . $screenshot->screenshot : null;

                    $screenshotCount = match ($status) {
                        'available' => 0,
                        'completed' => 2,
                        default => $advert->user_screenshot_count ?? 0,
                    };

                    return [
                        'id' => $advert->id,
                        'category' => $advert->category,
                        'created_at' => $advert->created_at,
                        'name' => $advert->name,
                        'badge' => $advert->badge,
                        'type' => $advert->type,
                        'description' => $advert->description,
                        'capacity' => $advert->capacity,
                        'updated_at' => $advert->updated_at,
                        'valid_until' => $advert->valid_until,
                        'image_path' => $advert->image_path,
                        'image_url' => asset($imagePath),
                        'download_url' => route('download.advert.personalized', ['advertId' => $advert->id]),
                        'video_path' => $advert->video_path,
                        'video_url' => $advert->video_path ? asset('storage/' . $advert->video_path) : null,
                        'video_download_url' => $advert->video_path ? route('download.advert.image', ['path' => $advert->video_path]) : null,
                        'user_screenshot' => $screenshot?->screenshot,
                        'screenshot_url' => $screenshotPath ? asset($screenshotPath) : null,
                        'screenshot_id' => $screenshot?->id,
                        'screenshot_count' => $screenshotCount,
                        'all_screenshots' => $advert->screenshots->map(function ($ss) {
                            return [
                                'views' => $ss->views,
                                'created_at' => $ss->created_at,
                            ];
                        })->values(),
                    ];
                }),
                'pagination' => [
                    'total' => $adverts->total(),
                    'per_page' => $adverts->perPage(),
                    'current_page' => $adverts->currentPage(),
                    'last_page' => $adverts->lastPage(),
                    'from' => $adverts->firstItem(),
                    'to' => $adverts->lastItem(),
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function uploadAdvertProducts(ProductAdvertRequest $request, $campaignId)
    {
        try {
            // Check if image file exists
            if (! $request->hasFile('image')) {
                return response()->json(['message' => 'No image uploaded.'], 400);
            }

            // Prepare the image
            $imageFile = $request->file('image');
            $originalImageName = $imageFile->getClientOriginalName();
            $sanitizedImageName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $originalImageName);
            $imageFilename = time() . '_' . $sanitizedImageName;
            $imageFile->move(public_path('storage/uploads'), $imageFilename);

            // Prepare the optional video
            $videoPath = null;
            if ($request->hasFile('video')) {
                $videoFile = $request->file('video');
                $originalVideoName = $videoFile->getClientOriginalName();
                $sanitizedVideoName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $originalVideoName);
                $videoFilename = time() . '_' . $sanitizedVideoName;
                $videoFile->move(public_path('storage/uploads'), $videoFilename);
                $videoPath = 'uploads/' . $videoFilename;
            }

            // Get campaign
            $campaign = Campaign::findOrFail($campaignId);

            // Save to DB
            $advert = new AdvertImages;
            $advert->image_path = 'uploads/' . $imageFilename;
            $advert->video_path = $videoPath;
            $advert->category = $request->category;
            $advert->name = $request->name;
            $advert->description = $request->description;
            $advert->badge = $request->badge;
            $advert->selling_price = 0;
            $advert->campaign_id = $campaignId;
            $advert->capital_invested = $request->capital_invested;
            $advert->valid_until = $request->valid_until;
            $advert->capacity = $request->capacity;
            $advert->target_audience = json_decode($request->target_audience, true);
            $advert->type = $request->type;
            $advert->save();

            // Notify users
            $title = '📢 New Product posted!';
            $body = "🔥 {$advert->name} is now live. Post it to your WhatsApp Status to grow your performance earnings.";
            $request->merge([
                'title' => $title,
                'message' => $body,
                'type' => 'info',
                'send_push' => true,
            ]);

            app(NotificationController::class)->notifyAllUsers($request);

            return response()->json([
                'ok' => true,
                'status' => 'Success',
                'message' => 'Advert uploaded successfully!',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function repostAdvertProducts(RepostAdvertRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {

                $original = AdvertImages::findOrFail($request->advert_id);

                $data = $original->only([
                    'image_path',
                    'video_path',
                    'category',
                    'name',
                    'selling_price',
                    'campaign_id',
                    'type',
                    'description',
                    'badge',
                    'capital_invested',
                    'valid_until',
                    'capacity',
                    'target_audience',
                    'submission_id',
                ]);

                // New advert UUID
                $data['id'] = (string) Str::uuid();

                // Mark lineage
                $data['repost_of_id'] = $original->id;

                // ✅ Reuse same submission_id (no new submission created)
                $data['submission_id'] = $original->submission_id;

                // Apply overrides only when provided
                foreach ($request->validated() as $key => $value) {
                    if ($key !== 'advert_id' && $request->has($key)) {
                        $data[$key] = $value;
                    }
                }

                $newAdvert = AdvertImages::create($data);

                // ✅ Notify users (same style as upload)
                $title = '📢 Product reposted!';
                $body = "🔥 {$newAdvert->name} is live again. Post it on your WhatsApp Status to grow your performance earnings.";

                $request->merge([
                    'title' => $title,
                    'message' => $body,
                    'type' => 'info',
                    'send_push' => true,
                ]);

                // app(NotificationController::class)->notifyAllUsers($request);

                return response()->json([
                    'ok' => true,
                    'status' => 'Success',
                    'message' => 'Advert reposted successfully!',
                    'data' => [
                        'original_advert_id' => $original->id,
                        'repost_advert_id' => $newAdvert->id,
                        'submission_id_reused' => $newAdvert->submission_id,
                    ],
                ], 200);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function uploadScreenShotPlusCompare(Request $request, $advert_id)
    {
        DB::beginTransaction();
        try {

            $request->validate([
                'screenshot' => 'required|image|mimes:jpeg,png,jpg|max:3072',
            ]);

            $userId = (string) $request->user()->id;
            $user = User::findOrFail($userId);
            $advert = AdvertImages::find($advert_id);

            if (! $advert) {
                DB::rollBack();

                return response()->json(['message' => 'Advert not found.'], 404);
            }

            $decodedText = app(ImageDecoderService::class)->decode($request->file('screenshot'));
            $verifiedQr = app(AdvertQrCodeService::class)->verifyOrFail(
                (string) $decodedText,
                $user,
                $advert
            );

            $campaign = Campaign::leftJoin('advert_images', 'campaigns.id', '=', 'advert_images.campaign_id')
                ->where('advert_images.id', $advert_id)
                ->select('campaigns.*')
                ->first();

            if (! $campaign) {
                DB::rollBack();

                return response()->json([
                    'ok' => false,
                    'status' => 'failed',
                    'message' => 'Campaign not found for the given advert ID',
                ], 404);
            }

            $previousScreenshot = Screenshots::where('advert_id', $advert_id)
                ->where('processed_by', $userId)
                ->lockForUpdate()
                ->latest()
                ->first();

            $allStarted = Screenshots::where('advert_id', $advert_id)
                ->where('number', 1)
                ->count();

            if (is_null($previousScreenshot)) {
                if ($allStarted >= $advert->capacity) {
                    DB::rollBack();

                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => 'This campaign has reached full capacity. Thank you for your interest.',
                    ], 400);
                }
            }

            $advertPath = public_path('storage/' . $advert->image_path);
            $previousScreenshot = Screenshots::where('advert_id', $advert_id)
                ->where('processed_by', $userId)
                ->latest()
                ->first();

            // let's ensure that the first screenshot and second have a differences of 18 hours
            $previousScreenshot = Screenshots::where('advert_id', $advert_id)
                ->where('processed_by', $userId)
                ->latest()
                ->first();

            if ($previousScreenshot !== null) {
                // Convert the previous time to Nairobi timezone
                $previousTime = Carbon::parse($previousScreenshot->created_at)->timezone('Africa/Nairobi');

                // Get current time minus 18 hours
                $eighteenHoursAgo = Carbon::now('Africa/Nairobi')->subHours(18);

                if ($previousTime > $eighteenHoursAgo) {
                    DB::rollBack();

                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => 'You can only process this after 18 hours since your last submission.',
                    ], 400);
                }
            }
            if ($previousScreenshot != null) {
                if ($previousScreenshot->number == 2) {
                    DB::rollBack();

                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => 'Already Completed this task',
                    ], 400);
                }
            }

            // Save the screenshot to local storage
            $file = $request->file('screenshot');
            $originalName = $file->getClientOriginalName();
            $sanitizedName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $originalName);
            $filename = time() . '_' . $sanitizedName;
            $file->move(public_path('storage/screenshots'), $filename);

            $screenshotPath = public_path("storage/screenshots/{$filename}");

            try {
                $json = app(ScreenshotVerificationService::class)->verify(
                    $advertPath,
                    $screenshotPath
                );
            } catch (ScreenshotVerificationException $exception) {
                @unlink($screenshotPath);
                DB::rollBack();

                return response()->json([
                    'message' => 'Screenshot verification could not be completed.',
                    'reason' => $exception->getMessage(),
                    'upstream_status' => $exception->upstreamStatus,
                    'request_id' => $exception->requestId,
                ], 502);
            }

            $isVerified = trim((string) $json['status']) === 'Screenshot Successfully Verified.';
            $verifiedViews = filter_var($json['views'] ?? null, FILTER_VALIDATE_INT);

            // Reject every response that is not the explicit success contract.
            if (! $isVerified || $verifiedViews === false || $verifiedViews < 0) {
                @unlink($screenshotPath);
                DB::rollBack();

                return response()->json([
                    'message' => $json['status'] ?? 'Screenshot not verified.',
                    'reason' => $verifiedViews === false
                        ? 'The screenshot does not contain a valid numeric view count.'
                        : ($json['reason'] ?? 'No reason provided'),
                    'views' => $json['views'] ?? 'Not visible',
                ], 400);
            }

            $number = $previousScreenshot ? $previousScreenshot->number + 1 : 1;

            // let's ensure that views is progressive
            if ($previousScreenshot != null) {
                $lastViews = $previousScreenshot->views;
                if ($lastViews >= $verifiedViews) {
                    DB::rollBack();

                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => 'This screenshot seems to have equal or lower views than your previous submission. Please try again with a higher view count.',
                    ], 400);
                }
            }

            $screenshot = new Screenshots;
            $screenshot->screenshot = 'screenshots/' . $filename;
            $screenshot->advert_id = $advert_id;
            $screenshot->views = $verifiedViews;
            $screenshot->timestamp = $json['timestamp'] ?? null;
            $screenshot->processed_by = $userId;
            $screenshot->number = $number;
            $screenshot->save();
            $message = $json['status'] . ' | Views: ' . $verifiedViews;
            if ($number == 2) {
                if ($verifiedViews < 50) {
                    DB::rollBack();

                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => 'To complete this task, your status must have at least 50 views. Please try again once you have met this requirement.',
                    ], 400);
                }

                // Recalculate the user's period earnings from the completed advert.
                // No advert-level amount or legacy invoice credit is created.
                $advert = AdvertImages::where('id', $advert_id)->first();
                app(RewardPeriodService::class)->recordAdvertCompletion($userId, $advert);
                $message = 'Task completed. Your performance earnings estimate has been updated.';
            }

            // Return final success response
            DB::commit();

            return response()->json([
                'message' => $message,
                'views' => $verifiedViews,
                'path' => 'screenshots/' . $filename,
                'qr' => [
                    'identifier' => $verifiedQr->identifier_snapshot,
                    'advert_id' => $verifiedQr->advert_id,
                ],
            ]);
        } catch (ValidationException $exception) {
            DB::rollBack();
            $errors = $exception->errors();

            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => collect($errors)->flatten()->first() ?? 'QR code verification failed.',
                'errors' => $errors,
            ], 422);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error verifying image: ' . $th->getMessage());

            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAdvertCampaignsFraud(Request $request, $campaignId)
    {
        try {
            // Get all advert IDs for the given campaign
            $advertIds = DB::table('advert_images')
                ->where('campaign_id', $campaignId)
                ->pluck('id');

            $fraudGroups = [];

            foreach ($advertIds as $advertId) {
                // Get all screenshots for this advert, ordered by user and number
                $screenshots = DB::table('screenshots')
                    ->where('advert_id', $advertId)
                    ->where('views', '>', 10)
                    ->get();

                // Group screenshots by a combined key of views + timestamp
                $patterns = [];

                foreach ($screenshots as $screenshot) {
                    $patternKey = "{$screenshot->views}_{$screenshot->timestamp}";

                    $patterns[$patternKey][] = [
                        'user_id' => $screenshot->processed_by,
                        'name' => DB::table('users')->where('id', $screenshot->processed_by)->value('fullname'),
                        'views' => $screenshot->views,
                        'timestamp' => $screenshot->timestamp,
                        'number' => $screenshot->number,
                        'url' => URL::to('storage/' . $screenshot->screenshot),
                    ];
                }

                // Only include suspicious patterns shared by 2 or more users
                foreach ($patterns as $pattern => $grouped) {
                    // Extract unique user IDs to avoid repetition
                    $uniqueUsers = collect($grouped)->pluck('user_id')->unique();

                    if ($uniqueUsers->count() >= 2) {
                        $fraudGroups[] = [
                            'advert_id' => $advertId,
                            'matching_views_timestamp' => $pattern,
                            'users' => $uniqueUsers->values(),
                            'details' => $grouped,
                        ];
                    }
                }
            }

            return response()->json([
                'fraud_groups' => $fraudGroups,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAdvertCampaigns(Request $request, $campaignId)
    {
        try {
            $adverts = AdvertImages::where('campaign_id', $campaignId)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $adverts->through(function ($advert) {
                    $imagePath = 'storage/' . $advert->image_path;
                    $videoPath = 'storage/' . $advert->video_path;

                    return [
                        'id' => $advert->id,
                        'category' => $advert->category,
                        'description' => $advert->description,
                        'capital_invested' => $advert->capital_invested,
                        'valid_until' => $advert->valid_until,
                        'capacity' => $advert->capacity,
                        'target_audience' => $advert->target_audience,
                        'badge' => $advert->badge,
                        'name' => $advert->name,
                        'selling_price' => $advert->selling_price,
                        'campaign_id' => $advert->campaign_id,
                        'created_at' => $advert->created_at,
                        'updated_at' => $advert->updated_at,
                        'image_path' => $advert->image_path,
                        'image_url' => asset($imagePath),
                        'video_url' => asset($videoPath),
                    ];
                }),
                'pagination' => [
                    'total' => $adverts->total(),
                    'per_page' => $adverts->perPage(),
                    'current_page' => $adverts->currentPage(),
                    'last_page' => $adverts->lastPage(),
                    'from' => $adverts->firstItem(),
                    'to' => $adverts->lastItem(),
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching adverts',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getDashboardData(Request $request, $userId)
    {
        try {
            $userId = (string) $request->user()->id;
            $performanceEarnings = app(RewardPeriodService::class)->summary($userId);

            // Get ongoing (not completed) adverts
            $ongoingThreshold = Carbon::now('Africa/Nairobi')->subDay()->toDateTimeString();

            $adverts = AdvertImages::query();
            $adverts->whereRaw('
            (
                SELECT MIN(created_at)
                FROM screenshots
                WHERE screenshots.advert_id = advert_images.id
                AND screenshots.processed_by = ?
            ) >= ?
        ', [$userId, $ongoingThreshold])
                ->withCount(['screenshots as user_screenshot_count' => function ($query) use ($userId) {
                    $query->where('processed_by', $userId);
                }])
                ->having('user_screenshot_count', '<', 2);

            $adverts = $adverts
                ->with([
                    'screenshots' => function ($query) use ($userId) {
                        $query->where('processed_by', $userId)
                            ->orderBy('created_at', 'asc');
                    },

                ])
                ->paginate(10);

            $ongoingData = [
                'message' => 'Adverts retrieved successfully.',
                'data' => $adverts->through(function ($advert) {
                    $imagePath = 'storage/' . $advert->image_path;
                    $screenshot = $advert->screenshots->first();
                    $screenshotPath = $screenshot?->screenshot ? 'storage/' . $screenshot->screenshot : null;

                    $screenshotCount = $advert->user_screenshot_count ?? 0;

                    return [
                        'id' => $advert->id,
                        'category' => $advert->category,
                        'created_at' => $advert->created_at,
                        'name' => $advert->name,
                        'updated_at' => $advert->updated_at,
                        'valid_until' => $advert->valid_until,
                        'image_path' => $advert->image_path,
                        'image_url' => asset($imagePath),
                        'download_url' => route('download.advert.personalized', ['advertId' => $advert->id]),
                        'user_screenshot' => $screenshot?->screenshot,
                        'screenshot_url' => $screenshotPath ? asset($screenshotPath) : null,
                        'screenshot_id' => $screenshot?->id,
                        'screenshot_count' => $screenshotCount,
                        'all_screenshots' => $advert->screenshots->map(function ($ss) {
                            return [
                                'views' => $ss->views,
                            ];
                        })->values(),
                    ];
                }),
                'pagination' => [
                    'total' => $adverts->total(),
                    'per_page' => $adverts->perPage(),
                    'current_page' => $adverts->currentPage(),
                    'last_page' => $adverts->lastPage(),
                    'from' => $adverts->firstItem(),
                    'to' => $adverts->lastItem(),
                ],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data fetched successfully',
                'data' => [
                    'performance_earnings' => $performanceEarnings,
                    'ongoing' => $ongoingData,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dashboard data',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAdminDashboardData(Request $request)
    {
        try {
            $timeQuery = $request->input('time_filter', 'today');

            $now = Carbon::now('Africa/Nairobi');
            [$start, $end] = $this->resolveTimeRange($timeQuery, $now);

            /**
             * IMPORTANT: Activity-based scope
             * We build the dashboard from "what happened in the time range"
             * Screenshots -> involved adverts -> involved campaigns.
             * Performance earnings are period-based and are deliberately not
             * allocated to individual adverts or campaigns.
             */
            $advertIdsFromScreens = Screenshots::query()
                ->whereBetween('screenshots.created_at', [$start, $end])
                ->distinct()
                ->pluck('screenshots.advert_id');

            $advertIds = $advertIdsFromScreens->filter()->unique()->values();

            $performanceEarnings = RewardLedgerEntry::query()
                ->where('type', RewardLedgerEntry::EARNING)
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount_minor') / 100;
            $performancePayments = RewardPayout::query()
                ->where('status', RewardPayout::PAID)
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount_minor') / 100;
            $outstandingPerformanceBalance = RewardLedgerEntry::sum('amount_minor') / 100;
            $performancePaymentsByDay = RewardPayout::query()
                ->where('status', RewardPayout::PAID)
                ->whereBetween('paid_at', [$start, $end])
                ->selectRaw('DATE(paid_at) as date, COALESCE(SUM(amount_minor),0) as amount_minor')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn($row) => [
                    'date' => $row->date,
                    'amount' => ((int) $row->amount_minor) / 100,
                ]);

            // Load involved adverts + campaigns
            $adverts = AdvertImages::query()
                ->whereIn('advert_images.id', $advertIds)
                ->get();

            $campaignIds = $adverts->pluck('campaign_id')->filter()->unique()->values();

            $campaigns = Campaign::query()
                ->whereIn('campaigns.id', $campaignIds)
                ->get();

            // Empty-safe response
            if ($advertIds->isEmpty()) {
                $totalUsers = User::count();
                $salesmen = User::leftJoin('roles', 'roles.id', '=', 'users.role_id')
                    ->where('roles.slug', 'salesman')
                    ->count();

                return response()->json([
                    'success' => true,
                    'message' => 'Admin dashboard data fetched successfully',
                    'meta' => [
                        'time_filter' => $timeQuery,
                        'timezone' => 'Africa/Nairobi',
                        'start' => $start->toDateTimeString(),
                        'end' => $end->toDateTimeString(),
                    ],
                    'kpis' => [
                        'campaigns_involved' => 0,
                        'adverts_involved' => 0,

                        'active_adverts' => 0,
                        'expired_adverts' => 0,

                        'total_capacity' => 0,
                        'expected_screenshots' => 0,
                        'screenshots_remaining' => 0,
                        'progress_percent' => 0,

                        'total_capital_invested' => 0,
                        'screenshots_submitted' => 0,
                        'total_views' => 0,
                        'unique_earners' => 0,

                        'performance_earnings' => (float) $performanceEarnings,
                        'performance_payments' => (float) $performancePayments,
                        'outstanding_performance_balance' => (float) $outstandingPerformanceBalance,
                    ],
                    'top_lists' => [
                        'campaigns_by_views' => [],
                        'campaigns_by_progress' => [],
                        'active_adverts' => [],
                        'adverts_by_views' => [],
                        'earners_by_views' => [],
                    ],
                    'trends' => [
                        'screenshots_by_day' => [],
                        'views_by_day' => [],
                        'performance_payments_by_day' => $performancePaymentsByDay,
                    ],
                    'system_overview' => [
                        'users' => [
                            'total' => (int) $totalUsers,
                            'salesmen' => (int) $salesmen,
                        ],
                    ],
                ]);
            }

            // Active/Expired adverts (by advert_images.valid_until)
            $activeAdvertsCount = $adverts->filter(fn($a) => Carbon::parse($a->valid_until)->gte($now))->count();
            $expiredAdvertsCount = $adverts->filter(fn($a) => Carbon::parse($a->valid_until)->lt($now))->count();

            // Capacity + invested (adverts store these already)
            $totalCapacity = (int) $adverts->sum('capacity');
            $totalInvested = (float) $adverts->sum('capital_invested');

            // Screenshots aggregates in range (for involved adverts)
            $screensAgg = Screenshots::query()
                ->whereIn('screenshots.advert_id', $advertIds)
                ->whereBetween('screenshots.created_at', [$start, $end])
                ->selectRaw('COUNT(*) as screenshots_count, COALESCE(SUM(views),0) as views_sum, COUNT(DISTINCT processed_by) as unique_earners')
                ->first();

            $screenshotsSubmitted = (int) ($screensAgg->screenshots_count ?? 0);
            $totalViews = (int) ($screensAgg->views_sum ?? 0);
            $uniqueEarners = (int) ($screensAgg->unique_earners ?? 0);

            // “2 screenshots per slot” rule
            $expectedScreenshots = (int) ($totalCapacity * 2);
            $effectiveSubmitted = min($screenshotsSubmitted, $expectedScreenshots);
            $screenshotsRemaining = max(0, $expectedScreenshots - $effectiveSubmitted);
            $progressPercent = $expectedScreenshots > 0 ? min(100, round(($effectiveSubmitted / $expectedScreenshots) * 100, 1)) : 0;

            /**
             * TOP LISTS (FIXED to avoid join-multiplication)
             * We aggregate adverts separately from screenshots, then merge by campaign_id.
             */

            // Adverts totals per campaign (no joins)
            $advertsAgg = AdvertImages::query()
                ->whereIn('advert_images.campaign_id', $campaignIds)
                ->selectRaw('
                advert_images.campaign_id as campaign_id,
                COUNT(*) as adverts_count,
                COALESCE(SUM(advert_images.capacity),0) as capacity_sum,
                COALESCE(SUM(advert_images.capital_invested),0) as invested_sum,
                COALESCE(SUM(CASE WHEN advert_images.valid_until >= ? THEN 1 ELSE 0 END),0) as active_adverts,
                COALESCE(SUM(CASE WHEN advert_images.valid_until < ? THEN 1 ELSE 0 END),0) as expired_adverts
            ', [$now->toDateTimeString(), $now->toDateTimeString()])
                ->groupBy('advert_images.campaign_id');

            // Screenshots totals per campaign in range
            $screensAggByCampaign = Screenshots::query()
                ->join('advert_images', 'advert_images.id', '=', 'screenshots.advert_id')
                ->whereIn('advert_images.campaign_id', $campaignIds)
                ->whereBetween('screenshots.created_at', [$start, $end])
                ->selectRaw('
                advert_images.campaign_id as campaign_id,
                COUNT(*) as screenshots_count,
                COALESCE(SUM(screenshots.views),0) as views_sum,
                COUNT(DISTINCT screenshots.processed_by) as unique_earners
            ')
                ->groupBy('advert_images.campaign_id');

            // Combine campaign stats
            $campaignStats = Campaign::query()
                ->whereIn('campaigns.id', $campaignIds)
                ->leftJoinSub($advertsAgg, 'a', fn($j) => $j->on('a.campaign_id', '=', 'campaigns.id'))
                ->leftJoinSub($screensAggByCampaign, 's', fn($j) => $j->on('s.campaign_id', '=', 'campaigns.id'))
                ->selectRaw('
                campaigns.id,
                campaigns.name,
                COALESCE(a.adverts_count,0) as adverts_created,
                COALESCE(a.active_adverts,0) as active_adverts,
                COALESCE(a.expired_adverts,0) as expired_adverts,
                COALESCE(a.capacity_sum,0) as capacity,
                (COALESCE(a.capacity_sum,0) * 2) as expected_screenshots,
                COALESCE(a.invested_sum,0) as capital_invested,
                COALESCE(s.screenshots_count,0) as screenshots_uploaded,
                COALESCE(s.views_sum,0) as views,
                COALESCE(s.unique_earners,0) as unique_earners
            ')
                ->get()
                ->map(function ($row) {
                    $expected = (int) $row->expected_screenshots;
                    $uploaded = (int) $row->screenshots_uploaded;
                    $effective = $expected > 0 ? min($uploaded, $expected) : 0;

                    $row->screenshots_remaining = max(0, $expected - $effective);
                    $row->progress_percent = $expected > 0 ? min(100, round(($effective / $expected) * 100, 1)) : 0;
                    $row->is_full = $expected > 0 ? ($uploaded >= $expected) : false;

                    return $row;
                });

            $topCampaignsByViews = $campaignStats->sortByDesc('views')->take(5)->values();
            $topCampaignsByProgress = $campaignStats->sortByDesc('progress_percent')->take(5)->values();

            /**
             * Active adverts detailed stats (includes campaign name + progress)
             * We show active adverts even if they had no activity in range? You asked active adverts stats,
             * so we base this list on "adverts involved in range" AND still active.
             */
            $campaignNameById = $campaigns->keyBy('id')->map(fn($c) => $c->name);

            $activeAdvertsStats = AdvertImages::query()
                ->whereIn('advert_images.id', $advertIds)
                ->where('advert_images.valid_until', '>=', $now)
                ->leftJoin('screenshots', function ($join) use ($start, $end) {
                    $join->on('screenshots.advert_id', '=', 'advert_images.id')
                        ->whereBetween('screenshots.created_at', [$start, $end]);
                })
                ->selectRaw('
                advert_images.id,
                advert_images.name,
                advert_images.campaign_id,
                advert_images.capacity,
                (advert_images.capacity * 2) as expected_screenshots,
                COALESCE(COUNT(DISTINCT screenshots.id),0) as screenshots_uploaded,
                COALESCE(SUM(screenshots.views),0) as views,
                advert_images.capital_invested,
                advert_images.valid_until
            ')
                ->groupBy(
                    'advert_images.id',
                    'advert_images.name',
                    'advert_images.campaign_id',
                    'advert_images.capacity',
                    'advert_images.capital_invested',
                    'advert_images.valid_until'
                )
                ->orderByDesc('views')
                ->limit(10)
                ->get()
                ->map(function ($row) use ($campaignNameById) {
                    $expected = (int) $row->expected_screenshots;
                    $uploaded = (int) $row->screenshots_uploaded;
                    $effective = $expected > 0 ? min($uploaded, $expected) : 0;

                    $row->screenshots_remaining = max(0, $expected - $effective);
                    $row->progress_percent = $expected > 0 ? min(100, round(($effective / $expected) * 100, 1)) : 0;
                    $row->is_full = $expected > 0 ? ($uploaded >= $expected) : false;

                    $row->campaign_name = $campaignNameById[$row->campaign_id] ?? null;

                    return $row;
                });

            // Top adverts by views (all involved adverts, not only active)
            $topAdverts = AdvertImages::query()
                ->whereIn('advert_images.id', $advertIds)
                ->leftJoin('screenshots', function ($join) use ($start, $end) {
                    $join->on('screenshots.advert_id', '=', 'advert_images.id')
                        ->whereBetween('screenshots.created_at', [$start, $end]);
                })
                ->selectRaw('
                advert_images.id,
                advert_images.name,
                advert_images.campaign_id,
                advert_images.capacity,
                (advert_images.capacity * 2) as expected_screenshots,
                advert_images.valid_until,
                COUNT(screenshots.id) as screenshots_uploaded,
                COALESCE(SUM(screenshots.views),0) as views
            ')
                ->groupBy(
                    'advert_images.id',
                    'advert_images.name',
                    'advert_images.campaign_id',
                    'advert_images.capacity',
                    'advert_images.valid_until'
                )
                ->orderByDesc('views')
                ->limit(10)
                ->get()
                ->map(function ($row) use ($campaignNameById) {
                    $expected = (int) $row->expected_screenshots;
                    $uploaded = (int) $row->screenshots_uploaded;
                    $effective = $expected > 0 ? min($uploaded, $expected) : 0;

                    $row->screenshots_remaining = max(0, $expected - $effective);
                    $row->progress_percent = $expected > 0 ? min(100, round(($effective / $expected) * 100, 1)) : 0;
                    $row->is_full = $expected > 0 ? ($uploaded >= $expected) : false;

                    $row->campaign_name = $campaignNameById[$row->campaign_id] ?? null;

                    return $row;
                });

            // Top earners by views
            $topEarners = Screenshots::query()
                ->whereIn('screenshots.advert_id', $advertIds)
                ->whereBetween('screenshots.created_at', [$start, $end])
                ->leftJoin('users', 'users.id', '=', 'screenshots.processed_by')
                ->selectRaw('
                screenshots.processed_by as user_id,
                users.fullname as name,
                COUNT(*) as screenshots_count,
                COALESCE(SUM(screenshots.views),0) as views_sum
            ')
                ->groupBy('screenshots.processed_by', 'users.fullname')
                ->orderByDesc('views_sum')
                ->limit(10)
                ->get();

            // Trends
            $screenshotsByDay = Screenshots::query()
                ->whereIn('screenshots.advert_id', $advertIds)
                ->whereBetween('screenshots.created_at', [$start, $end])
                ->selectRaw('DATE(screenshots.created_at) as date, COUNT(*) as count')
                ->groupBy('date')->orderBy('date')
                ->get();

            $viewsByDay = Screenshots::query()
                ->whereIn('screenshots.advert_id', $advertIds)
                ->whereBetween('screenshots.created_at', [$start, $end])
                ->selectRaw('DATE(screenshots.created_at) as date, COALESCE(SUM(screenshots.views),0) as views')
                ->groupBy('date')->orderBy('date')
                ->get();

            // Users overview
            $totalUsers = User::count();
            $salesmen = User::leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.slug', 'salesman')
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Admin dashboard data fetched successfully',
                'meta' => [
                    'time_filter' => $timeQuery,
                    'timezone' => 'Africa/Nairobi',
                    'start' => $start->toDateTimeString(),
                    'end' => $end->toDateTimeString(),
                ],
                'kpis' => [
                    'campaigns_involved' => (int) $campaigns->count(),
                    'adverts_involved' => (int) $adverts->count(),

                    'active_adverts' => (int) $activeAdvertsCount,
                    'expired_adverts' => (int) $expiredAdvertsCount,

                    'total_capacity' => (int) $totalCapacity,
                    'expected_screenshots' => (int) $expectedScreenshots,
                    'screenshots_remaining' => (int) $screenshotsRemaining,
                    'progress_percent' => (float) $progressPercent,

                    'total_capital_invested' => (float) $totalInvested,
                    'screenshots_submitted' => (int) $screenshotsSubmitted,
                    'total_views' => (int) $totalViews,
                    'unique_earners' => (int) $uniqueEarners,

                    'performance_earnings' => (float) $performanceEarnings,
                    'performance_payments' => (float) $performancePayments,
                    'outstanding_performance_balance' => (float) $outstandingPerformanceBalance,
                ],
                'top_lists' => [
                    'campaigns_by_views' => $topCampaignsByViews,
                    'campaigns_by_progress' => $topCampaignsByProgress,
                    'active_adverts' => $activeAdvertsStats,
                    'adverts_by_views' => $topAdverts,
                    'earners_by_views' => $topEarners,
                ],
                'trends' => [
                    'screenshots_by_day' => $screenshotsByDay,
                    'views_by_day' => $viewsByDay,
                    'performance_payments_by_day' => $performancePaymentsByDay,
                ],
                'system_overview' => [
                    'users' => [
                        'total' => (int) $totalUsers,
                        'salesmen' => (int) $salesmen,
                    ],
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching admin dashboard data',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    private function resolveTimeRange(string $timeQuery, Carbon $now): array
    {
        switch ($timeQuery) {
            case 'today':
                $start = $now->copy()->startOfDay();
                break;
            case 'this_week':
                $start = $now->copy()->startOfWeek();
                break;
            case 'this_month':
                $start = $now->copy()->startOfMonth();
                break;
            case 'this_year':
                $start = $now->copy()->startOfYear();
                break;
            default:
                $start = $now->copy()->startOfDay();
        }

        return [$start, $now->copy()];
    }

    public function getCampaignReports(Request $request)
    {
        try {
            $campaignId = $request->query('campaign_id');
            $campaign = Campaign::with('adverts.screenshots.user')->findOrFail($campaignId);

            $now = Carbon::now('Africa/Nairobi');
            $validUntil = Carbon::parse($campaign->valid_until, 'Africa/Nairobi');

            $completedUsers = [];
            $incompleteUsers = [];
            $ongoingUsers = [];
            $totalCompleted = 0;
            $totalIncomplete = 0;
            $totalOngoing = 0;
            $totalViewsAllUsers = 0; // ✅ new total

            foreach ($campaign->adverts as $advert) {
                $userScreenshots = $advert->screenshots->groupBy('processed_by');

                foreach ($userScreenshots as $screenshots) {
                    $firstScreenshot = $screenshots->where('number', 1)->first();
                    $lastScreenshot = $screenshots->where('number', 2)->first();
                    if (! $firstScreenshot) {
                        continue;
                    }

                    $user = $firstScreenshot->user;
                    if (! $user || ! $user->id) {
                        continue;
                    }

                    $userId = $user->id;
                    $isCompleted = $screenshots->contains('number', 2);
                    $screenshotCount = $screenshots->count();

                    $firstScreenshotTime = Carbon::parse($firstScreenshot->created_at, 'Africa/Nairobi');
                    $ongoingEnd = $firstScreenshotTime->copy()->addDay();
                    $isNowBetween = $now->between($firstScreenshotTime, $ongoingEnd, true);
                    $isOngoing = $screenshotCount < 2 && ! $isCompleted && $isNowBetween;

                    // Categorize
                    if ($isCompleted) {

                        $views = $lastScreenshot->views;
                        $totalViewsAllUsers += $views;
                        $completedUsers[] = [
                            'full_name' => $user->fullname,
                            'phone' => $user->phone ?? null,
                            'completed_screenshots' => $screenshotCount,
                            'views' => $views ?? null,
                        ];
                        $totalCompleted++;
                    } elseif ($isOngoing) {
                        $ongoingUsers[] = [
                            'full_name' => $user->fullname,
                            'phone' => $user->phone ?? null,
                            'ongoing_screenshots' => $screenshotCount,
                            'first_screenshot' => $firstScreenshotTime->toDateTimeString(),
                        ];
                        $totalOngoing++;
                    } elseif ($validUntil->lt($now)) {
                        if ($screenshotCount < 2 && $firstScreenshotTime->lt($now->copy()->subDay())) {
                            $incompleteUsers[] = [
                                'full_name' => $user->fullname,
                                'phone' => $user->phone ?? null,
                                'incomplete_screenshots' => $screenshotCount,
                                'first_screenshot' => $firstScreenshotTime->toDateTimeString(),
                            ];
                            $totalIncomplete++;
                        }
                    }
                }
            }

            $unusedSlots = $campaign->capacity - ($totalCompleted + $totalIncomplete);

            return view('reports.campaign_report', [
                'campaign' => $campaign,
                'completed_count' => $totalCompleted,
                'incomplete_count' => $totalIncomplete,
                'ongoing_count' => $totalOngoing,
                'unused_slots' => $unusedSlots,
                'completed_users' => $completedUsers,
                'incomplete_users' => $incompleteUsers,
                'ongoing_users' => $ongoingUsers,
                'total_views_all_users' => $totalViewsAllUsers,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching campaign report',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCampaignTimelyReports(Request $request)
    {
        try {
            $from = $request->query('from_date');
            $to = $request->query('to_date');

            // Paginated campaigns
            $campaigns = Campaign::with('adverts.screenshots.user')
                ->whereBetween('created_at', [$from, $to])
                ->paginate(5, ['*'], 'campaign_page');

            $now = Carbon::now('Africa/Nairobi');
            $campaignReports = [];

            $summary = [
                'total_completed' => 0,
                'total_incomplete' => 0,
                'total_ongoing' => 0,
                'total_unused_slots' => 0,
                'total_views_all_users' => 0,
                'performance_earnings' => RewardLedgerEntry::query()
                    ->where('type', RewardLedgerEntry::EARNING)
                    ->whereBetween('created_at', [$from, $to])
                    ->sum('amount_minor') / 100,
                'performance_payments' => RewardPayout::query()
                    ->where('status', RewardPayout::PAID)
                    ->whereBetween('paid_at', [$from, $to])
                    ->sum('amount_minor') / 100,
                'all_completed_users' => [],
                'all_incomplete_users' => [],
                'all_ongoing_users' => [],
            ];

            foreach ($campaigns as $campaign) {
                $validUntil = Carbon::parse($campaign->valid_until, 'Africa/Nairobi');

                $completedUsers = [];
                $incompleteUsers = [];
                $ongoingUsers = [];
                $totalCompleted = 0;
                $totalIncomplete = 0;
                $totalOngoing = 0;
                $totalViewsAllUsers = 0;

                // dd($campaign);

                foreach ($campaign->adverts as $advert) {
                    $userScreenshots = $advert->screenshots->groupBy('processed_by');

                    foreach ($userScreenshots as $screenshots) {
                        $firstScreenshot = $screenshots->where('number', 1)->first();
                        $lastScreenshot = $screenshots->where('number', 2)->first();
                        if (! $firstScreenshot) {
                            continue;
                        }

                        $user = $firstScreenshot->user;
                        if (! $user || ! $user->id) {
                            continue;
                        }

                        $userId = $user->id;
                        $isCompleted = $screenshots->contains('number', 2);
                        $screenshotCount = $screenshots->count();

                        $firstScreenshotTime = Carbon::parse($firstScreenshot->created_at, 'Africa/Nairobi');
                        $ongoingEnd = $firstScreenshotTime->copy()->addDay();
                        $isNowBetween = $now->between($firstScreenshotTime, $ongoingEnd, true);
                        $isOngoing = $screenshotCount < 2 && ! $isCompleted && $isNowBetween;

                        if ($isCompleted) {
                            $views = $lastScreenshot->views ?? 0;
                            $totalViewsAllUsers += $views;

                            $entry = [
                                'full_name' => $user->fullname,
                                'phone' => $user->phone ?? null,
                                'completed_screenshots' => $screenshotCount,
                                'views' => $views,
                                'campaign_name' => $campaign->name,
                            ];
                            $completedUsers[] = $entry;
                            $summary['all_completed_users'][] = $entry;
                            $totalCompleted++;
                        } elseif ($isOngoing) {
                            $entry = [
                                'full_name' => $user->fullname,
                                'phone' => $user->phone ?? null,
                                'ongoing_screenshots' => $screenshotCount,
                                'first_screenshot' => $firstScreenshotTime->toDateTimeString(),
                                'campaign_name' => $campaign->name,
                            ];
                            $ongoingUsers[] = $entry;
                            $summary['all_ongoing_users'][] = $entry;
                            $totalOngoing++;
                        } elseif ($validUntil->lt($now)) {
                            if ($screenshotCount < 2 && $firstScreenshotTime->lt($now->copy()->subDay())) {
                                $entry = [
                                    'full_name' => $user->fullname,
                                    'phone' => $user->phone ?? null,
                                    'incomplete_screenshots' => $screenshotCount,
                                    'first_screenshot' => $firstScreenshotTime->toDateTimeString(),
                                    'campaign_name' => $campaign->name,
                                ];
                                $incompleteUsers[] = $entry;
                                $summary['all_incomplete_users'][] = $entry;
                                $totalIncomplete++;
                            }
                        }
                    }
                }

                $unusedSlots = $campaign->capacity - ($totalCompleted + $totalIncomplete);

                $summary['total_completed'] += $totalCompleted;
                $summary['total_incomplete'] += $totalIncomplete;
                $summary['total_ongoing'] += $totalOngoing;
                $summary['total_unused_slots'] += $unusedSlots;
                $summary['total_views_all_users'] += $totalViewsAllUsers;

                $campaignReports[] = [
                    'campaign' => $campaign,
                    'completed_count' => $totalCompleted,
                    'incomplete_count' => $totalIncomplete,
                    'ongoing_count' => $totalOngoing,
                    'unused_slots' => $unusedSlots,
                    'completed_users' => $completedUsers,
                    'incomplete_users' => $incompleteUsers,
                    'ongoing_users' => $ongoingUsers,
                    'total_views_all_users' => $totalViewsAllUsers,
                ];
            }

            return view('reports.timely_admin_report', [
                'campaignReports' => $campaignReports,
                'summary' => $summary,
                'startDate' => $from,
                'upto' => $to,
                'campaigns' => $campaigns,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching timely campaign report',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCampaignTimelyPersionalReports(Request $request)
    {
        try {
            $from = $request->query('from_date');
            $to = $request->query('to_date');
            $processedBy = $request->query('processed_by');

            if (! $processedBy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing processed_by user ID.',
                ], 400);
            }
            $user = User::where('id', $processedBy)->first();

            $campaigns = Campaign::with('adverts.screenshots.user')
                ->whereBetween('created_at', [$from, $to])
                ->paginate(5, ['*'], 'campaign_page');

            $accountActivity = RewardLedgerEntry::query()
                ->where('user_id', $processedBy)
                ->whereBetween('created_at', [$from, $to])
                ->latest()
                ->get()
                ->map(fn(RewardLedgerEntry $entry) => [
                    'type' => $entry->type,
                    'amount' => $entry->amount_minor / 100,
                    'description' => $entry->description,
                    'processed_at' => $entry->created_at,
                ]);
            $accountBalance = RewardLedgerEntry::where('user_id', $processedBy)
                ->sum('amount_minor') / 100;
            $totalPayment = RewardPayout::query()
                ->where('user_id', $processedBy)
                ->where('status', RewardPayout::PAID)
                ->whereBetween('paid_at', [$from, $to])
                ->sum('amount_minor') / 100;

            $now = Carbon::now('Africa/Nairobi');
            $campaignReports = [];

            $summary = [
                'total_completed' => 0,
                'total_incomplete' => 0,
                'total_ongoing' => 0,
                'total_unused_slots' => 0,
                'total_views_all_users' => 0,
                'user_completed' => [],
                'user_incomplete' => [],
                'user_ongoing' => [],
            ];

            foreach ($campaigns as $campaign) {
                $validUntil = Carbon::parse($campaign->valid_until, 'Africa/Nairobi');

                $completedUsers = [];
                $incompleteUsers = [];
                $ongoingUsers = [];
                $totalCompleted = 0;
                $totalIncomplete = 0;
                $totalOngoing = 0;
                $totalViewsAllUsers = 0;

                foreach ($campaign->adverts as $advert) {
                    $screenshots = $advert->screenshots->where('processed_by', $processedBy);
                    if ($screenshots->isEmpty()) {
                        continue;
                    }

                    $firstScreenshot = $screenshots->where('number', 1)->first();
                    $lastScreenshot = $screenshots->where('number', 2)->first();
                    if (! $firstScreenshot) {
                        continue;
                    }

                    $user = $firstScreenshot->user;
                    if (! $user || $user->id != $processedBy) {
                        continue;
                    }

                    $isCompleted = $screenshots->contains('number', 2);
                    $screenshotCount = $screenshots->count();

                    $firstScreenshotTime = Carbon::parse($firstScreenshot->created_at, 'Africa/Nairobi');
                    $ongoingEnd = $firstScreenshotTime->copy()->addDay();
                    $isNowBetween = $now->between($firstScreenshotTime, $ongoingEnd, true);
                    $isOngoing = $screenshotCount < 2 && ! $isCompleted && $isNowBetween;

                    if ($isCompleted) {
                        $views = $lastScreenshot->views ?? 0;
                        $totalViewsAllUsers += $views;

                        $entry = [
                            'full_name' => $user->fullname,
                            'phone' => $user->phone ?? null,
                            'completed_screenshots' => $screenshotCount,
                            'views' => $views,
                            'campaign_name' => $campaign->name,
                        ];
                        $completedUsers[] = $entry;
                        $summary['user_completed'][] = $entry;
                        $totalCompleted++;
                    } elseif ($isOngoing) {
                        $entry = [
                            'full_name' => $user->fullname,
                            'phone' => $user->phone ?? null,
                            'ongoing_screenshots' => $screenshotCount,
                            'first_screenshot' => $firstScreenshotTime->toDateTimeString(),
                            'campaign_name' => $campaign->name,
                        ];
                        $ongoingUsers[] = $entry;
                        $summary['user_ongoing'][] = $entry;
                        $totalOngoing++;
                    } elseif ($validUntil->lt($now)) {
                        if ($screenshotCount < 2 && $firstScreenshotTime->lt($now->copy()->subDay())) {
                            $entry = [
                                'full_name' => $user->fullname,
                                'phone' => $user->phone ?? null,
                                'incomplete_screenshots' => $screenshotCount,
                                'first_screenshot' => $firstScreenshotTime->toDateTimeString(),
                                'campaign_name' => $campaign->name,
                            ];
                            $incompleteUsers[] = $entry;
                            $summary['user_incomplete'][] = $entry;
                            $totalIncomplete++;
                        }
                    }
                }

                $unusedSlots = $campaign->capacity - ($totalCompleted + $totalIncomplete);

                $summary['total_completed'] += $totalCompleted;
                $summary['total_incomplete'] += $totalIncomplete;
                $summary['total_ongoing'] += $totalOngoing;
                $summary['total_unused_slots'] += $unusedSlots;
                $summary['total_views_all_users'] += $totalViewsAllUsers;

                $campaignReports[] = [
                    'campaign' => $campaign,
                    'completed_count' => $totalCompleted,
                    'incomplete_count' => $totalIncomplete,
                    'ongoing_count' => $totalOngoing,
                    'unused_slots' => $unusedSlots,
                    'completed_users' => $completedUsers,
                    'incomplete_users' => $incompleteUsers,
                    'ongoing_users' => $ongoingUsers,
                    'total_views_all_users' => $totalViewsAllUsers,
                ];
            }

            return view('reports.individual_report', [
                'campaignReports' => $campaignReports,
                'summary' => $summary,
                'startDate' => $from,
                'upto' => $to,
                'user' => $user,
                'totalPayment' => $totalPayment,
                'accountActivity' => $accountActivity,
                'accountBalance' => $accountBalance,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching timely campaign report',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCampaignTimelyPersional(Request $request)
    {
        try {
            $userId = $request->query('user_id');
            $from = $request->query('from_date');
            $to = $request->query('to_date');
            $status = $request->query('status');

            if (! $userId || ! $status) {
                return response()->json([
                    'message' => 'User ID and status are required in the query parameters.',
                ], 400);
            }

            $adverts = AdvertImages::query()
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to);

            if ($status === 'available') {
                $adverts->whereDoesntHave('screenshots', function ($query) use ($userId) {
                    $query->where('processed_by', $userId);
                })->whereHas('campaign', function ($query) {
                    $query->where('valid_until', '>', Carbon::now('Africa/Nairobi'));
                });
            }

            if ($status === 'ongoing') {
                $ongoingThreshold = Carbon::now('Africa/Nairobi')->subDay()->toDateTimeString();

                $adverts->whereHas('screenshots', function ($query) use ($userId, $ongoingThreshold) {
                    $query->where('processed_by', $userId)
                        ->where('number', 1)
                        ->where('created_at', '>=', $ongoingThreshold);
                })
                    ->whereDoesntHave('screenshots', function ($query) use ($userId) {
                        $query->where('processed_by', $userId)->where('number', 2);
                    })
                    ->withCount(['screenshots as user_screenshot_count' => function ($query) use ($userId) {
                        $query->where('processed_by', $userId);
                    }])
                    ->having('user_screenshot_count', '<', 2);
            }

            // incomplete
            if ($status === 'incomplete') {
                $now = Carbon::now('Africa/Nairobi')->toDateTimeString();

                $adverts = AdvertImages::query()
                    ->whereHas('screenshots', function ($query) use ($userId) {
                        $query->where('processed_by', $userId)->where('number', 1);
                    })
                    ->whereDoesntHave('screenshots', function ($query) use ($userId) {
                        $query->where('processed_by', $userId)->where('number', 2);
                    })
                    ->whereHas('campaign', function ($query) use ($now) {
                        $query->where('valid_until', '<', $now);
                    })
                    ->withCount([
                        'screenshots as user_screenshot_count' => function ($query) use ($userId) {
                            $query->where('processed_by', $userId);
                        },
                    ])
                    ->having('user_screenshot_count', '<', 2)
                    ->with([
                        'screenshots' => function ($query) use ($userId) {
                            $query->where('processed_by', $userId)->orderBy('created_at', 'asc');
                        },
                        'campaign',
                    ])
                    ->paginate(10);
            }
            if ($status == 'account_activity') {
                $accountActivity = RewardLedgerEntry::query()
                    ->where('user_id', $userId)
                    ->whereBetween('created_at', [$from, $to])
                    ->latest()
                    ->get()
                    ->map(fn(RewardLedgerEntry $entry) => [
                        'type' => $entry->type,
                        'amount' => $entry->amount_minor / 100,
                        'currency' => $entry->currency,
                        'description' => $entry->description,
                        'created_at' => $entry->created_at,
                    ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Account activity retrieved successfully.',
                    'activity' => $accountActivity,
                    'outstanding_balance' => RewardLedgerEntry::where('user_id', $userId)
                        ->sum('amount_minor') / 100,
                ],);
            }

            if ($status === 'completed') {
                $adverts->whereHas('screenshots', function ($query) use ($userId) {
                    $query->where('processed_by', $userId)->where('number', 2);
                })
                    ->withCount(['screenshots as user_screenshot_count' => function ($query) use ($userId) {
                        $query->where('processed_by', $userId);
                    }])
                    ->orderBy('created_at', 'desc')
                    ->limit(20);
            }

            $adverts = $adverts
                ->with([
                    'screenshots' => function ($query) use ($userId) {
                        $query->where('processed_by', $userId)
                            ->orderBy('created_at', 'asc');
                    },
                    'campaign:id,valid_until',
                ])
                ->paginate(10);

            return response()->json([
                'message' => 'Adverts retrieved successfully.',
                'data' => $adverts->through(function ($advert) use ($status) {
                    $imagePath = 'storage/' . $advert->image_path;
                    $screenshot = $advert->screenshots->first();
                    $screenshotPath = $screenshot?->screenshot ? 'storage/' . $screenshot->screenshot : null;

                    $screenshotCount = match ($status) {
                        'available' => 0,
                        'completed' => 2,
                        default => $advert->user_screenshot_count ?? 0,
                    };

                    return [
                        'id' => $advert->id,
                        'category' => $advert->category,
                        'created_at' => $advert->created_at,
                        'name' => $advert->name,
                        'description' => $advert->description,
                        'updated_at' => $advert->updated_at,
                        'valid_until' => $advert->campaign?->valid_until,
                        'image_path' => $advert->image_path,
                        'image_url' => asset($imagePath),
                        'download_url' => route('download.advert.personalized', ['advertId' => $advert->id]),
                        'video_path' => $advert->video_path,
                        'video_url' => $advert->video_path ? asset('storage/' . $advert->video_path) : null,
                        'video_download_url' => $advert->video_path ? route('download.advert.image', ['path' => $advert->video_path]) : null,
                        'user_screenshot' => $screenshot?->screenshot,
                        'screenshot_url' => $screenshotPath ? asset($screenshotPath) : null,
                        'screenshot_id' => $screenshot?->id,
                        'screenshot_count' => $screenshotCount,
                        'all_screenshots' => $advert->screenshots->map(function ($ss) {
                            return [
                                'views' => $ss->views,
                            ];
                        })->values(),
                    ];
                }),
                'pagination' => [
                    'total' => $adverts->total(),
                    'per_page' => $adverts->perPage(),
                    'current_page' => $adverts->currentPage(),
                    'last_page' => $adverts->lastPage(),
                    'from' => $adverts->firstItem(),
                    'to' => $adverts->lastItem(),
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
