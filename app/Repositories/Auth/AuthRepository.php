<?php

namespace App\Repositories\Auth;

use App\Http\Controllers\NotificationController;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\AppVersion;
use App\Models\Permission;
use App\Models\RolesModel;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Counties;
use App\Repositories\Auth\AuthRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class AuthRepository implements AuthRepositoryInterface
{


    public function getAllUserReferred(Request $request, $userId)
    {
        try {
            // Clamp per_page between 1 and 100
            $perPage = (int) $request->query('per_page', 15);
            $perPage = max(1, min(100, $perPage));
            $page    = (int) $request->query('page', 1);

            // 1) Find the user (UUID or int — works with findOrFail)
            $user = User::findOrFail($userId);

            // 2) Grab their my_code
            $code = $user->my_code;

            // 3) If user has no my_code, return an empty paginator
            if (empty($code)) {
                $empty = new LengthAwarePaginator([], 0, $perPage, $page, [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]);

                return response()->json([
                    'ok'      => true,
                    'status'  => 'success',
                    'message' => 'User has no referral code; no referrals found.',
                    'user'    => [
                        'id'       => $user->id,
                        'fullname' => $user->fullname,
                        'my_code'  => $code,
                    ],
                    'referrals' => $empty,
                ]);
            }

            // 4) Fetch referred users (paginated)
            $referrals = User::where('referal_code', $code) // matches your field
                ->orderByDesc('created_at')
                ->paginate($perPage)
                ->appends($request->query());

            return response()->json([
                'ok'      => true,
                'status'  => 'success',
                'message' => 'Referrals fetched successfully.',
                'user'    => [
                    'id'       => $user->id,
                    'fullname' => $user->fullname,
                    'my_code'  => $code,
                ],
                'referrals' => $referrals,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'ok'      => false,
                'status'  => 'error',
                'message' => 'User not found.',
            ], 404);
        } catch (\Throwable $th) {
            Log::error('getAllUserReferred failed', [
                'userId' => $userId,
                'error'  => $th->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'status'  => 'error',
                'message' => 'Failed to fetch referrals.',
            ], 500);
        }
    }


    public function signUpUser(Request $request)
    {
        try {
            DB::beginTransaction();




            // Helper to generate unique code
            $generateUniqueCode = function (string $table, string $column = 'code', int $length = 10): string {
                do {
                    $code = preg_replace('/[^0-9]/', '', Str::random($length));
                    while (strlen($code) < $length) {
                        $code .= rand(0, 9);
                    }
                    $exists = DB::table($table)->where($column, $code)->exists();
                } while ($exists);

                return $code;
            };

            // Generate a unique my_code
            $myCode = $generateUniqueCode('users', 'my_code');
            $role = RolesModel::where('name', '=', 'Customer Champion')->first();
            $usersCount = User::count();

            $referalCode = $usersCount == 0 ?  $myCode : $request['code'];
            // dd(  $referalCode);
            $userCode = User::where('my_code', $referalCode)->first();



            if ($usersCount > 0 && !$userCode) {
                DB::rollBack();
                return response()->json([
                    'ok' => false,
                    'status' => 'error',
                    'message' => "Invalid referral code"
                ], 400);
            }

            $user = User::create([
                "fullname" => $request['fullname'],
                "username" => $request['username'],
                "email" => $request['email'],
                "password" => $request["password"],
                "phone" => $request['phone'],
                "county_id" => $request['county'],
                "subcounty_id" => $request['sub_county'],
                "role_id" => $role->id,
                "occupation" => $request['occupation'],
                "location" => $request['location'],
                "gender" => $request['gender'],
                "town" => $request['town'],
                "estate" => $request['estate'],
                "county" => $request['county'],
                "fcm_token" => $request['fcm_token'],
                "is_active" => false,
                "referal_code" => $usersCount == 0 ? $myCode : $referalCode,
                "my_code" => $usersCount == 0 ? $referalCode :  $myCode,
            ]);
            /*
        // Handle referral reward
        if ($userCode) {
            
            
            $whoReferedMe = $userCode;

            $customerLastInvoice = Invoice::where('processed_by', $whoReferedMe->id)->latest()->first();
            $customerBalance = $customerLastInvoice?->customer_balance ?? 0;
            $rewardCoin = 30;

            Invoice::create([
                "type" => "Referal",
                "amount" => $rewardCoin,
                "processed_by" => $whoReferedMe->id,
                "customer_balance" => $customerBalance + $rewardCoin,
                "posted_by" => $user->id,
            ]);
            
           
        }
         */

            // Notify admins
            $notificationController = new NotificationController();
            $notificationRequest = new Request(['new_user_id' => $user->id]);
            $notificationController->notifyAdminsNewAccount($notificationRequest);

            DB::commit();

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => "Account created successfully"
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::debug('Sign Up Error: ' . $th->getMessage());

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage()
            ]);
        }
    }




    public function signInUser(Request $request)
    {
        try {

            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                'app_version' => 'nullable|string',
            ]);

            $user = User::where(function ($query) use ($request) {
                $query->where('username', $request->username)
                    ->orWhere('email', $request->username)
                    ->orWhere('phone', $request->username);
            })->with(['county', 'subCounty'])
                ->first();
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'ok' => false,
                    'status' => 'warning',
                    'message' => "Invalid login Credetials.",
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!$user->is_active) {
                return response()->json([
                    'ok' => false,
                    'status' => 'failed',
                    'message' => "Account not yet Activated",
                ], Response::HTTP_UNAUTHORIZED);
            }
            $role = RolesModel::find($user->role_id);
            if ($role->slug != "admin") {
                if ($user->is_logged_in == 1) {
                    return response()->json([
                        'ok' => false,
                        'status' => 'failed',
                        'message' => "Already Logged in",
                    ], Response::HTTP_UNAUTHORIZED);
                }
            }




            if (!$role) {
                return response()->json([
                    'ok' => false,
                    'status' => 'failed',
                    'message' => "You have no role yet.",
                ], Response::HTTP_UNAUTHORIZED);
            }

            //let's update the login status 
            $user->is_logged_in = 1;
            $user->save();


            // Token with petrol_id stored as ability
            $token = $user->createToken('api-token-v' . $request->app_version)->plainTextToken;
            $tokenCreatedAt = now();
            //let

            $fraudStats = \Illuminate\Support\Facades\DB::table('fraud_reviews')
                ->where('status', 'CONFIRMED')
                ->where(function ($q) use ($user) {
                    $q->whereRaw("JSON_SEARCH(fraud_payload, 'one', ?) IS NOT NULL", [$user->id]);
                })
                ->selectRaw('COUNT(*) as total, MAX(reviewed_at) as last_confirmed_at')
                ->first();

            $isConfirmedFraud = ($fraudStats && (int)$fraudStats->total > 0);

            $responseData = [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'username' => $user->username,
                'phone' => $user->phone,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'role_id' => $user->role_id,
                'is_verified' => $user->is_verified,
                'is_logged_in' => $user->is_logged_in,
                'card_number' => $user->card_number,
                'occupation' => $user->occupation,
                'location' => $user->location,
                'gender' => $user->gender,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'deleted_at' => $user->deleted_at,

                'my_code' => $user->my_code,
                'fraud_status' => $isConfirmedFraud ? 'SUSPICIOUS' : 'APPROVED',
                'fraud_confirmed_count' => (int) ($fraudStats->total ?? 0),
                'last_fraud_at' => $fraudStats->last_confirmed_at,




                'role' => [
                    'id' => $user->role?->id,
                    'name' => $user->role?->name,
                    'slug' => $user->role?->slug
                ],


                'county' => $user->county ? [
                    'id' => $user->county->id,
                    'name' => $user->county->name,
                    'capital' => $user->county->capital,
                    'code' => $user->county->code ?? null,
                ] : null,

                'sub_county' => $user->subCounty ? [
                    'id' => $user->subCounty->id,
                    'name' => $user->subCounty->name,
                    'county_id' => $user->subCounty->county_id,
                ] : null,
            ];

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => "Successfully logged in.",
                'data' => $responseData,
                'token' => $token,
                'token_created_at' => $tokenCreatedAt
            ]);
        } catch (\Throwable $th) {
            Log::error('Sign In Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => "An error occurred. Please try again.",
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    public function signOut(Request $request)
    {
        try {
            // Retrieve the user_id from the request
            $userId = $request->input('user_id');

            // Find the user by the provided user_id
            $user = User::find($userId);

            // Check if the user exists
            if (!$user) {
                return response()->json([
                    'ok' => false,
                    'status' => 'failed',
                    'message' => 'User not found.'
                ], Response::HTTP_NOT_FOUND);
            }

            // Set the is_logged_in field to false
            $user->is_logged_in = false;
            $user->save();

            // Return success response
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Successfully logged out.'
            ]);
        } catch (\Throwable $th) {
            // Log the error and return a failure response
            Log::error('Sign Out Error: ' . $th->getMessage());

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    public function restorePassword(Request $request)
    {

        try {
            $user = User::where('username', $request['username'])
                ->where('phone', $request['phone'])
                ->where('email', $request['email'])
                ->first();
            if ($user != null) {
                //proceed with updating the user data
                $user->password = $request['password'];
                $user->save();
                return response()->json([
                    'ok' => true,
                    'status' => 'success',
                    'message' => "Password reset successfully ",
                ]);
            }
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => "No user with that data",
            ], 404);
        } catch (\Throwable $th) {
            Log::debug('Reset Password Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }

    public function getRoles()
    {
        try {
            $roles = RolesModel::where('slug', '!=', 'dev')
                ->get();
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'roles' => $roles,
            ]);
        } catch (\Throwable $th) {
            Log::debug('Get Roles Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }

    public function assignRole(Request $request)
    {
        try {
            $user = User::where('id', $request['user_id'])->first();

            $user->role_id = $request['role_id'];
            $user->save();
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'mesage' => "User assigned role successfully",
            ]);
        } catch (\Throwable $th) {
            Log::debug('Assign Roles Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }

    public function assignPermissionsToUser(Request $request)
    {
        try {
            // Find the user, throw a 404 error if not found
            $user = User::findOrFail($request->user_id);

            // Ensure that 'permissions' is an array in the request
            if (!is_array($request->permissions) || empty($request->permissions)) {
                return response()->json(['error' => 'Invalid permissions input'], 404);
            }

            // Retrieve valid permissions from the database
            $permissions = Permission::whereIn('slug', $request->permissions)->pluck('id');

            if ($permissions->isEmpty()) {
                return response()->json(['error' => 'No valid permissions found'], 404);
            }

            // Detach all existing permissions before assigning new ones
            $user->permissions()->detach();

            // Attach new permissions
            $user->permissions()->sync($permissions);


            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Permissions updated successfully',
                'assigned_permissions' => $request->permissions
            ]);
        } catch (\Throwable $th) {
            Log::debug('Assign Roles Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }

    public function getUserPermissions($userId)
    {
        try {
            // Find the user, throw a 404 error if not found
            $user = User::findOrFail($userId);

            // Get all permissions
            $allPermissions = Permission::select('permissions.*')->get(); // Explicitly select from permissions table

            // Get the user's assigned permissions using explicit table reference
            $assignedPermissions = $user->permissions()->select('permissions.id')->pluck('id')->toArray();

            // Transform permissions list to include an 'is_permitted' flag
            $permissions = $allPermissions->map(function ($permission) use ($assignedPermissions) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                    'is_permitted' => in_array($permission->id, $assignedPermissions), // true if assigned, false otherwise
                ];
            });

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'permissions' => $permissions,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }

    public function unAssignRole(Request $request)
    {
        try {
            $user = User::where('id', $request['user_id'])->first();
            $user->role_id = null;
            $user->save();
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => "User role unassigned successfully",
            ]);
        } catch (\Throwable $th) {
            Log::debug('Unassign Roles Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }
    public function searchUser(Request $request)
    {
        try {
            // Validate the incoming request to ensure that the search query is provided
            $request->validate([
                'query' => 'required|string|max:255'
            ]);

            // Extract the search query from the request
            $query = $request->input('query');

            // Set the number of users per page (you can adjust the limit as needed)
            $perPage = 5;

            // Search for users by username, fullname, or phone and paginate the results
            $users = User::where(function ($subQuery) use ($query) {
                $subQuery->where('username', 'LIKE', "%{$query}%")
                    ->orWhere('fullname', 'LIKE', "%{$query}%")
                    ->orWhere('phone', 'LIKE', "%{$query}%");
            })
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->select(
                    'users.id',
                    'users.fullname',
                    'users.username',
                    'users.email',
                    'users.phone',
                    'users.is_active',
                    'users.created_at',
                    'users.updated_at',
                    'users.deleted_at',
                    'roles.id as role_id',
                    'roles.name as role',
                    'roles.slug'
                )
                ->orderBy('users.id')
                ->paginate($perPage);

            // Check if any users were found
            if ($users->isEmpty()) {
                return response()->json([
                    'ok' => false,
                    'status' => 'not_found',
                    'message' => 'No users found.'
                ]);
            }

            // Return the users in the response with pagination data
            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Users retrieved successfully',
                'users' => $users,
            ]);
        } catch (\Throwable $th) {
            Log::debug('Get User Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }



    public function getAllUsers()
    {
        try {
            $perPage = 100;

            $users = User::with(['role', 'latestFraudFlag'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $users->getCollection()->transform(function ($u) {
                return [
                    'id' => $u->id,
                    'fullname' => $u->fullname,
                    'username' => $u->username,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'is_active' => $u->is_active,
                    'created_at' => $u->created_at,
                    'updated_at' => $u->updated_at,
                    'deleted_at' => $u->deleted_at,
                    'role_id' => $u->role?->id,
                    'role' => $u->role?->name,
                    'slug' => $u->role?->slug,

                    'is_banned' => (bool) $u->latestFraudFlag,
                    'fraud' => $u->latestFraudFlag ? [
                        'reason' => $u->latestFraudFlag->reason,
                        'details' => $u->latestFraudFlag->details,
                        'reported_by' => $u->latestFraudFlag->reported_by,
                        'flagged_at' => optional($u->latestFraudFlag->flagged_at)->toDateTimeString(),
                    ] : null,
                ];
            });

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Users retrieved successfully',
                'users' => $users,
            ]);
        } catch (\Throwable $th) {
            Log::debug('Get User Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }



    public function getAllUsersWithoutRole()
    {
        try {
            // Set the number of users per page, e.g., 10 users per page
            $perPage = 10;

            // Retrieve users without a role with pagination
            $users = User::where('role_id', '=', null)->paginate($perPage);

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => 'Users retrieved successfully',
                'users' => $users,
            ]);
        } catch (\Throwable $th) {
            Log::debug('Get User Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }


    public function deleteUser(Request $request)
    {
        try {
            $user = User::where('id', $request['user_id'])->first();
            $user->delete();

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => "Account deleted sucessfully",
            ]);
        } catch (\Throwable $th) {
            Log::debug('Delete User Error: ' . $th->getMessage());
            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }
    public function accountActivationCard(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'is_fraud' => 'sometimes|boolean',
                'fraud_reason' => 'nullable|string|max:255',
                'fraud_details' => 'nullable|string',
                'reported_by' => 'required_if:is_fraud,true|exists:users,id',
            ]);


            $user = User::find($request->input('user_id'));

            if (!$user) {
                return response()->json([
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'User not found.',
                ], 404);
            }

            // Toggle login status (if requested)
            if ($request->has('login_status')) {
                $user->is_logged_in = !$user->is_logged_in;
                $user->save();

                return response()->json([
                    'ok' => true,
                    'status' => 'success',
                    'message' => $user->is_logged_in ? 'User logged in.' : 'User logged out.',
                ]);
            }

            $previousStatus = (bool) $user->is_active;

            // Toggle active status
            $user->is_active = !$user->is_active;
            $user->save();

            // If we are DEACTIVATING now...
            if ($previousStatus === true && $user->is_active === false) {

                $isFraud = (bool) $request->input('is_fraud', false);

                if ($isFraud) {
                    // Require a reason if fraud is true
                    $reason = $request->input('fraud_reason');
                    if (!$reason) {
                        return response()->json([
                            'ok' => false,
                            'status' => 'error',
                            'message' => 'Please provide a fraud reason.',
                        ], 422);
                    }

                    \App\Models\UserFraud::create([
                        'user_id' => $user->id,
                        'reason' => $request->input('fraud_reason'),
                        'details' => $request->input('fraud_details'),
                        'reported_by' => $request->input('reported_by'),
                        'flagged_at' => now(),
                    ]);

                    $notificationController = new NotificationController();
                    $notificationRequest = new Request([
                        'user_id' => $user->id,
                        'send_push' => true,
                    ]);

                    $notificationController->notifyUserAccountFlaggedFraud($notificationRequest);
                }

                return response()->json([
                    'ok' => true,
                    'status' => 'success',
                    'message' => $isFraud
                        ? 'Account deactivated and flagged for review.'
                        : 'Account deactivated successfully.',
                ]);
            }

            if ($previousStatus === false && $user->is_active === true) {
                $notificationController = new NotificationController();
                $notificationRequest = new Request([
                    'user_id' => $user->id,
                    'send_push' => true,
                ]);
                $notificationController->notifyUserAccountActivated($notificationRequest);

                return response()->json([
                    'ok' => true,
                    'status' => 'success',
                    'message' => 'Account activated successfully.',
                ]);
            }

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => $user->is_active ? 'Account activated successfully.' : 'Account deactivated successfully.',
            ]);
        } catch (\Throwable $th) {
            Log::debug('Account Activation Error: ' . $th->getMessage());

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }



    public function updateProfile(UpdateProfileRequest $request)
    {
        try {
            // Retrieve the user_id from query parameters
            $userId = $request->query('user_id');

            // Find the user by ID or fail if not found
            $user = User::findOrFail($userId);

            // Update the user with validated data
            $user->update($request->validated());

            // Return a success response
            return response()->json([
                'message' => 'User profile updated successfully.',
                'user' => $user
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Handle user not found
            return response()->json([
                'message' => 'User not found.',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Throwable $th) {
            // Handle other errors
            return response()->json([
                'message' => 'An error occurred while updating the profile.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function transferUser(Request $request)
    {
        try {
            $user = User::find($request->input('user_id'));
            $stationId = $request->input('to_petrol_id');

            if (!$user) {
                return response()->json([
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'User not found.'
                ], 404);
            }

            $petrolStation = PetrolStation::find($stationId);
            if (!$petrolStation) {
                return response()->json([
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Target petrol station not found.'
                ], 404);
            }

            $user->update(['petrol_id' => $stationId]);

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'user' => $user,
                'message' => "User transferred successfully"
            ]);
        } catch (\Throwable $th) {
            Log::error('Transfer Error:', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
    }


    public function getCountiesWithSubCounties(Request $request)
    {
        try {
            $name = $request->query('name');

            $counties = Counties::with('subCounties')
                ->where('name', 'like', '%' . $name . '%')
                ->limit(5)
                ->get();

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'data' => $counties
            ]);
        } catch (\Throwable $th) {
            Log::error('Location Search error:', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(), // 👈 Return exact error here
                'trace' => $th->getTraceAsString(), // (optional) for full stack trace
            ], 500);
        }
    }


    public function activateAllInactiveAcounts(Request $request)
    {
        try {
            $processed_by = $request->input('user_id');
            $user = User::where('id', $processed_by)->with('role')->first();

            if ($user->role->name != "Admin") {

                return response()->json([
                    'ok' => false,
                    'status' => 'failed',
                    'message' => "Not Permitted"
                ], 403);
            }

            $allInactiveAccounts = User::where('is_active', false)->get();

            foreach ($allInactiveAccounts as $inactiveAccount) {
                $inactiveAccount->is_active = true;
                $inactiveAccount->save();
            }

            return response()->json([
                'ok' => true,
                'status' => 'success',
                'message' => count($allInactiveAccounts) . ' account(s) activated.'
            ]);
        } catch (\Throwable $th) {
            Log::error('Activate Accounts Error:', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'status' => 'error',
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ], 500);
        }
    }
}
