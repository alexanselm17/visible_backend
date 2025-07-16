<?php

namespace App\Filament\Resources\AdvertImagesResource\RelationManagers;

use App\Models\Screenshots;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ScreenshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'screenshots';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('screenshot')
                    ->label('Screenshot')
                    ->image()
                    ->required()
                    ->directory('screenshots')
                    ->visibility('public')
                    ->maxSize(5120) // 5MB
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                    ->imageEditor()
                    ->columnSpan(2),

                Select::make('processed_by')
                    ->relationship('user', 'name')
                    ->label('Processed By')
                    ->searchable()
                    ->preload()
                    ->placeholder('Select user'),

                TextInput::make('views')
                    ->label('Views')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->placeholder('Number of views'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('screenshot')
            ->columns([
                ImageColumn::make('screenshot')
                    ->size(60)
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder.png')),

                TextColumn::make('user.name')
                    ->label('Processed By')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('views')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('processed_by')
                    ->relationship('user', 'name')
                    ->label('Processed By')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('high_views')
                    ->query(fn(Builder $query): Builder => $query->where('views', '>=', 100))
                    ->label('High Views (≥100)'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No screenshots yet')
            ->emptyStateDescription('Upload screenshots for this advertisement to track engagement.')
            ->emptyStateIcon('heroicon-o-camera');
    }
}
