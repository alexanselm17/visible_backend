<?php

namespace App\Filament\Resources\DrumResource\Pages;

use App\Filament\Resources\DrumResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDrums extends ListRecords
{
    protected static string $resource = DrumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
