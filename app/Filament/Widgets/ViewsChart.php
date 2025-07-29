<?php

namespace App\Filament\Widgets;

use Filament\Widgets\BarChartWidget;

class ViewsChart extends BarChartWidget
{
    protected static ?string $heading = 'Daily Views (Last 5 Days)';

    protected function getData(): array
    {
        $labels = [];
        $views = [];

        foreach (range(4, 0) as $i) {
            $labels[] = now()->subDays($i)->format('D');
            $views[] = rand(100, 300);
        }

        return [
            'datasets' => [[
                'label' => 'Views',
                'data' => $views,
                'backgroundColor' => '#10b981',
            ]],
            'labels' => $labels,
        ];
    }
}
