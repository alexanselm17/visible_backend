<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\PetrolStation;
use App\Models\ProductsModel;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Get;
use Closure;

class ProductResource extends Resource
{
  protected static ?string $model = ProductsModel::class;

  protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
  protected static ?string $navigationLabel = 'Products';
  protected static ?string $navigationGroup = 'System Management';
  protected static ?int $navigationSort = 2;

  public static function getModelLabel(): string
  {
    return 'Products';
  }

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Grid::make(2)->schema([
          TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->placeholder('Enter product name')
            ->helperText('Enter the full name of the product'),

          Select::make('category')
            ->required()
            ->options([
              'FUEL' => 'FUEL',
              'LUBE' => 'LUBE',
              'OTHERS' => 'OTHERS'

            ])
            ->default('LUBE')
            ->placeholder('Select category')
            ->helperText('Choose whether this is a fuel or lubricant product'),

          TextInput::make('buying_price')
            ->required()
            ->numeric()
            ->minValue(0)
            ->placeholder('Enter buying price')
            ->live()
            ->rules(['required', 'numeric', 'min:0'])
            ->helperText('Enter the cost price per unit (in KES)'),

          TextInput::make('selling_price')
            ->required()
            ->numeric()
            ->minValue(0)
            ->placeholder('Enter selling price')
            ->live()
            ->rules([
              'required',
              'numeric',
              'min:0',

            ])
            ->helperText('Enter the selling price per unit (in KES)'),

          TextInput::make('min_stock')
            ->required()
            ->numeric()
            ->minValue(0)
            ->placeholder('Enter minimum stock level')
            ->rules(['required', 'numeric', 'min:0'])
            ->helperText('Set the minimum stock level for reorder alerts')
            ->columnSpanFull(),

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
            ->default(fn(?ProductsModel $record) => $record?->petrol_id)
            ->placeholder('Select a Petrol Station')
            ->helperText('Select the petrol station where this product will be stocked (cannot be changed later)')
            ->disabled(fn(?ProductsModel $record) => $record !== null)
            ->dehydrated()
        ])
      ]);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        TextColumn::make('name')
          ->searchable()
          ->sortable(),

        TextColumn::make('category')
          ->searchable()
          ->sortable(),

        TextColumn::make('buying_price')
          ->money('KES')
          ->sortable(),

        TextColumn::make('selling_price')
          ->money('KES')
          ->sortable(),

        TextColumn::make('min_stock')
          ->numeric()
          ->sortable(),

        TextColumn::make('petrolStation.name')
          ->label('Petrol Station')
          ->searchable()
          ->sortable(),
      ])
      ->filters([
        SelectFilter::make('category')
          ->options([
            'FUEL' => 'FUEL',
            'LUBE' => 'LUBE',
            'OTHERS' => 'OTHERS'
          ]),

        SelectFilter::make('petrol_id')
          ->label('Petrol Station')
          ->relationship('petrolStation', 'name', function (Builder $query) {
            return $query->where('company_id', Auth::user()->company_id);
          }),
      ])
      ->actions([
        EditAction::make()
          ->button(),
      ])
      ->bulkActions([
        DeleteBulkAction::make(),
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
      'index' => Pages\ListProducts::route('/'),
      'create' => Pages\CreateProduct::route('/create'),
      'edit' => Pages\EditProduct::route('/{record}/edit'),
    ];
  }

  public static function getEloquentQuery(): Builder
  {
    return parent::getEloquentQuery()
      ->whereHas('petrolStation', function (Builder $query) {
        $query->where('company_id', Auth::user()->company_id);
      });
  }
}
