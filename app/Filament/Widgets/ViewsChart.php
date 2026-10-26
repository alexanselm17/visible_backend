<?php

namespace App\Filament\Widgets;

use App\Models\Screenshots;
use Filament\Widgets\BarChartWidget;

class ViewsChart extends BarChartWidget
{
    protected static ?string $heading = 'Completed task views · last 7 days';

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
                ->where('number', 2)
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
