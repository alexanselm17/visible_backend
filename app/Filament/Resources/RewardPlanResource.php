<?php

namespace App\Filament\Resources;

use App\Filament\Support\Fields;
use App\Filament\Support\OperationsResource;
use App\Http\Controllers\Api\Rewards\RewardAdminController;
use App\Models\RewardPlan;
use App\Support\AdminWorkflow;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Table;

class RewardPlanResource extends OperationsResource
{
    protected static ?string $navigationLabel = 'Reward plans';

    protected static ?string $model = RewardPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static ?string $navigationGroup = 'Performance earnings';

    protected static string $area = 'finance';

    public static function form(Form $form): Form
    {
        return $form->schema([
            F\Section::make('Plan version')->description('Create a new version to change pricing. Existing periods retain their assigned version. Multiplication is the default.')->columns(2)->schema([
                F\TextInput::make('name')->required()->maxLength(255),
                F\TextInput::make('version')->integer()->minValue(1)->required()->default(1),
                F\Select::make('calculation_method')->options(['multiply' => 'Multiply all multipliers', 'weighted_average' => 'Weighted average'])->default('multiply')->required(),
                F\TextInput::make('monthly_maximum')->label('Monthly maximum (money)')->numeric()->minValue(.01)->default(5000)->required(),
                F\TextInput::make('currency')->default('KES')->length(3)->required(),
                F\DateTimePicker::make('effective_from')->required()->default(now()),
                F\DateTimePicker::make('effective_until')->after('effective_from'),
            ]),
            F\Repeater::make('metrics')->label('Performance multipliers')->minItems(1)->required()->columnSpanFull()->schema([
                F\TextInput::make('code')->required()->regex('/^[a-z0-9_]+$/')->maxLength(100)->helperText('Unique metric key, such as views. Use a new key when changing its evaluator.'),
                F\TextInput::make('name')->required()->maxLength(255),
                F\Select::make('evaluator_key')->required()->options(['views' => 'Verified views', 'consistency' => 'All eligible adverts + both screenshots (%)', 'conversion' => 'Qualified referrals']),
                F\TextInput::make('weight')->numeric()->minValue(0)->maxValue(1)->default(1)->helperText('Used only for weighted average.'),
                F\TextInput::make('minimum_multiplier')->numeric()->minValue(0)->maxValue(1)->default(0),
                F\TextInput::make('maximum_multiplier')->numeric()->minValue(0)->maxValue(1)->default(1),
                F\KeyValue::make('settings')->helperText('Consistency supports minimum_available_hours (default 18).')->columnSpanFull(),
                F\Repeater::make('tiers')->label('Ranges → multiplier')->minItems(1)->required()->schema([
                    F\TextInput::make('minimum')->numeric()->minValue(0)->required(),
                    F\TextInput::make('maximum')->numeric()->minValue(0)->helperText('Blank means no upper limit.'),
                    F\TextInput::make('multiplier')->numeric()->minValue(0)->maxValue(1)->required()->step(.0001)->helperText('0.1 = 10%, 1 = full value'),
                ])->columns(3)->columnSpanFull(),
            ])->columns(2)->collapsible()->itemLabel(fn (array $state) => $state['name'] ?? 'New multiplier'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with('planMetrics.metric', 'planMetrics.tiers'))->columns([
            C\TextColumn::make('name')->searchable()->weight('semibold'),
            C\TextColumn::make('version')->badge()->sortable(),
            C\TextColumn::make('calculation_method')->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->title()),
            Fields::money('monthly_maximum_minor', 'Monthly maximum'),
            C\IconColumn::make('is_active')->boolean(),
            C\TextColumn::make('effective_from')->dateTime()->sortable(),
            C\TextColumn::make('effective_until')->dateTime()->placeholder('No end date'),
        ])->headerActions([
            A\Action::make('new_version')->label('Create plan version')->icon('heroicon-o-plus')
                ->form(fn (Form $form) => static::form($form))->modalWidth('6xl')
                ->action(fn (array $data) => AdminWorkflow::run(RewardAdminController::class, 'storePlan', $data)),
        ])->actions([
            A\Action::make('view')->label('Rules & ranges')->icon('heroicon-o-eye')->modalSubmitAction(false)->modalWidth('5xl')
                ->modalContent(fn ($record) => view('filament.components.reward-plan', ['plan' => $record])),
            A\Action::make('copy')->label('Copy to new version')->icon('heroicon-o-document-duplicate')->modalWidth('6xl')
                ->fillForm(fn ($record) => static::copyData($record))
                ->form(fn (Form $form) => static::form($form))
                ->action(fn (array $data) => AdminWorkflow::run(RewardAdminController::class, 'storePlan', $data)),
            A\Action::make('activate')->color('success')->requiresConfirmation()->visible(fn ($record) => ! $record->is_active)
                ->modalDescription('This version becomes eligible from its effective date. Existing periods keep their assigned plan.')
                ->action(fn ($record) => AdminWorkflow::run(RewardAdminController::class, 'activatePlan', parameters: ['planId' => $record->id])),
        ]);
    }

    public static function copyData(RewardPlan $plan): array
    {
        return [
            'name' => $plan->name, 'version' => $plan->version + 1,
            'calculation_method' => $plan->calculation_method,
            'monthly_maximum' => $plan->monthly_maximum_minor / 100, 'currency' => $plan->currency,
            'effective_from' => now(), 'metrics' => $plan->planMetrics->map(fn ($m) => [
                'code' => $m->metric->code, 'name' => $m->metric->name, 'evaluator_key' => $m->metric->evaluator_key,
                'weight' => $m->weight_basis_points / 10000, 'minimum_multiplier' => $m->minimum_basis_points / 10000,
                'maximum_multiplier' => $m->maximum_basis_points / 10000, 'settings' => $m->settings,
                'tiers' => $m->tiers->map(fn ($t) => ['minimum' => $t->minimum_value, 'maximum' => $t->maximum_value, 'multiplier' => $t->multiplier_basis_points / 10000])->all(),
            ])->all(),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => RewardPlanResource\Pages\ManageRewardPlans::route('/')];
    }
}
