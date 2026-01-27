<?php

namespace App\Repositories\Campaign;

use App\Models\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function paginateByOwner(string $ownerId, int $perPage = 15): LengthAwarePaginator
    {
        return Campaign::query()
            ->where('owner_id', $ownerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }



    public function getOwnedByIdOrFail(string $ownerId, string $campaignId): Campaign
    {
        return Campaign::query()
            ->where('owner_id', $ownerId)
            ->where('id', $campaignId)
            ->firstOrFail();
    }

    public function updateOwnedByIdOrFail(string $ownerId, string $campaignId, array $data): Campaign
    {
        $campaign = $this->getOwnedByIdOrFail($ownerId, $campaignId);
        $campaign->name = $data['name'] ?? $campaign->name;
        $campaign->save();
        return $campaign;
    }

    public function deleteOwnedByIdOrFail(string $ownerId, string $campaignId): void
    {
        $campaign = $this->getOwnedByIdOrFail($ownerId, $campaignId);
        $campaign->delete();
    }
}
