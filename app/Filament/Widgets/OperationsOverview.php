<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AdvertSubmissionResource;
use App\Filament\Resources\RewardPayoutResource;
use App\Filament\Resources\TokenPurchaseResource;
use App\Models\AdvertImages;
use App\Models\AdvertSubmission;
use App\Models\RewardPayout;
use App\Models\Screenshots;
use App\Models\TokenPurchase;
use App\Models\User;
use App\Support\AdminAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $stats = [
            Stat::make('Active members', number_format(User::where('is_active', true)->count()))
                ->description('Accounts ready to use Visible')->icon('heroicon-o-user-group')->color('success'),
            Stat::make('Live adverts', number_format(AdvertImages::where('valid_until', '>=', now())->count()))
                ->description('Within their posting window')->icon('heroicon-o-megaphone')->color('primary'),
            Stat::make('Completed posting tasks', number_format(Screenshots::where('number', 2)->count()))
                ->description('Both screenshots submitted')->icon('heroicon-o-check-badge')->color('success'),
            Stat::make('Submissions to review', number_format(AdvertSubmission::where('status', 'PENDING_APPROVAL')->count()))
                ->description('Open the submission queue')->url(AdvertSubmissionResource::getUrl())
                ->icon('heroicon-o-inbox-stack')->color('warning'),
        ];

        if (AdminAccess::allows('finance')) {
            $stats[] = Stat::make('Purchases awaiting payment', number_format(TokenPurchase::where('status', TokenPurchase::PENDING)->count()))
                ->description('Confirm received payments')->url(TokenPurchaseResource::getUrl())
                ->icon('heroicon-o-ticket')->color('warning');
            $stats[] = Stat::make('Payouts needing attention', number_format(RewardPayout::whereIn('status', ['pending', 'failed'])->count()))
                ->description('Review payment and retry details')->url(RewardPayoutResource::getUrl())
                ->icon('heroicon-o-banknotes')->color('info');
        }

        return $stats;
    }
}
