<?php

namespace App\Filament\Resources;

use App\Filament\Support\Fields;
use App\Filament\Support\OperationsResource;
use App\Models\RewardPayoutPreference;
use App\Services\RewardPeriodService;
use Filament\Forms\Components as F;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns as C;
use Filament\Tables\Table;

class RewardPayoutPreferenceResource extends OperationsResource
{
    protected static ?string $model = RewardPayoutPreference::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Performance earnings';

    protected static ?string $navigationLabel = 'Payout schedules';

    protected static string $area = 'finance';

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with('user'))->columns([
            C\TextColumn::make('user.fullname')->label('Member')->searchable(),
            C\TextColumn::make('frequency')->badge(), C\TextColumn::make('effective_from')->dateTime()->sortable(),
        ])->headerActions([
            Action::make('schedule')->label('Set member schedule')->form([
                Fields::user()->required(), F\Select::make('frequency')->options(['weekly' => 'Weekly', 'monthly' => 'Monthly'])->required(),
            ])->action(function (array $data) {
                app(RewardPeriodService::class)->setPayoutFrequency($data['user_id'], $data['frequency']);
                Notification::make()->title('Schedule saved; current period remains intact')->success()->send();
            }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => RewardPayoutPreferenceResource\Pages\ManageRewardPayoutPreferences::route('/')];
    }
}
