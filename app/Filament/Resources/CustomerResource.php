<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customers;
use App\Models\PetrolStation;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section as FormSection;
use Filament\Infolists\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;

class CustomerResource extends Resource
{
  protected static ?string $model = Customers::class;
  protected static ?string $navigationIcon = 'heroicon-o-users';
  protected static ?string $navigationGroup = 'Customer Management';
  protected static ?int $navigationSort = 5;
  protected static ?string $recordTitleAttribute = 'name';

  public static function getNavigationLabel(): string
  {
    return 'Customers';
  }

  public static function getNavigationBadge(): ?string
  {
    return static::getModel()::whereHas('petrolStation', function (Builder $query) {
      $query->where('company_id', auth()->user()->company_id);
    })->count();
  }

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        FormSection::make('Customer Information')
          ->description('Manage customer details')
          ->schema([
            Grid::make(2)
              ->schema([
                TextInput::make('name')
                  ->required()
                  ->maxLength(255)
                  ->placeholder('Enter customer name'),

                TextInput::make('phone')
                  ->tel()
                  ->prefix('+254')
                  ->required()
                  ->maxLength(9)
                  ->dehydrateStateUsing(function ($state) {
                    // Remove +254 if present
                    return str_replace('+254', '', $state);
                  })
                  ->formatStateUsing(function ($state) {
                    // Remove +254 if present when displaying
                    return str_replace('+254', '', $state);
                  })
                  ->helperText('Enter active contact number'),

                Select::make('petrol_id')
                  ->label('Petrol Station')
                  ->options(
                    fn() => PetrolStation::query()
                      ->where('company_id', auth()->user()->company_id)
                      ->pluck('name', 'id')
                      ->toArray()
                  )
                  ->required()
                  ->searchable()
                  ->placeholder('Select a Petrol Station'),
              ]),
          ]),
      ]);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->modifyQueryUsing(function (Builder $query) {
        return $query->whereHas('petrolStation', function (Builder $query) {
          $query->where('company_id', auth()->user()->company_id);
        });
      })
      ->columns([
        TextColumn::make('name')
          ->searchable()
          ->sortable()
          ->description(fn(Customers $record): string => $record->phone)
          ->label('Customer Name'),



        TextColumn::make('petrolStation.name')
          ->label('Petrol Station')
          ->sortable()
          ->searchable(),

        TextColumn::make('invoiceLatest.amount')
          ->label('Latest Invoice Amount')
          ->money('KES')
          ->sortable(),

        TextColumn::make('created_at')
          ->label('Registration Date')
          ->dateTime('d M Y')
          ->sortable(),
      ])
      ->filters([
        SelectFilter::make('petrol_id')  // Changed from petrol_station_id to petrol_id
          ->label('Petrol Station')
          ->options(
            fn() => PetrolStation::query()
              ->where('company_id', auth()->user()->company_id)
              ->pluck('name', 'id')
              ->toArray()
          ),
      ])
      ->actions([
        ViewAction::make()
          ->button(),

        Tables\Actions\EditAction::make()
          ->button(),

        Action::make('reconcile')
          ->url(fn(Customers $record): string =>
          static::getUrl('reconcile', ['record' => $record->id]))
          ->button()
          ->color('success')
          ->icon('heroicon-o-currency-dollar'),

        Action::make('view_invoices')
          ->label('Invoices')
          ->icon('heroicon-o-document-text')
          ->url(fn(Customers $record): string =>
          static::getUrl('invoices', ['record' => $record->id]))
          ->button()
          ->color('info'),
      ])
      ->bulkActions([
        Tables\Actions\DeleteBulkAction::make()
          ->requiresConfirmation(),
      ])
      ->defaultSort('created_at', 'desc');
  }

  public static function infolist(Infolist $infolist): Infolist
  {
    return $infolist
      ->schema([
        Section::make('Customer Details')
          ->schema([
            TextEntry::make('name')
              ->label('Customer Name'),
            TextEntry::make('phone')
              ->label('Phone Number'),
            TextEntry::make('petrolStation.name')
              ->label('Assigned Petrol Station'),
            TextEntry::make('created_at')
              ->label('Registration Date')
              ->dateTime('d M Y'),
          ]),

        Section::make('Financial Information')
          ->schema([
            TextEntry::make('invoice.amount')
              ->label('Latest Invoice Amount')
              ->money('KES'),
            TextEntry::make('invoice.invoice_number')
              ->label('Latest Invoice Number'),
            TextEntry::make('invoice.created_at')
              ->label('Latest Invoice Date')
              ->dateTime('d M Y'),
          ]),
      ]);
  }

  public static function getRelations(): array
  {
    return [];
  }

  public static function getPages(): array
  {
    return [
      'index' => Pages\ListCustomers::route('/'),
      'create' => Pages\CreateCustomer::route('/create'),
      'edit' => Pages\EditCustomer::route('/{record}/edit'),
      'view' => Pages\ViewCustomer::route('/{record}'),
      'reconcile' => Pages\ReconcileCustomer::route('/{record}/reconcile'),
      'invoices' => Pages\CustomerInvoices::route('/{record}/invoices'),
    ];
  }
}
