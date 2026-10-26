<?php

namespace App\Filament\Resources;

use App\Filament\Support\OperationsResource;
use App\Models\SubCounty;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubCountyResource extends OperationsResource
{
    protected static ?string $navigationLabel = 'Subcounties';

    protected static ?string $model = SubCounty::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Settings';

    protected static string $area = 'settings';

    protected static bool $editable = true;

    public static function form(Form $form): Form
    {
        return $form->schema([
            F\TextInput::make('name')->required()->maxLength(255),
            F\Select::make('county_id')->relationship('county', 'name')->required()->searchable()->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->columns([
            C\TextColumn::make('name')->searchable(), C\TextColumn::make('county.name')->label('County')->searchable(),
        ])->filters([SelectFilter::make('county_id')->relationship('county', 'name')])->actions([A\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => SubCountyResource\Pages\ManageSubCountys::route('/')];
    }
}
