<?php

namespace App\Filament\Resources;

use App\Filament\Support\Fields;
use App\Filament\Support\OperationsResource;
use App\Models\TokenType;
use App\Models\TokenWallet;
use App\Services\TokenService;
use Filament\Forms\Components as F;
use Filament\Notifications\Notification;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TokenWalletResource extends OperationsResource
{
    protected static ?string $navigationLabel = 'Owner token wallets';

    protected static ?string $model = TokenWallet::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationGroup = 'Tokens';

    protected static string $area = 'finance';

    protected static array $exportColumns = ['id', 'user.fullname', 'tokenType.code', 'balance', 'locked_balance', 'updated_at'];

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with(['user', 'tokenType']))->columns([
            C\TextColumn::make('user.fullname')->label('Owner')->searchable()->sortable(),
            C\TextColumn::make('tokenType.code')->badge(),
            C\TextColumn::make('balance')->label('Available')->numeric()->sortable(),
            C\TextColumn::make('locked_balance')->label('Reserved')->numeric()->sortable(),
            C\TextColumn::make('total_balance')->label('Total')->numeric(),
            C\TextColumn::make('updated_at')->since()->label('Last change'),
        ])->filters([
            SelectFilter::make('token_type_id')->relationship('tokenType', 'name'),
            SelectFilter::make('user_id')->relationship('user', 'fullname')->searchable()->label('Owner'),
        ])
            ->headerActions([
                A\Action::make('adjust')->label('Adjust wallet')->icon('heroicon-o-adjustments-horizontal')->form([
                    Fields::user()->required(),
                    F\Select::make('token_type')->options(fn () => TokenType::pluck('name', 'code'))->required(),
                    F\TextInput::make('amount')->integer()->required()->rules(['not_in:0'])->helperText('Positive adds tokens; negative removes available tokens. Reserved tokens cannot be removed.'),
                    F\Textarea::make('reason')->required()->maxLength(255),
                ])->requiresConfirmation()->action(function (array $data) {
                    app(TokenService::class)->adjustBalance($data['user_id'], $data['token_type'], (int) $data['amount'], $data['reason'].' [staff: '.auth()->id().']');
                    Notification::make()->title('Wallet adjusted; transaction recorded')->success()->send();
                }),
            ])->actions([
                A\Action::make('transactions')->icon('heroicon-o-list-bullet')->url(fn ($record) => TokenTransactionResource::getUrl('index', ['tableFilters' => ['token_wallet_id' => ['value' => $record->id]]])),
                A\Action::make('holds')->url(fn ($record) => TokenHoldResource::getUrl('index', ['tableFilters' => ['token_wallet_id' => ['value' => $record->id]]])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => TokenWalletResource\Pages\ManageTokenWallets::route('/')];
    }
}
