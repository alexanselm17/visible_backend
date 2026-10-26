<?php

namespace App\Filament\Support;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

abstract class ManageOperations extends ManageRecords
{
    use NotifiesWorkflowErrors;
    use RefreshesWorkflowRecords;

    public function getTitle(): string
    {
        return static::getResource()::getNavigationLabel();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(fn () => static::getResource()::canCreate())];
    }
}
