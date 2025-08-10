<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Screenshots;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Carbon\Carbon;


class ScreenshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'screenshots';

    protected static ?string $title = 'Recent Screenshots';

    protected static ?string $modelLabel = 'Screenshot';

    protected static ?string $pluralModelLabel = 'Screenshots';

    protected static ?string $icon = 'heroicon-o-camera';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Screenshot Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\FileUpload::make('screenshot')
                                    ->label('Screenshot Image')
                                    ->image()
                                    ->directory('screenshots')
                                    ->visibility('public')
                                    ->imageEditor()
                                    ->imageEditorAspectRatios([
                                        '16:9',
                                        '4:3',
                                        '1:1',
                                    ])
                                    ->required()
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('advert_id')
                                    ->label('Related Advertisement')
                                    ->relationship('advert', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select advertisement'),

                                Forms\Components\TextInput::make('views')
                                    ->label('Views Count')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->placeholder('Number of views'),

                                Forms\Components\DateTimePicker::make('timestamp')
                                    ->label('Screenshot Timestamp')
                                    ->default(now())
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('screenshot')
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->circular()
                    ->size(60)
                    ->getStateUsing(function ($record) {
                        $path = $record->screenshot;

                        return $path
                            ? asset('storage/' . $path)
                            : asset('storage/products/default-product.png');
                    }),

                TextColumn::make('advert.name')
                    ->label('Advertisement')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No advertisement')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                BadgeColumn::make('views')
                    ->label('Views')
                    ->color(fn(int $state): string => match (true) {
                        $state === 0 => 'gray',
                        $state < 10 => 'warning',
                        $state < 50 => 'success',
                        default => 'primary',
                    })
                    ->sortable(),



                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Modified')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('advert_id')
                    ->label('Advertisement')
                    ->relationship('advert', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\Filter::make('views')
                    ->label('Popular Screenshots')
                    ->query(fn(Builder $query): Builder => $query->where('views', '>', 10))
                    ->toggle(),

                Tables\Filters\Filter::make('recent')
                    ->label('Recent (Last 7 Days)')
                    ->query(fn(Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7)))
                    ->toggle(),

                Tables\Filters\Filter::make('timestamp')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('timestamp', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('timestamp', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Upload Screenshot')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['processed_by'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->infolist([
                            Section::make('Screenshot Details')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            ImageEntry::make('screenshot')
                                                ->label('Screenshot')
                                                ->disk('public')
                                                ->height(200)
                                                ->columnSpanFull(),

                                            TextEntry::make('advert.name')
                                                ->label('Related Advertisement')
                                                ->placeholder('No advertisement linked'),

                                            TextEntry::make('views')
                                                ->label('Total Views')
                                                ->badge()
                                                ->color(fn(int $state): string => match (true) {
                                                    $state === 0 => 'gray',
                                                    $state < 10 => 'warning',
                                                    $state < 50 => 'success',
                                                    default => 'primary',
                                                }),

                                            TextEntry::make('timestamp')
                                                ->label('Screenshot Timestamp')
                                                ->dateTime('F j, Y \a\t g:i:s A'),

                                            TextEntry::make('created_at')
                                                ->label('Uploaded At')
                                                ->dateTime('F j, Y \a\t g:i:s A'),

                                            TextEntry::make('updated_at')
                                                ->label('Last Modified')
                                                ->dateTime('F j, Y \a\t g:i:s A'),
                                        ]),
                                ]),
                        ]),

                    EditAction::make()
                        ->mutateFormDataUsing(function (array $data): array {
                            $data['processed_by'] = $this->ownerRecord->id;
                            return $data;
                        }),

                    DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('incrementViews')
                        ->label('Increment Views')
                        ->icon('heroicon-m-eye')
                        ->color('info')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->increment('views');
                            });

                            $this->dispatch('notify', [
                                'type' => 'success',
                                'message' => 'Views incremented for selected screenshots'
                            ]);
                        })
                        ->requiresConfirmation()
                        ->modalDescription('This will increase the view count by 1 for all selected screenshots.'),
                ]),
            ])
            ->defaultSort('timestamp', 'desc')
            ->paginated([10, 25, 50])
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function canEdit($record): bool
    {
        return true;
    }

    protected function canDelete($record): bool
    {
        return true;
    }

    protected function canDeleteAny(): bool
    {
        return true;
    }
}
