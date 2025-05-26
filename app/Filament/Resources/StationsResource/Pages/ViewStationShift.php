<?php

namespace App\Filament\Resources\StationsResource\Pages;

use App\Filament\Resources\StationsResource;
use App\Http\Controllers\SalesController;
use App\Http\Requests\AssignStation;
use App\Http\Requests\RecordClosingStock;
use App\Http\Requests\RecordOpeningStock;
use App\Http\Requests\StationTransferRequest;
use App\Models\ProductsModel;
use App\Models\Stations;
use App\Models\StationSessiontModel;
use App\Models\StationShiftModel;
use App\Models\User;
use App\Repositories\Sales\SalesRepositoryInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Auth;

class ViewStationShift extends ViewRecord
{
  protected static string $resource = StationsResource::class;
  public $shift_id;
  public $isModalOpen = false;

  public function mount($record): void
  {
    parent::mount($record);
    $this->shift_id = request()->query('shift_id');
  }

  protected function getViewData(): array
  {
    $salesController = app(SalesController::class);
    $response = $salesController->getStationsProductsNotInSession(
      $this->record->id,
      $this->shift_id
    );

    $data = json_decode($response->getContent(), true);

    return [
      'sessions' => $data['products'] ?? []
    ];
  }

  public function infolist(Infolist $infolist): Infolist
  {
    return $infolist->schema([
      Section::make('Station Details')
        ->schema([
          TextEntry::make('name')->label('Station Name'),
          TextEntry::make('petrolStation.name')->label('Petrol Station'),
          TextEntry::make('is_on_shift')
            ->label('Is On Shift')
            ->formatStateUsing(fn(bool $state): string => $state ? 'Yes' : 'No')
            ->color(fn(bool $state): string => $state ? 'success' : 'danger'),
        ])
        ->columns(3),

      Section::make('Opening Stock')
        ->description('Products requiring opening stock to be recorded')
        ->icon('heroicon-o-play')
        ->collapsible()
        ->persistCollapsed()
        ->schema([
          TextEntry::make('products_opening')
            ->label('')
            ->html()
            ->state(function () {
              $data = $this->getViewData();
              $products = collect($data['sessions']['data'] ?? [])
                ->filter(fn($item) => is_null($item['opening_stock']));

              if ($products->isEmpty()) {
                return $this->getEmptyStateHtml('No products need opening stock recorded');
              }

              return $this->buildProductsTable($products, true);
            }),
        ]),

      Section::make('Closing Stock')
        ->description('Products requiring closing stock to be recorded')
        ->icon('heroicon-o-stop')
        ->collapsible()
        ->persistCollapsed()
        ->schema([
          TextEntry::make('products_closing')
            ->label('')
            ->html()
            ->state(function () {
              $data = $this->getViewData();
              $products = collect($data['sessions']['data'] ?? [])
                ->filter(fn($item) => !is_null($item['opening_stock']) && is_null($item['closing_stock']));

              if ($products->isEmpty()) {
                return $this->getEmptyStateHtml('No products need closing stock recorded');
              }

              return $this->buildProductsTable($products, false);
            }),
        ]),

      Section::make('Completed Stock')
        ->description('Products with both opening and closing stock recorded')
        ->icon('heroicon-o-check-circle')
        ->collapsible()
        ->persistCollapsed()
        ->schema([
          TextEntry::make('products_completed')
            ->label('')
            ->html()
            ->state(function () {
              $data = $this->getViewData();
              $products = collect($data['sessions']['data'] ?? [])
                ->filter(fn($item) => !is_null($item['opening_stock']) && !is_null($item['closing_stock']));

              if ($products->isEmpty()) {
                return $this->getEmptyStateHtml('No completed products');
              }

              return $this->buildProductsTable($products, false);
            }),
        ])
    ]);
  }


