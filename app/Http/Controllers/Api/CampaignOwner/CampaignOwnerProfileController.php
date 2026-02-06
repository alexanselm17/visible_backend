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
    /**
     * Resolve profile strictly by profile_id (UUID).
     * NO auth(), NO auto-create.
     */
    private function getProfile(string $profileId)
    {
        $profile = CampaignOwnerProfile::where('id', $profileId)->first();

        if (!$profile) {
            return response()->json([
                'ok' => false,
                'message' => 'Campaign owner profile not found.',
            ], 404);
        }

        return $profile;
    }

    /* ============================================================
     | Paths
     |============================================================ */
    private function diskDir(): string
    {
        // public/storage/uploads/logos
        return public_path('storage/uploads/logos');
    }

    private function dbPath(string $filename): string
    {
        // stored in DB
        return 'uploads/logos/' . $filename;
    }

    /* ============================================================
     | GET logos
     |============================================================ */
    // GET /api/owner-profiles/{profileId}/logos
    public function listLogos(string $profileId)
    {
        $profile = $this->getProfile($profileId);
        if ($profile instanceof \Illuminate\Http\JsonResponse) {
            return $profile;
        }

        $logos = CampaignOwnerLogo::where('profile_id', $profile->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => [
                'profile_id' => $profile->id,
                'current_logo_path' => $profile->logo_path,
                'logos' => $logos,
            ],
        ]);
    }

    /* ============================================================
     | UPLOAD logo
     |============================================================ */
    // POST /api/owner-profiles/{profileId}/logos
    public function uploadLogo(Request $request, string $profileId)
    {
        $profile = $this->getProfile($profileId);
        if ($profile instanceof \Illuminate\Http\JsonResponse) {
            return $profile;
        }

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'set_primary' => ['nullable'],
        ]);

        if (!File::exists($this->diskDir())) {
            File::makeDirectory($this->diskDir(), 0755, true);
        }

        $file = $request->file('logo');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $file->move($this->diskDir(), $filename);
        $path = $this->dbPath($filename);

        $setPrimary = filter_var(
            $request->input('set_primary', true),
            FILTER_VALIDATE_BOOLEAN
        );

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

    /* ============================================================
     | MAKE PRIMARY
     |============================================================ */
    // PATCH /api/owner-profiles/{profileId}/logos/{logoId}/make-primary
    public function makeLogoPrimary(string $profileId, string $logoId)
    {
        $profile = $this->getProfile($profileId);
        if ($profile instanceof \Illuminate\Http\JsonResponse) {
            return $profile;
        }

        $logo = CampaignOwnerLogo::where('id', $logoId)
            ->where('profile_id', $profile->id)
            ->first();

        if (!$logo) {
            return response()->json([
                'ok' => false,
                'message' => 'Logo not found for this profile.',
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
                    'current_logo_path' => $profile->fresh()->logo_path,
                ],
            ]);
        });
    }

    /* ============================================================
     | DELETE logo
     |============================================================ */
    // DELETE /api/owner-profiles/{profileId}/logos/{logoId}
    public function deleteLogo(string $profileId, string $logoId)
    {
        $profile = $this->getProfile($profileId);
        if ($profile instanceof \Illuminate\Http\JsonResponse) {
            return $profile;
        }

        $logo = CampaignOwnerLogo::where('id', $logoId)
            ->where('profile_id', $profile->id)
            ->first();

        if (!$logo) {
            return response()->json([
                'ok' => false,
                'message' => 'Logo not found for this profile.',
            ], 404);
        }

        $wasPrimary = $logo->is_primary;
        $absolutePath = public_path('storage/' . $logo->logo_path);

        return DB::transaction(function () use ($profile, $logo, $wasPrimary, $absolutePath) {

            $logo->delete();

            if (File::exists($absolutePath)) {
                @File::delete($absolutePath);
            }

            if ($wasPrimary) {
                $newPrimary = CampaignOwnerLogo::where('profile_id', $profile->id)
                    ->latest()
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
            ]);
        });
    }
}
