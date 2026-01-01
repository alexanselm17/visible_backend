<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdvertImagesResource\Pages;
use App\Filament\Resources\AdvertImagesResource\RelationManagers;
use App\Models\AdvertImages;
use App\Models\Campaign;
use App\Models\Counties;
use App\Models\SubCounty;
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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Carbon\Carbon;


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
                                DatePicker::make('valid_until')
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

                        Forms\Components\Fieldset::make('Investment & Rewards')
                            ->schema([
                                TextInput::make('capital_invested')
                                    ->required()
                                    ->numeric()
                                    ->prefix('KSH')
                                    ->minValue(0)
                                    ->placeholder('0.00')
                                    ->helperText('Amount invested in this advertisement'),

                                TextInput::make('capacity')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->placeholder('100')
                                    ->helperText('Maximum number of participants'),

                                TextInput::make('reward')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->placeholder('100')
                                    ->helperText('Reward per participant (KSH)'),
                            ])
                            ->columns(3),

                        // Combined Date + Time fields
                        Forms\Components\Fieldset::make('Valid Until')
                            ->schema([
                                DatePicker::make('valid_until_date')
                                    ->label('Valid Until Date')
                                    ->required()
                                    ->minDate(now())
                                    ->reactive()
                                    ->afterStateHydrated(function (Get $get, Set $set) {
                                        self::combineValidUntil($get, $set);
                                    })
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::combineValidUntil($get, $set);
                                    }),

                                TimePicker::make('valid_until_time')
                                    ->label('Valid Until Time')
                                    ->withoutSeconds()
                                    ->required()
                                    ->reactive()
                                    ->afterStateHydrated(function (Get $get, Set $set) {
                                        self::combineValidUntil($get, $set);
                                    })
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::combineValidUntil($get, $set);
                                    }),

                            ])
                            ->columns(2),

                        // Hidden field that stores the final datetime
                        TextInput::make('valid_until')
                            ->hidden()
                            ->required(),
                    ])
                    ->columns(2),

                // Target Audience
                Forms\Components\Section::make('Target Audience')
                    ->schema([
                        Repeater::make('target_audience')
                            ->label('Target Locations')
                            ->schema([
                                Select::make('county_id')
                                    ->label('County')
                                    ->options(Counties::all()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('sub_county_id', null);
                                    })
                                    ->placeholder('Select County'),

                                Select::make('sub_county_id')
                                    ->label('Sub County')
                                    ->options(
                                        fn(Get $get): array =>
                                        $get('county_id')
                                            ? SubCounty::where('county_id', $get('county_id'))->pluck('name', 'id')->toArray()
                                            : []
                                    )
                                    ->searchable()
                                    ->placeholder('Select Sub County')
                                    ->disabled(fn(Get $get): bool => !$get('county_id')),

                                Select::make('gender')
                                    ->label('Gender')
                                    ->options([
                                        'male' => 'Male',
                                        'female' => 'Female',
                                    ])
                                    ->placeholder('Select Gender'),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add Location')
                            ->helperText('Add target locations for this advertisement')
                            ->columnSpan(2),
                    ])
                    ->columns(1),

                // Content & Media
                Forms\Components\Section::make('Content & Media')
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Advertisement Image')
                            ->image()
                            ->required()
                            ->directory('advertisements')
                            ->visibility('public')
                            ->maxSize(5120)
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
                            ->maxSize(20480)
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
                ImageColumn::make('image')
                    ->label('Image')
                    ->size(60)
                    ->getStateUsing(function ($record) {
                        $path = $record->image_path;

                        return $path
                            ? asset('storage/' . $path)
                            : asset('storage/products/default-product.png');
                    })
                    ->action(
                        Action::make('preview')
                            ->label('')
                            ->modalHeading('Preview Image')
                            ->modalWidth('sm')
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalContent(
                                fn($record) =>
                                view('filament.components.image-preview', [
                                    'url' => $record->image_path
                                        ? asset('storage/' . $record->image_path)
                                        : asset('storage/products/default-product.png'),
                                ])
                            )
                    ),
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

                TextColumn::make('capital_invested')
                    ->money('KSH')
                    ->sortable()
                    ->alignment('right')
                    ->color('success'),

                TextColumn::make('capacity')
                    ->sortable()
                    ->alignment('center')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('valid_until')
                    ->date()
                    ->sortable()
                    ->color(fn($state) => now()->gt($state) ? 'danger' : 'success')
                    ->badge(),

                TextColumn::make('target_audience')
                    ->label('Locations')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'N/A';

                        $locations = is_string($state) ? json_decode($state, true) : $state;
                        if (!is_array($locations)) return 'N/A';

                        return collect($locations)->map(function ($location) {
                            $county = Counties::find($location['county_id'])?->name ?? 'Unknown';
                            $subCounty = SubCounty::find($location['sub_county_id'])?->name ?? 'Unknown';
                            return "{$county} - {$subCounty}";
                        })->join(', ');
                    })
                    ->limit(50),
                // ->tooltip(function ($state) {
                //     if (!$state) return null;

                //     $locations = is_string($state) ? json_decode($state, true) : $state;
                //     if (!is_array($locations)) return null;

                //     return collect($locations)->map(function ($location) {
                //         $county = Counties::find($location['county_id'])?->name ?? 'Unknown';
                //         $subCounty = SubCounty::find($location['sub_county_id'])?->name ?? 'Unknown';
                //         return "{$county} - {$subCounty}";
                //     })->join("\n");
                // }),

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

                SelectFilter::make('county')
                    ->options(Counties::all()->pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn(Builder $query, $countyId): Builder => $query->whereJsonContains('target_audience', [
                                ['county_id' => $countyId]
                            ])
                        );
                    })
                    ->searchable(),

                Tables\Filters\Filter::make('expired')
                    ->query(fn(Builder $query): Builder => $query->where('valid_until', '<', now()))
                    ->label('Expired Ads'),

                Tables\Filters\Filter::make('high_capacity')
                    ->query(fn(Builder $query): Builder => $query->where('capacity', '>=', 100))
                    ->label('High Capacity (≥100)'),

                Tables\Filters\Filter::make('high_investment')
                    ->query(fn(Builder $query): Builder => $query->where('capital_invested', '>=', 10000))
                    ->label('High Investment (≥10K)'),
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

    protected static function combineValidUntil(Get $get, Set $set): void
    {
        $date = $get('valid_until_date');
        $time = $get('valid_until_time');
        $existing = $get('valid_until');

        if ($date && $time) {
            if (strlen($time) === 5) {
                $time .= ':00'; // Add seconds if missing
            }
            $set('valid_until', Carbon::parse("$date $time")->format('Y-m-d H:i:s'));
        } elseif ($existing) {
            $set('valid_until', Carbon::parse($existing)->format('Y-m-d H:i:s'));
        } else {
            $set('valid_until', null); // Prevent ''
        }
    }
}
