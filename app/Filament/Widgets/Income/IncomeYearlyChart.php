<?php

namespace App\Filament\Widgets\Income;

class IncomeYearlyChart extends IncomeChart
{
    protected static ?string $heading = 'Income across years';

    public function getDescription(): string
    {
        return 'Five years ending in the main year. '.$this->comparisonDescription();
    }

    protected function getData(): array
    {
        $data = $this->reports()->yearly($this->incomeFilters());

        return ['labels' => $data['labels'], 'datasets' => [['label' => 'Approved income ('.$this->incomeFilters()->currency().')',
            'data' => $this->amounts($data['minor']), 'backgroundColor' => '#0d9488', 'borderRadius' => 6]]];
    }
}
