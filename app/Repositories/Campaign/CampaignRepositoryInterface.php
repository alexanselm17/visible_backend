<?php

namespace App\Repositories\Campaign;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Campaign;

interface CampaignRepositoryInterface
{
    public function paginateByOwner(string $ownerId, int $perPage = 15): LengthAwarePaginator;
    public function getOwnedByIdOrFail(string $ownerId, string $campaignId): Campaign;
    public function updateOwnedByIdOrFail(string $ownerId, string $campaignId, array $data): Campaign;
    public function deleteOwnedByIdOrFail(string $ownerId, string $campaignId): void;
}
