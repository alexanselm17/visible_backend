<?php

namespace App\Filament\Resources\PetrolStationResource\Pages;

use App\Filament\Resources\PetrolStationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPetrolStation extends EditRecord
{
    protected static string $resource = PetrolStationResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
