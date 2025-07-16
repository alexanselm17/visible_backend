<?php

namespace App\Filament\Resources\AdvertImagesResource\Pages;

use App\Filament\Resources\AdvertImagesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdvertImages extends EditRecord
{
    protected static string $resource = AdvertImagesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
