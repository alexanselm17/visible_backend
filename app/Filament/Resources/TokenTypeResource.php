<?php

namespace App\Filament\Resources;

use App\Filament\Support\OperationsResource;
use App\Http\Controllers\Api\Tokens\TokenController;
use App\Models\TokenType;
use App\Support\AdminWorkflow;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns as C;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TokenTypeResource extends OperationsResource
{
    protected static ?string $model = TokenType::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Tokens';

    protected static ?string $navigationLabel = 'Pricing & reach rules';

    protected static string $area = 'finance';

    protected static bool $editable = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            F\Section::make('Token pricing')->description('Gold must remain more expensive than Platinum, and Platinum more expensive than Silver.')->columns(2)->schema([
                F\TextInput::make('code')->disabled()->dehydrated(false),
                F\TextInput::make('unit_price')->numeric()->minValue(0.01)->required(),
                F\TextInput::make('currency')->length(3)->required(),
                F\TextInput::make('people_per_token')->integer()->minValue(1)->required()->helperText('People covered by one reach token. Existing adverts retain their saved pricing.'),
                F\TextInput::make('seconds_per_token')->integer()->minValue(1)->required(fn ($record) => $record->media_type === 'VIDEO')->visible(fn ($record) => $record->media_type === 'VIDEO'),
                F\TextInput::make('max_video_duration_seconds')->integer()->minValue(1)->visible(fn ($record) => $record->media_type === 'VIDEO'),
                F\Textarea::make('description')->columnSpanFull(), F\Toggle::make('is_active')->label('Available for purchase'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->columns([
            C\TextColumn::make('name')->searchable(), C\TextColumn::make('code')->badge(),
            C\TextColumn::make('media_type')->badge(), C\TextColumn::make('unit_price')->money(fn ($record) => $record->currency),
            C\TextColumn::make('people_per_token')->label('People / token'),
            C\TextColumn::make('seconds_per_token')->label('Seconds / token')->placeholder('—'),
            C\IconColumn::make('is_active')->boolean(),
        ])->actions([
            EditAction::make()->using(function (Model $record, array $data): Model {
                AdminWorkflow::run(TokenController::class, 'updateType', $data, ['code' => $record->code, 'tokenService' => app(\App\Services\TokenService::class)]);

                return $record->refresh();
            }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => TokenTypeResource\Pages\ManageTokenTypes::route('/')];
    }
}
