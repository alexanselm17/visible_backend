<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Invoice;
use App\Models\Customers;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;

class CustomerInvoices extends ListRecords
{
  protected static string $resource = CustomerResource::class;

  public Customers $customer;

  public function mount(): void
  {
    $this->customer = Customers::findOrFail(request()->route('record'));
  }

  public function getTitle(): string
  {
    return "{$this->customer->name}'s Invoices";
  }

  protected function getTableQuery(): Builder
  {
    return Invoice::query()
      ->with(['postedBy', 'banking'])
      ->where('customer_id', $this->customer->id);
  }

  public function table(Table $table): Table
  {
    return $table
      ->recordUrl(null)
      ->columns([
        BadgeColumn::make('type'),

        TextColumn::make('amount')
          ->label('Amount')
          ->money('KES')
          ->sortable(),

        TextColumn::make('customer_balance')
          ->label('Balance')
          ->money('KES'),

        TextColumn::make('invoice_note')
          ->label('Note')
          ->limit(30)
          ->wrap()
          ->searchable(),

        TextColumn::make('payment_method')
          ->label('Payment Method'),

        TextColumn::make('postedBy.fullname')
          ->label('Posted By')
          ->searchable(),

        TextColumn::make('created_at')
          ->label('Date')
          ->dateTime('d M Y, h:ia')
          ->sortable(),
      ])
      ->defaultSort('created_at', 'desc')
      ->striped()
      ->paginated([10, 25, 50]);
  }
}
