<?php

namespace App\Filament\Widgets\Income;

use App\Models\TokenPurchase;

class IncomeOrderStatusChart extends IncomeChart
{
    protected static ?string $heading = 'Purchase approval status';

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getDescription(): string
    {
        return 'Counts of orders CREATED in the report range, by their current status. Unpaid orders are not income.';
    }

    protected function getData(): array
    {
        $rows = $this->reports()->orderStatuses($this->incomeFilters());
        $statuses = [TokenPurchase::PAID => 'Approved', TokenPurchase::PENDING => 'Pending payment', TokenPurchase::FAILED => 'Failed',
            TokenPurchase::CANCELLED => 'Cancelled', TokenPurchase::REFUNDED => 'Marked refunded'];

        return ['labels' => array_values($statuses), 'datasets' => [['label' => 'Orders',
            'data' => array_map(fn ($status) => $rows[$status]['count'] ?? 0, array_keys($statuses)),
            'backgroundColor' => ['#0d9488', '#f59e0b', '#ef4444', '#94a3b8', '#a78bfa'], 'borderWidth' => 0, 'hoverOffset' => 6]]];
    }

    protected function getOptions(): array
    {
        return ['maintainAspectRatio' => false, 'cutout' => '70%', 'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }
}
