<?php

namespace App\Filament\Widgets\Income;

class IncomeTokenChart extends IncomeChart
{
    protected static ?string $heading = 'Income by token type';

    public function getDescription(): string
    {
        return $this->incomeFilters()->label().' · Historical purchases retain their original price, including inactive tokens.';
    }

    protected function getData(): array
    {
        $data = $this->reports()->byToken($this->incomeFilters());

        return ['labels' => $data['labels'], 'datasets' => [['label' => 'Approved income ('.$this->incomeFilters()->currency().')',
            'data' => $this->amounts($data['minor']), 'backgroundColor' => ['#f59e0b', '#94a3b8', '#10b981', '#818cf8'], 'borderRadius' => 6]]];
    }
}
