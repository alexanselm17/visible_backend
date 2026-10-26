<?php

namespace App\Filament\Resources\CampaignResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class AdvertsRelationManager extends RelationManager
{
    protected static string $relationship = 'adverts';

    public function table(Table $table): Table
    {
        return \App\Filament\Resources\AdvertImagesResource::table($table)
            ->headerActions([])->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
