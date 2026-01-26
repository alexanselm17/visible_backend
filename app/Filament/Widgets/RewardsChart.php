<?php

namespace App\Filament\Widgets;

use App\Models\Screenshots;
use Filament\Widgets\BarChartWidget;
use Illuminate\Support\Facades\DB;

class RewardsChart extends BarChartWidget
{
    protected static ?string $heading = 'Monthly Rewards Distribution';

    protected static ?int $sort = 6;

    protected function getData(): array
    {
        $monthlyData = Screenshots::select(
            DB::raw('MONTH(screenshots.created_at) as month'),
            DB::raw('COUNT(*) as screenshots_count')
        )
            ->join('advert_images', 'screenshots.advert_id', '=', 'advert_images.id')
            ->where('screenshots.created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->get();

        $months = [];
        $rewards = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $months[] = $month->format('M Y');

            $monthData = $monthlyData->firstWhere('month', $month->month);
            $rewards[] = $monthData ? $monthData->screenshots_count * 10 : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Rewards Distributed',
                    'data' => $rewards,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
