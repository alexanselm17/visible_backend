<?php

namespace App\Filament\Pages;

use App\Support\AdminAccess;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Operations overview';

    public function getWidgets(): array
    {
        $widgets = [
            \App\Filament\Widgets\OperationsGuide::class,
            \App\Filament\Widgets\OperationsOverview::class,
            \App\Filament\Widgets\ViewsChart::class,
            \App\Filament\Widgets\ScreenshotsSubmissionChart::class,
        ];
        if (AdminAccess::allows('finance')) {
            $widgets[] = \App\Filament\Widgets\TokenRevenueChart::class;
            $widgets[] = \App\Filament\Widgets\RewardsChart::class;
        }
        $widgets[] = \App\Filament\Widgets\RecentActivityWidget::class;

        return $widgets;
    }
}
