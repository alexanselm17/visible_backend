<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdvertImagesResource\Pages;
use App\Filament\Resources\AdvertImagesResource\RelationManagers;
use App\Models\AdvertImages;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;

class AdvertImagesResource extends Resource
{
    protected static ?string $model = AdvertImages::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Campaign Management';

    protected static ?string $navigationLabel = 'Advertisements';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Advertisement Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter advertisement name')
                            ->columnSpan(2),

                        Select::make('campaign_id')
                            ->relationship('campaign', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('capital_invested')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$'),
                                TextInput::make('reward')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$'),
                                Forms\Components\DatePicker::make('valid_until')
                                    ->required()
                                    ->minDate(now()),
                                TextInput::make('capacity')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1),
                            ])
                            ->helperText('Select the campaign this advertisement belongs to'),

                        Select::make('category')
                            ->required()
                            ->options([
                                'electronics' => 'Electronics',
                                'fashion' => 'Fashion',
                                'food' => 'Food & Beverage',
                                'automotive' => 'Automotive',
                                'health' => 'Health & Beauty',
                                'home' => 'Home & Garden',
                                'sports' => 'Sports & Recreation',
                                'entertainment' => 'Entertainment',
                                'education' => 'Education',
                                'services' => 'Services',
                                'other' => 'Other',
                            ])
                            ->searchable()
                            ->placeholder('Select category'),


                    ])
                    ->columns(2),

                Forms\Components\Section::make('Content & Media')
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Advertisement Image')
                            ->image()
                            ->required()
                            ->directory('advertisements')
                            ->visibility('public')
                            ->maxSize(5120) // 5MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->helperText('Upload advertisement image (max 5MB)')
                            ->columnSpan(2),


                        FileUpload::make('video_path')
                            ->label('Advertisement Video (optional)')
                            ->directory('advertisements')
                            ->visibility('public')
                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                            ->maxSize(20480) // 20MB
                            ->helperText('Upload a promotional video (optional, max 20MB)')
                            ->columnSpan(1),

                        Textarea::make('description')
                            ->required()
                            ->rows(4)
                            ->maxLength(1000)
                            ->placeholder('Enter advertisement description...')
                            ->columnSpan(2),

                        TagsInput::make('badge')
                            ->placeholder('Add tags (press Enter after each tag)')
                            ->suggestions([
                                'featured',
                                'limited-time',
                                'bestseller',
                                'new-arrival',
                                'sale',
                                'premium',
                                'exclusive',
                                'trending',
                            ])
                            ->helperText('Add relevant tags for this advertisement')
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->size(60)
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder.png')),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('campaign.name')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->limit(20),

                TextColumn::make('category')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('selling_price')
                    ->money('USD')
                    ->sortable()
                    ->alignment('right')
                    ->color('success'),

                TextColumn::make('reward')
                    ->money('USD')
                    ->sortable()
                    ->alignment('right')
                    ->color('warning'),

                TextColumn::make('screenshots_count')
                    ->counts('screenshots')
                    ->label('Screenshots')
                    ->alignment('center')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('invoices_count')
                    ->counts('invoices')
                    ->label('Invoices')
                    ->alignment('center')
                    ->badge()
                    ->color('success'),

                TextColumn::make('badge')
                    ->badge()
                    ->separator(',')
                    ->color('indigo')
                    ->limit(3),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('campaign_id')
                    ->relationship('campaign', 'name')
                    ->label('Campaign')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('category')
                    ->options([
                        'electronics' => 'Electronics',
                        'fashion' => 'Fashion',
                        'food' => 'Food & Beverage',
                        'automotive' => 'Automotive',
                        'health' => 'Health & Beauty',
                        'home' => 'Home & Garden',
                        'sports' => 'Sports & Recreation',
                        'entertainment' => 'Entertainment',
                        'education' => 'Education',
                        'services' => 'Services',
                        'other' => 'Other',
                    ])
                    ->searchable(),

                Tables\Filters\Filter::make('high_reward')
                    ->query(fn(Builder $query): Builder => $query->where('reward', '>=', 5))
                    ->label('High Reward (≥$5)'),

                Tables\Filters\Filter::make('premium_price')
                    ->query(fn(Builder $query): Builder => $query->where('selling_price', '>=', 100))
                    ->label('Premium Price (≥$100)'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Action::make('view_screenshots')
                    ->icon('heroicon-o-camera')
                    ->color('info')
                    ->url(fn($record) => route('filament.admin.resources.screenshots.index', [
                        'tableFilters' => [
                            'advert_id' => ['value' => $record->id],
                        ],
                    ]))
                    ->visible(fn($record) => $record->screenshots_count > 0),

                Action::make('boost_reward')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('success')
                    ->form([
                        TextInput::make('boost_amount')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0.01)
                            ->label('Boost Amount'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'reward' => $record->reward + $data['boost_amount']
                        ]);

                        Notification::make()
                            ->title('Reward Boosted')
                            ->body("Reward increased by ${$data['boost_amount']}")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('update_category')
                        ->label('Update Category')
                        ->icon('heroicon-o-tag')
                        ->color('info')
                        ->form([
                            Select::make('category')
                                ->required()
                                ->options([
                                    'electronics' => 'Electronics',
                                    'fashion' => 'Fashion',
                                    'food' => 'Food & Beverage',
                                    'automotive' => 'Automotive',
                                    'health' => 'Health & Beauty',
                                    'home' => 'Home & Garden',
                                    'sports' => 'Sports & Recreation',
                                    'entertainment' => 'Entertainment',
                                    'education' => 'Education',
                                    'services' => 'Services',
                                    'other' => 'Other',
                                ]),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update(['category' => $data['category']]);
                            });

                            Notification::make()
                                ->title('Categories Updated')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ScreenshotsRelationManager::class,
            RelationManagers\InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdvertImages::route('/'),
            'create' => Pages\CreateAdvertImages::route('/create'),
            // 'view' => Pages\ViewAdvertImages::route('/{record}'),
            'edit' => Pages\EditAdvertImages::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
