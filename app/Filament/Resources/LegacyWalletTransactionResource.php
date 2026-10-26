<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class LegacyWalletTransactionResource extends CatalogResource
{
    protected static ?string $model = \App\Models\WalletTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Legacy records';

    protected static ?string $navigationLabel = 'Credit history (legacy)';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['wallet.user.fullname', 'Owner', 'string'], ['type', 'Type', 'status'], ['amount', 'Credits', 'number'], ['description', 'Description', 'string'], ['created_at', 'Recorded', 'date']];

    protected static array $exportColumns = ['wallet.user.fullname', 'type', 'amount', 'description', 'created_at'];

    public static function getPages(): array
    {
        return ['index' => LegacyWalletTransactionResource\Pages\ManageLegacyWalletTransactions::route('/')];
    }
}
