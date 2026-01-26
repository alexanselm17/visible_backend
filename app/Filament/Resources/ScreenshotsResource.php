<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScreenshotsResource\Pages;
use App\Models\Screenshots;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScreenshotsResource extends Resource
{
    protected static ?string $model = Screenshots::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationGroup = 'Campaign Management';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Screenshot Details')
                    ->schema([
                        Select::make('advert_id')
                            ->relationship('advert', 'name', function ($query) {
                                return $query->with('campaign');
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->campaign->name})")
                            ->helperText('Select the advertisement this screenshot belongs to')
                            ->columnSpan(2),

                        Select::make('processed_by')
                            ->relationship('user', 'fullname')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(auth()->id())
                            ->helperText('User who processed this screenshot'),

                        TextInput::make('views')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Number of views for this screenshot'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Screenshot Image')
                    ->schema([
                        FileUpload::make('screenshot')
                            ->label('Screenshot')
                            ->image()
                            ->required()
                            ->directory('screenshots')
                            ->visibility('public')
                            ->maxSize(10240) // 10MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                '1:1',
                                '9:16',
                            ])
                            ->helperText('Upload screenshot image (max 10MB)')
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
                        $path = $record->screenshot;

                        return $path
                            ? asset('storage/'.$path)
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
                                fn ($record) => view('filament.components.image-preview', [
                                    'url' => $record->screenshot
                                        ? asset('storage/'.$record->screenshot)
                                        : asset('storage/products/default-product.png'),
                                ])
                            )
                    ),

                TextColumn::make('advert.name')
                    ->label('Advertisement')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->limit(25)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 25 ? $state : null;
                    }),

                TextColumn::make('advert.campaign.name')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->limit(20),

                TextColumn::make('advert.category')
                    ->label('Category')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('user.fullname')
                    ->label('Processed By')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('views')
                    ->numeric()
                    ->sortable()
                    ->alignment('center')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 1000 => 'success',
                        $state >= 100 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('advert.reward')
                    ->label('Reward')
                    ->money('USD')
                    ->sortable()
                    ->alignment('right')
                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($state) => $state->format('M d, Y H:i:s')),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('advert_id')
                    ->relationship('advert', 'name')
                    ->label('Advertisement')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('processed_by')
                    ->relationship('user', 'fullname')
                    ->label('Processed By')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('campaign')
                    ->relationship('advert.campaign', 'name')
                    ->label('Campaign')
                    ->searchable()
                    ->preload(),

                Filter::make('high_views')
                    ->query(fn (Builder $query): Builder => $query->where('views', '>=', 100))
                    ->label('High Views (≥100)'),

                Filter::make('recent')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7)))
                    ->label('Recent (7 days)')
                    ->default(),

                Filter::make('no_views')
                    ->query(fn (Builder $query): Builder => $query->where('views', 0))
                    ->label('No Views'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Action::make('view_full_image')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => asset($record->screenshot))
                    ->openUrlInNewTab(),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('60s');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScreenshots::route('/'),
            'create' => Pages\CreateScreenshots::route('/create'),
            // 'view' => Pages\ViewScreenshots::route('/{record}'),
            'edit' => Pages\EditScreenshots::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::count();

        return match (true) {
            $count > 100 => 'success',
            $count > 50 => 'warning',
            default => 'primary',
        };
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['advert', 'user']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['advert.name', 'advert.campaign.name', 'user.fullname'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Advertisement' => $record->advert->name,
            'Campaign' => $record->advert->campaign->name,
            'Views' => number_format($record->views),
            'Processed By' => $record->user->fullname,
        ];
    }
}
