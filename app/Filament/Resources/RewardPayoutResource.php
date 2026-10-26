<?php

namespace App\Filament\Resources;

use App\Filament\Support\Fields;
use App\Filament\Support\OperationsResource;
use App\Models\RewardPayout;
use App\Services\RewardPayoutService;
use Filament\Forms\Components as F;
use Filament\Notifications\Notification;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RewardPayoutResource extends OperationsResource
{
    protected static ?string $navigationLabel = 'Member payouts';

    protected static ?string $model = RewardPayout::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Performance earnings';

    protected static string $area = 'finance';

    protected static array $exportColumns = ['id', 'user.fullname', 'amount_minor', 'currency', 'status', 'provider', 'payment_reference', 'failure_reason', 'paid_at'];

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with(['user', 'period.metrics.metric']))->columns([
            C\TextColumn::make('user.fullname')->label('Member')->searchable(),
            C\TextColumn::make('user.phone')->label('Phone')->copyable()->searchable(),
            Fields::money('amount_minor', 'Amount'), Fields::status(),
            C\TextColumn::make('period.frequency')->badge(),
            C\TextColumn::make('period.ends_at')->label('Period ended')->date()->sortable(),
            C\TextColumn::make('provider')->placeholder('—'),
            C\TextColumn::make('payment_reference')->searchable()->copyable()->placeholder('—'),
            C\TextColumn::make('failure_reason')->wrap()->limit(70)->toggleable(),
            C\TextColumn::make('paid_at')->dateTime()->placeholder('—'),
        ])->filters([
            SelectFilter::make('status')->options(['pending' => 'Pending', 'processing' => 'Processing', 'paid' => 'Paid', 'failed' => 'Failed / retry']),
            SelectFilter::make('user_id')->relationship('user', 'fullname')->searchable(),
        ])->actions([
            A\Action::make('evidence')->label('Calculation')->icon('heroicon-o-eye')->modalSubmitAction(false)
                ->modalContent(fn ($record) => view('filament.components.reward-period', ['period' => $record->period])),
            A\Action::make('confirm')->label('Confirm payment')->color('success')->requiresConfirmation()
                ->visible(fn ($record) => in_array($record->status, ['pending', 'processing', 'failed']) && $record->user_id !== auth()->id())
                ->modalDescription('Record a payment already sent. The service checks duplicate references and the monthly cap. Failed payouts can be retried here.')
                ->form([
                    F\TextInput::make('provider')->required()->maxLength(100)->placeholder('e.g. mpesa'),
                    F\TextInput::make('payment_reference')->required()->maxLength(255),
                ])->action(function ($record, array $data) {
                    app(RewardPayoutService::class)->confirm($record->id, $data['provider'], $data['payment_reference'], auth()->id());
                    Notification::make()->title('Payout confirmed; payment history recorded')->success()->send();
                }),
            A\Action::make('fail')->label('Mark failed')->color('danger')->requiresConfirmation()->visible(fn ($record) => $record->status !== 'paid')
                ->form([F\Textarea::make('reason')->required()->maxLength(2000)])
                ->action(function ($record, array $data) {
                    app(RewardPayoutService::class)->fail($record->id, $data['reason'], auth()->id());
                    Notification::make()->title('Failure recorded; calculation preserved for retry')->success()->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => RewardPayoutResource\Pages\ManageRewardPayouts::route('/')];
    }
}
