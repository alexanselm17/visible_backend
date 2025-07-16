<?php

namespace App\Filament\Resources\PumpsResource\Pages;

use App\Filament\Resources\PumpsResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPumps extends ListRecords
{
    protected static string $resource = PumpsResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
