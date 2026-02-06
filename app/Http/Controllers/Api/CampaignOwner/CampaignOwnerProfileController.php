<?php

namespace App\Http\Controllers\Api\CampaignOwner;

use App\Http\Controllers\Controller;
use App\Models\CampaignOwnerLogo;
use App\Models\CampaignOwnerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CampaignOwnerProfileController extends Controller
{

    private function getOrCreateOwnedProfile(string $profileId)
    {
        $userId = auth()->id();

        if (!$userId) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized. Please login again.',
            ], 401);
        }

        $profile = CampaignOwnerProfile::where('id', $profileId)->first();

        if (!$profile) {
            $profile = CampaignOwnerProfile::create([
                'id' => $profileId,
                'user_id' => $userId,
                'business_name' => null,
                'business_category' => null,
                'mpesa_phone' => null,
                'website' => null,
                'logo_path' => null,
                'account_status' => 'pending',
                'current_subscription_id' => null,
            ]);
        }

        if ((string) $profile->user_id !== (string) $userId) {
            return response()->json([
                'ok' => false,
                'message' => 'Forbidden. This profile does not belong to you.',
            ], 403);
        }

        return $profile;
    }

    private function logosDiskDir(): string
    {
        return public_path('storage/uploads/logos');
    }

    private function logosDbPrefix(): string
    {
        return 'uploads/logos/';
    }

    public function listLogos(string $profileId)
    {
        $profileOrResponse = $this->getOrCreateOwnedProfile($profileId);
        if ($profileOrResponse instanceof \Illuminate\Http\JsonResponse) {
            return $profileOrResponse;
        }
        /** @var CampaignOwnerProfile $profile */
        $profile = $profileOrResponse;

        $logos = CampaignOwnerLogo::where('profile_id', $profile->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => [
                'profile_id' => $profile->id,
                'current_logo_path' => $profile->logo_path, // e.g. uploads/logos/xxx.png
                'logos' => $logos,
            ],
        ], 200);
    }

    // POST /api/owner-profiles/{profileId}/logos (multipart: logo, set_primary)
    public function uploadLogo(Request $request, string $profileId)
    {
        $profileOrResponse = $this->getOrCreateOwnedProfile($profileId);
        if ($profileOrResponse instanceof \Illuminate\Http\JsonResponse) {
            return $profileOrResponse;
        }
        /** @var CampaignOwnerProfile $profile */
        $profile = $profileOrResponse;

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'set_primary' => ['nullable'],
        ]);

        // Ensure folder exists
        $dir = $this->logosDiskDir();
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $file = $request->file('logo');

        // Use uuid filename to avoid collisions
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = (string) Str::uuid() . '.' . $ext;

        // Save file
        $file->move($dir, $filename);

        // Store DB path (relative)
        $path = $this->logosDbPrefix() . $filename; // uploads/logos/uuid.png

        // Accept "true/false/1/0" from mobile
        $setPrimary = filter_var($request->input('set_primary', true), FILTER_VALIDATE_BOOLEAN);

        return DB::transaction(function () use ($profile, $path, $setPrimary) {

            if ($setPrimary) {
                CampaignOwnerLogo::where('profile_id', $profile->id)
                    ->update(['is_primary' => false]);
            }

            $logo = CampaignOwnerLogo::create([
                'id' => (string) Str::uuid(),
                'profile_id' => $profile->id,
                'logo_path' => $path,
                'is_primary' => $setPrimary,
            ]);

            if ($setPrimary) {
                $profile->update(['logo_path' => $path]);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Logo uploaded successfully.',
                'data' => [
                    'profile_id' => $profile->id,
                    'logo' => $logo,
                    'current_logo_path' => $profile->fresh()->logo_path,
                ],
            ], 201);
        });
    }

    // PATCH /api/owner-profiles/{profileId}/logos/{logoId}/make-primary
    public function makeLogoPrimary(string $profileId, string $logoId)
    {
        $profileOrResponse = $this->getOrCreateOwnedProfile($profileId);
        if ($profileOrResponse instanceof \Illuminate\Http\JsonResponse) {
            return $profileOrResponse;
        }
        /** @var CampaignOwnerProfile $profile */
        $profile = $profileOrResponse;

        $logo = CampaignOwnerLogo::where('id', $logoId)
            ->where('profile_id', $profile->id)
            ->first();

        if (!$logo) {
            return response()->json([
                'ok' => false,
                'message' => 'Logo not found.',
            ], 404);
        }

        return DB::transaction(function () use ($profile, $logo) {

            CampaignOwnerLogo::where('profile_id', $profile->id)
                ->update(['is_primary' => false]);

            $logo->update(['is_primary' => true]);
            $profile->update(['logo_path' => $logo->logo_path]);

            return response()->json([
                'ok' => true,
                'message' => 'Logo set as primary.',
                'data' => [
                    'profile_id' => $profile->id,
                    'logo_id' => $logo->id,
                    'current_logo_path' => $profile->fresh()->logo_path,
                ],
            ], 200);
        });
    }

    // DELETE /api/owner-profiles/{profileId}/logos/{logoId}
    public function deleteLogo(string $profileId, string $logoId)
    {
        $profileOrResponse = $this->getOrCreateOwnedProfile($profileId);
        if ($profileOrResponse instanceof \Illuminate\Http\JsonResponse) {
            return $profileOrResponse;
        }
        /** @var CampaignOwnerProfile $profile */
        $profile = $profileOrResponse;

        $logo = CampaignOwnerLogo::where('id', $logoId)
            ->where('profile_id', $profile->id)
            ->first();

        if (!$logo) {
            return response()->json([
                'ok' => false,
                'message' => 'Logo not found.',
            ], 404);
        }

        $wasPrimary = (bool) $logo->is_primary;
        $logoPath = $logo->logo_path; // uploads/logos/uuid.png

        return DB::transaction(function () use ($profile, $logo, $wasPrimary, $logoPath) {

            // Delete DB record
            $logo->delete();

            // Try delete file too (safe)
            $abs = public_path('storage/' . $logoPath); // storage/uploads/logos/uuid.png
            if (File::exists($abs)) {
                @File::delete($abs);
            }

            if ($wasPrimary) {
                $newPrimary = CampaignOwnerLogo::where('profile_id', $profile->id)
                    ->orderByDesc('created_at')
                    ->first();

                if ($newPrimary) {
                    CampaignOwnerLogo::where('profile_id', $profile->id)
                        ->update(['is_primary' => false]);

                    $newPrimary->update(['is_primary' => true]);
                    $profile->update(['logo_path' => $newPrimary->logo_path]);
                } else {
                    $profile->update(['logo_path' => null]);
                }
            }

            return response()->json([
                'ok' => true,
                'message' => 'Logo deleted.',
                'data' => [
                    'profile_id' => $profile->id,
                    'current_logo_path' => $profile->fresh()->logo_path,
                ],
            ], 200);
        });
    }
}
