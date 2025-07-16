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
use Filament\Support\Enums\FontWeight;
use Carbon\Carbon;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected static ?string $title = 'Campaign Details';

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Campaign Overview')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Campaign Name')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg')
                                    ->color('primary'),

                                TextEntry::make('capital_invested')
                                    ->label('Capital Invested')
                                    ->money('USD')
                                    ->color('success')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('reward')
                                    ->label('Reward per Engagement')
                                    ->money('USD')
                                    ->color('warning')
                                    ->weight(FontWeight::Bold),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('capacity')
                                    ->label('Maximum Participants')
                                    ->numeric()
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('valid_until')
                                    ->label('Valid Until')
                                    ->formatStateUsing(fn($state) => Carbon::parse($state)->format('M d, Y'))
                                    ->badge()
                                    ->color(fn($record) => $record->valid_until < now() ? 'danger' : 'success'),

                                TextEntry::make('adverts_count')
                                    ->label('Total Adverts')
                                    ->getStateUsing(fn($record) => $record->adverts->count())
                                    ->badge()
                                    ->color('gray'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime()
                                    ->color('gray'),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime()
                                    ->color('gray'),
                            ]),
                    ])
                    ->collapsible(),

                Tabs::make('Campaign Details')
                    ->tabs([
                        Tabs\Tab::make('Adverts & Screenshots')
                            ->schema([
                                RepeatableEntry::make('adverts')
                                    ->label('')
                                    ->schema([
                                        Section::make('')
                                            ->schema([
                                                Grid::make(4)
                                                    ->schema([
                                                        ImageEntry::make('image_path')
                                                            ->label('Advert Image')
                                                            ->height(120)
                                                            ->width(120)
                                                            ->columnSpan(1),

                                                        TextEntry::make('name')
                                                            ->label('Product Name')
                                                            ->weight(FontWeight::Bold)
                                                            ->size('lg')
                                                            ->columnSpan(1),

                                                        TextEntry::make('selling_price')
                                                            ->label('Selling Price')
                                                            ->money('USD')
                                                            ->color('success')
                                                            ->weight(FontWeight::Bold)
                                                            ->columnSpan(1),

                                                        TextEntry::make('reward')
                                                            ->label('Reward')
                                                            ->money('USD')
                                                            ->color('warning')
                                                            ->weight(FontWeight::Bold)
                                                            ->columnSpan(1),
                                                    ]),

                                                Grid::make(2)
                                                    ->schema([
                                                        TextEntry::make('category')
                                                            ->label('Category')
                                                            ->badge()
                                                            ->color('info'),

                                                        TextEntry::make('screenshots_count')
                                                            ->label('Screenshots Submitted')
                                                            ->getStateUsing(fn($record) => $record->screenshots->count())
                                                            ->badge()
                                                            ->color('primary'),
                                                    ]),

                                                TextEntry::make('description')
                                                    ->label('Description')
                                                    ->markdown()
                                                    ->columnSpanFull(),

                                                TextEntry::make('badge')
                                                    ->label('Badges')
                                                    ->listWithLineBreaks()
                                                    ->bulleted()
                                                    ->color('gray'),

                                                Section::make('User Screenshots')
                                                    ->schema([
                                                        RepeatableEntry::make('screenshots')
                                                            ->label('')
                                                            ->schema([
                                                                Grid::make(4)
                                                                    ->schema([
                                                                        ImageEntry::make('screenshot')
                                                                            ->label('Screenshot')
                                                                            ->height(100)
                                                                            ->width(100)
                                                                            ->columnSpan(1),

                                                                        TextEntry::make('user.fullname')
                                                                            ->label('Submitted By')
                                                                            ->weight(FontWeight::Bold)
                                                                            ->color('primary')
                                                                            ->columnSpan(1),

                                                                        TextEntry::make('views')
                                                                            ->label('Views')
                                                                            ->numeric()
                                                                            ->badge()
                                                                            ->color('success')
                                                                            ->columnSpan(1),

                                                                        TextEntry::make('created_at')
                                                                            ->label('Submitted')
                                                                            ->dateTime()
                                                                            ->color('gray')
                                                                            ->columnSpan(1),
                                                                    ]),
                                                            ])
                                                            ->contained(false)
                                                            ->visible(fn($record) => $record->screenshots->count() > 0),
                                                    ])
                                                    ->collapsible()
                                                    ->visible(fn($record) => $record->screenshots->count() > 0),

                                                TextEntry::make('no_screenshots')
                                                    ->label('')
                                                    ->getStateUsing(fn() => 'No screenshots submitted yet')
                                                    ->color('gray')
                                                    ->extraAttributes(['class' => 'italic'])
                                                    ->visible(fn($record) => $record->screenshots->count() === 0),

                                            ])
                                            ->headerActions([])
                                            ->collapsible(),
                                    ])
                                    ->contained(false),
                            ]),

                        Tabs\Tab::make('Statistics')
                            ->schema([
                                Section::make('Campaign Statistics')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('total_adverts')
                                                    ->label('Total Adverts')
                                                    ->getStateUsing(fn($record) => $record->adverts->count())
                                                    ->badge()
                                                    ->size('lg')
                                                    ->color('primary'),

                                                TextEntry::make('total_screenshots')
                                                    ->label('Total Screenshots')
                                                    ->getStateUsing(fn($record) => $record->adverts->sum(function ($advert) {
                                                        return $advert->screenshots->count();
                                                    }))
                                                    ->badge()
                                                    ->size('lg')
                                                    ->color('success'),

                                                TextEntry::make('total_views')
                                                    ->label('Total Views')
                                                    ->getStateUsing(fn($record) => $record->adverts->sum(function ($advert) {
                                                        return $advert->screenshots->sum('views');
                                                    }))
                                                    ->badge()
                                                    ->size('lg')
                                                    ->color('info'),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('total_rewards_distributed')
                                                    ->label('Total Rewards Distributed')
                                                    ->getStateUsing(fn($record) => $record->adverts->sum(function ($advert) {
                                                        return $advert->screenshots->count() * $advert->reward;
                                                    }))
                                                    ->money('USD')
                                                    ->color('warning')
                                                    ->weight(FontWeight::Bold),

                                                TextEntry::make('remaining_budget')
                                                    ->label('Remaining Budget')
                                                    ->getStateUsing(fn($record) => $record->capital_invested - $record->adverts->sum(function ($advert) {
                                                        return $advert->screenshots->count() * $advert->reward;
                                                    }))
                                                    ->money('USD')
                                                    ->color('success')
                                                    ->weight(FontWeight::Bold),
                                            ]),

                                        TextEntry::make('campaign_status')
                                            ->label('Campaign Status')
                                            ->getStateUsing(fn($record) => $record->valid_until < now() ? 'Expired' : 'Active')
                                            ->badge()
                                            ->color(fn($record) => $record->valid_until < now() ? 'danger' : 'success')
                                            ->size('lg'),
                                    ])
                                    ->collapsible(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\Action::make('duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->action(function () {
                    $record = $this->record;
                    $newCampaign = $record->replicate();
                    $newCampaign->name = $record->name . ' (Copy)';
                    $newCampaign->valid_until = now()->addDays(30);
                    $newCampaign->save();

                    \Filament\Notifications\Notification::make()
                        ->title('Campaign Duplicated Successfully')
                        ->success()
                        ->send();

                    return redirect()->route('filament.admin.resources.campaigns.view', ['record' => $newCampaign]);
                }),
        ];
    }
}
