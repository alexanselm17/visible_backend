<?php

namespace App\Filament\Widgets;

use App\Models\AdvertImages;
use App\Models\Campaign;
use App\Models\RewardLedgerEntry;
use App\Models\RewardPayout;
use App\Models\Screenshots;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CampaignCardsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $now = Carbon::now('Africa/Nairobi');

        // Time filter logic - start of this year
        $start = $now->copy()->startOfYear();
        $end = $now;

        // Original stats
        $totalCampaigns = Campaign::count();
        $activeCampaigns = Campaign::count();

        $totalAdverts = AdvertImages::count();
        $activeAdverts = AdvertImages::where('valid_until', '>=', $today)->count();

        $totalScreenshots = Screenshots::count();
        $totalViews = Screenshots::sum('views');

        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();

        $activePercentage = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0;
        $inactivePercentage = $totalUsers > 0 ? round(($inactiveUsers / $totalUsers) * 100, 1) : 0;

        $performanceEarnings = RewardLedgerEntry::where('type', RewardLedgerEntry::EARNING)
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount_minor') / 100;
        $paymentDone = RewardPayout::where('status', RewardPayout::PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount_minor') / 100;
        $totalBalance = RewardLedgerEntry::sum('amount_minor') / 100;

        return [
            Stat::make('Total Users', number_format($totalUsers))
                ->icon('heroicon-o-user-group')
                ->description('All registered platform users')
                ->color('success'),

            Stat::make('Active Users', number_format($activeUsers))
                ->icon('heroicon-o-users')
                ->description("{$activePercentage}% of total users")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('primary'),

            Stat::make('Inactive Users', number_format($inactiveUsers))
                ->icon('heroicon-o-user-minus')
                ->description("{$inactivePercentage}% of total users")
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Total Campaigns', $totalCampaigns)
                ->description($activeCampaigns.' currently active')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),

            Stat::make('Active Campaigns', $activeCampaigns)
                ->description('Running campaigns')
                ->descriptionIcon('heroicon-m-play-circle')
                ->color('info'),

            Stat::make('Total Advertisements', $totalAdverts)
                ->description('Across all campaigns')
                ->descriptionIcon('heroicon-m-photo')
                ->chart([15, 4, 10, 2, 12, 4, 12])
                ->color('warning'),

            Stat::make('Active Advertisements', $activeAdverts)
                ->description('Still valid')
                ->descriptionIcon('heroicon-m-bolt')
                ->chart([5, 8, 6, 12, 9, 11, 13])
                ->color('info'),

            Stat::make('Screenshots Submitted', $totalScreenshots)
                ->description('User submissions')
                ->descriptionIcon('heroicon-m-camera')
                ->chart([3, 8, 5, 10, 15, 8, 12])
                ->color('primary'),

            Stat::make('Total Views', number_format($totalViews))
                ->description('Engagement metrics')
                ->descriptionIcon('heroicon-m-eye')
                ->chart([10, 15, 8, 20, 12, 25, 18])
                ->color('success'),

            // New payment-related statistics
            Stat::make('Performance Earnings', 'KSh '.number_format($performanceEarnings, 2))
                ->icon('heroicon-o-gift')
                ->description('Locked multiplier earnings')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            Stat::make('Payments Done', 'KSh '.number_format($paymentDone, 2))
                ->icon('heroicon-o-banknotes')
                ->description('Completed payments today')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Pending Payments', 'KSh '.number_format($totalBalance, 2))
                ->icon('heroicon-o-clock')
                ->description('Outstanding balances')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }

    protected static ?int $sort = 1;
}
