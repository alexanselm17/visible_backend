<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class AdminFraudController extends Controller
{
    /**
     * Build a unique hash for a fraud group (campaign + advert + pattern).
     */
    private function fraudHash($campaignId, $advertId, $patternKey): string
    {
        return sha1($campaignId . '|' . $advertId . '|' . $patternKey);
    }

    /**
     * Prefetch reviewed hashes for a campaign (so reviewed groups never appear again).
     */
    private function reviewedHashesForCampaign($campaignId): array
    {
        return DB::table('fraud_reviews')
            ->where('campaign_id', $campaignId)
            ->pluck('pattern_hash')
            ->toArray();
    }

    /**
     * GET /v1/admin/fraud/campaign/{campaignId}?min_views=10
     * Returns UNREVIEWED fraud candidates for one campaign.
     * ✅ FULLY UPDATED: now includes campaign_id inside each fraud group.
     * ✅ Shows names (fullname) in details.
     */
    public function getFraudForCampaign(Request $request, $campaignId)
    {
        try {
            $minViews = (int) $request->query('min_views', 10);

            // adverts under this campaign
            $advertIds = DB::table('advert_images')
                ->where('campaign_id', $campaignId)
                ->pluck('id');

            if ($advertIds->isEmpty()) {
                return response()->json([
                    'campaign_id' => (string) $campaignId,
                    'fraud_groups' => [],
                ]);
            }

            // screenshots for these adverts
            $screenshots = DB::table('screenshots')
                ->whereIn('advert_id', $advertIds)
                ->where('views', '>', $minViews)
                ->get();

            if ($screenshots->isEmpty()) {
                return response()->json([
                    'campaign_id' => (string) $campaignId,
                    'fraud_groups' => [],
                ]);
            }

            // Prefetch user names once
            $userIds = $screenshots->pluck('processed_by')->unique()->values();
            $usersMap = DB::table('users')
                ->whereIn('id', $userIds)
                ->pluck('fullname', 'id'); // [id => fullname]

            // Prefetch reviewed hashes once (fast filtering)
            $reviewed = array_flip($this->reviewedHashesForCampaign($campaignId));

            $fraudGroups = [];

            foreach ($advertIds as $advertId) {
                $shotsForAdvert = $screenshots->where('advert_id', $advertId)->values();
                if ($shotsForAdvert->isEmpty()) continue;

                // Group by views + timestamp
                $patterns = [];

                foreach ($shotsForAdvert as $s) {
                    $patternKey = "{$s->views}_{$s->timestamp}";

                    $patterns[$patternKey][] = [
                        'user_id' => (string) $s->processed_by,
                        'name' => $usersMap[$s->processed_by] ?? null,
                        'views' => (int) $s->views,
                        'timestamp' => (string) $s->timestamp,
                        'number' => isset($s->number) ? (int) $s->number : null,
                        'url' => URL::to('storage/' . $s->screenshot),
                    ];
                }

                // Create fraud groups for patterns shared by 2+ users
                foreach ($patterns as $patternKey => $grouped) {
                    $uniqueUsers = collect($grouped)->pluck('user_id')->unique()->values();

                    if ($uniqueUsers->count() < 2) {
                        continue;
                    }

                    // Skip already-reviewed groups
                    $hash = $this->fraudHash($campaignId, $advertId, $patternKey);
                    if (isset($reviewed[$hash])) {
                        continue;
                    }

                    $fraudGroups[] = [
                        'campaign_id' => (string) $campaignId,          // ✅ ADDED HERE
                        'advert_id' => (string) $advertId,
                        'matching_views_timestamp' => (string) $patternKey,
                        'users' => $uniqueUsers,
                        'details' => $grouped,
                    ];
                }
            }

            return response()->json([
                'campaign_id' => (string) $campaignId,
                'fraud_groups' => $fraudGroups,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /v1/admin/fraud/campaigns?min_views=10&only_with_fraud=true
     * Returns UNREVIEWED fraud candidates for ALL campaigns.
     * ✅ Automatically includes campaign_id in each fraud group because getFraudForCampaign does.
     */
    public function getFraudForAllCampaigns(Request $request)
    {
        try {
            $minViews = (int) $request->query('min_views', 10);
            $onlyWithFraud = filter_var(
                $request->query('only_with_fraud', true),
                FILTER_VALIDATE_BOOLEAN
            );

            $campaigns = DB::table('campaigns')
                ->select('id', 'name', 'created_at')
                ->orderByDesc('created_at')
                ->get();

            $out = [];

            foreach ($campaigns as $c) {
                // Reuse logic by calling getFraudForCampaign() directly
                $fakeReq = new Request(['min_views' => $minViews]);

                $data = $this->getFraudForCampaign($fakeReq, $c->id)->getData(true);
                $fraudGroups = $data['fraud_groups'] ?? [];

                if ($onlyWithFraud && empty($fraudGroups)) {
                    continue;
                }

                $out[] = [
                    'campaign_id' => (string) $c->id,
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

    /**
     * POST /v1/admin/fraud/review
     * Body:
     * {
     *   "campaign_id": "...",
     *   "advert_id": "...",
     *   "pattern_key": "...",
     *   "status": "CONFIRMED" | "REJECTED",
     *   "fraud_payload": { ... full fraud group json ... },
     *   "notes": "optional",
     *   "reviewed_by": "optional admin uuid"
     * }
     *
     * Stores review decision in fraud_reviews so it never appears again.
     */
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

            $hash = $this->fraudHash(
                $data['campaign_id'],
                $data['advert_id'],
                $data['pattern_key']
            );

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
                'message' => 'Review saved. This group will not appear again.',
                'pattern_hash' => $hash,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Optional: GET /v1/admin/fraud/reviews?status=CONFIRMED|REJECTED
     * Returns history of reviewed items.
     */
    public function listReviews(Request $request)
    {
        try {
            $status = $request->query('status'); // optional
            $perPage = (int) $request->query('per_page', 50);

            $q = DB::table('fraud_reviews')
                ->orderByDesc('reviewed_at');

            if ($status) {
                $q->where('status', $status);
            }

            $rows = $q->paginate($perPage);

            // decode payload (optional convenience)
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
