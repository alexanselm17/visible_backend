<?php

namespace App\Filament\Widgets;

use Filament\Widgets\LineChartWidget;
use App\Models\Screenshots;

class ScreenshotsSubmissionChart extends LineChartWidget
{
    protected static ?string $heading = 'Screenshots Submissions (Last 7 Days)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $labels = [];
        $submissions = [];

        foreach (range(6, 0) as $i) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');
            
            $dailySubmissions = Screenshots::whereDate('created_at', $date)
                ->count();
            
            $submissions[] = $dailySubmissions;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Screenshots Submitted',
                    'data' => $submissions,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
