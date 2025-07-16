<?php

namespace App\Filament\Resources\ScreenshotsResource\Pages;

use App\Filament\Resources\ScreenshotsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScreenshots extends ListRecords
{
    protected static string $resource = ScreenshotsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
