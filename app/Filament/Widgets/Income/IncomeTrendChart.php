<?php

namespace App\Filament\Widgets\Income;

class IncomeTrendChart extends IncomeChart
{
    protected static ?string $heading = 'Approved income trend';

    protected function getType(): string
    {
        return 'line';
    }

    public function getDescription(): string
    {
        return $this->incomeFilters()->label().' · Income uses approval date, not order date.';
    }

    protected function getData(): array
    {
        $data = $this->reports()->trend($this->incomeFilters());

        return ['labels' => $data['labels'], 'datasets' => [
            ['label' => 'Approved income ('.$this->incomeFilters()->currency().')', 'data' => $this->amounts($data['minor']),
                'borderColor' => '#0d9488', 'backgroundColor' => 'rgba(13,148,136,0.12)', 'fill' => true, 'tension' => 0.25, 'pointRadius' => 2, 'yAxisID' => 'y'],
            ['label' => 'Approved purchases', 'data' => $data['counts'], 'borderColor' => '#818cf8', 'backgroundColor' => '#818cf8',
                'borderDash' => [5, 4], 'tension' => 0.25, 'pointRadius' => 2, 'yAxisID' => 'y1'],
        ]];
    }

    protected function getOptions(): array
    {
        return array_replace_recursive(parent::getOptions(), ['interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => ['y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false],
                'ticks' => ['precision' => 0], 'title' => ['display' => true, 'text' => 'Purchases']]]]);
    }
}