  private function buildProductsTable($products, bool $isOpeningStock): string
  {
    $modalActions = [];
    $tableHtml = '<div class="overflow-x-auto">
            <table class="min-w-full border border-gray-600">
                <thead>
                    <tr>
                        <th class="px-6 py-3 bg-gray-800 text-left text-xs font-medium text-gray-200 uppercase border border-gray-600">Product</th>
                        <th class="px-6 py-3 bg-gray-800 text-left text-xs font-medium text-gray-200 uppercase border border-gray-600">Category</th>
                        <th class="px-6 py-3 bg-gray-800 text-right text-xs font-medium text-gray-200 uppercase border border-gray-600">Current Stock</th>
                        <th class="px-6 py-3 bg-gray-800 text-right text-xs font-medium text-gray-200 uppercase border border-gray-600">Opening Stock</th>
                        <th class="px-6 py-3 bg-gray-800 text-right text-xs font-medium text-gray-200 uppercase border border-gray-600">Closing Stock</th>
                        <th class="px-6 py-3 bg-gray-800 text-right text-xs font-medium text-gray-200 uppercase border border-gray-600">Price</th>
                        <th class="px-6 py-3 bg-gray-800 text-center text-xs font-medium text-gray-200 uppercase border border-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-gray-900">';

    foreach ($products as $product) {
      if ($isOpeningStock || is_null($product['closing_stock'])) {
        $action = $isOpeningStock ?
          $this->createOpeningStockAction($product) :
          $this->createClosingStockAction($product);

        $modalActions[] = $action;
        $buttonHtml = $action->render();
      } else {
        $buttonHtml = '<span class="px-4 py-2 bg-gray-500 text-white rounded">Done</span>';
      }

      $tableHtml .= sprintf(
        '<tr>
                    <td class="px-6 py-4 text-sm text-gray-200 border border-gray-600">%s</td>
                    <td class="px-6 py-4 text-sm text-gray-200 border border-gray-600">%s</td>
                    <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">%.2f</td>
                    <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">%s</td>
                    <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">%s</td>
                    <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">KES %.2f</td>
                    <td class="px-6 py-4 text-sm text-center text-gray-200 border border-gray-600">%s</td>
                </tr>',
        htmlspecialchars($product['product']['name']),
        htmlspecialchars($product['product']['category']),
        $product['stock'],
        $product['opening_stock'] ?? 'Not Set',
        $product['closing_stock'] ?? 'Not Set',
        $product['product']['selling_price'],
        $buttonHtml
      );
    }

    $tableHtml .= '</tbody></table></div>';
    $tableHtml .= sprintf(
      '<div class="mt-4 text-sm text-gray-600">Showing %d products</div>',
      count($products)
    );

    foreach ($modalActions as $action) {
      $tableHtml .= $action->getModalContent();
    }

    return $tableHtml;
  }
  public function createOpeningStockAction($product): Action
  {
    return Action::make('opening_stock_')
      ->form([
        TextInput::make('stock')
          ->label('Opening Stock')
          ->required()
          ->numeric()
          ->minValue(0)
          ->prefix('Units')
          ->helperText('Enter the opening stock value'),
      ])
      ->action(function (array $data) use ($product) {
        try {
          $request = new RecordOpeningStock();
          $request->merge([
            'product_id' => $product['id'],
            'stock' => $data['stock'],
            'station_id' => $this->record->id
          ]);

          $response = app(SalesController::class)->startProductSession(
            request: $request,
            petrolStationId: $this->record->petrol_id,
            shiftId: $this->shift_id
          );

          $this->handleResponse($response);
        } catch (\Exception $e) {
          $this->handleError($e);
        }
      })
      ->modalHeading('Record Opening Stock for ' . $product['product']['name'])
      ->modalDescription('Enter the opening stock value for this product');
  }

  private function createClosingStockAction($product): Action
  {
    return Action::make('closing_stock_')
      ->form([
        TextInput::make('stock')
          ->label('Closing Stock')
          ->required()
          ->numeric()
          ->minValue(0)
          ->prefix('Units')
          ->helperText('Enter the closing stock value'),
      ])
      ->action(function (array $data) use ($product) {
        try {
          $request = new RecordClosingStock();
          $request->merge([
            'product_id' => $product['id'],
            'stock' => $data['stock'],
            'station_id' => $this->record->id
          ]);

          $response = app(SalesController::class)->endProductSession(
            request: $request,
            petrolStationId: $this->record->petrol_id,
            shiftId: $this->shift_id
          );

          $this->handleResponse($response);
        } catch (\Exception $e) {
          $this->handleError($e);
        }
      })
      ->modalHeading('Record Closing Stock for ' . $product['product']['name'])
      ->modalDescription('Enter the closing stock value for this product');
  }



  private function getEmptyStateHtml(string $message): string
  {
    return sprintf(
      '<div class="flex flex-col items-center justify-center h-48 text-gray-500">
              <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
              </svg>
              <span class="text-lg font-medium">%s</span>
          </div>',
      $message
    );
  }

  public function getFormStatePath(): ?string

  {

    return null;
  }

