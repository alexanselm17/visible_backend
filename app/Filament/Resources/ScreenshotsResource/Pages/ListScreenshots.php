<?php

namespace App\Filament\Resources\ScreenshotsResource\Pages;

use App\Filament\Resources\ScreenshotsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScreenshots extends ListRecords
{
    use \App\Filament\Support\NotifiesWorkflowErrors;
    use \App\Filament\Support\RefreshesWorkflowRecords;

    protected static string $resource = ScreenshotsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
