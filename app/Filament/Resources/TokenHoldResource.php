<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class TokenHoldResource extends CatalogResource
{
    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return parent::table($table)->filters([
            \Filament\Tables\Filters\SelectFilter::make('token_wallet_id')->relationship('wallet', 'id')->searchable()->label('Wallet'),
        ]);
    }

    protected static ?string $model = \App\Models\TokenHold::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'Tokens';

    protected static ?string $navigationLabel = 'Token reservations';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['wallet.user.fullname', 'Owner', 'string'], ['wallet.tokenType.code', 'Token', 'string'], ['submission.name', 'Submission', 'string'], ['amount_locked', 'Reserved', 'number'], ['amount_spent', 'Spent', 'number'], ['amount_released', 'Returned', 'number'], ['status', 'Status', 'status'], ['created_at', 'Reserved at', 'date']];

    protected static array $exportColumns = ['wallet.user.fullname', 'wallet.tokenType.code', 'submission.name', 'amount_locked', 'amount_spent', 'amount_released', 'status', 'created_at'];

    public static function getPages(): array
    {
        return ['index' => TokenHoldResource\Pages\ManageTokenHolds::route('/')];
    }
}
