<?php

namespace App\Filament\Widgets;

use App\Http\Controllers\SalesController;
use App\Repositories\Sales\SalesRepositoryInterface;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Filament\Support\Colors\Color;

class StatsOverview extends BaseWidget
{
  use InteractsWithPageFilters;

  protected static ?string $pollingInterval = '15s';
  protected int|string|array $columnSpan = 'full';

  protected function getCards(): array
  {
    try {
      // Get controller instance through service container
      $salesRepository = app(SalesRepositoryInterface::class);
      $controller = new SalesController($salesRepository);

      // Create request with filters
      $date = $this->filters['startDate'] ?? now()->toDateString();
      $petrolStationId = $this->filters['petrol_station'] ?? null;

      $request = new Request([
        'date_filter' => $date,
        'petrol_station' => $petrolStationId,
        'compare_with' => 'yesterday', // Default comparison
      ]);

      // Get statistics from controller
      $response = $controller->statisticData($request);
      $stats = $response->getData();

      if (!$stats->ok) {
        throw new \Exception($stats->message ?? 'Failed to fetch statistics');
      }

      $cards = [];

      // Total Revenue Card
      $cards[] = Card::make('Total Revenue', 'KES ' . $stats->total_sales)
        ->description($stats->percentage_change . '% ' . ($stats->percentage_change >= 0 ? 'increase' : 'decrease'))
        ->descriptionIcon($stats->percentage_change >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
        ->icon('heroicon-o-wallet')
        ->color($stats->percentage_change >= 0 ? 'success' : 'danger');

      // Fuel Sales Card
      $cards[] = Card::make('Fuel Sales', 'KES ' . $stats->total_fuel_sales)
        ->description('vs KES ' . $stats->previous_sales . ' previous period')
        ->icon('heroicon-o-bug-ant')
        ->color('success');

      // Non-Fuel Sales Card
      $cards[] = Card::make('Non-Fuel Sales', 'KES ' . $stats->total_non_fuel_sales)
        ->description('Total non-fuel sales')
        ->icon('heroicon-o-shopping-bag')
        ->color('success');

      foreach ($stats->fuel_sales_by_product as $product) {
        $cards[] = Card::make($product->products_name, 'KES ' . $product->total_sold)
          ->description($this->getProductDescription($product->products_name))
          ->descriptionIcon('heroicon-o-arrow-trending-up')
          ->icon($this->getProductIcon($product->products_name))
          ->color('success')
          ->chart([7, 4, 6, 8, 5, 9, 3, 4, 6, 7, 8, 6])
          ->chartColor(match (strtolower($product->products_name)) {
            'super', 'super petrol', 'petrol' => Color::Red,
            'diesel' => Color::Yellow,
            'kerosine', 'kerosene' => Color::Blue,
            default => Color::Emerald,
          });
      }

      // Payment Method Cards
      foreach ($stats->bankings_by_method as $banking) {
        $cards[] = Card::make($banking->method . ' PAYMENT', 'KES ' . $banking->total_amount)
          ->description('Total payments')
          ->icon($this->getPaymentIcon($banking->method))
          ->color('success');
      }

      // Total Invoices Card
      $cards[] = Card::make('Total Invoices', 'KES ' . $stats->total_invoices_sales)
        ->description('Total invoice sales')
        ->icon('heroicon-o-document-text')
        ->color('warning');

      return $cards;
    } catch (\Exception $e) {
      report($e);
      return [
        Card::make('Error', 'Failed to load statistics')
          ->description($e->getMessage())
          ->icon('heroicon-o-exclamation-circle')
          ->color('danger')
      ];
    }
  }

  private function getProductDescription(string $productName): string
  {
    return match (strtolower($productName)) {
      'super', 'super petrol', 'petrol' => 'Total super sales',
      'diesel' => 'Total diesel sales',
      'kerosine', 'kerosene' => 'Total kerosene sales',
      default => 'Quality fuel product for your needs'
    };
  }

  private function getPaymentIcon(string $methodName): string
  {
    return match (strtolower($methodName)) {
      'mpesa', 'm-pesa' => 'heroicon-o-device-phone-mobile',
      'cash' => 'heroicon-o-banknotes',
      'bank transfer', 'bank' => 'heroicon-o-building-library',
      'card', 'credit card' => 'heroicon-o-credit-card',
      'cheque', 'check' => 'heroicon-o-document-text',
      'ewallet', 'e-wallet' => 'heroicon-o-wallet',
      default => 'heroicon-o-banknotes',
    };
  }

  private function getProductIcon(string $productName): string
  {
    return match (strtolower($productName)) {
      'super', 'super petrol', 'petrol' => 'heroicon-o-arrow-down-on-square',
      'diesel' => 'heroicon-o-truck',
      'kerosine', 'kerosene' => 'heroicon-o-light-bulb',
      'gas', 'lpg' => 'heroicon-o-fire',
      default => 'heroicon-o-beaker',
    };
  }
}
