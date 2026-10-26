<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ReferredUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'referredUsers';

    public function table(Table $table): Table
    {
        return \App\Filament\Resources\UserResource::table($table)->headerActions([])->actions([])->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
