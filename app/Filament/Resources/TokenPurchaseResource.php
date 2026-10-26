<?php

namespace App\Filament\Resources;

use App\Filament\Support\Fields;
use App\Filament\Support\OperationsResource;
use App\Models\TokenPurchase;
use App\Models\TokenType;
use App\Services\TokenService;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TokenPurchaseResource extends OperationsResource
{
    protected static ?string $navigationLabel = 'Token purchases';

    protected static ?string $model = TokenPurchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Tokens';

    protected static string $area = 'finance';

    protected static array $exportColumns = ['id', 'user.fullname', 'tokenType.code', 'quantity', 'total_amount', 'currency', 'status', 'payment_reference', 'paid_at'];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Fields::user()->required(),
            F\Select::make('token_type')->options(fn () => TokenType::where('is_active', true)->pluck('name', 'code'))->required(),
            F\TextInput::make('quantity')->integer()->minValue(1)->maxValue(100000)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with(['user', 'tokenType']))->columns([
            C\TextColumn::make('user.fullname')->searchable()->label('Owner'), C\TextColumn::make('tokenType.code')->badge(),
            C\TextColumn::make('quantity')->numeric()->sortable(), C\TextColumn::make('total_amount')->money(fn ($record) => $record->currency),
            Fields::status(), C\TextColumn::make('payment_reference')->copyable()->searchable()->placeholder('Awaiting payment'),
            C\TextColumn::make('paid_at')->dateTime()->placeholder('—'), C\TextColumn::make('created_at')->since()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(array_combine(['PENDING', 'PAID', 'FAILED', 'CANCELLED', 'REFUNDED'], ['Pending', 'Paid', 'Failed', 'Cancelled', 'Refunded'])),
            SelectFilter::make('user_id')->relationship('user', 'fullname')->label('User')->searchable(),
        ])
            ->headerActions([
                A\Action::make('new_purchase')->label('Record purchase')->form(fn (Form $form) => static::form($form))
                    ->action(function (array $data) {
                        app(TokenService::class)->createPurchase($data['user_id'], $data['token_type'], (int) $data['quantity']);
                        Notification::make()->title('Purchase recorded; confirm after receiving payment')->success()->send();
                    }),
            ])->actions([
                A\Action::make('confirm')->label('Confirm payment')->color('success')->requiresConfirmation()
                    ->modalDescription('Only confirm money already received. This credits the owner’s token wallet.')
                    ->visible(fn ($record) => $record->status === TokenPurchase::PENDING)
                    ->form([F\TextInput::make('payment_reference')->required()->maxLength(255)])
                    ->action(function ($record, array $data) {
                        app(TokenService::class)->confirmPurchase($record->id, $data['payment_reference']);
                        Notification::make()->title('Payment confirmed and wallet credited')->success()->send();
                    }),
                A\Action::make('cancel')->color('danger')->requiresConfirmation()->visible(fn ($record) => $record->status === TokenPurchase::PENDING)
                    ->action(fn ($record) => app(TokenService::class)->cancelPurchase($record->id, $record->user_id)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => TokenPurchaseResource\Pages\ManageTokenPurchases::route('/')];
    }
}
