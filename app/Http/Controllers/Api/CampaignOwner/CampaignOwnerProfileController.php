<?php

namespace App\Http\Controllers\Api\CampaignOwner;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

use App\Models\CampaignOwnerProfile;
use App\Models\CampaignOwnerLogo;

class CampaignOwnerProfileController extends Controller
{
    private function ownedProfileOrFail(string $profileId): CampaignOwnerProfile
    {
        return CampaignOwnerProfile::where('id', $profileId)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    // GET /api/owner-profiles/{profileId}/logos
    public function listLogos(string $profileId)
    {
        $profile = $this->ownedProfileOrFail($profileId);

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
        ], 200);
    }

    // POST /api/owner-profiles/{profileId}/logos  (multipart: logo, set_primary)
    public function uploadLogo(Request $request, string $profileId)
    {
        $profile = $this->ownedProfileOrFail($profileId);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'set_primary' => ['nullable'],
        ]);

        $file = $request->file('logo');
        $safeName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $file->getClientOriginalName());
        $filename = time() . '_' . $safeName;

        $file->move(public_path('storage/uploads'), $filename);
        $path = 'uploads/' . $filename;

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
        $profile = $this->ownedProfileOrFail($profileId);

        $logo = CampaignOwnerLogo::where('id', $logoId)
            ->where('profile_id', $profile->id)
            ->firstOrFail();

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
        $profile = $this->ownedProfileOrFail($profileId);

        $logo = CampaignOwnerLogo::where('id', $logoId)
            ->where('profile_id', $profile->id)
            ->firstOrFail();

        $wasPrimary = (bool) $logo->is_primary;

        return DB::transaction(function () use ($profile, $logo, $wasPrimary) {

            $logo->delete();

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
