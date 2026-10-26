<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class FraudFlagsRelationManager extends RelationManager
{
    protected static string $relationship = 'fraudFlags';

    protected static ?string $title = 'Fraud flags';

    public function table(Table $table): Table
    {
        return \App\Filament\Resources\UserFraudResource::table($table)
            ->headerActions([])->actions([])->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
