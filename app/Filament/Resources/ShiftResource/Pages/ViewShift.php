<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Filament\Resources\DrumResource;
use App\Filament\Resources\ShiftResource;
use App\Filament\Resources\StationsResource;
use App\Http\Controllers\SalesController;
use App\Http\Requests\CahierApprovalRequest;
use App\Http\Requests\CustomerReport;
use App\Http\Requests\PersonalSalesReport;
use App\Http\Requests\PurchasesRequest;
use App\Http\Requests\RepaymentRequest;
use App\Http\Requests\StockReport;
use App\Models\Customers;
use App\Models\Drum;
use App\Models\DrumSessionDetail;
use App\Models\PetrolStation;
use App\Models\ProductsModel;
use App\Models\Stations;
use App\Models\StationSessiontModel;
use App\Models\Stock;
use App\Models\SysMeta;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Support\Facades\Dialog;
use Filament\Forms\Components\Hidden;
use Illuminate\Http\Request;

class ViewShift extends ViewRecord
{
  protected static string $resource = ShiftResource::class;

  protected function getViewData(): array
  {
    logger()->debug('Retrieving shift data', [
      'shift_id' => $this->record->id
    ]);

    $drumSessions = $this->record->drumSessionDetails()
      ->with(['drum'])
      ->get();

    $pumpSessions = $this->record->pumpSessionDetails()
      ->with(['pump'])
      ->get();

    return [
      'drumSessions' => $drumSessions,
      'pumpSessions' => $pumpSessions,
    ];
  }

  public function updateApproval(int $bankingId, int $approvalStatus): void
  {
    try {
      $requestData = [
        'banking_id' => $bankingId,
        'approval_status' => $approvalStatus,
      ];

      $userId = auth()->id();

      $request = new CahierApprovalRequest($requestData);
      $result = app(SalesController::class)->cashierApprovals($request, $userId);

      $response = json_decode($result->getContent(), true);

      if ($response['ok'] ?? false) {
        Notification::make()
          ->title('Success')
          ->body('Transaction Approved successfully')
          ->success()
          ->send();

        $this->refreshContent();
      } else {
        Notification::make()
          ->title('Error')
          ->body($response['message'] ?? 'Failed to update transaction status')
          ->danger()
          ->send();
      }
    } catch (\Exception $e) {
      Notification::make()
        ->title('Error')
        ->body($e->getMessage())
        ->danger()
        ->send();
    }
  }

  private function refreshContent(): void
  {
    $this->dispatch('close-modal');
    $this->dispatch('refresh-page');
  }

  public function infolist(Infolist $infolist): Infolist
  {
    return $infolist
      ->schema([
        Section::make('Shift Details')
          ->schema([
            TextEntry::make('started_at')->dateTime(),
            TextEntry::make('ended_at')
              ->label('Ended At')
              ->getStateUsing(fn($record) => $record->ended_at ?? 'Ongoing')
              ->color(fn($state) => $state === 'Ongoing' ? 'success' : 'default'),
            TextEntry::make('description'),
            TextEntry::make('petrolStation.name')->label('Station'),
          ])
          ->columns(4),

        Section::make('Drum Sales')
          ->schema([
            $this->buildDrumTable(),
          ]),

        Section::make('Pump Sales')
          ->schema([
            $this->buildPumpTable(),
          ]),
      ]);
  }

  private function buildDrumTable(): TextEntry
  {
    return TextEntry::make('drumSessions')
      ->label('')
      ->html()
      ->state(function () {
        logger()->debug('Processing drum sessions');

        $sessions = $this->getViewData()['drumSessions'];
        $totalSales = 0;

        $html = $this->getTableHeader('drum');
        $html .= '<tbody class="bg-gray-900">';

        foreach ($sessions as $session) {

          $salesData = $this->calculateDrumSales($session);
          $totalSales += $salesData['sessionSales'];
          $html .= $this->getDrumTableRow($session, $salesData);
        }

        $html .= $this->getDrumTotalRow($totalSales);
        $html .= '</tbody></table></div>';

        return $html;
      });
  }

  private function buildPumpTable(): TextEntry
  {
    return TextEntry::make('pumpSessions')
      ->label('')
      ->html()
      ->state(function () {
        logger()->debug('Processing pump sessions');

        $sessions = $this->getViewData()['pumpSessions'];
        $totalSales = 0;

        $html = $this->getTableHeader('pump');
        $html .= '<tbody class="bg-gray-900">';

        foreach ($sessions as $session) {


          $salesData = $this->calculatePumpSales($session);
          $totalSales += $salesData['sessionSales'];
          $html .= $this->getPumpTableRow($session, $salesData);
        }

        $html .= $this->getTotalRow($totalSales);
        $html .= '</tbody></table></div>';

        return $html;
      });
  }

