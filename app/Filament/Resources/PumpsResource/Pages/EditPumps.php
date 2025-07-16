<?php

namespace App\Filament\Resources\PumpsResource\Pages;

use App\Filament\Resources\PumpsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPumps extends EditRecord
{
    protected static string $resource = PumpsResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
