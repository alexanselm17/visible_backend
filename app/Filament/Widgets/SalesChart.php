<?php

namespace App\Filament\Widgets;

use App\Models\TransactionProduct;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\BarChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesChart extends BarChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Monthly Transactions 2025';

    protected static ?string $maxHeight = '400px';

    protected function getData(): array
    {
        try {
            // Get selected petrol station
            $petrolStationId = request()->query('petrol_station');

            // Get the current year's data
            $query = TransactionProduct::select([
                DB::raw("DATE_FORMAT(tran_products.created_at, '%M') as month"),
                DB::raw("DATE_FORMAT(tran_products.created_at, '%m') as month_number"),
                DB::raw('SUM(tran_products.total) as total_amount'),
            ])
                ->join('transactions', 'tran_products.transaction_id', '=', 'transactions.id')
                ->join('trans_details', 'transactions.id', '=', 'trans_details.transaction_id')
                ->where('transactions.petrol_id', $petrolStationId)
                ->whereYear('tran_products.created_at', date('Y'))
                ->where('trans_details.transaction_type', 'Sales')
                ->groupBy('month', 'month_number')
                ->orderBy('month_number');

            Log::info('Transaction Query:', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);

            $results = $query->get();

            Log::info('Query Results:', [
                'data' => $results->toArray(),
            ]);

            // Initialize all months with zero values
            $monthlyData = collect(CarbonPeriod::create(
                Carbon::create(date('Y'), 1, 1),
                '1 month',
                Carbon::create(date('Y'), 12, 31)
            ))->mapWithKeys(fn ($date) => [
                $date->format('F') => 0,
            ])->toArray();

            // Fill in actual values
            foreach ($results as $result) {
                $monthlyData[$result->month] = round($result->total_amount, 2);
            }

            Log::info('Processed Monthly Data:', [
                'data' => $monthlyData,
            ]);

            return [
                'datasets' => [
                    [
                        'label' => 'Monthly Transactions (KES)',
                        'data' => array_values($monthlyData),
                        'backgroundColor' => '#10B981',
                        'borderColor' => '#059669',
                        'borderWidth' => 1,
                    ],
                ],
                'labels' => array_keys($monthlyData),
            ];
        } catch (\Exception $e) {
            Log::error('Error in getData:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'datasets' => [
                    [
                        'label' => 'Monthly Transactions (KES)',
                        'data' => [0],
                        'backgroundColor' => '#10B981',
                    ],
                ],
                'labels' => ['No Data'],
            ];
        }
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'color' => '#ffffff',
                    ],
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                    'callbacks' => [
                        'label' => "function(context) {
                            return context.dataset.label + ': KES ' + 
                                new Intl.NumberFormat().format(context.raw);
                        }",
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                        'color' => '#374151',
                    ],
                    'ticks' => [
                        'color' => '#ffffff',
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => '#374151',
                    ],
                    'ticks' => [
                        'color' => '#ffffff',
                        'callback' => "function(value) {
                            return 'KES ' + new Intl.NumberFormat().format(value);
                        }",
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'backgroundColor' => '#1F2937',
        ];
    }
}
