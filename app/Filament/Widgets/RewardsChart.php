<?php

namespace App\Filament\Widgets;

use Filament\Widgets\BarChartWidget;

class RewardsChart extends BarChartWidget
{
    protected static ?string $heading = 'Daily Rewards (Last 5 Days)';

    protected function getData(): array
    {
        $labels = [];
        $rewards = [];

        foreach (range(4, 0) as $i) {
            $labels[] = now()->subDays($i)->format('D');
            $rewards[] = rand(500, 1500);
        }

        return [
            'datasets' => [[
                'label' => 'Rewards (Ksh)',
                'data' => $rewards,
                'backgroundColor' => '#3b82f6',
            ]],
            'labels' => $labels,
        ];
    }
}