  private function getTableHeader(string $type): string
  {
    $columns = $type === 'pump'
      ? ['Pump', 'Start Volume', 'Start Cash', 'End Cash', 'End Volume', 'Price', 'Sales']
      : ['Drum', 'Start Volume', 'End Volume', 'Price', 'Sold Litres'];

    $header = '<div class="overflow-x-auto">
          <table class="min-w-full border border-gray-600">
              <thead>
                  <tr>';

    foreach ($columns as $column) {
      $header .= sprintf(
        '<th class="px-6 py-3 bg-gray-800 text-left text-xs font-medium text-gray-200 uppercase border border-gray-600">%s</th>',
        $column
      );
    }

    $header .= '</tr></thead>';

    return $header;
  }

  private function calculateDrumSales($session): array
  {
    $startVolume = $session->start_volume ?? 0;
    $endVolume = $session->ended_volume ?? 0;
    $salesVolume = max(0, $endVolume - $startVolume);
    $sessionSales = $startVolume - $endVolume;


    return [
      'startVolume' => $startVolume,
      'endVolume' => $endVolume,
      'salesVolume' => $salesVolume,
      'sessionSales' => $sessionSales
    ];
  }

  private function calculatePumpSales($session): array
  {
    $startReading = $session->start_volume ?? 0;
    $endReading = $session->ended_volume ?? $startReading;
    $startCash = $session->start_cash ?? 0;
    $endCash = $session->ended_cash ?? 0;
    $salesVolume = max(0, $endReading - $startReading);
    $sessionSales = $endCash != 0 ? $endCash - $startCash : 0;


    return [
      'startReading' => $startReading,
      'endReading' => $endReading,
      'salesVolume' => $salesVolume,
      'startCash' => $startCash,
      'endCash' => $endCash,
      'sessionSales' => $sessionSales
    ];
  }

  private function getDrumTableRow($session, array $salesData): string
  {
    return sprintf(
      '<tr>
              <td class="px-6 py-4 text-sm text-gray-200 border border-gray-600">%s</td>
              <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">%s</td>
              <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">%s</td>
              <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">KES %s</td>
              <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600"> %s</td>
          </tr>',
      $session->drum->name ?? 'N/A',
      number_format($salesData['startVolume'], 2),
      number_format($salesData['endVolume'], 2),
      number_format($session->price, 2),
      number_format($salesData['sessionSales'], 2)
    );
  }

  private function getPumpTableRow($session, array $salesData): string
  {
    return sprintf(
      '<tr>
            <td class="px-6 py-4 text-sm text-gray-200 border border-gray-600">%s</td>
            <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">%s</td>
            <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">KES %s</td>
            <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">KES %s</td>
            <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">%s</td>
            <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">KES %s</td>
            <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">KES %s</td>
        </tr>',
      $session->pump->name ?? 'N/A',
      number_format($salesData['startReading'], 2),
      number_format($salesData['startCash'], 2),
      number_format($salesData['endCash'], 2),
      number_format($salesData['endReading'], 2),
      number_format($session->price, 2),
      number_format($salesData['sessionSales'], 2)
    );
  }

  private function getTotalRow($totalSales): string
  {
    return sprintf(
      '<tr>
            <td colspan="5" class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600 font-bold"></td>
            <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600 font-bold">Total Sales:</td>
            <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600 font-bold">KES %s</td>
        </tr>',
      number_format($totalSales, 2)
    );
  }

  private function getDrumTotalRow($totalSales): string
  {
    return sprintf(
      '<tr>
            <td colspan="4" class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600 font-bold">Total Litres Sold (L)</td>
            <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600 font-bold"> %s</td>
        </tr>',
      number_format($totalSales, 2)
    );
  }

