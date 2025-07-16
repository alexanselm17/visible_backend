<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StationResource\Pages\CreateStations;
use App\Filament\Resources\StationsResource\Pages;
use App\Models\PetrolStation;
use App\Models\Stations;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StationsResource extends Resource
{
  protected static ?string $model = Stations::class;

  protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
  protected static ?string $navigationGroup = 'System Management';

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Card::make()->schema([
          TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->placeholder('Enter station name')
            ->label('Station Name'),

          Select::make('petrol_id')
            ->label('Petrol Station')
            ->relationship(
              'petrolStation',
              'name',
              fn(Builder $query) => $query->where('company_id', Auth::user()->company_id)
            )
            ->required()
            ->searchable()
            ->preload()
            ->placeholder('Select a Petrol Station')
            ->disabled(fn(?Stations $record) => $record !== null)
            ->dehydrated(),
        ])
          ->columns(1)
      ]);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        TextColumn::make('name')
          ->searchable()
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

        SelectFilter::make('petrol_id')
          ->label('Petrol Station')
          ->relationship('petrolStation', 'name', function (Builder $query) {
            return $query->where('company_id', Auth::user()->company_id);
          }),
      ])
      ->actions([
        ViewAction::make()
          ->button(),

        EditAction::make()
          ->button(),

      ])
      ->bulkActions([
        Tables\Actions\BulkActionGroup::make([
          Tables\Actions\DeleteBulkAction::make(),
        ]),
      ])
      ->modifyQueryUsing(function (Builder $query) {
        return $query->whereHas('petrolStation', function (Builder $query) {
          $query->where('company_id', Auth::user()->company_id);
        });
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
      'index' => Pages\ListStations::route('/'),
      'create' => CreateStations::route('/create'),
      'edit' => Pages\EditStations::route('/{record}/edit'),
      'view' => Pages\ViewStations::route('/{record}/view'),
      'shift-view' => Pages\ViewStationShift::route('/{record}/shift-view'),


    ];
  }

  public static function getEloquentQuery(): Builder
  {
    return parent::getEloquentQuery()
      ->whereHas('petrolStation', function (Builder $query) {
        $query->where('company_id', Auth::user()->company_id);
      });
  }

  // Optional: Add global search if needed
  public static function getGloballySearchableAttributes(): array
  {
    return ['name', 'petrolStation.name'];
  }
}
