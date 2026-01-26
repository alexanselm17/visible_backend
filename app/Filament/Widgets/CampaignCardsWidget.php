<?php

namespace App\Filament\Widgets;

use App\Models\AdvertImages;
use App\Models\Campaign;
use App\Models\Invoice;
use App\Models\Screenshots;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

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

        // New payment-related statistics based on your function
        $campaigns = Campaign::with([
            'adverts.screenshots',
            'adverts.invoices' => function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end]);
            },
        ])
            ->whereBetween('created_at', [$start, $end])
            ->get();

        // Collect campaign IDs
        $campaignIds = $campaigns->pluck('id');
        $advertCampaigns = AdvertImages::whereIn('campaign_id', $campaignIds)->get();

        $rewardAssigned = 0;

        // Calculate reward assigned
        $campaigns->each(function ($campaign) use (&$rewardAssigned) {
            $comp = $campaign->adverts->filter(fn ($ad) => $ad->invoices->isNotEmpty())->count();
            $compReward = $comp * ($campaign->reward ?? 0);
            $rewardAssigned += $compReward;
        });

        // Calculate total payment done
        $paymentDone = Invoice::whereBetween('created_at', [$start, $end])
            ->where('type', 'Payment')
            ->sum('amount');

        // Calculate pending payments
        $latestInvoiceIds = Invoice::select(DB::raw('MAX(id) as id'))
            ->groupBy('processed_by');

        $totalBalance = Invoice::whereIn('id', $latestInvoiceIds)
            ->sum('customer_balance');

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
            Stat::make('Rewards Assigned', 'KSh '.number_format($rewardAssigned, 2))
                ->icon('heroicon-o-gift')
                ->description('Total rewards allocated')
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
