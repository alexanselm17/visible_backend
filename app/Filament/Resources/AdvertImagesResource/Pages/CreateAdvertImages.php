<?php

namespace App\Filament\Resources\AdvertImagesResource\Pages;

use App\Filament\Resources\AdvertImagesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdvertImages extends CreateRecord
{
    protected static string $resource = AdvertImagesResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['capital_invested'] = 0;
        $data['selling_price'] = 0;

        return $data;
    }
}
