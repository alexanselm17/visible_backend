<?php

namespace App\Filament\Resources;

use App\Filament\Support\Fields;
use App\Filament\Support\OperationsResource;
use App\Models\RewardPeriod;
use App\Services\RewardPeriodService;
use Filament\Notifications\Notification;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RewardPeriodResource extends OperationsResource
{
    protected static ?string $navigationLabel = 'Earning periods';

    protected static ?string $model = RewardPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Performance earnings';

    protected static string $area = 'finance';

    protected static array $exportColumns = ['id', 'user.fullname', 'frequency', 'starts_at', 'ends_at', 'status', 'currency', 'calculated_amount_minor'];

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with(['user', 'plan', 'metrics.metric']))->columns([
            C\TextColumn::make('user.fullname')->label('Member')->searchable(),
            C\TextColumn::make('plan.name')->label('Plan')->description(fn ($record) => 'Version '.$record->plan?->version),
            C\TextColumn::make('frequency')->badge(),
            C\TextColumn::make('starts_at')->date()->sortable(), C\TextColumn::make('ends_at')->date()->sortable(),
            Fields::status(), C\TextColumn::make('combined_multiplier_basis_points')->label('Combined multiplier')->formatStateUsing(fn ($state) => '× '.($state / 10000)),
            Fields::money('calculated_amount_minor', 'Calculated'), Fields::money('maximum_amount_minor', 'Period cap'),
        ])->filters([
            SelectFilter::make('status')->options(['open' => 'Open estimate', 'payment_pending' => 'Awaiting payment', 'paid' => 'Paid', 'locked' => 'Locked']),
            SelectFilter::make('frequency')->options(['weekly' => 'Weekly', 'monthly' => 'Monthly']),
            SelectFilter::make('user_id')->relationship('user', 'fullname')->searchable(),
        ])->headerActions([
            A\Action::make('close_ended')->label('Close eligible ended periods')->requiresConfirmation()
                ->modalDescription('Calculates ended periods after the configured grace period, locks their results and prepares payouts.')
                ->action(function () {
                    $count = app(RewardPeriodService::class)->closeEndedPeriods();
                    Notification::make()->title("{$count} periods closed")->success()->send();
                }),
        ])->actions([
            A\Action::make('evidence')->label('Calculation & evidence')->icon('heroicon-o-calculator')->modalSubmitAction(false)->modalWidth('5xl')
                ->modalContent(fn ($record) => view('filament.components.reward-period', ['period' => $record])),
            A\Action::make('recalculate')->visible(fn ($record) => $record->status === 'open')
                ->action(function ($record) {
                    app(RewardPeriodService::class)->calculate($record);
                    Notification::make()->title('Estimate updated')->success()->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => RewardPeriodResource\Pages\ManageRewardPeriods::route('/')];
    }
}
