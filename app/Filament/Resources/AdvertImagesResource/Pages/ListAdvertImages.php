<?php

namespace App\Filament\Resources\AdvertImagesResource\Pages;

use App\Filament\Resources\AdvertImagesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdvertImages extends ListRecords
{
    protected static string $resource = AdvertImagesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
