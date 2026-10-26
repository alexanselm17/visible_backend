<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class NotificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'notifications';

    public function table(Table $table): Table
    {
        return \App\Filament\Resources\NotificationResource::table($table)->headerActions([])->actions([])->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
