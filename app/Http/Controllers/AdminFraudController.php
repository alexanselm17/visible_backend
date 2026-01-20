<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class AdminFraudController extends Controller
{


    private function fraudHash($campaignId, $advertId, $patternKey): string
    {
        return sha1($campaignId . '|' . $advertId . '|' . $patternKey);
    }

    private function isReviewed(string $patternHash): bool
    {
        return DB::table('fraud_reviews')
            ->where('pattern_hash', $patternHash)
            ->exists();
    }


    private function reviewedHashesForCampaign($campaignId): array
    {
        return DB::table('fraud_reviews')
            ->where('campaign_id', $campaignId)
            ->pluck('pattern_hash')
            ->toArray();
    }


    public function getFraudForCampaign(Request $request, $campaignId)
    {
        try {
            $minViews = (int) $request->query('min_views', 10);

            // 1) Get adverts for campaign
            $advertIds = DB::table('advert_images')
                ->where('campaign_id', $campaignId)
                ->pluck('id');

            if ($advertIds->isEmpty()) {
                return response()->json([
                    'campaign_id' => $campaignId,
                    'fraud_groups' => [],
                ]);
            }

            // 2) Fetch screenshots for those adverts
            $screenshots = DB::table('screenshots')
                ->whereIn('advert_id', $advertIds)
                ->where('views', '>', $minViews)
                ->get();

            if ($screenshots->isEmpty()) {
                return response()->json([
                    'campaign_id' => $campaignId,
                    'fraud_groups' => [],
                ]);
            }

            // 3) Prefetch names
            $userIds = $screenshots->pluck('processed_by')->unique()->values();
            $usersMap = DB::table('users')
                ->whereIn('id', $userIds)
                ->pluck('fullname', 'id'); // [id => fullname]

            // 4) Prefetch reviewed hashes for this campaign
            $reviewed = array_flip($this->reviewedHashesForCampaign($campaignId));

            $fraudGroups = [];

            // 5) For each advert, group by pattern views_timestamp
            foreach ($advertIds as $advertId) {
                $shotsForAdvert = $screenshots->where('advert_id', $advertId)->values();
                if ($shotsForAdvert->isEmpty()) continue;

                $patterns = [];

                foreach ($shotsForAdvert as $s) {
                    $patternKey = "{$s->views}_{$s->timestamp}";
                    $patterns[$patternKey][] = [
                        'user_id' => $s->processed_by,
                        'name' => $usersMap[$s->processed_by] ?? null,
                        'views' => (int) $s->views,
                        'timestamp' => $s->timestamp,
                        'number' => $s->number,
                        'url' => URL::to('storage/' . $s->screenshot),
                    ];
                }

                foreach ($patterns as $patternKey => $grouped) {
                    $uniqueUsers = collect($grouped)->pluck('user_id')->unique()->values();

                    // suspicious only if >= 2 different users
                    if ($uniqueUsers->count() < 2) continue;

                    // exclude reviewed
                    $hash = $this->fraudHash($campaignId, $advertId, $patternKey);
                    if (isset($reviewed[$hash])) continue;

                    $fraudGroups[] = [
                        'campaign_id' => $campaignId,
                        'advert_id' => $advertId,
                        'matching_views_timestamp' => $patternKey,
                        'users' => $uniqueUsers,
                        'details' => $grouped, // contains urls for preview
                    ];
                }
            }

            return response()->json([
                'campaign_id' => $campaignId,
                'fraud_groups' => $fraudGroups,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /* =========================================================
       STEP 3B) GET fraud candidates for ALL campaigns (UNREVIEWED)
       Endpoint: GET /api/admin/fraud/campaigns?min_views=10&only_with_fraud=1
       ========================================================= */
    public function getFraudForAllCampaigns(Request $request)
    {
        try {
            $minViews = (int) $request->query('min_views', 10);
            $onlyWithFraud = filter_var($request->query('only_with_fraud', true), FILTER_VALIDATE_BOOLEAN);

            $campaigns = DB::table('campaigns')
                ->select('id', 'name', 'created_at')
                ->orderByDesc('created_at')
                ->get();

            $out = [];

            foreach ($campaigns as $c) {
                // Reuse the logic by calling getFraudForCampaign() directly
                $fakeReq = new Request(['min_views' => $minViews]);

                $data = $this->getFraudForCampaign($fakeReq, $c->id)->getData(true);
                $fraudGroups = $data['fraud_groups'] ?? [];

                if ($onlyWithFraud && empty($fraudGroups)) {
                    continue;
                }

                $out[] = [
                    'campaign_id' => $c->id,
                    'campaign_name' => $c->name ?? null,
                    'fraud_groups_count' => count($fraudGroups),
                    'fraud_groups' => $fraudGroups,
                ];
            }

            return response()->json([
                'campaigns_count' => count($out),
                'campaigns' => $out,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /* =========================================================
       STEP 3C) POST admin review (CONFIRMED/REJECTED)
       Endpoint: POST /api/admin/fraud/review
       Body must include fraud_payload (the whole group)
       After this, that group will NEVER appear again.
       ========================================================= */
    public function reviewFraudGroup(Request $request)
    {
        try {
            $data = $request->validate([
                'campaign_id' => 'required',
                'advert_id' => 'required',
                'pattern_key' => 'required|string',
                'status' => 'required|in:CONFIRMED,REJECTED',
                'fraud_payload' => 'required|array',
                'notes' => 'nullable|string',
                'reviewed_by' => 'nullable|string',
            ]);

            $hash = $this->fraudHash($data['campaign_id'], $data['advert_id'], $data['pattern_key']);

            DB::table('fraud_reviews')->updateOrInsert(
                ['pattern_hash' => $hash],
                [
                    'campaign_id' => $data['campaign_id'],
                    'advert_id' => $data['advert_id'],
                    'pattern_key' => $data['pattern_key'],
                    'pattern_hash' => $hash,
                    'fraud_payload' => json_encode($data['fraud_payload']),
                    'status' => $data['status'],
                    'notes' => $data['notes'] ?? null,
                    'reviewed_by' => $data['reviewed_by'] ?? null,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            return response()->json([
                'ok' => true,
                'message' => 'Review saved. This group will not appear again in fraud endpoints.',
                'pattern_hash' => $hash,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /* =========================================================
       STEP 3D) OPTIONAL: list review history
       Endpoint: GET /api/admin/fraud/reviews?status=CONFIRMED|REJECTED
       ========================================================= */
    public function listReviews(Request $request)
    {
        try {
            $status = $request->query('status');
            $perPage = (int) $request->query('per_page', 50);

            $q = DB::table('fraud_reviews')
                ->orderByDesc('reviewed_at');

            if ($status) {
                $q->where('status', $status);
            }

            $rows = $q->paginate($perPage);

            $rows->getCollection()->transform(function ($r) {
                $r->fraud_payload = json_decode($r->fraud_payload, true);
                return $r;
            });

            return response()->json($rows);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
