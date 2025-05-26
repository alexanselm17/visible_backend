<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Filament\Forms\Components\Card;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class SalesReport extends Page implements HasTable
{
  use InteractsWithTable;

  protected static ?string $navigationIcon = 'heroicon-o-document-report';
  protected static string $view = 'reports.sales_report';
  public $shiftId;
  public $petrolStationId;
  public $shift;
  public $petrolStation;
  public $sales;
  public $pumps;
  public $stations;
  public $bankings;
  public $invoiceSales;
  public $invoiceRepayments;
  public $productPumpSales;
  public $totalSales;
  public $totalBankingsAmount;
  public $totalInvoiceAmount;
  public $totalAmountRepaid;

  public function mount($petrolStationId, $shiftId)
  {
    $this->petrolStationId = $petrolStationId;
    $this->shiftId = $shiftId;
    $this->loadData();
  }

  protected function loadData()
  {
    // Fetch petrol station
    $this->petrolStation = \App\Models\PetrolStation::findOrFail($this->petrolStationId);

    // Fetch shift with related data
    $this->shift = \App\Models\Shift::with(['drumSessionDetails', 'pumpSessionDetails'])
      ->where('petrol_id', $this->petrolStationId)
      ->findOrFail($this->shiftId);

    // Load all related data using your existing repository logic
    $repository = new \App\Repositories\Sales\SalesRepository();
    $request = new \Illuminate\Http\Request();
    $data = $repository->generateSalesReport($request, $this->petrolStationId, $this->shiftId);

    // Assign the data to class properties
    $this->sales = $data['sales'];
    $this->pumps = $data['pumps'];
    $this->stations = $data['stations'];
    $this->bankings = $data['bankings'];
    $this->invoiceSales = $data['invoiceSales'];
    $this->invoiceRepayments = $data['invoiceRepayments'];
    $this->productPumpSales = $data['productPumpSales'];
    $this->totalSales = $data['totalSales'];
    $this->totalBankingsAmount = $data['totalBankingsAmount'];
    $this->totalInvoiceAmount = $data['totalInvoiceAmount'];
    $this->totalAmountRepaid = $data['totalAmountRepaid'];
  }

  public function getHeader(): ?\Illuminate\Contracts\View\View
  {
    return view('reports.sales_report', [
      'petrolStation' => $this->petrolStation,
      'shift' => $this->shift,
      'totalSales' => $this->totalSales,
      'totalBankingsAmount' => $this->totalBankingsAmount,
      'totalInvoiceAmount' => $this->totalInvoiceAmount,
      'totalAmountRepaid' => $this->totalAmountRepaid,
    ]);
  }

  protected function getTableQuery(): Builder
  {
    return Transaction::query()
      ->where('shift_id', $this->shiftId)
      ->where('petrol_id', $this->petrolStationId);
  }

  protected function getTableColumns(): array
  {
    return [
      TextColumn::make('id')
        ->label('Transaction ID')
        ->searchable(),
      TextColumn::make('created_at')
        ->label('Date')
        ->dateTime()
        ->sortable(),
      TextColumn::make('amount')
        ->label('Amount')
        ->money('USD'),
      TextColumn::make('type')
        ->label('Type')
        ->searchable(),
      TextColumn::make('status')
        ->label('Status')
        ->badge()
    ];
  }

  protected function getActions(): array
  {
    return [
      \Filament\Pages\Actions\Action::make('print')
        ->label('Print Report')
        ->icon('heroicon-o-printer')
        ->action(function () {
          // Handle print action
        }),
      \Filament\Pages\Actions\Action::make('export')
        ->label('Export')
        ->icon('heroicon-o-download')
        ->action(function () {
          // Handle export action
        })
    ];
  }
}
