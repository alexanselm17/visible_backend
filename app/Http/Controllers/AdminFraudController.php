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

            $advertsMap = DB::table('advert_images')
                ->whereIn('id', $advertIds)
                ->pluck('name', 'id');


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
                ->pluck('fullname', 'id');

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
                        'campaign_id' => (string) $campaignId,
                        'advert_id' => (string) $advertId,
                        'advert_name' => $advertsMap[$advertId] ?? null,
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

    public function getGuiltyFraudUsers(Request $request)
    {
        try {
            $campaignId = $request->query('campaign_id'); // optional filter
            $limitReviews = (int) $request->query('limit_reviews', 500); // safety limit

            // 1) Get confirmed fraud reviews
            $q = DB::table('fraud_reviews')
                ->select('campaign_id', 'advert_id', 'pattern_key', 'fraud_payload', 'reviewed_at')
                ->where('status', 'CONFIRMED')
                ->orderByDesc('reviewed_at')
                ->limit($limitReviews);

            if ($campaignId) {
                $q->where('campaign_id', $campaignId);
            }

            $reviews = $q->get();

            if ($reviews->isEmpty()) {
                return response()->json([
                    'guilty_users_count' => 0,
                    'guilty_users' => [],
                ]);
            }

            // 2) Prefetch campaign names + advert names (1 query each)
            $campaignIds = $reviews->pluck('campaign_id')->unique()->values();
            $advertIds = $reviews->pluck('advert_id')->unique()->values();

            $campaignMap = DB::table('campaigns')
                ->whereIn('id', $campaignIds)
                ->pluck('name', 'id'); // [id => name]

            $advertMap = DB::table('advert_images')
                ->whereIn('id', $advertIds)
                ->pluck('name', 'id'); // CHANGE 'name' if needed

            // 3) Aggregate per-user
            $users = []; // user_id => data

            foreach ($reviews as $r) {
                $payload = json_decode($r->fraud_payload, true);
                if (!is_array($payload)) continue;

                $details = $payload['details'] ?? [];
                if (!is_array($details)) continue;

                $cid = (string) $r->campaign_id;
                $aid = (string) $r->advert_id;

                $campaignName = $campaignMap[$cid] ?? null;
                $advertName = $advertMap[$aid] ?? ($payload['advert_name'] ?? null);

                // Count group once per unique user in this review
                $uniqueUserIds = collect($details)->pluck('user_id')->unique()->values();

                foreach ($uniqueUserIds as $uid) {
                    $uid = (string) $uid;

                    // Find a name from details for this user
                    $firstMatch = collect($details)->firstWhere('user_id', $uid);
                    $userName = is_array($firstMatch) ? ($firstMatch['name'] ?? null) : null;

                    if (!isset($users[$uid])) {
                        $users[$uid] = [
                            'user_id' => $uid,
                            'name' => $userName,
                            'confirmed_groups_count' => 0,
                            'campaigns' => [], // [{campaign_id, campaign_name, count}]
                            'adverts' => [],   // [{advert_id, advert_name, count}]
                            'last_confirmed_at' => $r->reviewed_at,
                            'sample_evidence' => [], // up to 5 urls
                        ];
                    }

                    if (!$users[$uid]['name'] && $userName) {
                        $users[$uid]['name'] = $userName;
                    }

                    // Increase group count
                    $users[$uid]['confirmed_groups_count']++;

                    // last_confirmed_at
                    if ($r->reviewed_at && (!$users[$uid]['last_confirmed_at'] || $r->reviewed_at > $users[$uid]['last_confirmed_at'])) {
                        $users[$uid]['last_confirmed_at'] = $r->reviewed_at;
                    }

                    // Track campaigns with counts
                    if ($cid) {
                        if (!isset($users[$uid]['campaigns'][$cid])) {
                            $users[$uid]['campaigns'][$cid] = [
                                'campaign_id' => $cid,
                                'campaign_name' => $campaignName,
                                'count' => 0,
                            ];
                        }
                        $users[$uid]['campaigns'][$cid]['count']++;
                    }

                    // Track adverts with counts
                    if ($aid) {
                        if (!isset($users[$uid]['adverts'][$aid])) {
                            $users[$uid]['adverts'][$aid] = [
                                'advert_id' => $aid,
                                'advert_name' => $advertName,
                                'count' => 0,
                            ];
                        }
                        $users[$uid]['adverts'][$aid]['count']++;
                    }
                }

                // Add a few evidence urls per user (limit)
                foreach ($details as $d) {
                    $uid = isset($d['user_id']) ? (string) $d['user_id'] : null;
                    $url = $d['url'] ?? null;
                    if (!$uid || !$url || !isset($users[$uid])) continue;

                    if (count($users[$uid]['sample_evidence']) < 5 && !in_array($url, $users[$uid]['sample_evidence'], true)) {
                        $users[$uid]['sample_evidence'][] = $url;
                    }
                }
            }

            // 4) Convert campaign/adverts maps into arrays + sort (most frequent first)
            $usersList = array_values($users);

            foreach ($usersList as &$u) {
                $u['campaigns'] = array_values($u['campaigns']);
                usort($u['campaigns'], fn($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

                $u['adverts'] = array_values($u['adverts']);
                usort($u['adverts'], fn($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
            }
            unset($u);

            // Sort guilty users by most confirmed groups
            usort($usersList, fn($a, $b) => ($b['confirmed_groups_count'] ?? 0) <=> ($a['confirmed_groups_count'] ?? 0));

            return response()->json([
                'campaign_filter' => $campaignId,
                'guilty_users_count' => count($usersList),
                'guilty_users' => $usersList,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
