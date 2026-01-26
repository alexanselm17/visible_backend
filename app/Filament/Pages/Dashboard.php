<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\CampaignCardsWidget::class,
            \App\Filament\Widgets\RecentActivityWidget::class,
            \App\Filament\Widgets\ViewsChart::class,
            \App\Filament\Widgets\ScreenshotsSubmissionChart::class,
            \App\Filament\Widgets\RevenueChart::class,
            \App\Filament\Widgets\UserEngagementChart::class,
            \App\Filament\Widgets\TopPerformingAdvertsChart::class,

        ];
    }
}
