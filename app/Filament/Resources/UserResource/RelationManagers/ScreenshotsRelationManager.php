<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ScreenshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'screenshots';

    public function table(Table $table): Table
    {
        return \App\Filament\Resources\ScreenshotsResource::table($table)->headerActions([])->actions([])->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
