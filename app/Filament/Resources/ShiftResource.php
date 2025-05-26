<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use App\Models\PetrolStation;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Form;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ShiftResource extends Resource
{
  protected static ?string $model = Shift::class;

  protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';
  protected static ?string $navigationGroup = 'System Management';
  protected static ?int $navigationSort = 1;

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Card::make()->schema([
          TextInput::make('description')
            ->required()
            ->maxLength(255)
            ->placeholder('Enter shift description'),



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
            ->default(fn() => Auth::user()->petrol_id)
            ->disabled(fn() => !Auth::user()->isAdmin())
            ->placeholder('Select a Petrol Station'),
        ])
          ->columns(1),
      ]);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        TextColumn::make('description')
          ->searchable()
          ->sortable(),

        TextColumn::make('started_at')
          ->label('Started At')
          ->dateTime('Y-m-d H:i:s')
          ->sortable(),

        TextColumn::make('ended_at')
          ->label('Ended At')
          ->getStateUsing(fn($record) => $record->ended_at ?? 'Ongoing')
          ->color(fn($state) => $state === 'Ongoing' ? 'success' : 'default')
          ->sortable(),

        TextColumn::make('petrolStation.name')
          ->label('Petrol Station')
          ->searchable()
          ->sortable(),
      ])
      ->filters([
        SelectFilter::make('petrol_id')
          ->label('Petrol Station')
          ->relationship('petrolStation', 'name', function (Builder $query) {
            return $query->where('company_id', Auth::user()->company_id);
          })
          ->visible(fn() => Auth::user()->isAdmin()),

        SelectFilter::make('status')
          ->label('Status')
          ->options([
            'ongoing' => 'Ongoing',
            'ended' => 'Ended'
          ])
          ->query(function (Builder $query, array $data) {
            if ($data['value'] === 'ongoing') {
              $query->whereNull('ended_at');
            } elseif ($data['value'] === 'ended') {
              $query->whereNotNull('ended_at');
            }
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
      ->defaultSort('created_at', 'desc')

      ->modifyQueryUsing(function (Builder $query) {
        if (!Auth::user()->isAdmin()) {
          $query->where('petrol_id', Auth::user()->petrol_id);
        } else {
          $query->whereHas('petrolStation', function (Builder $query) {
            $query->where('company_id', Auth::user()->company_id);
          });
        }
        return $query;
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
      'index' => Pages\ListShifts::route('/'),
      'create' => Pages\CreateShift::route('/create'),
      'edit' => Pages\EditShift::route('/{record}/edit'),
      'view' => Pages\ViewShift::route('/{record}/view'),


    ];
  }

  public static function getEloquentQuery(): Builder
  {
    return parent::getEloquentQuery()
      ->when(!Auth::user()->isAdmin(), function (Builder $query) {
        $query->where('petrol_id', Auth::user()->petrol_id);
      })
      ->when(Auth::user()->isAdmin(), function (Builder $query) {
        $query->whereHas('petrolStation', function (Builder $query) {
          $query->where('company_id', Auth::user()->company_id);
        });
      });
  }

  // Optional: Add global search if needed
  public static function getGloballySearchableAttributes(): array
  {
    return ['description', 'petrolStation.name'];
  }
}
