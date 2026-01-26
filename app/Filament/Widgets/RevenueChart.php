<?php

namespace App\Filament\Widgets;

use App\Models\AdvertImages;
use Filament\Widgets\LineChartWidget;

class RevenueChart extends LineChartWidget
{
    protected static ?string $heading = 'Revenue vs Rewards Distributed (Last 7 Days)';

    protected static ?int $sort = 5;

    protected function getData(): array
    {
        $labels = [];
        $revenue = [];
        $rewards = [];

        foreach (range(6, 0) as $i) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');

            // Calculate daily revenue (sum of selling prices for adverts created that day)
            $dailyRevenue = AdvertImages::whereDate('created_at', $date)
                ->sum('capital_invested');

            // Calculate daily rewards distributed
            $dailyRewards = AdvertImages::whereHas('screenshots', function ($query) use ($date) {
                $query->whereDate('created_at', $date);
            })->with(['screenshots' => function ($query) use ($date) {
                $query->whereDate('created_at', $date);
            }])->get()->sum(function ($advert) {
                return $advert->screenshots->count() * $advert->reward;
            });

            $revenue[] = (float) $dailyRevenue;
            $rewards[] = (float) $dailyRewards;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue ($)',
                    'data' => $revenue,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
                [
                    'label' => 'Rewards Distributed ($)',
                    'data' => $rewards,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
