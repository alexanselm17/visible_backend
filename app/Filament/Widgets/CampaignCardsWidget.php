<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class CampaignCardsWidget extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        return [
            Card::make('Campaign A', '150/200')->description('Reward: Ksh 5,000')->color('primary'),
            Card::make('Campaign B', '120/150')->description('Reward: Ksh 3,500')->color('success'),
            Card::make('Campaign C', '90/100')->description('Reward: Ksh 2,000')->color('warning'),
            Card::make('Campaign D', '210/250')->description('Reward: Ksh 7,000')->color('info'),
            Card::make('Campaign E', '50/80')->description('Reward: Ksh 1,000')->color('danger'),
        ];
    }
}
