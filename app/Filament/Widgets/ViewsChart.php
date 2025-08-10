<?php

namespace App\Filament\Widgets;

use Filament\Widgets\BarChartWidget;
use App\Models\Screenshots;

class ViewsChart extends BarChartWidget
{
    protected static ?string $heading = 'Daily Views (Last 7 Days)';
    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $labels = [];
        $views = [];

        foreach (range(6, 0) as $i) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');

            // Get actual views data from screenshots
            $dailyViews = Screenshots::whereDate('created_at', $date)
                ->sum('views');

            $views[] = $dailyViews ?: 0;
        }

        return [
            'datasets' => [[
                'label' => 'Views',
                'data' => $views,
                'backgroundColor' => '#10b981',
                'borderColor' => '#059669',
                'borderWidth' => 1,
            ]],
            'labels' => $labels,
        ];
    }
}
