<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Http\Controllers\ProductController;
use App\Models\Screenshots;
use App\Http\Controllers\ProductsController;
use App\Repositories\Products\ProductRepositoryInterface;
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
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

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
                Forms\Components\Section::make('Screenshot Upload & Compare')
                    ->description('Upload a screenshot and it will be automatically compared with existing products')
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
                                    ->columnSpanFull()
                                    ->helperText('Upload a screenshot to automatically compare with products and advertisements'),

                                Forms\Components\Select::make('advert_id')
                                    ->label('Related Advertisement')
                                    ->relationship('advert', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select advertisement (will be auto-detected if not selected)')
                                    ->helperText('Leave blank for automatic detection during comparison'),

                                Forms\Components\Hidden::make('processed_by')
                                    ->default(fn() => $this->ownerRecord->id),
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
                    ->placeholder('Auto-detected')
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

                TextColumn::make('comparison_score')
                    ->label('Match Score')
                    ->badge()
                    ->color(fn($state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 90 => 'success',
                        $state >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn($state) => $state ? $state . '%' : 'N/A')
                    ->sortable()
                    ->toggleable(),

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

                Tables\Filters\Filter::make('high_match')
                    ->label('High Match Score (>80%)')
                    ->query(fn(Builder $query): Builder => $query->where('comparison_score', '>', 80))
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
                    ->label('Upload & Compare Screenshot')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        // The selected user in the relation manager
                        $data['processed_by'] = $this->ownerRecord->id;
                        return $data;
                    })
                    ->using(function (array $data): Screenshots {
                        try {
                            $advert_id = $data['advert_id'] ?? null; // productId in Dart
                            $user_id   = $this->ownerRecord->id;     // selected user id

                            // Create request object
                            $request = new Request();
                            $request->merge([
                                'user_id' => $user_id,
                            ]);

                            // Convert stored file path from FileUpload into UploadedFile
                            if (!empty($data['screenshot'])) {
                                // File path in public storage
                                $filePath = storage_path('app/public/' . $data['screenshot']);

                                if (file_exists($filePath)) {
                                    $uploadedFile = new UploadedFile(
                                        $filePath,
                                        basename($filePath),
                                        mime_content_type($filePath),
                                        null,
                                        true // test mode so Laravel won't try to move it
                                    );

                                    // Attach file to request
                                    $request->files->set('screenshot', $uploadedFile);
                                } else {
                                    throw new \Exception('Screenshot file not found at ' . $filePath);
                                }
                            }

                            // Call your controller method
                            $controller = new ProductController(app(ProductRepositoryInterface::class));
                            $response   = $controller->uploadScreenShotPlusCompare($request, $advert_id);

                            // Handle JSON response from controller
                            if ($response instanceof \Illuminate\Http\JsonResponse) {
                                $responseData = $response->getData(true);

                                if (!empty($responseData['success'])) {
                                    Notification::make()
                                        ->title('Screenshot uploaded and compared successfully!')
                                        ->success()
                                        ->body($responseData['message'] ?? 'Screenshot processed successfully.')
                                        ->send();

                                    return $responseData['screenshot'] ?? Screenshots::latest()->first();
                                } else {
                                    throw new \Exception($responseData['message'] ?? 'Upload failed');
                                }
                            }

                            // Fallback success
                            Notification::make()
                                ->title('Screenshot uploaded successfully!')
                                ->success()
                                ->send();

                            return $response instanceof Screenshots ? $response : Screenshots::latest()->first();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Upload Error')
                                ->danger()
                                ->body('Failed to upload and compare screenshot: ' . $e->getMessage())
                                ->send();

                            throw $e;
                        }
                    })
                    ->successNotificationTitle('Screenshot uploaded and comparison completed!')
                    ->after(function () {
                        $this->dispatch('refresh');
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
                                                ->placeholder('Auto-detected or not linked'),

                                            TextEntry::make('views')
                                                ->label('Total Views')
                                                ->badge()
                                                ->color(fn(int $state): string => match (true) {
                                                    $state === 0 => 'gray',
                                                    $state < 10 => 'warning',
                                                    $state < 50 => 'success',
                                                    default => 'primary',
                                                }),

                                            TextEntry::make('comparison_score')
                                                ->label('Comparison Match Score')
                                                ->badge()
                                                ->color(fn($state): string => match (true) {
                                                    $state === null => 'gray',
                                                    $state >= 90 => 'success',
                                                    $state >= 70 => 'warning',
                                                    default => 'danger',
                                                })
                                                ->formatStateUsing(fn($state) => $state ? $state . '%' : 'Not compared'),

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

                    Tables\Actions\Action::make('recompare')
                        ->label('Re-compare')
                        ->icon('heroicon-m-arrow-path')
                        ->color('info')
                        ->action(function (Screenshots $record) {
                            try {
                                // Create a request with the existing screenshot
                                $request = new Request();
                                $request->merge([
                                    'screenshot_id' => $record->id,
                                    'processed_by' => $this->ownerRecord->id,
                                ]);

                                $controller = new ProductController($this->productRepository);
                                $response = $controller->uploadScreenShotPlusCompare($request, $record->advert_id);

                                Notification::make()
                                    ->title('Screenshot re-compared successfully!')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Re-comparison failed')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        })
                        ->requiresConfirmation()
                        ->modalDescription('This will re-run the comparison algorithm on this screenshot.'),

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

                            Notification::make()
                                ->title('Views updated')
                                ->success()
                                ->body('Views incremented for ' . $records->count() . ' screenshots')
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalDescription('This will increase the view count by 1 for all selected screenshots.'),

                    Tables\Actions\BulkAction::make('recompareBulk')
                        ->label('Re-compare Selected')
                        ->icon('heroicon-m-arrow-path')
                        ->color('info')
                        ->action(function ($records) {
                            $successCount = 0;
                            $failCount = 0;

                            $records->each(function ($record) use (&$successCount, &$failCount) {
                                try {
                                    $request = new Request();
                                    $request->merge([
                                        'screenshot_id' => $record->id,
                                        'processed_by' => $this->ownerRecord->id,
                                    ]);

                                    $controller = new ProductController($this->productRepository);
                                    $controller->uploadScreenShotPlusCompare($request, $record->advert_id);
                                    $successCount++;
                                } catch (\Exception $e) {
                                    $failCount++;
                                }
                            });

                            $message = "Re-compared {$successCount} screenshots successfully";
                            if ($failCount > 0) {
                                $message .= ", {$failCount} failed";
                            }

                            Notification::make()
                                ->title('Bulk re-comparison completed')
                                ->success()
                                ->body($message)
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalDescription('This will re-run the comparison algorithm on all selected screenshots.'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
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
