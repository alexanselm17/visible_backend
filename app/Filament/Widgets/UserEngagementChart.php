<?php

namespace App\Filament\Widgets;

use App\Models\Screenshots;
use Filament\Widgets\BarChartWidget;

class UserEngagementChart extends BarChartWidget
{
    protected static ?string $heading = 'Top 10 Most Active Users (Screenshots Submitted)';

    protected static ?int $sort = 7;

    protected function getData(): array
    {
        $topUsers = Screenshots::select('processed_by')
            ->selectRaw('COUNT(*) as screenshot_count')
            ->with('user')
            ->groupBy('processed_by')
            ->orderByDesc('screenshot_count')
            ->limit(10)
            ->get();

        $labels = [];
        $counts = [];

        foreach ($topUsers as $userStats) {
            $userName = $userStats->user ? $userStats->user->username : 'Unknown User';
            $labels[] = \Str::limit($userName, 15);
            $counts[] = $userStats->screenshot_count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Screenshots Submitted',
                    'data' => $counts,
                    'backgroundColor' => '#8b5cf6',
                    'borderColor' => '#7c3aed',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
