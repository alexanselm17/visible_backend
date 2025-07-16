<?php

namespace App\Filament\Resources\ScreenshotsResource\Pages;

use App\Filament\Resources\ScreenshotsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScreenshots extends EditRecord
{
    protected static string $resource = ScreenshotsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
