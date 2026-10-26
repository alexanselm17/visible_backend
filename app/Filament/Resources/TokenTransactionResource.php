<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class TokenTransactionResource extends CatalogResource
{
    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return parent::table($table)->filters([
            \Filament\Tables\Filters\SelectFilter::make('token_wallet_id')->relationship('wallet', 'id')->searchable()->label('Wallet'),
        ]);
    }

    protected static ?string $model = \App\Models\TokenTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Tokens';

    protected static ?string $navigationLabel = 'Token history';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['wallet.user.fullname', 'Owner', 'string'], ['wallet.tokenType.code', 'Token', 'string'], ['type', 'Movement', 'status'], ['amount', 'Tokens (+/−)', 'number'], ['description', 'Reason', 'string'], ['reference_id', 'Reference', 'string'], ['created_at', 'Recorded', 'date']];

    protected static array $exportColumns = ['wallet.user.fullname', 'wallet.tokenType.code', 'type', 'amount', 'description', 'reference_id', 'created_at'];

    public static function getPages(): array
    {
        return ['index' => TokenTransactionResource\Pages\ManageTokenTransactions::route('/')];
    }
}
