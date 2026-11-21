<?php

namespace App\Filament\Widgets\Income;

use App\Models\NcbaPayment;

class IncomeBankStatusChart extends IncomeChart
{
    protected static ?string $heading = 'Bank receipt reconciliation';

    public function getDescription(): string
    {
        return $this->incomeFilters()->label().' · Bank transaction dates; current status. Allocated receipts overlap approved income: do not add these totals.';
    }

    protected function getData(): array
    {
        $rows = $this->reports()->bankStatuses($this->incomeFilters());
        $statuses = [NcbaPayment::APPLIED => 'Allocated', NcbaPayment::REVIEW => 'Pending approval', NcbaPayment::RESOLVED => 'Resolved outside token crediting'];

        return ['labels' => array_values($statuses), 'datasets' => [['label' => 'Bank receipts ('.$this->incomeFilters()->currency().')',
            'data' => $this->amounts(array_map(fn ($status) => $rows[$status]['minor'] ?? 0, array_keys($statuses))),
            'backgroundColor' => ['#0d9488', '#f59e0b', '#94a3b8'], 'borderRadius' => 6]]];
    }
}
