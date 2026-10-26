<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    use \App\Filament\Support\NotifiesWorkflowErrors;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(\App\Services\AdminUserService::class)->update($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ViewAction::make(),
            \Filament\Actions\ActionGroup::make(\App\Filament\Support\UserAccountActions::make(\Filament\Actions\Action::class, $this->record))->label('Manage account')->button(),
        ];
    }
}
