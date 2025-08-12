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
use Illuminate\Support\Facades\DB;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected static ?string $title = 'Campaign Analytics Dashboard';

    protected function detectFraudPatterns($campaignId): array
    {
        try {
            // Get all advert IDs for the given campaign
            $advertIds = DB::table('advert_images')
                ->where('campaign_id', $campaignId)
                ->pluck('id');

            $fraudGroups = [];

            foreach ($advertIds as $advertId) {
                // Get all screenshots for this advert
                $screenshots = DB::table('screenshots')
                    ->where('advert_id', $advertId)
                    ->get();

                // Group screenshots by a combined key of views + timestamp
                $patterns = [];

                foreach ($screenshots as $screenshot) {
                    $patternKey = "{$screenshot->views}_{$screenshot->timestamp}";

                    $patterns[$patternKey][] = [
                        'user_id' => $screenshot->processed_by,
                        'name' => DB::table('users')->where('id', $screenshot->processed_by)->value('fullname'),
                        'views' => $screenshot->views,
                        'timestamp' => $screenshot->timestamp,
                        'number' => $screenshot->number,
                        'screenshot_id' => $screenshot->id,
                        'url' => asset('storage/' . $screenshot->screenshot),
                    ];
                }

                // Only include suspicious patterns shared by 2 or more users
                foreach ($patterns as $pattern => $grouped) {
                    $uniqueUsers = collect($grouped)->pluck('user_id')->unique();

                    if ($uniqueUsers->count() >= 2) {
                        $fraudGroups[] = [
                            'advert_id' => $advertId,
                            'advert_name' => DB::table('advert_images')->where('id', $advertId)->value('name'),
                            'matching_pattern' => $pattern,
                            'user_count' => $uniqueUsers->count(),
                            'users' => $uniqueUsers->values(),
                            'details' => $grouped,
                            'risk_level' => $this->calculateRiskLevel($uniqueUsers->count(), $grouped),
                        ];
                    }
                }
            }

            return $fraudGroups;
        } catch (\Throwable $th) {
            return [];
        }
    }

    /**
     * Calculate risk level based on pattern data
     */
    protected function calculateRiskLevel($userCount, $details): array
    {
        $riskScore = 0;
        $factors = [];

        // More users = higher risk
        if ($userCount >= 5) {
            $riskScore += 3;
            $factors[] = "Large group coordination ({$userCount} users)";
        } elseif ($userCount >= 3) {
            $riskScore += 2;
            $factors[] = "Medium group coordination ({$userCount} users)";
        } else {
            $riskScore += 1;
            $factors[] = "Small group coordination ({$userCount} users)";
        }

        // Check if views are suspiciously high or identical
        $views = collect($details)->pluck('views')->unique();
        if ($views->count() === 1 && $views->first() > 1000) {
            $riskScore += 2;
            $factors[] = "Suspiciously high identical views ({$views->first()})";
        }

        // Determine risk level
        if ($riskScore >= 4) {
            $level = 'HIGH';
            $color = 'danger';
            $icon = 'heroicon-o-exclamation-triangle';
        } elseif ($riskScore >= 2) {
            $level = 'MEDIUM';
            $color = 'warning';
            $icon = 'heroicon-o-exclamation-circle';
        } else {
            $level = 'LOW';
            $color = 'info';
            $icon = 'heroicon-o-information-circle';
        }

        return [
            'level' => $level,
            'color' => $color,
            'icon' => $icon,
            'score' => $riskScore,
            'factors' => $factors,
        ];
    }

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
                                            ->icon(
                                                function ($record) {
                                                    // Check if any advert in this campaign is still active
                                                    $hasActiveAdverts = $record->adverts->some(function ($advert) {
                                                        return $advert->valid_until && now()->lessThanOrEqualTo(Carbon::parse($advert->valid_until));
                                                    });
                                                    return $hasActiveAdverts ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';
                                                }
                                            )
                                            ->color(
                                                function ($record) {
                                                    // Check if any advert in this campaign is still active
                                                    $hasActiveAdverts = $record->adverts->some(function ($advert) {
                                                        return $advert->valid_until && now()->lessThanOrEqualTo(Carbon::parse($advert->valid_until));
                                                    });
                                                    return $hasActiveAdverts ? 'success' : 'danger';
                                                }
                                            ),

                                        TextEntry::make('campaign_status')
                                            ->label('')
                                            ->getStateUsing(
                                                function ($record) {
                                                    // Check if any advert in this campaign is still active
                                                    $hasActiveAdverts = $record->adverts->some(function ($advert) {
                                                        return $advert->valid_until && now()->lessThanOrEqualTo(Carbon::parse($advert->valid_until));
                                                    });
                                                    return $hasActiveAdverts ? 'ACTIVE CAMPAIGN' : 'EXPIRED CAMPAIGN';
                                                }
                                            )
                                            ->badge()
                                            ->color(
                                                function ($record) {
                                                    // Check if any advert in this campaign is still active
                                                    $hasActiveAdverts = $record->adverts->some(function ($advert) {
                                                        return $advert->valid_until && now()->lessThanOrEqualTo(Carbon::parse($advert->valid_until));
                                                    });
                                                    return $hasActiveAdverts ? 'success' : 'danger';
                                                }
                                            )
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
                                        ->getStateUsing(fn($record) => $record->adverts->sum('capital_invested'))
                                        ->money('KSH')
                                        ->color('primary')
                                        ->weight(FontWeight::Bold)
                                        ->size('xl')
                                        ->icon('heroicon-o-banknotes'),

                                    TextEntry::make('remaining_budget')
                                        ->label('Remaining Budget')
                                        ->getStateUsing(
                                            fn($record) =>
                                            $record->adverts->sum('capital_invested') - $record->adverts->sum(fn($advert) => $advert->screenshots->count() * $advert->reward)
                                        )
                                        ->money('KSH')
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
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->adverts->sum(fn($advert) => $advert->screenshots->count())
                                    )
                                    ->badge()
                                    ->size('xl')
                                    ->color('success')
                                    ->icon('heroicon-o-camera')
                                    ->extraAttributes(['class' => 'text-center']),

                                TextEntry::make('total_views')
                                    ->label('Total Engagement')
                                    ->getStateUsing(
                                        fn($record) =>
                                        number_format($record->adverts->sum('views'))
                                    )
                                    ->badge()
                                    ->size('xl')
                                    ->color('info')
                                    ->icon('heroicon-o-eye')
                                    ->extraAttributes(['class' => 'text-center']),

                                TextEntry::make('rewards_distributed')
                                    ->label('Rewards Paid')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->adverts->sum(
                                            fn($advert) =>
                                            $advert->screenshots->count() * $advert->reward
                                        )
                                    )
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
                                    ->label('Total Expected Participants')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->adverts->sum('capacity')
                                    )
                                    ->numeric()
                                    ->badge()
                                    ->color('gray')
                                    ->icon('heroicon-o-users'),

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
                                    ->formatStateUsing(
                                        fn($state) =>
                                        Carbon::parse($state)->format('M d, Y g:i A')
                                    )
                                    ->color('success')
                                    ->icon('heroicon-o-rocket-launch')
                                    ->weight(FontWeight::Medium),

                                TextEntry::make('days_running')
                                    ->label('Days Running')
                                    ->getStateUsing(function ($record) {
                                        $start = Carbon::parse($record->created_at);
                                        $diff = $start->diff(now());
                                        return "{$diff->d} days {$diff->h} hours {$diff->i} minutes";
                                    })
                                    ->badge()
                                    ->color('info')
                                    ->icon('heroicon-o-clock'),

                                TextEntry::make('days_remaining')
                                    ->label('Days Remaining')
                                    ->getStateUsing(function ($record) {
                                        if (!$record->valid_until) {
                                            return 'No End Date';
                                        }
                                        if (now()->greaterThan(Carbon::parse($record->valid_until))) {
                                            return 'Expired';
                                        }
                                        $end = Carbon::parse($record->valid_until);
                                        $diff = now()->diff($end);
                                        return "{$diff->d} days {$diff->h} hours {$diff->i} minutes";
                                    })
                                    ->badge()
                                    ->color(
                                        fn($record) =>
                                        $record->valid_until &&
                                            now()->lessThanOrEqualTo(Carbon::parse($record->valid_until))
                                            ? 'primary'
                                            : 'danger'
                                    )
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
                                                            ->getStateUsing(function ($record) {
                                                                $path = $record->image_path ?? $record->image_path;
                                                                return $path
                                                                    ? asset('storage/' . $path)
                                                                    : asset('storage/products/default-product.png');
                                                            })
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
                                                            TextEntry::make('capital_invested')
                                                                ->label('Capital Invested')
                                                                ->money('KSH')
                                                                ->color('success')
                                                                ->weight(FontWeight::Bold)
                                                                ->size('lg')
                                                                ->icon('heroicon-o-tag'),

                                                            TextEntry::make('reward')
                                                                ->label('Reward')
                                                                ->money('KSH')
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
                                                                            ->getStateUsing(function ($record) {
                                                                                $path = $record->screenshot;
                                                                                return $path
                                                                                    ? asset('storage/' . $path)
                                                                                    : asset('storage/screenshots/default-screenshot.png');
                                                                            })
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
                                                                            ->money('kSH')
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

                        Tabs\Tab::make('fraud_detection')
                            ->label('Security & Fraud Detection')
                            ->icon('heroicon-o-shield-exclamation')
                            ->badge(function ($record) {
                                $fraudPatterns = $this->detectFraudPatterns($record->id);
                                return count($fraudPatterns) > 0 ? count($fraudPatterns) : null;
                            })
                            ->badgeColor(function ($record) {
                                $fraudPatterns = $this->detectFraudPatterns($record->id);
                                if (count($fraudPatterns) === 0) return 'success';

                                $highRiskCount = collect($fraudPatterns)->where('risk_level.level', 'HIGH')->count();
                                if ($highRiskCount > 0) return 'danger';

                                $mediumRiskCount = collect($fraudPatterns)->where('risk_level.level', 'MEDIUM')->count();
                                if ($mediumRiskCount > 0) return 'warning';

                                return 'info';
                            })
                            ->schema([
                                Section::make('Fraud Detection Overview')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextEntry::make('fraud_status')
                                                    ->label('Security Status')
                                                    ->getStateUsing(function ($record) {
                                                        $fraudPatterns = $this->detectFraudPatterns($record->id);
                                                        if (count($fraudPatterns) === 0) {
                                                            return '🛡️ SECURE';
                                                        }

                                                        $highRisk = collect($fraudPatterns)->where('risk_level.level', 'HIGH')->count();
                                                        if ($highRisk > 0) return '🚨 HIGH RISK DETECTED';

                                                        $mediumRisk = collect($fraudPatterns)->where('risk_level.level', 'MEDIUM')->count();
                                                        if ($mediumRisk > 0) return '⚠️ MEDIUM RISK DETECTED';

                                                        return '🔍 LOW RISK PATTERNS';
                                                    })
                                                    ->badge()
                                                    ->size('xl')
                                                    ->color(function ($record) {
                                                        $fraudPatterns = $this->detectFraudPatterns($record->id);
                                                        if (count($fraudPatterns) === 0) return 'success';

                                                        $highRisk = collect($fraudPatterns)->where('risk_level.level', 'HIGH')->count();
                                                        if ($highRisk > 0) return 'danger';

                                                        $mediumRisk = collect($fraudPatterns)->where('risk_level.level', 'MEDIUM')->count();
                                                        if ($mediumRisk > 0) return 'warning';

                                                        return 'info';
                                                    }),

                                                TextEntry::make('fraud_patterns_count')
                                                    ->label('Suspicious Patterns')
                                                    ->getStateUsing(fn($record) => count($this->detectFraudPatterns($record->id)))
                                                    ->badge()
                                                    ->size('xl')
                                                    ->color(function ($record) {
                                                        $count = count($this->detectFraudPatterns($record->id));
                                                        return $count > 0 ? 'warning' : 'success';
                                                    })
                                                    ->icon('heroicon-o-exclamation-triangle'),

                                                TextEntry::make('affected_users')
                                                    ->label('Users Under Review')
                                                    ->getStateUsing(function ($record) {
                                                        $fraudPatterns = $this->detectFraudPatterns($record->id);
                                                        $allUsers = collect($fraudPatterns)->pluck('users')->flatten()->unique();
                                                        return $allUsers->count();
                                                    })
                                                    ->badge()
                                                    ->size('xl')
                                                    ->color('info')
                                                    ->icon('heroicon-o-users'),

                                                TextEntry::make('last_scan')
                                                    ->label('Last Security Scan')
                                                    ->getStateUsing(fn() => now()->format('M d, Y g:i A'))
                                                    ->badge()
                                                    ->size('lg')
                                                    ->color('gray')
                                                    ->icon('heroicon-o-clock'),
                                            ]),
                                    ])
                                    ->extraAttributes(['class' => 'bg-gradient-to-r from-red-50 to-orange-50 border-l-4 border-red-400']),

                                Section::make('Detailed Fraud Analysis')
                                    ->schema(function ($record) {
                                        $fraudPatterns = $this->detectFraudPatterns($record->id);

                                        if (count($fraudPatterns) === 0) {
                                            return [
                                                TextEntry::make('no_fraud')
                                                    ->label('')
                                                    ->default("🎉 **No suspicious patterns detected!**\n\nYour campaign appears to be clean with no coordinated fraud attempts detected.")
                                                    ->markdown()
                                                    ->columnSpanFull()
                                            ];
                                        }

                                        $entries = [];

                                        foreach ($fraudPatterns as $fraud) {
                                            // Show advert name
                                            $entries[] = TextEntry::make("advert_{$fraud['advert_name']}")
                                                ->label('')
                                                ->default("### 📌 Advert: {$fraud['advert_name']}")
                                                ->markdown()
                                                ->columnSpanFull();

                                            // Group users into pairs for side-by-side comparison
                                            $userPairs = array_chunk($fraud['details'], 2);

                                            foreach ($userPairs as $pair) {
                                                $columns = [];

                                                foreach ($pair as $detail) {
                                                    $columns[] = Grid::make(1)
                                                        ->schema([
                                                            TextEntry::make("user_info_{$detail['user_id']}")
                                                                ->label('')
                                                                ->default("**{$detail['name']}**\n{$detail['views']} views at {$detail['timestamp']}")
                                                                ->markdown()
                                                                ->columnSpan(1),

                                                            ImageEntry::make("user_img_{$detail['user_id']}")
                                                                ->label('')
                                                                ->height(150)
                                                                ->width(150)
                                                                ->getStateUsing(fn() => $detail['url'] ?? 'https://visibledm.com/storage/products/default-product.png')
                                                                ->url(fn() => $detail['url'] ?? null, true) // opens full-size in new tab
                                                                ->columnSpan(1),
                                                        ])
                                                        ->columnSpan(1);
                                                }

                                                // If odd number, make right column empty
                                                if (count($columns) < 2) {
                                                    $columns[] = TextEntry::make('empty_col')->label('')->default('')->columnSpan(1);
                                                }

                                                $entries[] = Grid::make(2)
                                                    ->schema($columns)
                                                    ->columnSpanFull();
                                            }
                                        }

                                        return $entries;
                                    })
                                    ->collapsible()
                                    ->collapsed(fn($record) => count($this->detectFraudPatterns($record->id)) === 0),


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

            \Filament\Actions\Action::make('add_budget')
                ->label('Generate Campaign Report')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'text-white font-semibold px-4 py-2 rounded-lg shadow-sm transition-colors duration-200',
                    'style' => 'background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); border: none; color: white !important;',
                ])
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
