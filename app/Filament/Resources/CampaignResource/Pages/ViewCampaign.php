<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Models\Campaign;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\Group;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconSize;
use Carbon\Carbon;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected static ?string $title = 'Campaign Analytics Dashboard';

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Hero Section with Key Metrics
                Section::make()
                    ->schema([
                        Split::make([
                            Grid::make(1)
                                ->schema([
                                    TextEntry::make('name')
                                        ->label('')
                                        ->weight(FontWeight::Bold)
                                        ->size('2xl')
                                        ->color('primary')
                                        ->extraAttributes(['class' => 'mb-2']),

                                    Group::make([
                                        IconEntry::make('is_active')
                                            ->label('')
                                            ->icon(fn($record) => $record->is_active ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                            ->color(fn($record) => $record->is_active ? 'success' : 'danger'),

                                        TextEntry::make('campaign_status')
                                            ->label('')
                                            ->getStateUsing(fn($record) => $record->is_active ? 'ACTIVE CAMPAIGN' : 'EXPIRED CAMPAIGN')
                                            ->badge()
                                            ->color(fn($record) => $record->is_active ? 'success' : 'danger')
                                            ->size('lg')
                                            ->weight(FontWeight::Bold),
                                    ])
                                        ->extraAttributes(['class' => 'flex items-center space-x-3']),
                                ])
                                ->columnSpan(2),

                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('capital_invested')
                                        ->label('Total Investment')
                                        ->money('USD')
                                        ->color('primary')
                                        ->weight(FontWeight::Bold)
                                        ->size('xl')
                                        ->icon('heroicon-o-banknotes'),

                                    TextEntry::make('remaining_budget')
                                        ->label('Remaining Budget')
                                        ->getStateUsing(fn($record) => $record->remaining_budget)
                                        ->money('USD')
                                        ->color('success')
                                        ->weight(FontWeight::Bold)
                                        ->size('xl')
                                        ->icon('heroicon-o-wallet'),
                                ])
                                ->columnSpan(2),
                        ])
                            ->from('md'),
                    ])
                    ->extraAttributes(['class' => 'bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-400']),

                // Key Performance Indicators
                Section::make('Performance Overview')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_adverts')
                                    ->label('Active Adverts')
                                    ->getStateUsing(fn($record) => $record->adverts->count())
                                    ->badge()
                                    ->size('xl')
                                    ->color('primary')
                                    ->icon('heroicon-o-megaphone')
                                    ->extraAttributes(['class' => 'text-center']),

                                TextEntry::make('total_screenshots')
                                    ->label('Total Submissions')
                                    ->getStateUsing(fn($record) => $record->total_screenshots)
                                    ->badge()
                                    ->size('xl')
                                    ->color('success')
                                    ->icon('heroicon-o-camera')
                                    ->extraAttributes(['class' => 'text-center']),

                                TextEntry::make('total_views')
                                    ->label('Total Engagement')
                                    ->getStateUsing(fn($record) => number_format($record->total_views))
                                    ->badge()
                                    ->size('xl')
                                    ->color('info')
                                    ->icon('heroicon-o-eye')
                                    ->extraAttributes(['class' => 'text-center']),

                                TextEntry::make('rewards_distributed')
                                    ->label('Rewards Paid')
                                    ->getStateUsing(fn($record) => $record->total_rewards_distributed)
                                    ->money('USD')
                                    ->badge()
                                    ->size('xl')
                                    ->color('warning')
                                    ->icon('heroicon-o-gift')
                                    ->extraAttributes(['class' => 'text-center']),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('capacity')
                                    ->label('Max Participants')
                                    ->numeric()
                                    ->badge()
                                    ->color('gray')
                                    ->icon('heroicon-o-users'),

                                TextEntry::make('reward')
                                    ->label('Reward per Submission')
                                    ->money('USD')
                                    ->color('warning')
                                    ->weight(FontWeight::Bold)
                                    ->icon('heroicon-o-currency-dollar'),

                                TextEntry::make('valid_until')
                                    ->label('Campaign Ends')
                                    ->formatStateUsing(fn($state) => Carbon::parse($state)->format('M d, Y g:i A'))
                                    ->badge()
                                    ->color(fn($record) => $record->is_active ? 'success' : 'danger')
                                    ->icon('heroicon-o-calendar'),
                            ]),
                    ])
                    ->collapsible(),

                // Campaign Timeline
                Section::make('Campaign Timeline')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Campaign Launched')
                                    ->formatStateUsing(fn($state) => Carbon::parse($state)->format('M d, Y g:i A'))
                                    ->color('success')
                                    ->icon('heroicon-o-rocket-launch')
                                    ->weight(FontWeight::Medium),

                                TextEntry::make('days_running')
                                    ->label('Days Running')
                                    ->getStateUsing(fn($record) => Carbon::parse($record->created_at)->diffInDays(now()) . ' days')
                                    ->badge()
                                    ->color('info')
                                    ->icon('heroicon-o-clock'),

                                TextEntry::make('days_remaining')
                                    ->label('Days Remaining')
                                    ->getStateUsing(fn($record) => $record->is_active ?
                                        Carbon::parse($record->valid_until)->diffInDays(now()) . ' days' :
                                        'Expired')
                                    ->badge()
                                    ->color(fn($record) => $record->is_active ? 'primary' : 'danger')
                                    ->icon('heroicon-o-calendar-days'),
                            ]),
                    ])
                    ->collapsible(),

                // Main Content Tabs
                Tabs::make('Campaign Management')
                    ->tabs([
                        // Adverts & Screenshots Tab
                        Tabs\Tab::make('adverts')
                            ->label('Adverts & User Content')
                            ->icon('heroicon-o-photo')
                            ->badge(fn($record) => $record->adverts->count())
                            ->schema([
                                RepeatableEntry::make('adverts')
                                    ->label('')
                                    ->schema([
                                        Section::make()
                                            ->schema([
                                                // Advert Header with Image and Details
                                                Grid::make(5)
                                                    ->schema([
                                                        ImageEntry::make('image_path')
                                                            ->label('')
                                                            ->height(150)
                                                            ->width(150)
                                                            // ->extraImageAttributes(['class' => 'rounded-lg shadow-md'])
                                                            ->columnSpan(1),

                                                        Group::make([
                                                            TextEntry::make('name')
                                                                ->label('Product Name')
                                                                ->weight(FontWeight::Bold)
                                                                ->size('lg')
                                                                ->color('primary'),

                                                            TextEntry::make('category')
                                                                ->label('Category')
                                                                ->badge()
                                                                ->color('gray'),

                                                            TextEntry::make('description')
                                                                ->label('Description')
                                                                ->limit(100)
                                                                ->markdown(),
                                                        ])
                                                            ->columnSpan(2),

                                                        Group::make([
                                                            TextEntry::make('selling_price')
                                                                ->label('Price')
                                                                ->money('USD')
                                                                ->color('success')
                                                                ->weight(FontWeight::Bold)
                                                                ->size('lg')
                                                                ->icon('heroicon-o-tag'),

                                                            TextEntry::make('reward')
                                                                ->label('Reward')
                                                                ->money('USD')
                                                                ->color('warning')
                                                                ->weight(FontWeight::Bold)
                                                                ->size('lg')
                                                                ->icon('heroicon-o-gift'),
                                                        ])
                                                            ->columnSpan(1),

                                                        Group::make([
                                                            TextEntry::make('screenshots_count')
                                                                ->label('Submissions')
                                                                ->getStateUsing(fn($record) => $record->screenshots->count())
                                                                ->badge()
                                                                ->size('lg')
                                                                ->color('primary')
                                                                ->icon('heroicon-o-camera'),

                                                            TextEntry::make('total_views')
                                                                ->label('Total Views')
                                                                ->getStateUsing(fn($record) => number_format($record->total_views))
                                                                ->badge()
                                                                ->size('lg')
                                                                ->color('info')
                                                                ->icon('heroicon-o-eye'),

                                                            TextEntry::make('unique_users')
                                                                ->label('Unique Users')
                                                                ->getStateUsing(fn($record) => $record->unique_users)
                                                                ->badge()
                                                                ->size('lg')
                                                                ->color('success')
                                                                ->icon('heroicon-o-users'),
                                                        ])
                                                            ->columnSpan(1),
                                                    ]),

                                                // Badges Section
                                                TextEntry::make('badge')
                                                    ->label('Product Badges')
                                                    ->listWithLineBreaks()
                                                    ->bulleted()
                                                    ->color('gray')
                                                    ->visible(fn($record) => !empty($record->badge)),

                                                // User Screenshots Section
                                                Section::make('User Submissions')
                                                    ->schema([
                                                        RepeatableEntry::make('screenshots')
                                                            ->label('')
                                                            ->schema([
                                                                Grid::make(6)
                                                                    ->schema([
                                                                        ImageEntry::make('screenshot')
                                                                            ->label('')
                                                                            ->height(120)
                                                                            ->width(120)
                                                                            // ->extraImageAttributes(['class' => 'rounded-lg shadow-sm'])
                                                                            ->columnSpan(1),

                                                                        TextEntry::make('user.fullname')
                                                                            ->label('User')
                                                                            ->weight(FontWeight::Bold)
                                                                            ->color('primary')
                                                                            ->icon('heroicon-o-user')
                                                                            ->columnSpan(1),

                                                                        TextEntry::make('views')
                                                                            ->label('Views')
                                                                            ->numeric()
                                                                            ->badge()
                                                                            ->color('success')
                                                                            ->icon('heroicon-o-eye')
                                                                            ->columnSpan(1),

                                                                        TextEntry::make('engagement_rate')
                                                                            ->label('Engagement')
                                                                            ->getStateUsing(
                                                                                fn($record) =>
                                                                                $record->views > 0 ?
                                                                                    number_format(($record->views / 100) * 100, 1) . '%' :
                                                                                    '0%'
                                                                            )
                                                                            ->badge()
                                                                            ->color('info')
                                                                            ->columnSpan(1),

                                                                        TextEntry::make('reward_earned')
                                                                            ->label('Reward Earned')
                                                                            ->getStateUsing(fn($record) => $record->advert->reward ?? 0)
                                                                            ->money('USD')
                                                                            ->color('warning')
                                                                            ->weight(FontWeight::Bold)
                                                                            ->columnSpan(1),

                                                                        TextEntry::make('created_at')
                                                                            ->label('Submitted')
                                                                            ->formatStateUsing(fn($state) => Carbon::parse($state)->format('M d, Y'))
                                                                            ->color('gray')
                                                                            ->columnSpan(1),
                                                                    ]),
                                                            ])
                                                            ->contained(false)
                                                            ->visible(fn($record) => $record->screenshots->count() > 0),

                                                        // No screenshots message
                                                        Group::make([
                                                            TextEntry::make('no_screenshots')
                                                                ->label('')
                                                                ->getStateUsing(fn() => '📷 No submissions yet - waiting for user engagement!')
                                                                ->color('gray')
                                                                ->size('lg')
                                                                ->extraAttributes(['class' => 'text-center italic']),
                                                        ])
                                                            ->visible(fn($record) => $record->screenshots->count() === 0),
                                                    ])
                                                    ->collapsible()
                                                    ->collapsed(fn($record) => $record->screenshots->count() === 0)
                                                    ->extraAttributes(['class' => 'border-t mt-4 pt-4']),

                                            ])
                                            ->headerActions([])
                                            ->collapsible()
                                            ->extraAttributes(['class' => 'border-2 border-dashed border-gray-200 rounded-xl']),
                                    ])
                                    ->contained(false),
                            ]),

                        // Analytics Tab
                        Tabs\Tab::make('analytics')
                            ->label('Analytics & Insights')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Section::make('Campaign Performance Metrics')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Group::make([
                                                    TextEntry::make('engagement_rate')
                                                        ->label('Overall Engagement Rate')
                                                        ->getStateUsing(
                                                            fn($record) =>
                                                            $record->total_screenshots > 0 ?
                                                                number_format(($record->total_views / $record->total_screenshots), 1) . ' views per submission' :
                                                                'No data yet'
                                                        )
                                                        ->badge()
                                                        ->size('xl')
                                                        ->color('info'),

                                                    TextEntry::make('roi')
                                                        ->label('Return on Investment')
                                                        ->getStateUsing(
                                                            fn($record) =>
                                                            $record->capital_invested > 0 ?
                                                                number_format(($record->total_views / $record->capital_invested) * 100, 2) . ' views per $1' :
                                                                'Calculating...'
                                                        )
                                                        ->badge()
                                                        ->size('xl')
                                                        ->color('success'),

                                                    TextEntry::make('cost_per_engagement')
                                                        ->label('Cost per Submission')
                                                        ->getStateUsing(
                                                            fn($record) =>
                                                            $record->total_screenshots > 0 ?
                                                                '$' . number_format($record->total_rewards_distributed / $record->total_screenshots, 2) :
                                                                '$' . number_format($record->reward, 2)
                                                        )
                                                        ->badge()
                                                        ->size('xl')
                                                        ->color('warning'),
                                                ]),

                                                Group::make([
                                                    TextEntry::make('budget_utilization')
                                                        ->label('Budget Utilization')
                                                        ->getStateUsing(
                                                            fn($record) =>
                                                            $record->capital_invested > 0 ?
                                                                number_format(($record->total_rewards_distributed / $record->capital_invested) * 100, 1) . '%' :
                                                                '0%'
                                                        )
                                                        ->badge()
                                                        ->size('xl')
                                                        ->color('primary'),

                                                    TextEntry::make('average_views_per_advert')
                                                        ->label('Avg Views per Advert')
                                                        ->getStateUsing(
                                                            fn($record) =>
                                                            $record->adverts->count() > 0 ?
                                                                number_format($record->total_views / $record->adverts->count()) :
                                                                '0'
                                                        )
                                                        ->badge()
                                                        ->size('xl')
                                                        ->color('info'),

                                                    TextEntry::make('participation_rate')
                                                        ->label('Participation Rate')
                                                        ->getStateUsing(
                                                            fn($record) =>
                                                            $record->capacity > 0 ?
                                                                number_format(($record->total_screenshots / $record->capacity) * 100, 1) . '%' :
                                                                'Unlimited'
                                                        )
                                                        ->badge()
                                                        ->size('xl')
                                                        ->color('success'),
                                                ]),
                                            ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Financial Summary')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextEntry::make('capital_invested')
                                                    ->label('Total Investment')
                                                    ->money('USD')
                                                    ->color('primary')
                                                    ->weight(FontWeight::Bold)
                                                    ->size('xl'),

                                                TextEntry::make('total_rewards_distributed')
                                                    ->label('Rewards Distributed')
                                                    ->getStateUsing(fn($record) => $record->total_rewards_distributed)
                                                    ->money('USD')
                                                    ->color('warning')
                                                    ->weight(FontWeight::Bold)
                                                    ->size('xl'),

                                                TextEntry::make('remaining_budget')
                                                    ->label('Remaining Budget')
                                                    ->getStateUsing(fn($record) => $record->remaining_budget)
                                                    ->money('USD')
                                                    ->color('success')
                                                    ->weight(FontWeight::Bold)
                                                    ->size('xl'),

                                                TextEntry::make('projected_total_cost')
                                                    ->label('Projected Total Cost')
                                                    ->getStateUsing(
                                                        fn($record) =>
                                                        $record->capacity ?
                                                            $record->capacity * $record->reward :
                                                            $record->total_rewards_distributed
                                                    )
                                                    ->money('USD')
                                                    ->color('gray')
                                                    ->weight(FontWeight::Bold)
                                                    ->size('xl'),
                                            ]),
                                    ]),
                            ]),

                        // Campaign Management Tab
                        Tabs\Tab::make('management')
                            ->label('Campaign Management')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Section::make('Campaign Status & Controls')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('campaign_health')
                                                    ->label('Campaign Health')
                                                    ->getStateUsing(
                                                        fn($record) =>
                                                        $record->is_active && $record->remaining_budget > 0 ?
                                                            '🟢 Healthy' : ($record->is_active ? '🟡 Low Budget' : '🔴 Expired')
                                                    )
                                                    ->badge()
                                                    ->size('lg')
                                                    ->color(
                                                        fn($record) =>
                                                        $record->is_active && $record->remaining_budget > 0 ?
                                                            'success' : ($record->is_active ? 'warning' : 'danger')
                                                    ),

                                                TextEntry::make('auto_pause_threshold')
                                                    ->label('Auto-Pause Threshold')
                                                    ->getStateUsing(
                                                        fn($record) =>
                                                        $record->remaining_budget < ($record->reward * 10) ?
                                                            'Low Budget Alert' :
                                                            'Budget Sufficient'
                                                    )
                                                    ->badge()
                                                    ->color(
                                                        fn($record) =>
                                                        $record->remaining_budget < ($record->reward * 10) ?
                                                            'warning' :
                                                            'success'
                                                    ),

                                                TextEntry::make('recommended_action')
                                                    ->label('Recommended Action')
                                                    ->getStateUsing(function ($record) {
                                                        if (!$record->is_active) return '⏰ Extend or Archive';
                                                        if ($record->remaining_budget < $record->reward) return '💰 Add Budget';
                                                        if ($record->total_screenshots === 0) return '📢 Promote Campaign';
                                                        return '✅ Monitor Performance';
                                                    })
                                                    ->badge()
                                                    ->color('info'),
                                            ]),
                                    ]),

                                Section::make('Campaign Insights & Recommendations')
                                    ->schema([
                                        TextEntry::make('performance_insights')
                                            ->label('Performance Insights')
                                            ->getStateUsing(function ($record) {
                                                $insights = [];

                                                if ($record->total_screenshots === 0) {
                                                    $insights[] = "🎯 No submissions yet - consider promoting the campaign or adjusting rewards";
                                                }

                                                if ($record->total_views > 1000) {
                                                    $insights[] = "🚀 High engagement! This campaign is performing well";
                                                }

                                                if ($record->remaining_budget < ($record->reward * 5)) {
                                                    $insights[] = "⚠️ Budget running low - consider adding funds to continue";
                                                }

                                                if (empty($insights)) {
                                                    $insights[] = "📊 Campaign is running smoothly - monitor regularly for optimization opportunities";
                                                }

                                                return implode("\n", $insights);
                                            })
                                            ->markdown()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->record->refresh();
                    \Filament\Notifications\Notification::make()
                        ->title('Campaign data refreshed successfully')
                        ->success()
                        ->send();
                }),

            \Filament\Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square'),

            \Filament\Actions\Action::make('duplicate')
                ->label('Duplicate Campaign')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Duplicate Campaign')
                ->modalDescription('This will create an exact copy of this campaign with a 30-day extension.')
                ->action(function () {
                    $record = $this->record;
                    $newCampaign = $record->replicate();
                    $newCampaign->name = $record->name . ' (Copy ' . now()->format('M Y') . ')';
                    $newCampaign->valid_until = now()->addDays(30);
                    $newCampaign->save();

                    // Duplicate adverts as well
                    foreach ($record->adverts as $advert) {
                        $newAdvert = $advert->replicate();
                        $newAdvert->campaign_id = $newCampaign->id;
                        $newAdvert->save();
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Campaign Duplicated Successfully')
                        ->body('New campaign created with ' . $record->adverts->count() . ' adverts')
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->button()
                                ->url(CampaignResource::getUrl('view', ['record' => $newCampaign]))
                        ])
                        ->send();

                    return redirect()->to(CampaignResource::getUrl('view', ['record' => $newCampaign]));
                }),

            \Filament\Actions\Action::make('extend')
                ->label('Extend Campaign')
                ->icon('heroicon-o-calendar-days')
                ->color('success')
                ->visible(fn() => $this->record->is_active)
                ->form([
                    \Filament\Forms\Components\DateTimePicker::make('new_end_date')
                        ->label('New End Date')
                        ->required()
                        ->minDate(now())
                        ->default(now()->addDays(30)),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'valid_until' => $data['new_end_date'],
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Campaign Extended Successfully')
                        ->body('Campaign will now run until ' . Carbon::parse($data['new_end_date'])->format('M d, Y'))
                        ->success()
                        ->send();
                }),

            \Filament\Actions\Action::make('add_budget')
                ->label('Generate Campaign Report')
                ->icon('heroicon-o-banknotes')
                ->color('warning')

                ->action(function ($record) {
                    return redirect()->route('campaign_report', [
                        'campaign_id' => $record->id,
                    ]);
                }),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->name . ' - Campaign Analytics';
    }
}
