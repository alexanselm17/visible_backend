<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Filament\Resources\CampaignResource\RelationManagers;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DateTimePicker;

use Carbon\Carbon;
use Filament\Forms\Components\TimePicker;

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

                        TextInput::make('capital_invested')
                            ->required()
                            ->numeric()
                            ->prefix('Ksh')
                            ->placeholder('0.00')
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText('Total capital invested in this campaign')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $reward = $get('reward');
                                if ($reward > 0) {
                                    $set('capacity', floor($state / $reward));
                                }
                            }),

                        TextInput::make('reward')
                            ->required()
                            ->numeric()
                            ->prefix('Ksh')
                            ->placeholder('0.00')
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText('Reward amount per engagement')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $capital = $get('capital_invested');
                                if ($state > 0) {
                                    $set('capacity', floor($capital / $state));
                                }
                            }),

                        // DatePicker::make('valid_until_date')
                        //     ->label('Expiry Date')
                        //     ->required()
                        //     ->native(false),

                        // TimePicker::make('valid_until_time')
                        //     ->label('Expiry Time')
                        //     ->required()
                        //     ->seconds(false),


                        TextInput::make('capacity')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true) // ✅ This forces Filament to include this field in the submitted form data
                            ->placeholder('Auto-calculated')
                            ->helperText('Auto-calculated: capital ÷ reward'),

                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->description(fn($record) => "Capital: Ksh " . number_format($record->capital_invested, 2)),

                TextColumn::make('capital_invested')
                    ->money('Ksh')
                    ->sortable()
                    ->alignment('right')
                    ->color('success'),

                TextColumn::make('reward')
                    ->money('Ksh')
                    ->sortable()
                    ->alignment('right')
                    ->color('primary'),

                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable()
                    ->alignment('center')
                    ->badge()
                    ->color('info'),

                TextColumn::make('adverts_count')
                    ->counts('adverts')
                    ->label('Adverts')
                    ->alignment('center')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('screenshots_count')
                    ->label('Screenshots')
                    ->getStateUsing(fn($record) => $record->adverts->sum(function ($advert) {
                        return $advert->screenshots->count();
                    }))
                    ->alignment('center')
                    ->badge()
                    ->color('primary'),

                // TextColumn::make('valid_until')
                //     ->date()
                //     ->sortable()
                //     ->badge()
                //     ->color(fn($record) => $record->valid_until < now() ? 'danger' : 'success')
                //     ->formatStateUsing(fn($state) => Carbon::parse($state)->format('M d, Y')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filter::make('active')
                //     ->query(fn(Builder $query): Builder => $query->where('valid_until', '>=', now()))
                //     ->label('Active Campaigns')
                //     ->default(),

                // Filter::make('expired')
                //     ->query(fn(Builder $query): Builder => $query->where('valid_until', '<', now()))
                //     ->label('Expired Campaigns'),

                Filter::make('high_reward')
                    ->query(fn(Builder $query): Builder => $query->where('reward', '>=', 10))
                    ->label('High Reward (≥$10)'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->icon('heroicon-o-eye')
                    ->tooltip('View campaign details'),
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->tooltip('Edit campaign'),

                // Action::make('extend')
                //     ->icon('heroicon-o-calendar-days')
                //     ->color('warning')
                //     ->tooltip('Extend campaign')
                //     ->visible(fn($record) => $record->valid_until < now()->addDays(7))
                //     ->form([
                //         DatePicker::make('new_valid_until')
                //             ->required()
                //             ->minDate(now())
                //             ->default(now()->addDays(30))
                //             ->label('New Expiration Date'),
                //     ])
                //     ->action(function ($record, array $data) {
                //         $record->update(['valid_until' => $data['new_valid_until']]);

                //         Notification::make()
                //             ->title('Campaign Extended')
                //             ->success()
                //             ->send();
                //     }),

                // Action::make('duplicate')
                //     ->icon('heroicon-o-document-duplicate')
                //     ->color('info')
                //     ->tooltip('Duplicate campaign')
                //     ->action(function ($record) {
                //         $newCampaign = $record->replicate();
                //         $newCampaign->name = $record->name . ' (Copy)';
                //         $newCampaign->valid_until = now()->addDays(30);
                //         $newCampaign->save();

                //         Notification::make()
                //             ->title('Campaign Duplicated')
                //             ->success()
                //             ->send();
                //     }),
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
