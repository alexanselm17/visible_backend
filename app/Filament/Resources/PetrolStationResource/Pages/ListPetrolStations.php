<?php

namespace App\Filament\Resources\PetrolStationResource\Pages;

use App\Filament\Resources\PetrolStationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPetrolStations extends ListRecords
{
    protected static string $resource = PetrolStationResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
