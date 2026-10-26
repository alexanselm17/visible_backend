<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class LegacyWalletResource extends CatalogResource
{
    protected static ?string $model = \App\Models\Wallet::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Legacy records';

    protected static ?string $navigationLabel = 'Credit wallets (legacy)';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['user.fullname', 'Owner', 'string'], ['balance', 'Available credits', 'number'], ['locked_balance', 'Reserved credits', 'number'], ['updated_at', 'Last change', 'date']];

    protected static array $exportColumns = ['user.fullname', 'balance', 'locked_balance', 'updated_at'];

    public static function getPages(): array
    {
        return ['index' => LegacyWalletResource\Pages\ManageLegacyWallets::route('/')];
    }
}
