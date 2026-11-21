<?php

namespace App\Filament\Widgets\Income;

use Filament\Widgets\ChartWidget;

abstract class IncomeChart extends ChartWidget
{
    use UsesIncomeFilters;

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = '60s';

    protected static ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => $this->incomeFilters()->currency()]]]];
    }

    protected function amounts(array $minor): array
    {
        // Chart.js requires numbers in major units. Accounting aggregates stay in integer minor units.
        return array_map(fn ($value) => $value === null ? null : $value / 100, $minor);
    }

    protected function comparisonDescription(): string
    {
        $filters = $this->incomeFilters();

        return $filters->currency().' · '.($filters->values['comparison_scope'] === 'calendar'
            ? 'Full calendar years; the current year stops at today.'
            : 'Same elapsed window: 1 Jan through '.$filters->comparisonCutoff()->format('d M').' in each year. Future dates are excluded.');
    }
}
