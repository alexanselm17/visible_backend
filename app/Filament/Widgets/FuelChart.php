<?php

namespace App\Filament\Widgets;

use App\Models\Drum;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\PieChartWidget;
use Illuminate\Support\Facades\DB;

class FuelChart extends PieChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Petroleum Sales';
    protected static ?string $maxHeight = '300px';


    protected function getData(): array
    {
        // Retrieve start and end date from filters
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        // Retrieve the total sales per drum, filtering by date range
        $salesData = Drum::with(['pumps.pumpSessionDetails' => function ($query) use ($startDate, $endDate) {
            // Filter the pump session details by the start and end dates
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // Select only the necessary fields to optimize the query
            $query->select('pump_id', DB::raw('SUM(ended_cash - start_cash) as total_sales'))
                ->groupBy('pump_id');
        }])->get()->map(function ($drum) {
            // Sum the total sales from all pumps associated with the drum
            return [
                'name' => $drum->name,
                'sales' => $drum->pumps->sum(function ($pump) {
                    return $pump->pumpSessionDetails->sum('total_sales');
                }),
            ];
        });

        $colors = [
            '#FF6384',
            '#36A2EB',
            '#FFCE56',
            '#4BC0C0',
            '#9966FF',
            '#FF9F40',
        ];

        // Prepare data for the pie chart
        $salesValues = $salesData->pluck('sales')->toArray();
        $salesLabels = $salesData->pluck('name')->toArray();


        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => $salesValues,
                    'backgroundColor' => collect($salesValues)->keys()->map(function ($index) use ($colors) {
                        return $colors[$index % count($colors)];
                    })->toArray(),
                ],
            ],
            'labels' => $salesLabels,
        ];
    }
}
