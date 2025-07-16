<?php

namespace App\Filament\Resources\StationsResource\Pages;

use App\Filament\Resources\StationsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStations extends EditRecord
{
    protected static string $resource = StationsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
