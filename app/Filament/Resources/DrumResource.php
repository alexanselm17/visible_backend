<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DrumResource\Pages;
use App\Models\Drum;
use App\Models\ProductsModel;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DrumResource extends Resource
{
  protected static ?string $model = Drum::class;

  protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

  protected static ?string $navigationGroup = 'Fuel Management';


  protected static ?string $navigationLabel = 'Tanks';

  protected static ?string $modelLabel = 'Tank';

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Section::make('Drum Details')
          ->description('Enter the basic information for the drum.')
          ->schema([
            TextInput::make('name')
              ->required()
              ->maxLength(255)
              ->placeholder('Enter drum name')
              ->label('Drum Name')
              ->helperText('Enter the name of the drum'),

            TextInput::make('capacity')
              ->label('Volume')
              ->numeric()
              ->prefix('Litres')
              ->minValue(0)
              ->required()
              ->rules(['numeric', 'min:0'])
              ->helperText('Enter the volume in litres'),

            Select::make('petrol_station_id')
              ->label('Petrol Station')
              ->relationship(
                'petrolStation',
                'name',
                fn($query) => $query->where('company_id', Auth::user()->company_id)
              )
              ->required()
              ->searchable()
              ->preload()
              ->live()
              ->helperText('Select the petrol station of the drum')
              ->afterStateUpdated(fn(callable $set) => $set('product_id', null)),

            Select::make('product_id')
              ->label('Product')
              ->options(function (callable $get) {
                $stationId = $get('petrol_station_id');

                if (!$stationId) {
                  return [];
                }

                return ProductsModel::query()
                  ->where('petrol_id', $stationId)
                  ->where('category', 'FUEL')
                  ->pluck('name', 'id');
              })
              ->required()
              ->searchable()
              ->preload()
              ->helperText('Select the product/fuel of the drum')
              ->disabled(fn(callable $get) => !$get('petrol_station_id')),

          ])
          ->columns(2),
      ]);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        TextColumn::make('name')
          ->searchable()
          ->sortable(),
        TextColumn::make('product.name')
          ->searchable()
          ->sortable(),
        TextColumn::make('capacity')
          ->sortable(),
        TextColumn::make('is_on_shift')
          ->label('On Shift')
          ->getStateUsing(fn($record) => $record->is_on_shift ? 'True' : 'False')
          ->color(fn($state) => $state === 'True' ? 'success' : 'danger'),
        TextColumn::make('petrolStation.name')
          ->label('Petrol Station')
          ->searchable()
          ->sortable(),
      ])
      ->filters([
        SelectFilter::make('is_on_shift')
          ->label('On Shift')
          ->options([
            false => 'False',
            true => 'True',
          ]),
        SelectFilter::make('petrol_station_id')
          ->label('Petrol Station')
          ->relationship('petrolStation', 'name', function (Builder $query) {
            return $query->where('company_id', Auth::user()->company_id);
          }),
      ])
      ->actions([
        ViewAction::make()
          ->button(),

        Tables\Actions\EditAction::make()
          ->button(),
      ])
      ->bulkActions([
        Tables\Actions\BulkActionGroup::make([
          Tables\Actions\DeleteBulkAction::make(),
        ]),
      ])
      ->defaultSort('id', 'desc')
      ->modifyQueryUsing(function (Builder $query) {
        return $query
          ->whereHas('petrolStation', function (Builder $query) {
            $query->where('company_id', Auth::user()->company_id);
          })
          ->latest();
      });
  }

  public static function getRelations(): array
  {
    return [
      //
    ];
  }

  public static function getPages(): array
  {
    return [
      'index' => Pages\ListDrums::route('/'),
      'create' => Pages\CreateDrum::route('/create'),
      'edit' => Pages\EditDrum::route('/{record}/edit'),
      'view' => Pages\ViewDrum::route('/{record}'),
    ];
  }

  public static function getGloballySearchableAttributes(): array
  {
    return ['name', 'product.name', 'petrolStation.name'];
  }
}
