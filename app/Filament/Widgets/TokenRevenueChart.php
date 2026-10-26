<?php

namespace App\Filament\Widgets;

use App\Models\TokenPurchase;
use Filament\Widgets\ChartWidget;

class TokenRevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Token sales · last 7 days';

    protected function getData(): array
    {
        $currencies = TokenPurchase::where('status', TokenPurchase::PAID)->distinct()->pluck('currency');

        return [
            'labels' => collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('M j'))->all(),
            'datasets' => $currencies->map(fn ($currency) => [
                'label' => $currency,
                'data' => collect(range(6, 0))->map(fn ($i) => (float) TokenPurchase::where('status', TokenPurchase::PAID)
                    ->where('currency', $currency)->whereDate('paid_at', now()->subDays($i))->sum('total_amount'))->all(),
                'borderColor' => '#0d9488',
            ])->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
