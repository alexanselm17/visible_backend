<?php

namespace App\Repositories\Products;

use App\Models\AdvertImages;
use App\Repositories\Products\ProductRepositoryInterface;
use App\Models\ProductsModel;
use App\Models\Drum;
use App\Models\Pump;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\CreateDrumRequest;
use App\Http\Requests\UpdateDrumRequest;
use App\Http\Requests\CreatePumpRequest;
use App\Http\Requests\CreatesStationRequest;
use App\Http\Requests\ProductAdvertRequest;
use App\Http\Requests\StartCampaignRequest;
use App\Http\Requests\UpdatePumpRequest;
use App\Models\Campaign;
use App\Models\Invoice;
use App\Models\PumpLog;
use App\Models\PumpReading;
use App\Models\Screenshots;
use App\Models\Stations;
use App\Models\User;
use App\Models\Stock;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use App\Exports\GenericExport;
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
            $advert->reward = $request->input('reward', $advert->reward);
            $advert->capacity = $request->input('capacity', $advert->capacity);

            // Save updated advert
            $advert->save();

            return response()->json([
                'ok' => true,
                'status' => "Success",
                'message' => 'Advert updated successfully!',
                'data' => $advert
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Advert not found.',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
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
                'name' => $request->input('name', $campaign->name)]);


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
            $campaigns = Campaign::with(['adverts.screenshots', 'adverts.invoices'])->get();

            $campaigns = $campaigns->map(function ($campaign) {
                $completed = 0;
                $ongoing = 0;
                $now = Carbon::now('Africa/Nairobi');
                $threshold = $now->copy()->subDay();

                foreach ($campaign->adverts as $advert) {
                    if ($advert->invoices->isNotEmpty()) {
                        $completed++;
                    } else {
                        $firstScreenshotTime = $advert->screenshots
                            ->where('processed_by', '!=', null)
                            ->sortBy('created_at')
                            ->first()?->created_at;

                        $userScreenshotCount = $advert->screenshots
                            ->where('processed_by', '!=', null)
                            ->count();

                        if ($firstScreenshotTime && $firstScreenshotTime >= $threshold && $userScreenshotCount < 5) {
                            $ongoing++;
                        }
                    }
                }

                $available = $campaign->capacity - ($completed + $ongoing);
                $totalRewardsGiven = $completed * ($campaign->reward ?? 0);

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'completed' => $completed,
                    'ongoing' => $ongoing,
                    'available' => $available,
                    'total_rewards_given' => $totalRewardsGiven,
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
            $userId = $request->query('user_id');
            $status = $request->query('status');

            if (!$userId || !$status) {
                return response()->json([
                    'message' => 'User ID and status are required in the query parameters.',
                ], 400);
            }

    $user = User::with(['county', 'subCounty'])->find($userId);
    if (!$user) {
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
                    $query
                        // Matches where no target_audience (open to all)
                        ->whereNull('target_audience')
                      
        
                        // Or check if user matches target_audience
                        ->orWhere(function ($subQuery) use ($user) {
                            $subQuery->whereJsonContains('target_audience->gender', $user->gender)
                                ->whereJsonContains('target_audience->county_id', $user->county_id)
                                ->where(function ($subSubQuery) use ($user) {
                                    $subSubQuery
                                        ->whereNull('target_audience->subcounty_id') // Optional match
                                        ->orWhereJsonContains('target_audience->subcounty_id', $user->subcounty_id);
                                });
                        });
                });
            }

            if ($status === 'ongoing') {
                $ongoingThreshold = Carbon::now('Africa/Nairobi')->subDay()->toDateTimeString();

                $adverts->whereDoesntHave('invoices', function ($query) use ($userId) {
                    $query->where('processed_by', $userId);
                })
                    ->whereRaw("
                    (
                        SELECT MIN(created_at)
                        FROM screenshots
                        WHERE screenshots.advert_id = advert_images.id
                        AND screenshots.processed_by = ?
                    ) >= ?
                ", [$userId, $ongoingThreshold])
                    ->withCount(['screenshots as user_screenshot_count' => function ($query) use ($userId) {
                        $query->where('processed_by', $userId);
                    }])
                    ->having('user_screenshot_count', '<', 5);
            }

            if ($status === 'completed') {
                $adverts->whereHas('invoices', function ($query) use ($userId) {
                    $query->where('processed_by', $userId);
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
                    'campaign:id'
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
                        'description' => $advert->description,
                        'reward' => $advert->reward,
                        'capacity' => $advert->capacity,
                        'updated_at' => $advert->updated_at,
                        'valid_until' => $advert->campaign?->valid_until,
                        'image_path' => $advert->image_path,
                        'image_url' => asset($imagePath),
                        'download_url' => route('download.advert.image', ['path' => $advert->image_path]),
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
                'error' => $th->getMessage()
            ], 500);
        }
    }





    public function uploadAdvertProducts(ProductAdvertRequest $request, $campaignId)
    {
        try {
            // Check if image file exists
            if (!$request->hasFile('image')) {
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
            $advert = new AdvertImages();
            $advert->image_path = 'uploads/' . $imageFilename;
            $advert->video_path = $videoPath;
            $advert->category = $request->category;
            $advert->name = $request->name;
            $advert->description = $request->description;
            $advert->badge = $request->badge;
            $advert->selling_price = 0;
            $advert->campaign_id = $campaignId;
            $advert->reward = $campaign->reward;
            //
            $advert->capital_invested = $request->capital_invested;
            $advert->valid_until = $request->valid_until;
            $advert->capacity = $request->capacity;
            $advert->reward = $request->reward;
            $advert->target_audience = json_decode($request->target_audience, true);
            $advert->save();

            // Notify users
            $title = "📢 New Product posted!";
            $body = "🔥 {$advert->name} is now live. Check it out before it’s gone!";
            $request->merge([
                'title' => $title,
                'message' => $body,
                'type' => 'info',
                'send_push' => true,
            ]);

            app(NotificationController::class)->notifyAllUsers($request);
            return response()->json([
                'ok' => true,
                'status' => "Success",
                'message' => 'Advert uploaded successfully!',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
            ], 500);
        }
    }



    public function uploadScreenShotPlusCompare(Request $request, $advert_id)
    {
        DB::beginTransaction();
        try {

            $request->validate([
                'screenshot' => 'required|image|mimes:jpeg,png,jpg|max:3072',
                'user_id' => 'required|exists:users,id',
            ]);

            $campaign = Campaign::leftJoin('advert_images', 'campaigns.id', '=', 'advert_images.campaign_id')
                ->where('advert_images.id', $advert_id)
                ->select('campaigns.*')
                ->first();

             $advert=AdvertImages::where('id',$advert_id)->first();   

            if (!$campaign) {
                DB::rollBack();
                return response()->json([
                    'ok' => false,
                    'status' => 'failed',
                    'message' => "Campaign not found for the given advert ID"
                ], 404);
            }

            $previousScreenshot = Screenshots::where('advert_id', $advert_id)
                ->where('processed_by', $request->user_id)
                ->latest()
                ->first();

            $allStarted = Screenshots::where('advert_id', $advert_id)
                ->where('number', 1)
                ->count();

            if (is_null($previousScreenshot)) {
                if ($allStarted >=  $advert->capacity) {
                    DB::rollBack();
                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => "Capacity already attained"
                    ], 400);
                }
            }





            $advert = AdvertImages::find($advert_id);
            if (!$advert) {
                return response()->json(['message' => '❌ Advert not found.'], 404);
            }

            $advertPath = public_path('storage/' . $advert->image_path);
            $previousScreenshot = Screenshots::where('advert_id', $advert_id)
                ->where('processed_by', $request->user_id)
                ->latest()
                ->first();
            if ($previousScreenshot != null) {
                if ($previousScreenshot->number == 5) {
                    DB::rollBack();
                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => "Already Completed this task"
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

            // Encode images to base64
            $advertBase64 = base64_encode(file_get_contents($advertPath));
            $screenshotBase64 = base64_encode(file_get_contents($screenshotPath));

            // Prepare OpenAI request
            $apiKey = "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"; 
            // Get from .env file for security
            $prompt = "
            You are verifying whether a WhatsApp Status screenshot contains a specific media item (either an image or a video) and the necessary WhatsApp interface elements.
            
            Instructions:
            1. Confirm the screenshot is from WhatsApp and clearly displays 'My status' and a visible timestamp (e.g., 'Just now', 'Yesterday', '10:24pm', or '9 minutes ago').
            2. Confirm that the media shown in the screenshot (either a static image or a thumbnail/frame from a video) matches the original media provided.
               - If the original is a video, compare the screenshot to the provided video thumbnail or a representative frame.
               - Ensure layout, colors, and content alignment are consistent.
            3. Extract the number of views from the screenshot if it is clearly visible.
            4. Extract the timestamp shown below 'My status'.
            
            Respond only in this valid JSON format:
            
            {
              \"status\": \"✅ Verified: The media was successfully posted.\" OR \"❌ Not Verified\",
              \"reason\": \"[If not verified, explain why. If verified, return null]\",
              \"views\": \"[Exact number of views like '91', or 'Not visible']\",
              \"timestamp\": \"[e.g., 'Just now', 'Today, 1:06 PM', or '43 minutes ago']\"
            }
            
            Do not include any other text before or after the JSON.
            ";
            

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $advertBase64]], // image OR video thumbnail
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $screenshotBase64]],
                        ]
                    ]
                ],
                'max_tokens' => 300,
            ]);


            $output = $response->json('choices.0.message.content') ?? '❌ Not Verified';
            

            // Remove markdown formatting like ```json ... ``` if present
            $output = trim($output);
            $output = preg_replace('/^```json|```$/i', '', $output);
            $output = trim($output);

            // Decode cleaned JSON
            $json = json_decode($output, true);

            // Handle invalid or unexpected response
            if (!$json || !isset($json['status'])) {
                @unlink($screenshotPath);
                return response()->json([
                    'message' => '❌ Not Verified',
                    'reason' => 'Invalid format from the verification model...',
                    'raw' => $output 
                ], 400);
            }

            // If verification failed
            if (str_starts_with($json['status'], '❌')) {
                @unlink($screenshotPath);
                return response()->json([
                    'message' => $json['status'],
                    'reason' => $json['reason'] ?? 'No reason provided',
                    'views' => $json['views'] ?? 'Not visible'
                ], 400);
            }


            $number = $previousScreenshot ? $previousScreenshot->number + 1 : 1;

            //let's ensure that views is progressive
            if ($previousScreenshot != null) {
                $lastViews = $previousScreenshot->views;
                if ($lastViews >= $json['views']) {
                    DB::rollBack();
                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => "This screenshot has already been uploaded or not valid"
                    ], 400);
                }
            }



            // Save the verified screenshot to the database
            $screenshot = new Screenshots();
            $screenshot->screenshot = 'screenshots/' . $filename;
            $screenshot->advert_id = $advert_id;
            $screenshot->views = $json['views'] ?? 0;
            $screenshot->timestamp = $json['timestamp'] ?? null;
            $screenshot->processed_by = $request->user_id;
            $screenshot->number = $number;
            $screenshot->save();
            $message = $json['status'] . ' | Views: ' . ($json['views'] ?? 'Not visible');
            if ($number == 2) {
                //let's also ensure that only more than 50 views for last screenshot
                if ($json['views'] < 50) {
                    DB::rollBack();
                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => "Minimum threshold not attained"
                    ], 400);
                }
                //we now proceed to reward the users
                $advert = AdvertImages::where('id', $advert_id)->first();
               // $campaign = Campaign::where('id', $advert->campaign_id)->first();
                $reward = $advert->reward;


                $customerLastInvoice = Invoice::where('processed_by', $request->user_id)->latest()->first();
                $customerBalance = $customerLastInvoice ? $customerLastInvoice->customer_balance : 0;


                $invoice = Invoice::create([
                    "type" => "Reward",
                    "amount" =>  $reward,
                    "processed_by" => $request->user_id,
                    "customer_balance" => $customerBalance + $reward,
                    "posted_by" => $request->user_id,
                    'advert_id' => $advert_id
                ]);
                $message = "Task Completed and rewarded Successfuly";
            }

            // Return final success response
            DB::commit();
            return response()->json([
                'message' => $message,
                'views' => $json['views'] ?? 'Not visible',
                'path' => 'screenshots/' . $filename
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("Error verifying image: " . $th->getMessage());
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
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
                        'reward' => $advert->reward,
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
                'error' => $th->getMessage()
            ], 500);
        }
    }
    public function getDashboardData(Request $request, $userId)
    {
        try {
            $now = Carbon::now('Africa/Nairobi');
            $startOfDay = $now->copy()->startOfDay();
            $endOfDay = $now->copy()->endOfDay();
            $startOfWeek = $now->copy()->startOfWeek();
            $startOfMonth = $now->copy()->startOfMonth();

            // Total reward invoices
            $rewardInvoices = Invoice::where('processed_by', $userId)
                ->where('type', 'Reward')
                ->get();

            $totalRewards = $rewardInvoices->sum('amount');
            $totalCampaigns = $rewardInvoices->count();

            // Today's rewards
            $todayRewards = Invoice::where('processed_by', $userId)
                ->where('type', 'Reward')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->get();

            $todayRewardTotal = $todayRewards->sum('amount');
            $todayRewardCount = $todayRewards->count();

            // Latest invoice and pending balance
            $latestInvoice = Invoice::where('processed_by', $userId)
                ->latest()
                ->first();

            $pendingBalance = $latestInvoice?->customer_balance ?? 0;

            // Inline achievement calculations
            $getAchievementData = function ($userId, $start, $end) {
                $adverts = AdvertImages::whereBetween('created_at', [$start, $end])->get();
                $advertIds = $adverts->pluck('id');

                $completed = Invoice::where('processed_by', $userId)
                    ->whereIn('advert_id', $advertIds)
                    ->whereBetween('created_at', [$start, $end])
                    ->count();

                return [
                    'created' => $adverts->count(),
                    'completed' => $completed,
                ];
            };

            $achievements = [
                'daily' => $getAchievementData($userId, $startOfDay, $endOfDay),
                'weekly' => $getAchievementData($userId, $startOfWeek, $endOfDay),
                'monthly' => $getAchievementData($userId, $startOfMonth, $endOfDay),
            ];

            // Get last 5 reward invoices with advert names
            $recentRewards = Invoice::where('processed_by', $userId)
                ->where('type', 'Reward')
                ->leftJoin('advert_images', 'invoices.advert_id', '=', 'advert_images.id')
                ->orderBy('invoices.created_at', 'desc')
                ->limit(5)
                ->get(['invoices.*', 'advert_images.name as advert_name']);

            // Get ongoing (not completed) adverts
            $ongoingThreshold = Carbon::now('Africa/Nairobi')->subDay()->toDateTimeString();

            $adverts = AdvertImages::query();
            $adverts->whereDoesntHave('invoices', function ($query) use ($userId) {
                $query->where('processed_by', $userId);
            })
                ->whereRaw("
            (
                SELECT MIN(created_at)
                FROM screenshots
                WHERE screenshots.advert_id = advert_images.id
                AND screenshots.processed_by = ?
            ) >= ?
        ", [$userId, $ongoingThreshold])
                ->withCount(['screenshots as user_screenshot_count' => function ($query) use ($userId) {
                    $query->where('processed_by', $userId);
                }])
                ->having('user_screenshot_count', '<', 5);

            $adverts = $adverts
                ->with([
                    'screenshots' => function ($query) use ($userId) {
                        $query->where('processed_by', $userId)
                            ->orderBy('created_at', 'asc');
                    },
                    'campaign:id,valid_until,reward'
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
                        'valid_until' => $advert->campaign?->valid_until,
                        'reward' => $advert->campaign?->reward,
                        'image_path' => $advert->image_path,
                        'image_url' => asset($imagePath),
                        'download_url' => route('download.advert.image', ['path' => $advert->image_path]),
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
                    'total_rewards' => $totalRewards,
                    'total_campaigns' => $totalCampaigns,
                    'today_rewards' => [
                        'count' => $todayRewardCount,
                        'amount' => $todayRewardTotal,
                    ],
                    'pending_balance' => $pendingBalance,
                    'achievements' => $achievements,
                    'recent_rewards' => $recentRewards,
                    'ongoing' => $ongoingData,
                ]
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
            $timeQuery = $request->input('time_filter');

            $now = Carbon::now('Africa/Nairobi');
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
            $end = $now;

            $campaigns = Campaign::with([
                'adverts.screenshots',
                'adverts.invoices' => function ($q) use ($start, $end) {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            ])
                ->whereBetween('created_at', [$start, $end])
                ->get();

            $campaignCount = $campaigns->count();
            $completed = 0;
            $ongoing = 0;
            $pending = 0;
            $rewardAssigned = 0;
            $paymentDone = 0;
            $pendingPayment = 0;

            $campaignStats = $campaigns->map(function ($campaign) use (&$completed, &$ongoing, &$rewardAssigned, &$pending, &$paymentDone, &$pendingPayment) {
                $comp = $campaign->adverts->filter(fn($ad) => $ad->invoices->isNotEmpty())->count();

                $ong = $campaign->adverts->filter(function ($ad) {
                    if ($ad->invoices->isNotEmpty()) return false;

                    $minDate = $ad->screenshots->min('created_at');
                    if (!$minDate || Carbon::parse($minDate)->lt(Carbon::now('Africa/Nairobi')->subDay())) return false;

                    return $ad->screenshots->count() < 5;
                })->count();

                $compReward = $comp * ($campaign->reward ?? 0);

                $completed += $comp;
                $ongoing += $ong;
                $rewardAssigned += $compReward;
                $pending += $campaign->capacity - ($comp + $ong);

                $campaign->adverts->each(function ($ad) use (&$paymentDone, &$pendingPayment) {
                    foreach ($ad->invoices as $invoice) {
                        if ($invoice->type === 'Payment') {
                            $paymentDone += $invoice->amount;
                        }
                        $pendingPayment += $invoice->customer_balance ?? 0;
                    }
                });

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'completed' => $comp,
                    'capacity' => $campaign->capacity,
                    'reward_total' => $compReward,
                ];
            });
            $totalUsers = User::leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.slug', 'salesman')
                ->count();


            $topCampaigns = $campaignStats->sortByDesc('completed')->take(5)->values();

            return response()->json([
                'success' => true,
                'message' => 'Admin dashboard data fetched successfully',
                'data' => [
                    'campaigns_created' => $campaignCount,
                    'rewards_assigned' => $rewardAssigned,
                    'completed' => $completed,
                    'ongoing' => $ongoing,
                    'unused_slots' => $pending,
                    'payment_done' => $paymentDone,
                    'pending_payment' => $pendingPayment,
                    'total_users' => $totalUsers,
                    'top_campaigns' => $topCampaigns,

                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching admin dashboard data',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCampaignReports(Request $request)
    {
        try {
            $campaignId = $request->query('campaign_id');
            $campaign = Campaign::with('adverts.screenshots.user', 'adverts.invoices')->findOrFail($campaignId);

            $now = Carbon::now('Africa/Nairobi');
            $validUntil = Carbon::parse($campaign->valid_until, 'Africa/Nairobi');

            $completedUsers = [];
            $incompleteUsers = [];
            $ongoingUsers = [];
            $totalRewardAwarded = 0;

            $totalCompleted = 0;
            $totalIncomplete = 0;
            $totalOngoing = 0;
            $totalViewsAllUsers = 0; // ✅ new total

            foreach ($campaign->adverts as $advert) {
                $userScreenshots = $advert->screenshots->groupBy('processed_by');

                foreach ($userScreenshots as $screenshots) {
                    $firstScreenshot = $screenshots->where('number', 1)->first();
                    $lastScreenshot = $screenshots->where('number', 5)->first();
                    if (!$firstScreenshot) continue;

                    $user = $firstScreenshot->user;
                    if (!$user || !$user->id) continue;

                    $userId = $user->id;
                    $hasInvoice = $advert->invoices->where('processed_by', $userId)->isNotEmpty();
                    $screenshotCount = $screenshots->count();

                    $firstScreenshotTime = Carbon::parse($firstScreenshot->created_at, 'Africa/Nairobi');
                    $ongoingEnd = $firstScreenshotTime->copy()->addDay();
                    $isNowBetween = $now->between($firstScreenshotTime, $ongoingEnd, true);
                    $isOngoing = $screenshotCount < 5 && !$hasInvoice && $isNowBetween;



                    // Categorize
                    if ($hasInvoice) {

                        $views =  $lastScreenshot->views;
                        $totalViewsAllUsers += $views;
                        $completedUsers[] = [
                            'full_name' => $user->fullname,
                            'phone' => $user->phone ?? null,
                            'completed_screenshots' => $screenshotCount,
                            'reward' => $advert->reward ?? $campaign->reward,
                            'views' => $views,
                        ];
                        $totalCompleted++;
                        $totalRewardAwarded += $advert->reward ?? $campaign->reward;
                    } elseif ($isOngoing) {
                        $ongoingUsers[] = [
                            'full_name' => $user->fullname,
                            'phone' => $user->phone ?? null,
                            'ongoing_screenshots' => $screenshotCount,
                            'first_screenshot' => $firstScreenshotTime->toDateTimeString(),
                        ];
                        $totalOngoing++;
                    } elseif ($validUntil->lt($now)) {
                        if ($screenshotCount < 5 && $firstScreenshotTime->lt($now->copy()->subDay())) {
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
                'total_reward_awarded' => $totalRewardAwarded,
                'completed_users' => $completedUsers,
                'incomplete_users' => $incompleteUsers,
                'ongoing_users' => $ongoingUsers,
                'total_views_all_users' => $totalViewsAllUsers, // ✅ passed to Blade
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
            $campaigns = Campaign::with(['adverts.screenshots.user', 'adverts.invoices.processedByUser'])
                ->whereBetween('created_at', [$from, $to])
                ->paginate(5, ['*'], 'campaign_page');

            $now = Carbon::now('Africa/Nairobi');
            $campaignReports = [];

            $summary = [
                'total_completed' => 0,
                'total_incomplete' => 0,
                'total_ongoing' => 0,
                'total_unused_slots' => 0,
                'total_reward_awarded' => 0,
                'total_views_all_users' => 0,
                'total_invoices' => 0,
                'all_completed_users' => [],
                'all_incomplete_users' => [],
                'all_ongoing_users' => [],
                'all_invoice_records' => [],
            ];

            foreach ($campaigns as $campaign) {
                $validUntil = Carbon::parse($campaign->valid_until, 'Africa/Nairobi');

                $completedUsers = [];
                $incompleteUsers = [];
                $ongoingUsers = [];
                $invoiceSummary = [];

                $totalRewardAwarded = 0;
                $totalCompleted = 0;
                $totalIncomplete = 0;
                $totalOngoing = 0;
                $totalViewsAllUsers = 0;

                foreach ($campaign->adverts as $advert) {
                    $userScreenshots = $advert->screenshots->groupBy('processed_by');

                    // Invoices
                    foreach ($advert->invoices as $invoice) {
                        $user = $invoice->processedByUser;
                        $invoiceEntry = [
                            'campaign_name' => $campaign->name,
                            'invoice_number' => $invoice->invoice_number ?? 'N/A',
                            'amount' => $invoice->amount ?? 0,
                            'type' => $invoice->type,
                            'balance' => $invoice->customer_balance ?? 0,
                            'user_name' => $user?->fullname ?? 'Unknown',
                            'user_phone' => $user?->phone ?? 'N/A',
                            'processed_at' => Carbon::parse($invoice->created_at)->toDayDateTimeString(),
                        ];
                        $invoiceSummary[] = $invoiceEntry;
                        $summary['all_invoice_records'][] = $invoiceEntry;
                        $summary['total_invoices'] += $invoice->amount;
                    }

                    foreach ($userScreenshots as $screenshots) {
                        $firstScreenshot = $screenshots->where('number', 1)->first();
                        $lastScreenshot = $screenshots->where('number', 5)->first();
                        if (!$firstScreenshot) continue;

                        $user = $firstScreenshot->user;
                        if (!$user || !$user->id) continue;

                        $userId = $user->id;
                        $hasInvoice = $advert->invoices->where('processed_by', $userId)->isNotEmpty();
                        $screenshotCount = $screenshots->count();

                        $firstScreenshotTime = Carbon::parse($firstScreenshot->created_at, 'Africa/Nairobi');
                        $ongoingEnd = $firstScreenshotTime->copy()->addDay();
                        $isNowBetween = $now->between($firstScreenshotTime, $ongoingEnd, true);
                        $isOngoing = $screenshotCount < 5 && !$hasInvoice && $isNowBetween;

                        if ($hasInvoice) {
                            $views = $lastScreenshot->views ?? 0;
                            $totalViewsAllUsers += $views;

                            $entry = [
                                'full_name' => $user->fullname,
                                'phone' => $user->phone ?? null,
                                'completed_screenshots' => $screenshotCount,
                                'reward' => $advert->reward ?? $campaign->reward,
                                'views' => $views,
                                'campaign_name' => $campaign->name,
                            ];
                            $completedUsers[] = $entry;
                            $summary['all_completed_users'][] = $entry;
                            $totalCompleted++;
                            $totalRewardAwarded += $advert->reward ?? $campaign->reward;
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
                            if ($screenshotCount < 5 && $firstScreenshotTime->lt($now->copy()->subDay())) {
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
                $summary['total_reward_awarded'] += $totalRewardAwarded;
                $summary['total_views_all_users'] += $totalViewsAllUsers;

                $campaignReports[] = [
                    'campaign' => $campaign,
                    'completed_count' => $totalCompleted,
                    'incomplete_count' => $totalIncomplete,
                    'ongoing_count' => $totalOngoing,
                    'unused_slots' => $unusedSlots,
                    'total_reward_awarded' => $totalRewardAwarded,
                    'completed_users' => $completedUsers,
                    'incomplete_users' => $incompleteUsers,
                    'ongoing_users' => $ongoingUsers,
                    'total_views_all_users' => $totalViewsAllUsers,
                    'invoices_summary' => $invoiceSummary,
                ];
            }

            return view('reports.timely_admin_report', [
                'campaignReports' => $campaignReports,
                'summary' => $summary,
                'startDate' => $from,
                'upto' => $to,
                'campaigns' => $campaigns // Pass paginator to blade for pagination links
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

            if (!$processedBy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing processed_by user ID.',
                ], 400);
            }
            $user = User::where('id', $processedBy)->first();

            $campaigns = Campaign::with(['adverts.screenshots.user', 'adverts.invoices.processedByUser'])
                ->whereBetween('created_at', [$from, $to])
                ->paginate(5, ['*'], 'campaign_page');

            $now = Carbon::now('Africa/Nairobi');
            $campaignReports = [];

            $summary = [
                'total_completed' => 0,
                'total_incomplete' => 0,
                'total_ongoing' => 0,
                'total_unused_slots' => 0,
                'total_reward_awarded' => 0,
                'total_views_all_users' => 0,
                'total_invoices' => 0,
                'user_completed' => [],
                'user_incomplete' => [],
                'user_ongoing' => [],
                'user_invoice_records' => [],
            ];

            foreach ($campaigns as $campaign) {
                $validUntil = Carbon::parse($campaign->valid_until, 'Africa/Nairobi');

                $completedUsers = [];
                $incompleteUsers = [];
                $ongoingUsers = [];
                $invoiceSummary = [];

                $totalRewardAwarded = 0;
                $totalCompleted = 0;
                $totalIncomplete = 0;
                $totalOngoing = 0;
                $totalViewsAllUsers = 0;

                $invoicingActivity = Invoice::where('invoices.processed_by', $processedBy)
                    ->whereBetween('invoices.created_at', [$from, $to])
                    ->leftJoin('users', 'invoices.processed_by', '=', 'users.id')
                    ->leftJoin('bankings', 'invoices.banking', '=', 'bankings.id')
                    ->leftJoin('advert_images', 'invoices.advert_id', '=', 'advert_images.id')
                    ->leftJoin('campaigns', 'advert_images.campaign_id', '=', 'campaigns.id')
                    ->select(
                        'invoices.amount',
                        'invoices.customer_balance',
                        'invoices.type',
                        'advert_images.name as advert_name',
                        'bankings.reference',
                        'campaigns.name as campaign_name'
                    )
                    ->get();

                $totalRewards = Invoice::where('processed_by', $processedBy)
                    ->whereBetween('created_at', [$from, $to])
                    ->where('type', 'Reward')
                    ->sum('amount');
                $latestRecord = Invoice::where('processed_by', $processedBy)
                    ->where('type', 'Reward')
                    ->latest()
                    ->first();

                $totalPayment = Invoice::where('processed_by', $processedBy)
                    ->whereBetween('created_at', [$from, $to])
                    ->where('type', 'Payment')
                    ->sum('amount');



                foreach ($campaign->adverts as $advert) {
                    // ✅ Filter invoices for this user
                    $userInvoices = $advert->invoices->where('processed_by', $processedBy);

                    foreach ($userInvoices as $invoice) {
                        $user = $invoice->processedByUser;
                        $invoiceEntry = [
                            'campaign_name' => $campaign->name,
                            'invoice_number' => $invoice->invoice_number ?? 'N/A',
                            'amount' => $invoice->amount ?? 0,
                            'type' => $invoice->type,
                            'balance' => $invoice->customer_balance ?? 0,
                            'user_name' => $user?->fullname ?? 'Unknown',
                            'user_phone' => $user?->phone ?? 'N/A',
                            'processed_at' => Carbon::parse($invoice->created_at)->toDayDateTimeString(),
                        ];
                        $invoiceSummary[] = $invoiceEntry;
                        $summary['user_invoice_records'][] = $invoiceEntry;
                        $summary['total_invoices'] += $invoice->amount;
                    }

                    $screenshots = $advert->screenshots->where('processed_by', $processedBy);
                    if ($screenshots->isEmpty()) continue;

                    $firstScreenshot = $screenshots->where('number', 1)->first();
                    $lastScreenshot = $screenshots->where('number', 5)->first();
                    if (!$firstScreenshot) continue;

                    $user = $firstScreenshot->user;
                    if (!$user || $user->id != $processedBy) continue;

                    $hasInvoice = $userInvoices->isNotEmpty();
                    $screenshotCount = $screenshots->count();

                    $firstScreenshotTime = Carbon::parse($firstScreenshot->created_at, 'Africa/Nairobi');
                    $ongoingEnd = $firstScreenshotTime->copy()->addDay();
                    $isNowBetween = $now->between($firstScreenshotTime, $ongoingEnd, true);
                    $isOngoing = $screenshotCount < 5 && !$hasInvoice && $isNowBetween;

                    if ($hasInvoice) {
                        $views = $lastScreenshot->views ?? 0;
                        $totalViewsAllUsers += $views;

                        $entry = [
                            'full_name' => $user->fullname,
                            'phone' => $user->phone ?? null,
                            'completed_screenshots' => $screenshotCount,
                            'reward' => $advert->reward ?? $campaign->reward,
                            'views' => $views,
                            'campaign_name' => $campaign->name,
                        ];
                        $completedUsers[] = $entry;
                        $summary['user_completed'][] = $entry;
                        $totalCompleted++;
                        $totalRewardAwarded += $advert->reward ?? $campaign->reward;
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
                        if ($screenshotCount < 5 && $firstScreenshotTime->lt($now->copy()->subDay())) {
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
                $summary['total_reward_awarded'] += $totalRewardAwarded;
                $summary['total_views_all_users'] += $totalViewsAllUsers;

                $campaignReports[] = [
                    'campaign' => $campaign,
                    'completed_count' => $totalCompleted,
                    'incomplete_count' => $totalIncomplete,
                    'ongoing_count' => $totalOngoing,
                    'unused_slots' => $unusedSlots,
                    'total_reward_awarded' => $totalRewardAwarded,
                    'completed_users' => $completedUsers,
                    'incomplete_users' => $incompleteUsers,
                    'ongoing_users' => $ongoingUsers,
                    'total_views_all_users' => $totalViewsAllUsers,
                    'invoices_summary' => $invoiceSummary,
                ];
            }
            return view('reports.individual_report', [
                'campaignReports' => $campaignReports,
                'summary' => $summary,
                'startDate' => $from,
                'upto' => $to,
                'user' => $user,
                'total_reward' => $totalRewards ?? 0.00,
                'total_payment' => $totalPayment ?? 0.00,
                'invoicingActivity' => $invoicingActivity ?? [],
                'accountBalance' => $latestRecord->customer_balance ?? 0.00
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

            if (!$userId || !$status) {
                return response()->json([
                    'message' => 'User ID and status are required in the query parameters.',
                ], 400);
            }

            $adverts = AdvertImages::query()
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to);;

            if ($status === 'available') {
                $adverts->whereDoesntHave('screenshots', function ($query) use ($userId) {
                    $query->where('processed_by', $userId);
                })->whereHas('campaign', function ($query) {
                    $query->where('valid_until', '>', Carbon::now('Africa/Nairobi'));
                });
            }

            if ($status === 'ongoing') {
                $ongoingThreshold = Carbon::now('Africa/Nairobi')->subDay()->toDateTimeString();

                $adverts->whereDoesntHave('invoices', function ($query) use ($userId) {
                    $query->where('processed_by', $userId);
                })
                    ->whereRaw("
                (
                    SELECT MIN(created_at)
                    FROM screenshots
                    WHERE screenshots.advert_id = advert_images.id
                    AND screenshots.processed_by = ?
                ) >= ?
            ", [$userId, $ongoingThreshold])
                    ->withCount(['screenshots as user_screenshot_count' => function ($query) use ($userId) {
                        $query->where('processed_by', $userId);
                    }])
                    ->having('user_screenshot_count', '<', 5);
            }

            // incomplete
            if ($status === 'incomplete') {
                $now = Carbon::now('Africa/Nairobi')->toDateTimeString();

                $adverts = AdvertImages::query()
                    ->whereDoesntHave('invoices', function ($query) use ($userId) {
                        $query->where('processed_by', $userId);
                    })
                    ->whereHas('campaign', function ($query) use ($now) {
                        $query->where('valid_until', '<', $now);
                    })
                    ->withCount([
                        'screenshots as user_screenshot_count' => function ($query) use ($userId) {
                            $query->where('processed_by', $userId);
                        }
                    ])
                    ->having('user_screenshot_count', '<', 5)
                    ->with([
                        'screenshots' => function ($query) use ($userId) {
                            $query->where('processed_by', $userId)->orderBy('created_at', 'asc');
                        },
                        'campaign'
                    ])
                    ->paginate(10);
            }
            if ($status == "account_activity") {
                $invoicingActivity = Invoice::where('invoices.processed_by', $userId)
                    ->whereBetween('invoices.created_at', [$from, $to])
                    ->leftJoin('users', 'invoices.processed_by', '=', 'users.id')
                    ->leftJoin('bankings', 'invoices.banking', '=', 'bankings.id')
                    ->leftJoin('advert_images', 'invoices.advert_id', '=', 'advert_images.id')
                    ->leftJoin('campaigns', 'advert_images.campaign_id', '=', 'campaigns.id')
                    ->select(
                        'invoices.amount',
                        'invoices.customer_balance',
                        'invoices.type',
                        'advert_images.name as advert_name',
                        'bankings.reference',
                        'campaigns.name as campaign_name'
                    )
                    ->get();
                return response()->json([
                    'success' => true,
                    'message' => 'Successfuly retrived a/c activity',
                    'activity' =>      $invoicingActivity,
                ],);
            }



            if ($status === 'completed') {
                $adverts->whereHas('invoices', function ($query) use ($userId) {
                    $query->where('processed_by', $userId);
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
                    'campaign:id,valid_until'
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
                        'completed' => 5,
                        default => $advert->user_screenshot_count ?? 0,
                    };

                    return [
                        'id' => $advert->id,
                        'category' => $advert->category,
                        'created_at' => $advert->created_at,
                        'name' => $advert->name,
                        'description' => $advert->description,
                        'reward' => $advert->reward,
                        'updated_at' => $advert->updated_at,
                        'valid_until' => $advert->campaign?->valid_until,
                        'image_path' => $advert->image_path,
                        'image_url' => asset($imagePath),
                        'download_url' => route('download.advert.image', ['path' => $advert->image_path]),
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
                'error' => $th->getMessage()
            ], 500);
        }
    }




    public function getExcellFileForPayment(Request $request)
    {
        try {
            // Get the latest invoice for each processed_by
            $latestInvoices = Invoice::select('invoices.*')
                ->join(DB::raw('(SELECT MAX(created_at) AS latest_created, processed_by 
                                FROM invoices 
                                GROUP BY processed_by) AS latest'), function ($join) {
                    $join->on('invoices.processed_by', '=', 'latest.processed_by')
                         ->on('invoices.created_at', '=', 'latest.latest_created');
                })
                ->orderBy('invoices.created_at', 'desc')
                ->leftJoin('users', 'invoices.processed_by', '=', 'users.id')
                ->select(
                    'users.fullname',
                    'invoices.id',
                    'invoices.customer_balance',
                    'users.phone',
                    'users.town',
                    'users.estate',
                    'users.county'
                )
                ->where('invoices.customer_balance', '>', '0')
                ->get();
               
    
            // Transform data
            $data = $latestInvoices->map(function ($invoice) {
                return [
                     99,
                     "112",
                     (string) $invoice->phone,
                     $invoice->fullname,
                     $invoice->customer_balance,
                    'Payment',
                    $invoice->town,
                    $invoice->estate,
                    $invoice->county,
                    "SALA",
                ];
            });
    
            // Return Excel file for download
            return Excel::download(
                new GenericExport($data), 
                'payment_as_at_' . now()->format('Y-m-d_H-i-s') . '.xlsx');
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching Excel sheet',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    
}
