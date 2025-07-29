<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class DashboardStatsWidget extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        return [
            Card::make('Users', 1223)->icon('heroicon-o-user-group')->color('success'),
            Card::make('Ongoing Campaigns', 8)->icon('heroicon-o-rocket-launch')->color('primary'),
            Card::make('Completed Campaigns', 17)->icon('heroicon-o-check-circle')->color('info'),
        ];
    }
}
