<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Services\AdminUserService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApiSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    protected static ?string $title = 'API login sessions';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('App / token label'),
            TextColumn::make('created_at')->dateTime()->sortable(),
            TextColumn::make('last_used_at')->dateTime()->placeholder('Not used'),
            TextColumn::make('expires_at')->dateTime()->placeholder('App expiry rules apply'),
        ])->defaultSort('created_at', 'desc')->actions([
            Action::make('revoke')->color('danger')->requiresConfirmation()
                ->visible(fn () => ! $this->getOwnerRecord()->trashed() && AdminUserService::canManage($this->getOwnerRecord()))
                ->action(function ($record) {
                    app(AdminUserService::class)->revokeApiToken($this->getOwnerRecord(), (string) $record->id);
                    $this->flushCachedTableRecords();
                    Notification::make()->title('API session revoked')->success()->send();
                }),
        ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
