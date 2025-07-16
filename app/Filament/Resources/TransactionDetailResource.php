<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionDetailResource\Pages;
use App\Models\TransactionDetail;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Pump;
use App\Models\Station;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TransactionDetailResource extends Resource
{
  protected static ?string $model = TransactionDetail::class;

  protected static ?string $navigationIcon = 'heroicon-o-banknotes';
  protected static ?string $navigationGroup = 'Money Management';
  protected static ?int $navigationSort = 2;
  protected static ?string $recordTitleAttribute = 'transaction_id';

  public static function getNavigationBadge(): ?string
  {
    return static::getModel()::count();
  }

  public static function form(Form $form): Form
  {
    $user = Auth::user();

    return $form
      ->schema([
        Select::make('transaction_type')
          ->options([
            'sale' => 'Sale',
            'transfer' => 'Transfer',
            'return' => 'Return',
          ])
          ->required()
          ->searchable(),

        Select::make('transaction_id')
          ->relationship('transaction', 'id', function (Builder $query) use ($user) {
            if (!$user->isAdmin()) {
              return $query->whereHas('shifts', function ($q) use ($user) {
                $q->where('petrol_id', $user->petrol_id);
              });
            }
            return $query->whereHas('shifts.petrolStation', function ($q) use ($user) {
              $q->where('company_id', $user->company_id);
            });
          })
          ->required()
          ->searchable(),

        Select::make('processed_by')
          ->relationship('processedBy', 'fullname', function (Builder $query) use ($user) {
            if (!$user->isAdmin()) {
              return $query->where('petrol_id', $user->petrol_id);
            }
            return $query->where('company_id', $user->company_id);
          })
          ->required()
          ->searchable(),

        Select::make('pump_id')
          ->relationship('pump', 'name', function (Builder $query) use ($user) {
            if (!$user->isAdmin()) {
              return $query->where('petrol_id', $user->petrol_id);
            }
            return $query->whereHas('petrolStation', function ($q) use ($user) {
              $q->where('company_id', $user->company_id);
            });
          })
          ->required()
          ->searchable(),

        TextInput::make('gross_total')
          ->numeric()
          ->prefix('KSH')
          ->maxValue(42949672.95)
          ->required(),

        Select::make('station_id')
          ->relationship('station', 'name', function (Builder $query) use ($user) {
            if (!$user->isAdmin()) {
              return $query->where('id', $user->petrol_id);
            }
            return $query->where('company_id', $user->company_id);
          })
          ->required()
          ->searchable(),

        Select::make('station_id_from')
          ->relationship('stationFrom', 'name', function (Builder $query) use ($user) {
            return $query->where('company_id', $user->company_id);
          })
          ->visible(fn($get) => $get('transaction_type') === 'transfer')
          ->searchable(),

        Select::make('station_id_to')
          ->relationship('stationTo', 'name', function (Builder $query) use ($user) {
            return $query->where('company_id', $user->company_id);
          })
          ->visible(fn($get) => $get('transaction_type') === 'transfer')
          ->searchable(),

        Select::make('transfer_status')
          ->options([
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
          ])
          ->visible(fn($get) => $get('transaction_type') === 'transfer')
          ->default('pending'),

        Select::make('drum_id')
          ->relationship('drum', 'name', function (Builder $query) use ($user) {
            if (!$user->isAdmin()) {
              return $query->where('petrol_id', $user->petrol_id);
            }
            return $query->whereHas('petrolStation', function ($q) use ($user) {
              $q->where('company_id', $user->company_id);
            });
          })
          ->visible(fn($get) => $get('transaction_type') === 'transfer')
          ->searchable(),

        Select::make('approved_by')
          ->relationship('approvedBy', 'fullname', function (Builder $query) use ($user) {
            return $query->where('company_id', $user->company_id);
          })
          ->visible(fn($get) => $get('transaction_type') === 'transfer')
          ->searchable(),
      ])
      ->columns(2);
  }

  public static function table(Table $table): Table
  {
    $user = Auth::user();

    return $table
      ->recordUrl(null)
      ->modifyQueryUsing(function (Builder $query) use ($user) {
        if ($user->isAdmin()) {
          return $query->whereHas('transaction.shifts', function ($query) use ($user) {
            $query->whereHas('petrolStation', function ($query) use ($user) {
              $query->where('company_id', $user->company_id);
            });
          });
        } else {
          return $query->whereHas('transaction.shifts', function ($query) use ($user) {
            $query->where('petrol_id', $user->petrol_id);
          });
        }
      })
      ->columns([
        TextColumn::make('transaction_type')
          ->badge()
          ->formatStateUsing(fn(?string $state): string => ucfirst($state ?? 'Unknown'))
          ->color(fn(?string $state): string => match ($state ?? '') {
            'sale' => 'success',
            'transfer' => 'warning',
            'return' => 'danger',
            default => 'gray',
          })
          ->searchable(),

        TextColumn::make('transaction.shifts.description')
          ->label('Shift')
          ->searchable()
          ->sortable(),

        TextColumn::make('transaction.shifts.petrolStation.name')
          ->label('Petrol Station')
          ->searchable()
          ->sortable(),

        TextColumn::make('processedBy.fullname')
          ->label('Processed By')
          ->searchable()
          ->sortable(),

        TextColumn::make('gross_total')
          ->money('ksh')
          ->sortable(),

        TextColumn::make('transfer_status')
          ->badge()
          ->color(fn($state) => match ($state) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'gray',
          })
          ->visible(fn($record) => $record?->transaction_type === 'transfer'),

        TextColumn::make('created_at')
          ->dateTime()
          ->sortable()
          ->toggleable(isToggledHiddenByDefault: true),
      ])
      ->filters([
        Tables\Filters\SelectFilter::make('transaction_type')
          ->options([
            'sale' => 'Sale',
            'transfer' => 'Transfer',
            'return' => 'Return',
          ]),

        Tables\Filters\SelectFilter::make('transfer_status')
          ->options([
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
          ]),

        Tables\Filters\SelectFilter::make('petrol_station')
          ->label('Petrol Station')
          ->visible(fn() => Auth::user()->isAdmin())
          ->relationship('transaction.shifts.petrolStation', 'name', function (Builder $query) {
            return $query->where('company_id', Auth::user()->company_id);
          })
          ->multiple()
          ->preload(),
      ])
      ->actions([
        // Tables\Actions\EditAction::make(),
        // Tables\Actions\DeleteAction::make(),
      ])
      ->bulkActions([
        Tables\Actions\BulkActionGroup::make([
          Tables\Actions\DeleteBulkAction::make(),
        ]),
      ])
      ->defaultSort('created_at', 'desc');
  }

  public static function getEloquentQuery(): Builder
  {
    $user = Auth::user();
    $query = parent::getEloquentQuery();

    if ($user->isAdmin()) {
      return $query->whereHas('transaction.shifts', function ($query) use ($user) {
        $query->whereHas('petrolStation', function ($query) use ($user) {
          $query->where('company_id', $user->company_id);
        });
      });
    } else {
      return $query->whereHas('transaction.shifts', function ($query) use ($user) {
        $query->where('petrol_id', $user->petrol_id);
      });
    }
  }

  public static function getRelations(): array
  {
    return [];
  }

  public static function getPages(): array
  {
    return [
      'index' => Pages\ListTransactionDetails::route('/'),
      'create' => Pages\CreateTransactionDetail::route('/create'),
      'edit' => Pages\EditTransactionDetail::route('/{record}/edit'),
    ];
  }
}
