<?php

namespace App\Http\Controllers\Api\CampaignOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Http\Requests\Campaign\UpdateCampaignRequest;
use App\Http\Requests\StartCampaignRequest;
use App\Repositories\Campaign\CampaignRepositoryInterface;
use App\Models\User;
use App\Repositories\Products\ProductRepositoryInterface;
use Illuminate\Http\Request;

class CampaignOwnerController extends Controller
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepo,
        private readonly ProductRepositoryInterface $productRepo
    ) {}

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

    public function index(Request $request)
    {
        $userId = (string) $request->query('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'user_id is required'], 422);
        }

        $this->assertCampaignOwner($userId);

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        return response()->json([
            'ok' => true,
            'message' => 'Campaigns fetched successfully.',
            'data' => $this->campaignRepo->paginateByOwner($userId, $perPage),
        ]);
    }

    public function store(StartCampaignRequest $request)
    {
        return $this->productRepo->startCampaigns($request);
    }

    public function show(Request $request, string $id)
    {
        $userId = (string) $request->query('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'user_id is required'], 422);
        }

        $this->assertCampaignOwner($userId);

        $campaign = $this->campaignRepo->getOwnedByIdOrFail($userId, $id);

        return response()->json([
            'ok' => true,
            'message' => 'Campaign fetched successfully.',
            'data' => $campaign,
        ]);
    }



    public function destroy(Request $request, string $id)
    {
        $userId = (string) ($request->input('user_id') ?? $request->query('user_id'));
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'user_id is required'], 422);
        }

        $this->assertCampaignOwner($userId);

        $this->campaignRepo->deleteOwnedByIdOrFail($userId, $id);

        return response()->json([
            'ok' => true,
            'message' => 'Campaign deleted successfully.',
        ]);
    }
}
