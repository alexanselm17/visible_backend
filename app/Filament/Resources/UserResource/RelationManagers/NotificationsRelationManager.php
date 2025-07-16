<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NotificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'notifications';

    protected static ?string $title = 'Notifications';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->rows(3),

                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options([
                        'info' => 'Info',
                        'success' => 'Success',
                        'warning' => 'Warning',
                        'error' => 'Error',
                    ])
                    ->default('info')
                    ->required(),

                Forms\Components\Toggle::make('is_read')
                    ->label('Mark as Read')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\IconColumn::make('is_read')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-o-envelope')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn(bool $state): string => $state ? 'Read' : 'Unread'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->weight(fn($record) => $record->is_read ? 'normal' : 'bold'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'info',
                        'success' => 'success',
                        'warning' => 'warning',
                        'danger' => 'error',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_read')
                    ->label('Status')
                    ->options([
                        false => 'Unread',
                        true => 'Read',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'info' => 'Info',
                        'success' => 'Success',
                        'warning' => 'Warning',
                        'error' => 'Error',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Send Notification')
                    ->color('primary')
                    ->icon('heroicon-m-bell'),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_as_read')
                    ->label('Mark as Read')
                    ->icon('heroicon-m-envelope-open')
                    ->color('success')
                    ->action(fn($record) => $record->update(['is_read' => true]))
                    ->visible(fn($record) => !$record->is_read),

                Tables\Actions\Action::make('mark_as_unread')
                    ->label('Mark as Unread')
                    ->icon('heroicon-m-envelope')
                    ->color('warning')
                    ->action(fn($record) => $record->update(['is_read' => false]))
                    ->visible(fn($record) => $record->is_read),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_as_read')
                        ->label('Mark as Read')
                        ->icon('heroicon-m-envelope-open')
                        ->color('success')
                        ->action(fn($records) => $records->each->update(['is_read' => true])),

                    Tables\Actions\BulkAction::make('mark_as_unread')
                        ->label('Mark as Unread')
                        ->icon('heroicon-m-envelope')
                        ->color('warning')
                        ->action(fn($records) => $records->each->update(['is_read' => false])),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No notifications')
            ->emptyStateDescription('This user has no notifications.')
            ->emptyStateIcon('heroicon-o-bell');
    }
}
