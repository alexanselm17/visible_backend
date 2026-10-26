<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    use \App\Filament\Support\NotifiesWorkflowErrors;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\ActionGroup::make(\App\Filament\Support\UserAccountActions::make(\Filament\Actions\Action::class, $this->record))->label('Manage account')->button(),
        ];
    }
}
