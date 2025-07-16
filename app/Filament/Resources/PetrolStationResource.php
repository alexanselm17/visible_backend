<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PetrolStationResource\Pages;
use App\Models\PetrolStation;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PetrolStationResource extends Resource
{
  protected static ?string $model = PetrolStation::class;
  protected static ?string $navigationGroup = 'System Management';
  protected static ?string $navigationIcon = 'heroicon-o-folder-open';

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Card::make()
          ->schema([
            TextInput::make('name')
              ->label('Petrol Station Name')
              ->required()
              ->maxLength(255),

            Select::make('type')
              ->label('Station Type')
              ->options([
                'IOT' => 'IOT',
                'NON-IOT' => 'NON-IOT',
              ])
              ->required(),

            // Hidden field for company_id that automatically sets to user's company
            TextInput::make('company_id')
              ->default(fn() => Auth::user()->company_id)
              ->hidden()
          ])
          ->columns(1),
      ]);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        TextColumn::make('name')
          ->label('Petrol Station Name')
          ->searchable()
          ->sortable(),
        TextColumn::make('type')
          ->label('Type')
          ->searchable()
          ->sortable(),
        TextColumn::make('company.name')
          ->label('Company')
          ->searchable()
          ->sortable(),
      ])
      ->filters([
        // Add any additional filters if needed
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
      ->modifyQueryUsing(function (Builder $query) {
        return $query->where('company_id', Auth::user()->company_id);
      });
  }

  public static function getRelations(): array
  {
    return [
      // Define relationships if needed
    ];
  }

  public static function getPages(): array
  {
    return [
      'index' => Pages\ListPetrolStations::route('/'),
      'create' => Pages\CreatePetrolStation::route('/create'),
      'edit' => Pages\EditPetrolStation::route('/{record}/edit'),
      'view' => Pages\ViewPetrolStation::route('/{record}'),
    ];
  }

  public static function getEloquentQuery(): Builder
  {
    return parent::getEloquentQuery()
      ->where('company_id', Auth::user()->company_id);
  }

  // Optional: Add global search if needed
  public static function getGloballySearchableAttributes(): array
  {
    return ['name', 'type', 'company.name'];
  }
}
