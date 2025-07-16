<?php

namespace App\Filament\Widgets;

use Filament\Widgets\BarChartWidget;

class CompletedTasksChart extends BarChartWidget
{
    protected static ?string $heading = 'Completed Tasks (Last 5 Days)';

    protected function getData(): array
    {
        $labels = [];
        $completed = [];

        foreach (range(4, 0) as $i) {
            $labels[] = now()->subDays($i)->format('D');
            $completed[] = rand(20, 100);
        }

        return [
            'datasets' => [[
                'label' => 'Completed Tasks',
                'data' => $completed,
                'backgroundColor' => '#f59e0b',
            ]],
            'labels' => $labels,
        ];
    }
}
