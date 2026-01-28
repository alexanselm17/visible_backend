<?php

namespace App\Http\Controllers\Api\CampaignOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\CampaignOwner\CreateCampaignOwnerRequest;
use App\Models\Campaign;
use App\Models\CampaignOwnerProfile;
use App\Models\RolesModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CampaignOwnerAuthController extends Controller
{

    private function assertCampaignOwner(string $userId): User
    {
        $user = User::findOrFail($userId);

        $roleSlug = $user->role->slug ?? null;

        if ($roleSlug !== 'campaign_owner') {
            abort(response()->json([
                'ok' => false,
                'message' => 'User is not a campaign owner.',
            ], 403));
        }

        return $user;
    }

    public function register(CreateCampaignOwnerRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {

                $role = RolesModel::where('slug', 'campaign_owner')->first();
                if (!$role) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Role campaign_owner not found. Please seed roles.'
                    ], 500);
                }

                $user = new User();
                $user->id = (string) Str::uuid();
                $user->fullname = $request->fullname;
                $user->username = $request->username;
                $user->email = $request->email;
                $user->phone = $request->phone;
                $user->county_id = $request->county_id;
                $user->subcounty_id = $request->subcounty_id;
                $user->occupation =  'Business Owner';
                $user->gender = 'Male';
                $user->role_id = $role->id;

                $user->referal_code = $request->input('referal_code', '');
                $user->my_code = $request->input('my_code', Str::upper(Str::random(8)));

                $user->password = $request->password;
                $user->save();

                $profile = new CampaignOwnerProfile();
                $profile->id = (string) Str::uuid();
                $profile->user_id = $user->id;
                $profile->business_name = $request->business_name;
                $profile->business_category = $request->business_category;
                $profile->mpesa_phone = $request->phone;
                $profile->website = $request->website;
                $profile->account_status = 'PENDING';
                $profile->save();

                $campaign = new Campaign();
                $campaign->id = (string) Str::uuid();
                $campaign->name = $profile->business_name;
                $campaign->owner_id = $user->id;
                $campaign->save();

                return response()->json([
                    'ok' => true,
                    'message' => 'Account created successfully.',
                    'data' => [
                        'user' => $user,
                        'profile' => $profile,
                        'campaign' => $campaign,
                    ],
                ], 201);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to create campaign owner account.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show(string $userId)
    {
        try {
            $user = $this->assertCampaignOwner($userId);

            if (!$user->campaignOwnerProfile) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Campaign owner profile not found.'
                ], 404);
            }

            $campaign = Campaign::where('owner_id', $userId)
                ->orderBy('created_at', 'asc')
                ->first();

            $role = $user->role;

            $subscription = $user->activeSubscription()->with('plan')->first();

            return response()->json([
                'ok' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'fullname' => $user->fullname,
                        'username' => $user->username,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'county_id' => $user->county_id,
                        'subcounty_id' => $user->subcounty_id,
                        'is_active' => $user->is_active,
                    ],
                    'business_profile' => [
                        'business_name' => $user->campaignOwnerProfile->business_name,
                        'business_category' => $user->campaignOwnerProfile->business_category,
                        'mpesa_phone' => $user->campaignOwnerProfile->mpesa_phone,
                        'website' => $user->campaignOwnerProfile->website,
                        'logo_path' => $user->campaignOwnerProfile->logo_path,
                        'account_status' => $user->campaignOwnerProfile->account_status,
                    ],
                    'default_campaign' => $campaign,
                    'role' => [
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ],
                    'subscription' => $subscription ? [
                        'status' => $subscription->status,
                        'starts_at' => $subscription->starts_at,
                        'ends_at' => $subscription->ends_at,
                        'plan' => [
                            'name' => $subscription->plan->name,
                            'billing_period' => $subscription->plan->billing_period,
                            'price' => $subscription->plan->price,
                            'limits' => $subscription->plan->limits,
                            'features' => $subscription->plan->features,
                        ]
                    ] : null
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch campaign owner profile.',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    /**
     * Get all users with Campaign Owner role
     */
    public function campaignOwners(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'search'   => 'nullable|string|max:255',
        ]);

        $perPage = $request->per_page ?? 10;
        $search  = $request->search;

        $query = User::with(['role'])
            ->whereHas('role', function ($q) {
                $q->where('slug', 'campaign_owner');
            });

        // Optional search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
                'has_more'     => $users->hasMorePages(),
            ],
        ]);
    }
}
