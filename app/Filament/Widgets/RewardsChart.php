<?php

namespace App\Filament\Widgets;

use App\Models\RewardLedgerEntry;
use Filament\Widgets\BarChartWidget;

class RewardsChart extends BarChartWidget
{
    protected static ?string $heading = 'Monthly Performance Earnings';

    protected static ?int $sort = 6;

    protected function getData(): array
    {
        $months = [];
        $earnings = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $months[] = $month->format('M Y');

            $earnings[] = RewardLedgerEntry::query()
                ->where('type', RewardLedgerEntry::EARNING)
                ->whereBetween('created_at', [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                ])
                ->sum('amount_minor') / 100;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Performance Earnings (KSh)',
                    'data' => $earnings,
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
