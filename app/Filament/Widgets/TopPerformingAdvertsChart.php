<?php

namespace App\Filament\Widgets;

use Filament\Widgets\BarChartWidget;
use App\Models\AdvertImages;

class TopPerformingAdvertsChart extends BarChartWidget
{
    protected static ?string $heading = 'Top 5 Performing Adverts (By Views)';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $topAdverts = AdvertImages::withCount('screenshots')
            ->with('screenshots')
            ->get()
            ->sortByDesc(function ($advert) {
                return $advert->screenshots->sum('views');
            })
            ->take(5);

        $labels = [];
        $views = [];

        foreach ($topAdverts as $advert) {
            $labels[] = \Str::limit($advert->name, 20);
            $views[] = $advert->screenshots->sum('views');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Views',
                    'data' => $views,
                    'backgroundColor' => [
                        '#8b5cf6',
                        '#06b6d4',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }
}
