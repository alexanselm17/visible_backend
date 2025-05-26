<?php

namespace App\Filament\Resources\StationsResource\Pages;

use App\Filament\Resources\StationsResource;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesController;
use App\Http\Requests\AssignStation;
use App\Http\Requests\RecordClosingStock;
use App\Http\Requests\RecordOpeningStock;
use App\Http\Requests\StationTransferRequest;
use App\Http\Requests\StockReconcileRequest;
use App\Models\PetrolStation;
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
use Filament\Pages\Actions\ButtonAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Concerns\InteractsWithActions;

class ViewStations extends ViewRecord
{
  protected static string $resource = StationsResource::class;

  public $shift_id;

  public function mount($record): void
  {
    parent::mount($record);
    $this->shift_id = request()->query('shift_id');
  }

  protected function getViewData(): array
  {

    $productController = app(ProductController::class);
    $response = $productController->getStationsProducts($this->record->id);
    $data = json_decode($response->getContent(), true);

    return [
      'sessions' => ['data' => $data['stock']['data'] ?? []],
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
      Section::make('Current Stock')
        ->schema([
          TextEntry::make('products_table')
            ->label('')
            ->html()
            ->state(fn() =>
            $this->buildStationViewTable($this->getViewData()['sessions']['data'] ?? []))
        ])
    ]);
  }


  private function buildStationViewTable($products): string
  {
    $tableHtml = '<div class="overflow-x-auto">
          <table class="min-w-full border border-gray-600">
              <thead>
                  <tr>
                      <th class="px-6 py-3 bg-gray-800 text-left text-xs font-medium text-gray-200 uppercase border border-gray-600">Product</th>
                      <th class="px-6 py-3 bg-gray-800 text-left text-xs font-medium text-gray-200 uppercase border border-gray-600">Category</th>
                      <th class="px-6 py-3 bg-gray-800 text-right text-xs font-medium text-gray-200 uppercase border border-gray-600">Current Stock</th>
                      <th class="px-6 py-3 bg-gray-800 text-right text-xs font-medium text-gray-200 uppercase border border-gray-600">Unit Price</th>
                      <th class="px-6 py-3 bg-gray-800 text-right text-xs font-medium text-gray-200 uppercase border border-gray-600">Total Value</th>
                  </tr>
              </thead>
              <tbody class="bg-gray-900">';

    $totalValue = 0;

    foreach ($products as $product) {
      $stock = floatval($product['stock']);
      $price = floatval($product['product']['selling_price']);
      $value = $stock * $price;
      $totalValue += $value;

      $tableHtml .= sprintf(
        '<tr>
                  <td class="px-6 py-4 text-sm text-gray-200 border border-gray-600">%s</td>
                  <td class="px-6 py-4 text-sm text-gray-200 border border-gray-600">%s</td>
                  <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">%.2f</td>
                  <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">KES %.2f</td>
                  <td class="px-6 py-4 text-sm text-right text-gray-200 border border-gray-600">KES %.2f</td>
              </tr>',
        htmlspecialchars($product['product']['name']),
        htmlspecialchars($product['product']['category']),
        $stock,
        $price,
        $value
      );
    }

    $tableHtml .= sprintf(
      '<tr>
              <td colspan="4" class="px-6 py-4 text-sm font-bold text-right text-gray-200 border border-gray-600">Total Value:</td>
              <td class="px-6 py-4 text-sm font-bold text-right text-gray-200 border border-gray-600">KES %.2f</td>
          </tr>',
      $totalValue
    );

    $tableHtml .= '</tbody></table></div>';

    $tableHtml .= sprintf(
      '<div class="mt-4 text-sm text-gray-600">
              Showing %d products
          </div>',
      count($products)
    );

    return $tableHtml;
  }





  protected function getHeaderActions(): array
  {

    return [

      Action::make('station_report')
        ->label('Station Report')
        ->color('gray')
        ->icon('heroicon-o-document-chart-bar')

        ->action(function (array $data) {
          $petrolStationId = $this->record->petrol_id;

          try {
            return redirect()->route('reports.stock_report', [
              'petrolStationId' => $petrolStationId,
              'station_id' => $this->record->id,
              'type' => 'station'
            ]);
          } catch (\Exception $e) {
            Notification::make()
              ->title('Error')
              ->body('Failed to generate station report: ' . $e->getMessage())
              ->danger()
              ->send();
          }
        }),
      Action::make('reconcile')
        ->label('Reconcile Product')
        ->color('success')
        ->icon('heroicon-o-calculator')
        ->modalHeading('Reconcile Product')
        ->modalDescription('Update product quantity after physical count')
        ->form([
          Select::make('product_id')
            ->label('Product')
            ->options(function () {
              return ProductsModel::where('petrol_id', $this->record->petrol_id)
                ->where('category', '!=', 'FUEL')
                ->pluck('name', 'id');
            })
            ->required()
            ->searchable(),

          TextInput::make('quantity')
            ->label('Actual Quantity')
            ->numeric()
            ->required()
            ->minValue(0)
            ->helperText('Enter the physically counted quantity'),
        ])
        ->action(function (array $data): void {
          try {
            $request = new StockReconcileRequest;
            $request->merge([
              'quantity' => $data['quantity'],
              'station_id' => $this->record->id,
              'product_id' => $data['product_id'],
            ]);

            $salesRepository = app(SalesRepositoryInterface::class);
            $controller = new SalesController($salesRepository);
            $response = $controller->reconcileStock($request, $this->record->petrol_id);
            $responseData = $response->getData();

            if ($responseData->ok === true) {
              Notification::make()
                ->title($responseData->message)
                ->success()
                ->send();
            } else {
              Notification::make()
                ->title($responseData->error)
                ->danger()
                ->send();
            }
          } catch (\Exception $e) {
            Notification::make()
              ->title('Error occurred')
              ->body($e->getMessage())
              ->danger()
              ->send();
          }
        }),
    ];
  }


  public function getTitle(): string
  {
    return "{$this->record->name}";
  }
}