  protected function getHeaderActions(): array
  {
    return [
      Action::make('assign')
        ->label('Start Station Session')
        ->button()
        ->color('success')
        ->icon('heroicon-o-play')
        ->size(ActionSize::Large)
        ->extraAttributes(['class' => 'w-[400px] h-40 text-2xl inline-flex mx-2'])
        ->visible(function () {
          $session = StationShiftModel::where('station_id', $this->record->id)
            ->where('shift_id', $this->shift_id)
            ->latest()
            ->first();

          return !$session || is_null($session->assigned_to);
        })
        ->form([
          Select::make('user_id')
            ->label('Select User')
            ->helperText('Select the user to assign to this station')
            ->options(function () {
              return User::where('petrol_id', $this->record->petrol_id)
                ->pluck('fullname', 'id');
            })
            ->required()
            ->searchable(),
        ])
        ->action(function (array $data) {
          try {
            $request = new AssignStation();
            $request->merge([
              'assigned_to' => $data['user_id'],
              'station_id' => $this->record->id,
              'assigned_by' => Auth::id(),
            ]);

            $response = app(SalesController::class)->assignStation(
              $request,
              $this->record->petrol_id,
              $this->shift_id
            );
            $responseData = $response->getData();


            if ($responseData->ok === true) {
              Notification::make()
                ->success()
                ->title('Assignment Success')
                ->body('The Station has been assigned successfully .')
                ->send();
            } else {
              Notification::make()
                ->danger()
                ->title('Transfer Failed')
                ->body($response->error)
                ->send();
            }
          } catch (\Exception $e) {
            $this->handleError($e);
          }
        }),

      Action::make('transfer')
        ->label('Transfer Products')
        ->button()
        ->color('warning')
        ->icon('heroicon-o-arrow-path-rounded-square')
        ->size(ActionSize::Large)
        ->extraAttributes(['class' => 'w-[400px] h-40 text-2xl inline-flex mx-2'])
        ->modalHeading('Transfer Products')
        ->modalDescription('Transfer products from this station to another station')
        ->form([
          Select::make('to_station')
            ->label('Destination Station')
            ->helperText('Select the station to transfer products to')
            ->options(function () {
              return Stations::where('petrol_id', $this->record->petrol_id)
                ->where('id', '!=', $this->record->id)
                ->pluck('name', 'id');
            })
            ->required()
            ->searchable(),

          Select::make('product_id')
            ->label('Product')
            ->helperText('Select the product to transfer')
            ->options(function () {
              return ProductsModel::where('petrol_id', $this->record->petrol_id)
                ->where('category', '!=', 'FUEL')
                ->whereExists(function ($query) {
                  $query->select('id')
                    ->from('stations')
                    ->whereColumn('stations.id', 'stations.id')
                    ->where('stations.id', $this->record->id);
                })
                ->pluck('name', 'id');
            })
            ->required()
            ->searchable(),

          TextInput::make('quantity')
            ->label('Transfer Quantity')
            ->helperText('Enter the quantity to transfer')
            ->numeric()
            ->required()
            ->minValue(1)
          // ->rules([
          //   function () {
          //     return function (string $attribute, $value, \Closure $fail) {
          //       $currentStock = StationSessiontModel::where('station_id', $this->record->id)
          //         ->where('product_id', $this->data['product_id'] ?? null)
          //         ->value('opening_stock') ?? 0;

          //       if ($value > $currentStock) {
          //         $fail("Transfer quantity cannot exceed current stock of {$currentStock}");
          //       }
          //     };
          //   },
          // ]),
        ])
        ->action(function (array $data) {
          try {
            $request = new StationTransferRequest;
            $request->merge([
              'products' => [[
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
              ]],
              'processed_by' => Auth::id(),
              'to_station' => $data['to_station'],
              'from_station' => $this->record->id,
            ]);

            $controller = new SalesController(app(SalesRepositoryInterface::class));
            $response = $controller->stationTransfers($request, $this->shift_id);
            $responseData = $response->getData();


            if ($responseData->ok === true) {
              Notification::make()
                ->success()
                ->title('Transfer Initiated')
                ->body('The transfer request has been created and is pending approval.')
                ->send();
            } else {
              Notification::make()
                ->danger()
                ->title('Transfer Failed')
                ->body($responseData->error)
                ->send();
            }
          } catch (\Exception $e) {
            Notification::make()
              ->danger()
              ->title('Error')
              ->body('An unexpected error occurred. Please try again.')
              ->send();
          }
        }),
    ];
  }

  private function handleError(\Exception $e)
  {
    Notification::make()
      ->title('Error')
      ->body($e->getMessage())
      ->danger()
      ->send();

    throw $e;
  }
  public function getTitle(): string
  {
    return "{$this->record->name}";
  }
}
