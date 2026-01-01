<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\TextInput;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Campaign Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Campaign Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter campaign name')
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Campaign Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable(),

                TextColumn::make('adverts_count')
                    ->label('Total Adverts')
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'success' : 'gray')
                    ->counts('adverts')
                    ->sortable(),

                TextColumn::make('active_adverts_count')
                    ->label('Active Adverts')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function ($record) {
                        return $record->adverts()->where('valid_until', '>', now())->count() ?? 0;
                    }),

                TextColumn::make('total_screenshots')
                    ->label('Screenshots')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(fn($record) => $record->total_screenshots ?? 0),

                TextColumn::make('total_views')
                    ->label('Total Views')
                    ->badge()
                    ->color('purple')
                    ->getStateUsing(fn($record) => number_format($record->total_views ?? 0)),

                TextColumn::make('total_rewards_distributed')
                    ->label('Rewards Paid')
                    ->money('KSH')
                    ->color('success')
                    ->getStateUsing(fn($record) => $record->total_rewards_distributed ?? 0),

                BadgeColumn::make('is_active')
                    ->label('Status')
                    ->getStateUsing(fn($record) => $record->is_active ? 'Active' : 'Expired')
                    ->colors([
                        'success' => 'Active',
                        'danger' => 'Expired',
                    ])
                    ->icons([
                        'heroicon-m-check-circle' => 'Active',
                        'heroicon-m-x-circle' => 'Expired',
                    ]),

            ])
            ->actions([
                Tables\Actions\Action::make('campaignReport')
                    ->label('Generate Report')
                    ->icon('heroicon-o-chart-bar')
                    ->color('warning')
                    ->size('sm')
                    ->button()
                    ->extraAttributes([
                        'class' => 'bg-gradient-to-r from-amber-400 to-orange-500 hover:from-amber-500 hover:to-orange-600 text-white font-semibold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200',
                    ])
                    ->action(function ($record) {
                        return redirect()->route('campaign_report', [
                            'campaign_id' => $record->id,
                        ]);
                    }),

                Tables\Actions\ViewAction::make()
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->size('sm')
                    ->button()
                    ->extraAttributes([
                        'class' => 'bg-gradient-to-r from-blue-400 to-blue-600 hover:from-blue-500 hover:to-blue-700 text-white font-medium shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200',
                    ]),

                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('success')
                    ->size('sm')
                    ->button()
                    ->extraAttributes([
                        'class' => 'bg-gradient-to-r from-emerald-400 to-emerald-600 hover:from-emerald-500 hover:to-emerald-700 text-white font-medium shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200',
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->recordUrl(fn($record) => Pages\ViewCampaign::getUrl([$record->id]));
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\AdvertsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'view' => Pages\ViewCampaign::route('/{record}'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