  protected function getHeaderActions(): array
  {
    return [


      ActionGroup::make([
        Action::make('station')
          ->label('Stations')
          ->form([
            Select::make('station_id')
              ->label('Select Station')
              ->options(function () {
                return Stations::where('petrol_id', $this->record->petrol_id)
                  ->pluck('name', 'id');
              })
              ->default($this->record->petrol_station_id)
              ->required()
              ->searchable(),
          ])
          ->action(function (array $data) {
            return redirect()->to("/admin/stations/{$data['station_id']}/shift-view?shift_id={$this->record->id}");
          })
          ->modalWidth('md')
          ->extraAttributes(['class' => 'w-[200px] h-40 text-2xl inline-flex mx-2'])
          ->color('success')
          ->icon('heroicon-o-building-office-2'),



        Action::make('drum')
          ->label('Drum')
          ->form([
            Select::make('drum_id')
              ->label('Select Drum')
              ->options(function () {
                return \App\Models\Drum::where('petrol_id', $this->record->petrol_id)
                  ->pluck('name', 'id');
              })
              ->required()
              ->searchable(),
          ])
          ->action(function (array $data) {
            $baseUrl = DrumResource::getUrl('view', ['record' => $data['drum_id']]);
            if ($this->record->id) {
              $baseUrl .= (parse_url($baseUrl, PHP_URL_QUERY) ? '&' : '?') . "shift_id={$this->record->id}";
            }
            return redirect()->to($baseUrl);
          })
          ->modalWidth('md')
          ->extraAttributes(['class' => 'w-[400px] h-40 text-2xl inline-flex mx-2'])
          ->color('warning')
          ->icon('heroicon-o-beaker'),

        Action::make('pump')
          ->label('Pump')
          ->form([
            Select::make('pump_id')
              ->label('Select Pump')
              ->options(function () {
                return \App\Models\Pump::where('petrol_id', $this->record->petrol_id)
                  ->pluck('name', 'id');
              })
              ->required()
              ->searchable(),
          ])
          ->action(function (array $data) {
            $shiftId = $this->record->id;
            $url = "/admin/pumps/{$data['pump_id']}";

            if ($shiftId) {
              $url .= "?shift_id={$shiftId}";
            }

            return redirect()->to($url);
          })
          ->modalWidth('md')
          ->extraAttributes(['class' => 'w-[400px] h-40 text-2xl inline-flex mx-2'])
          ->color('info')
          ->icon('heroicon-o-adjustments-vertical'),

        Action::make('reports')

          ->label('Reports')
          ->extraAttributes(['class' => 'w-full md:w-96 h-40 text-2xl inline-flex mx-2'])
          ->color('primary')
          ->icon('heroicon-o-document-chart-bar')
          ->form([
            Select::make('report_type')
              ->label('Select Report Type')
              ->options([
                'sale_summary' => 'Sales Summary',
                'personal_sale_summary' => 'Personal Sales Summary',
                'customer' => 'Customer Report',
                'station_stock' => 'Station Stock Report',
                'drum_stock' => 'Drum Stock Report'
              ])
              ->live()
              ->required(),

            Select::make('user_id')
              ->label('Select User')
              ->options(function () {
                $petrolStationId = $this->record->petrol_id;
                return User::where('petrol_id', $petrolStationId)->pluck('fullname', 'id');
              })
              ->visible(fn(Get $get) => $get('report_type') === 'personal_sale_summary')
              ->required(fn(Get $get) => $get('report_type') === 'personal_sale_summary'),

            // Customer Report Fields
            Select::make('customer_id')
              ->label('Select Customer')
              ->options(Customers::where('petrol_id', $this->record->petrol_id)->pluck('name', 'id'))
              ->searchable()
              ->preload()
              ->visible(fn(Get $get): bool => $get('report_type') === 'customer')
              ->required(fn(Get $get): bool => $get('report_type') === 'customer'),

            DatePicker::make('from')
              ->label('From Date')
              ->visible(fn(Get $get): bool => $get('report_type') === 'customer')
              ->required(fn(Get $get): bool => $get('report_type') === 'customer'),

            DatePicker::make('to')
              ->label('To Date')
              ->visible(fn(Get $get): bool => $get('report_type') === 'customer')
              ->required(fn(Get $get): bool => $get('report_type') === 'customer')
              ->after('from')
              ->beforeOrEqual(now()),

            // Station Stock Report Field
            Select::make('station_id')
              ->label('Select Station')
              ->options(function () {
                return Stations::where('petrol_id', $this->record->petrol_id)
                  ->pluck('name', 'id');
              })
              ->searchable()
              ->preload()
              ->visible(fn(Get $get): bool => $get('report_type') === 'station_stock')
              ->required(fn(Get $get): bool => $get('report_type') === 'station_stock'),

            // Drum Stock Report Field
            Select::make('drum_id')
              ->label('Select Drum')
              ->options(function () {
                return Drum::where('petrol_id', $this->record->petrol_id)
                  ->pluck('name', 'id');
              })
              ->searchable()
              ->preload()
              ->visible(fn(Get $get): bool => $get('report_type') === 'drum_stock')
              ->required(fn(Get $get): bool => $get('report_type') === 'drum_stock'),
          ])
          ->action(function (array $data) {
            $petrolStationId = $this->record->petrol_id;
            $shiftId = $this->record->id;

            try {
              return match ($data['report_type']) {
                'sale_summary' => redirect()->route('reports.sales_report', [
                  'petrolStationId' => $petrolStationId,
                  'shiftId' => $shiftId
                ]),
                'personal_sale_summary' => redirect()->route('reports.personal_sales_report', [
                  'petrol_station_id' => $petrolStationId,
                  'shift_id' => $shiftId,
                  'user_id' => $data['user_id'] ?? null
                ]),
                'customer' => redirect()->route('reports.customer_report', [
                  'customer_id' => $data['customer_id'],
                  'petrol_id' => $petrolStationId,
                  'from' => $data['from'],
                  'to' => $data['to']
                ]),
                'station_stock' => redirect()->route('reports.stock_report', [
                  'petrolStationId' => $petrolStationId,
                  'station_id' => $data['station_id'],
                  'type' => 'station'
                ]),
                'drum_stock' => redirect()->route('reports.stock_report', [
                  'petrolStationId' => $petrolStationId,
                  'drum_id' => $data['drum_id'],
                  'type' => 'drum'
                ]),
                default => null
              };
            } catch (\Exception $e) {
              Notification::make()
                ->title('Error')
                ->body('Failed to generate report: ' . $e->getMessage())
                ->danger()
                ->send();
            }
          }),
      ])
        ->label('Management Actions')
        ->button()
        ->icon('heroicon-o-cog-6-tooth'),

      ActionGroup::make([
        Action::make('banking')
          ->label('Banking')
          ->extraAttributes(['class' => 'w-[400px] h-40 text-2xl inline-flex mx-2'])
          ->color('primary')
          ->url(function () {
            // Create a query string with shift and station filters
            $queryParams = http_build_query([
              'tableFilters[shift_id][value]' => $this->record->id,
              'tableFilters[petrol_station_id][value]' => $this->record->petrol_id,
            ]);

            return "/admin/bankings?{$queryParams}";
          })
          ->icon('heroicon-o-banknotes'),


        Action::make('repayment')
          ->label('Repayments')
          ->form([
            Select::make('payment.method')
              ->label('Payment Method')
              ->helperText('Select the method used for this repayment (e.g., Cash, M-PESA, Bank Transfer)')
              ->options(function () {
                return SysMeta::where('meta_key', 'deposit_account')
                  ->where('petrol_id', $this->record->petrol_id)
                  ->get()
                  ->pluck('meta_value', 'meta_value')
                  ->toArray();
              })
              ->required()
              ->live()
              ->searchable(),

            TextInput::make('payment.reference')
              ->label('Reference')
              ->helperText('Enter reference number for this payment ')
              ->required(
                fn(Get $get): bool =>
                strtolower($get('payment.method')) !== 'cash'
              )
              ->visible(
                fn(Get $get): bool =>
                strtolower($get('payment.method')) !== 'cash'
              ),
            TextInput::make('payment.amount')
              ->label('Amount')
              ->helperText('Enter the repayment amount in Kenya Shillings (KES)')
              ->required()
              ->numeric()
              ->minValue(0)
              ->prefix('KES'),

            Select::make('customer_id')
              ->label('Customer')
              ->helperText('Select the customer making the repayment')
              ->options(function () {
                return Customers::where('petrol_id', $this->record->petrol_id)
                  ->pluck('name', 'id');
              })
              ->required()
              ->searchable()
          ])
          ->action(function (array $data) {
            try {
              $requestData = [
                'payment' => [
                  'method' => $data['payment']['method'],
                  'amount' => $data['payment']['amount']
                ],
                'posted_by' => auth()->id(),
              ];

              // Add reference for M-PESA payments
              if (strtolower($data['payment']['method']) === 'mpesa') {
                $requestData['payment']['reference'] = $data['payment']['reference'];
              }

              $request = new RepaymentRequest();
              $request->merge($requestData);

              $response = app(SalesController::class)->customerRepayment(
                request: $request,
                shiftId: $this->record->id,
                customerId: $data['customer_id']
              );

              $responseData = json_decode($response->getContent(), true);

              if ($responseData['ok'] === true && $responseData['status'] === 'success') {
                Notification::make()
                  ->title('Success')
                  ->body($responseData['message'])
                  ->success()
                  ->send();

                $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
              } else {
                throw new \Exception($responseData['message'] ?? 'Failed to record repayment');
              }
            } catch (\Exception $e) {
              Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
            }
          })
          ->modalWidth('md')
          ->extraAttributes(['class' => 'w-[400px] h-40 text-2xl inline-flex mx-2'])
          ->color('warning')
          ->icon('heroicon-o-credit-card'),
        Action::make('cashier_approvals')
          ->label('Cashier Approvals')
          ->extraAttributes(['class' => 'w-[400px] h-40 text-2xl inline-flex mx-2'])
          ->color('success')
          ->modalHeading('Cashier Approvals')
          ->modalContent(function () {
            $response = app(SalesController::class)->getUnApprovedCashierTransactions(
              $this->record->petrol_id,
              $this->record->id
            );

            $data = json_decode($response->getContent(), true);

            return view('filament.custom.cashier-approvals', [
              'transactions' => $data['transactions']['data'] ?? [],
            ]);
          })
          ->modalWidth('4xl')
          ->modalActions([])
          ->icon('heroicon-o-check-circle'),


        Action::make('purchase')
          ->extraAttributes(['class' => 'w-[400px] h-40 text-2xl inline-flex mx-2'])
          ->color('info')
          ->icon('heroicon-o-shopping-cart')

          ->label('Record Purchase')
          ->form([
            Select::make('purchase_type')
              ->label('Purchase Type')
              ->options([
                'product' => 'Product',
                'fuel' => 'Fuel',
              ])
              ->helperText('Select whether you are purchasing a product or fuel')
              ->required()
              ->live(),

            Select::make('station_id')
              ->label('Station')
              ->helperText('Select the station where the purchase will be recorded')
              ->options(function () {
                return StationSessiontModel::select('stations_session_details.station_id', 'stations.name')
                  ->join('stations', 'stations.id', '=', 'stations_session_details.station_id')
                  ->where('stations_session_details.shift_id', $this->record->id)
                  ->distinct()
                  ->pluck('stations.name', 'stations_session_details.station_id');
              })
              ->required(fn(Get $get) => $get('purchase_type') === 'product')
              ->visible(fn(Get $get) => $get('purchase_type') === 'product')
              ->searchable(),

            Select::make('drum_id')
              ->label('Tank')
              ->helperText('Select the tank/drum where the fuel purchase will be recorded')
              ->options(function () {
                return Stock::select('drums.id', 'drums.name')
                  ->join('drums', 'drums.id', '=', 'stock.drum_id')
                  ->where('stock.petrol_id', auth()->user()->petrol_id)
                  ->distinct()
                  ->pluck('drums.name', 'drums.id');
              })
              ->required(fn(Get $get) => $get('purchase_type') === 'fuel')
              ->visible(fn(Get $get) => $get('purchase_type') === 'fuel')
              ->searchable(),

            Select::make('product_id')
              ->label(fn(Get $get) => $get('purchase_type') === 'product' ? 'Product' : 'Fuel')
              ->helperText(fn(Get $get) => $get('purchase_type') === 'product'
                ? 'Select the product being purchased'
                : 'Select the type of fuel being purchased')
              ->options(function (Get $get) {
                if ($get('purchase_type') === 'product') {
                  return Stock::select('products.id', 'products.name')
                    ->join('products', 'products.id', '=', 'stock.product_id')
                    ->where('stock.station_id', $get('station_id'))
                    ->whereNotNull('stock.product_id')
                    ->distinct()
                    ->pluck('products.name', 'products.id');
                } else {
                  if (!$get('drum_id')) return [];
                  return Stock::select('products.id', 'products.name')
                    ->join('drums', 'drums.id', '=', 'stock.drum_id')
                    ->join('products', 'products.id', '=', 'drums.product_id')
                    ->where('stock.drum_id', $get('drum_id'))
                    ->distinct()
                    ->pluck('products.name', 'products.id');
                }
              })
              ->required()
              ->searchable()
              ->reactive()
              ->afterStateUpdated(function ($state, Set $set) {
                if ($state) {
                  try {
                    $product = ProductsModel::find($state);
                    if ($product) {
                      $set('product_name', $product->name);
                      if ($product->selling_price) {
                        $set('price', $product->selling_price);
                      }
                    }
                  } catch (\Exception $e) {
                    \Log::error('Error setting price: ' . $e->getMessage());
                  }
                }
              }),

            Hidden::make('product_name'),

            TextInput::make('quantity')
              ->label(fn(Get $get) => $get('purchase_type') === 'product' ? 'Quantity' : 'Liters')
              ->helperText(fn(Get $get) => $get('purchase_type') === 'product'
                ? 'Enter the number of units being purchased'
                : 'Enter the number of liters being purchased')
              ->numeric()
              ->required()
              ->minValue(1),

            TextInput::make('price')
              ->label('Price per unit')
              ->helperText(fn(Get $get) => $get('purchase_type') === 'product'
                ? 'Enter the price per unit in KES'
                : 'Enter the price per liter in KES')
              ->numeric()
              ->required()
              ->prefix('KES')
              ->minValue(1),
          ])
          ->action(function (array $data) {
            try {
              // Prepare the single product data
              $product = [
                'id' => (int)$data['product_id'],
                'name' => $data['product_name'],
                'quantity' => (int)$data['quantity'],
                'price' => (float)$data['price']
              ];

              if ($data['purchase_type'] === 'product') {
                $request = new PurchasesRequest();

                $request->merge([
                  'products' => [$product],
                  'station_id' => (int)$data['station_id'],
                  'user_id' => auth()->id(),
                  'shift_id' => $this->record->id,
                  'petrol_id' => auth()->user()->petrol_id,
                ]);



                $response = app(SalesController::class)->recordPurchases($request);
              } else {
                $request = new PurchasesRequest();
                $request->merge([
                  'products' => [$product],
                  'drum_id' => (int)$data['drum_id'],
                  'user_id' => auth()->id(),
                  'shift_id' => $this->record->id,
                  'petrol_id' => auth()->user()->petrol_id,
                ]);


                $response = app(SalesController::class)->recordPurchases($request);
              }

              $responseData = json_decode($response->getContent(), true);

              if (isset($responseData['status']) && $responseData['status'] === 'success') {
                Notification::make()
                  ->title('Success')
                  ->body($responseData['message'] ?? 'Purchase recorded successfully')
                  ->success()
                  ->send();

                $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
              } else {
                throw new \Exception($responseData['message'] ?? 'Failed to record purchase');
              }
            } catch (\Exception $e) {
              Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
            }
          })
          ->modalWidth('xl')
          ->modalHeading('Record Purchase')

      ])
        ->label('Financial Actions')
        ->button()
        ->icon('heroicon-o-currency-dollar'),

      Action::make('end_shift')
        ->label('End Shift')
        ->extraAttributes(['class' => 'w-[400px] h-40 text-2xl inline-flex mx-2'])
        ->color('danger')
        ->icon('heroicon-o-arrow-path-rounded-square')
        ->requiresConfirmation()
        ->modalIcon('heroicon-o-exclamation-triangle')
        ->modalIconColor('danger')
        ->modalHeading('End Shift')
        ->modalDescription('Are you sure you want to end this shift? This action cannot be undone.')
        ->modalSubmitActionLabel('Yes, end shift')
        ->modalCancelActionLabel('No, cancel')
        ->action(function () {
          try {
            $response = app(SalesController::class)->endShift(
              request: new Request(),
              shiftId: $this->record->id
            );
            $responseData = json_decode($response->getContent(), true);

            if (isset($responseData['status']) && $responseData['status'] === 'success') {
              Notification::make()
                ->title('Success')
                ->body($responseData['message'] ?? 'Shift ended successfully')
                ->success()
                ->send();

              $this->redirect($this->getResource()::getUrl('index'));
            } else {
              throw new \Exception($responseData['message'] ?? 'Failed to end shift');
            }
          } catch (\Exception $e) {
            Notification::make()
              ->title('Error')
              ->body($e->getMessage())
              ->danger()
              ->send();
          }
        }),
    ];
  }

  public function showReport(): Action
  {
    return Action::make('showReport')
      ->modalContent(fn(array $data) => view('filament.modals.report-viewer', [
        'content' => $data['content']
      ]))
      ->modalWidth('7xl')
      ->modalSubmitAction(false)
      ->modalCancelAction(false)
      ->modalActions([
        Action::make('download')
          ->label('Download Report')
          ->url(fn() => "/report/sales-report/download/{$this->record->id}")
          ->openUrlInNewTab()
      ]);
  }
}
