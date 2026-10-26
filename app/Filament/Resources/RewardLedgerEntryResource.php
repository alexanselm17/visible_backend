<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class RewardLedgerEntryResource extends CatalogResource
{
    protected static ?string $model = \App\Models\RewardLedgerEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Performance earnings';

    protected static ?string $navigationLabel = 'Earnings ledger';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['user.fullname', 'Member', 'string'], ['type', 'Entry', 'status'], ['amount_minor', 'Amount (+/−)', 'minor'], ['currency', 'Currency', 'string'], ['description', 'Description', 'string'], ['created_at', 'Recorded', 'date'], ['metadata', 'Evidence', 'json']];

    protected static array $exportColumns = ['user.fullname', 'type', 'amount_minor', 'currency', 'description', 'created_at', 'metadata'];

    public static function getPages(): array
    {
        return ['index' => RewardLedgerEntryResource\Pages\ManageRewardLedgerEntrys::route('/')];
    }
}
