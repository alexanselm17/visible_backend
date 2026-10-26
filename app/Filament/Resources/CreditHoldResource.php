<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class CreditHoldResource extends CatalogResource
{
    protected static ?string $model = \App\Models\CreditHold::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Legacy records';

    protected static ?string $navigationLabel = 'Credit reservations (legacy)';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['wallet.user.fullname', 'Owner', 'string'], ['advert_submission_id', 'Submission', 'string'], ['amount_locked', 'Reserved', 'number'], ['amount_spent', 'Spent', 'number'], ['amount_released', 'Returned', 'number'], ['status', 'Status', 'status']];

    protected static array $exportColumns = ['wallet.user.fullname', 'advert_submission_id', 'amount_locked', 'amount_spent', 'amount_released', 'status'];

    public static function getPages(): array
    {
        return ['index' => CreditHoldResource\Pages\ManageCreditHolds::route('/')];
    }
}
