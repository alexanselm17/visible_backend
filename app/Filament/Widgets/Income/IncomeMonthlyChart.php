<?php

namespace App\Filament\Widgets\Income;

class IncomeMonthlyChart extends IncomeChart
{
    protected static ?string $heading = 'Monthly income comparison';

    public function getDescription(): string
    {
        return $this->comparisonDescription();
    }

    protected function getData(): array
    {
        $data = $this->reports()->monthly($this->incomeFilters());
        $datasets = [];
        foreach ($data['years'] as $year => $amounts) {
            $datasets[] = ['label' => (string) $year, 'data' => $this->amounts($amounts),
                'backgroundColor' => count($datasets) ? '#818cf8' : '#0d9488', 'borderRadius' => 4];
        }

        return ['labels' => $data['labels'], 'datasets' => $datasets];
    }
}
