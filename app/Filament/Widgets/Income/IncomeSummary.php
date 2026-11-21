<?php

namespace App\Filament\Widgets\Income;

use App\Filament\Resources\NcbaPaymentResource;
use App\Filament\Resources\NcbaReceiptClaimResource;
use App\Filament\Resources\TokenPurchaseResource;
use App\Services\IncomeReportService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IncomeSummary extends StatsOverviewWidget
{
    use UsesIncomeFilters;

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $filters = $this->incomeFilters();
        $data = $this->reports()->summary($filters);
        $money = fn ($amount) => IncomeReportService::money($amount, $filters->currency());
        $growth = $data['growth_percent'];
        $previous = $growth === null ? 'No prior-period income to compare' : ($growth >= 0 ? '+' : '').$growth.'% vs previous equal-length period';
        $pending = $data['bank']['REVIEW'] ?? ['minor' => 0, 'count' => 0];
        $unpaid = $data['orders']['PENDING'] ?? ['minor' => 0, 'count' => 0];
        $allocation = $data['bank_count'] ? round(($data['bank']['APPLIED']['count'] ?? 0) / $data['bank_count'] * 100, 1).'%' : '0%';

        return [
            Stat::make('Approved income', $money($data['approved']['minor']))->description($previous)->descriptionColor(($growth ?? 0) < 0 ? 'danger' : 'success')->icon('heroicon-o-banknotes')->color('success'),
            Stat::make('Pending approval · bank received', $money($pending['minor']))->description(number_format($pending['count']).' receipts awaiting allocation')->icon('heroicon-o-clock')->color('warning')->url(NcbaPaymentResource::getUrl()),
            Stat::make('Unpaid orders · not income', $money($unpaid['minor']))->description(number_format($unpaid['count']).' pending orders created in this range')->icon('heroicon-o-shopping-cart')->color('gray')->url(TokenPurchaseResource::getUrl()),
            Stat::make('Bank receipts · do not add to sales', $money($data['bank_total_minor']))->description($data['bank_count'].' receipts · '.$allocation.' allocated')->icon('heroicon-o-building-library')->color('info'),
            Stat::make('Approved purchases', number_format($data['approved']['count']))->description(number_format($data['approved']['tokens']).' tokens purchased')->icon('heroicon-o-check-badge')->color('success'),
            Stat::make('Paying owners', number_format($data['buyers']))->description(number_format($data['repeat_buyers']).' bought more than once in this range')->icon('heroicon-o-user-group'),
            Stat::make('Average approved purchase', $money($data['average_minor']))->description('Approved income ÷ approved purchases')->icon('heroicon-o-calculator'),
            Stat::make('Receipt claims · no bank confirmation', number_format($data['waiting_claims']))->description($data['missing_paid_dates'] ? $data['missing_paid_dates'].' paid orders missing approval dates are excluded from income.' : 'Open claims submitted in this range · not income')
                ->icon('heroicon-o-receipt-percent')->color('warning')->url(NcbaReceiptClaimResource::getUrl()),
        ];
    }
}
