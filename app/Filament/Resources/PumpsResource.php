<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PumpsResource\Pages;
use App\Models\PetrolStation;
use App\Models\Pump;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PumpsResource extends Resource
{
  protected static ?string $model = Pump::class;

  protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

  protected static ?string $navigationGroup = 'Fuel Management';

  protected static ?int $navigationSort = 3;

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Card::make()->schema([
          TextInput::make('name')
            ->required()
            ->maxLength(255),

          Select::make('petrol_station_id')
            ->label('Petrol Station')
            ->options(fn() => PetrolStation::where('company_id', Auth::user()->company_id)
              ->pluck('name', 'id')->toArray())
            ->required()
            ->searchable()
            ->live()
            ->afterStateUpdated(fn(callable $set) => $set('drum_id', null)),

          Select::make('drum_id')
            ->label('Drum')
            ->options(function (callable $get) {
              $stationId = $get('petrol_station_id');
              if (!$stationId) {
                return [];
              }
              return \App\Models\Drum::where('petrol_id', $stationId)
                ->pluck('name', 'id');
            })
            ->required()
            ->searchable()
            ->disabled(fn(callable $get) => !$get('petrol_station_id')),
        ]),
      ]);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        TextColumn::make('name'),
        TextColumn::make('curr_volume'),
        TextColumn::make('curr_cash'),
        TextColumn::make('drum.name')
          ->label('Drum Name'),
        TextColumn::make('is_on_shift')
          ->label('On Shift')
          ->getStateUsing(fn($record) => $record->is_on_shift ? 'True' : 'False')
          ->color(fn($state) => $state === 'True' ? 'success' : 'danger'),
        TextColumn::make('petrolStation.name')
          ->label('Petrol Station'),
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
          ->relationship('petrolStation', 'name', function ($query) {
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
        DeleteBulkAction::make(),
      ])
      ->defaultSort('id', 'desc')
      ->modifyQueryUsing(function ($query) {
        return $query
          ->whereHas('petrolStation', function ($query) {
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
      'index' => Pages\ListPumps::route('/'),
      'create' => Pages\CreatePumps::route('/create'),
      'edit' => Pages\EditPumps::route('/{record}/edit'),
      'view' => Pages\ViewPump::route('/{record}'),
    ];
  }
}
