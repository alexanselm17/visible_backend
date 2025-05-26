<?php
namespace App\Repositories\Products;
use App\Models\AdvertImages;
use App\Repositories\Products\ProductRepositoryInterface;
use App\Models\ProductsModel;
use App\Models\Drum;
use App\Models\Pump;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

use App\Http\Requests\ProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\CreateDrumRequest;
use App\Http\Requests\UpdateDrumRequest;
use App\Http\Requests\CreatePumpRequest;
use App\Http\Requests\CreatesStationRequest;
use App\Http\Requests\ProductAdvertRequest;
use App\Http\Requests\StartCampaignRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\UpdatePumpRequest;
use App\Models\Campaign;
use App\Models\Invoice;
use App\Models\PumpLog;
use App\Models\PumpReading;
use App\Models\Screenshots;
use App\Models\Stations;
use App\Models\Stock;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProductRepository implements ProductRepositoryInterface{

    public function createProduct(ProductRequest $request){
        try {
            // Create the product using mass assignment
            $product = ProductsModel::create([
                'name' => strtoupper($request->input('name')),
                'min_stock' => $request->input('min_stock'),
                'selling_price' => $request->input('selling_price'),
                'unit_name'=>$request->input('unit_name')
            ]);

            // Return a success response with the created product
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Product created successfully.',
                'product' => $product
            ]);

        } catch (\Throwable $th) {
            // Log the error and return an error response
            Log::debug('Create Product Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'Failed to create product. Please try again.',
                'error' => $th->getMessage()
            ]);
        }
    }

    public function createChildProduct(Request $request,$masterProductId){
        try {
            $masterProduct=ProductsModel::where('id',$masterProductId)->first();
            if ($masterProduct ==  null) {
                return response()->json([
                    'ok' => false,
                    'status' => 'failed',
                    'message' => 'Product not found',
                ]);
            }
              // Create the product using mass assignment
              $product = ProductsModel::create([
                'name' => strtoupper($request->input('name')),
                'min_stock' => 0,
                'selling_price' => $request->input('selling_price'),
                'unit_name'=>$masterProduct->unit_name,
                'parent_id'=>$masterProduct->id,
                'unit'=>$request->input('unit')
            ]);

            // Return a success response with the created product
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Product created successfully.',
                'product' => $product
            ]);
        } catch (\Throwable $th) {
            Log::debug('Create Child Product Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'Failed to create product. Please try again.',
                'error' => $th->getMessage()
            ]);
        }
    }

    public function updateProduct(UpdateProductRequest $request, $productId){
        try {
            // Find the product by ID
            $product = ProductsModel::findOrFail($productId);

            // Update the product with the validated data
            $product->update([
                'name' => strtoupper($request->input('name')),
                'selling_price' => $request->input('selling_price'),
                'unit_name'=>$request->input('unit_name')
            ]);

            // Return a success response with the updated product
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Product updated successfully.',
                'product' => $product
            ]);

        } catch (\Throwable $th) {
            // Log the error and return an error response
            Log::debug('Update Product Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'Failed to update product. Please try again.',
                'error' => $th->getMessage()
            ]);
        }
    }

    public function getProducts(){
        try {
            // Define the number of products per page
            $perPage = 10;

            // Fetch paginated products
            $products = ProductsModel::where('id','!=',null)->paginate($perPage);

            // Return a success response with the paginated products
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Products retrieved successfully.',
                'products' => $products
            ]);

        } catch (\Throwable $th) {
            // Log the error and return an error response
            Log::debug('Get Products Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'Failed to retrieve products. Please try again.',
                'error' => $th->getMessage()
            ]);
        }
    }
    public function searchProducts(Request $request){
      try{
        $searchQuery = $request->query('query');


        // Define the number of products per page
        $perPage = 10;


        // Fetch paginated products with name like the search query
        $products = ProductsModel::where('name', 'like', '%' . $searchQuery . '%')
                                   ->paginate($perPage);

        // Return a success response with the paginated products
        return response()->json([
            'ok' => true,
            'status' => 'success',
            'message' => 'Products retrieved successfully.',
            'products' => $products
        ]);
      }
      catch (\Throwable $th) {
        Log::debug('Get Products Error: ' . $th->getMessage());
        return response()->json([
          'ok' => false,
          'status' => 'error',
          'message' => 'Failed to retrieve products. Please try again.',
          'error' => $th->getMessage()
        ]);
      }
    }



    public function startCampaigns(StartCampaignRequest $request)
    {
        try {
            // Create and save the campaign
            $campaign = Campaign::create([
                'name' => $request->input('name'),
                'capital_invested' => $request->input('capital_invested'),
                'valid_until' => $request->input('valid_until'),
                'reward' => $request->input('reward'),
                'capacity' => $request->input('capacity'),
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
            $campaigns = Campaign::orderBy('created_at', 'desc')->paginate(10); // 10 items per page

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
    
            $adverts = AdvertImages::query();
    
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
                        'updated_at' => $advert->updated_at,
                        'valid_until' => $advert->campaign?->valid_until,
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
        // Check if file exists
        if ($request->hasFile('image')) {
            // Generate a clean and unique file name
            $file = $request->file('image');
            $originalName = $file->getClientOriginalName();
            $sanitizedName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $originalName); // Replace bad characters
            $filename = time().'_'.$sanitizedName;

            // Move file directly to public/storage/uploads
            $file->move(public_path('storage/uploads'), $filename);

            // Save path and category to DB
            $advert = new AdvertImages();
            $advert->image_path = 'uploads/' . $filename; // Save relative path
            $advert->category = "Default";
            $advert->name = $request->name;
            $advert->selling_price = "0.00";
            $advert->campaign_id = $campaignId;
            $advert->save();

            return response()->json([
                'ok' => true,
                'status' => "Success",
                'message' => 'Image uploaded successfully!',
            ], 200);
        } else {
            return response()->json(['message' => 'No image uploaded.'], 400);
        }

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

        $advert = AdvertImages::find($advert_id);
        if (!$advert) {
            return response()->json(['message' => '❌ Advert not found.'], 404);
        }

        $advertPath = public_path('storage/' . $advert->image_path);
        $previousScreenshot = Screenshots::where('advert_id', $advert_id)
        ->where('processed_by', $request->user_id)
        ->latest()
        ->first();
        if($previousScreenshot != null){
             if( $previousScreenshot->number == 5){
            DB::rollBack();
            return response()->json([
                'ok'=>false,
                'status'=>'failed',
                'message' => "Already Completed this task"
            ],400);
        }
       
        }
       
        // Save the screenshot to local storage
        $file = $request->file('screenshot');
        $originalName = $file->getClientOriginalName();
        $sanitizedName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $originalName);
        $filename = time().'_'.$sanitizedName;
        $file->move(public_path('storage/screenshots'), $filename);

        $screenshotPath = public_path("storage/screenshots/{$filename}");

        // Encode images to base64
        $advertBase64 = base64_encode(file_get_contents($advertPath));
        $screenshotBase64 = base64_encode(file_get_contents($screenshotPath));

        // Prepare OpenAI request
        $apiKey = "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"; // Get from .env file for security

        $prompt = "
        You are verifying whether a WhatsApp Status screenshot contains a specific image and relevant interface elements.

        Instructions:
        1. Confirm the screenshot is from WhatsApp and displays 'My status' and a timestamp (e.g., 'Just now', 'Yesterday', '10:24pm', or '9 minutes ago').
        2. Confirm that the status image in the screenshot matches the original image in layout and content.
        3. Extract the number of views from the screenshot if it is clearly visible.

        Respond only in this valid JSON format:

        {
          \"status\": \"✅ Verified: The image was successfully posted.\" OR \"❌ Not Verified\",
          \"reason\": \"[If not verified, explain why. If verified, return null]\",
          \"views\": \"[Exact number of views like '91', or 'Not visible']\"
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
                        ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $advertBase64]],
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
                'reason' => 'Invalid format from the verification model.'
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
        if( $previousScreenshot != null){
               $lastViews=$previousScreenshot->views;
        if($lastViews >= $json['views'] ){
            DB::rollBack();
            return response()->json([
                'ok'=>false,
                'status'=>'failed',
                'message' => "This screenshot has already been uploaded or not valid"
            ],400);
        }
        }
     
    

        // Save the verified screenshot to the database
        $screenshot = new Screenshots();
        $screenshot->screenshot = 'screenshots/' . $filename;
        $screenshot->advert_id = $advert_id;
        $screenshot->views=$json['views'] ?? 0;
        $screenshot->processed_by = $request->user_id;
        $screenshot->number= $number;
        $screenshot->save();
        $message= $json['status'] . ' | Views: ' . ($json['views'] ?? 'Not visible');
        if($number == 5){
            //we now proceed to reward the users
            $advert=AdvertImages::where('id',$advert_id)->first();
            $campaign=Campaign::where('id',$advert->campaign_id)->first();
            $reward=$campaign->reward;


            $customerLastInvoice = Invoice::where('processed_by', $request->user_id)->latest()->first();
            $customerBalance = $customerLastInvoice ? $customerLastInvoice->customer_balance : 0;
    
    
            $invoice=Invoice::create([
              "type" => "Reward",
              "amount" =>  $reward,
              "processed_by" => $request->user_id,
              "customer_balance" => $customerBalance + $reward,
              "posted_by" => $request->user_id,
              'advert_id'=> $advert_id
            ]);
            $message="Task Completed and rewarded Successfuly";
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

                return [
                    'id' => $advert->id,
                    'category' => $advert->category,
                    'name' => $advert->name,
                    'selling_price' => $advert->selling_price,
                    'campaign_id' => $advert->campaign_id,
                    'created_at' => $advert->created_at,
                    'updated_at' => $advert->updated_at,
                    'image_path' => $advert->image_path,
                    'image_url' => asset($imagePath),
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
}


