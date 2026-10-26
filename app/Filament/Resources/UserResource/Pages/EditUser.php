<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! \App\Support\AdminAccess::allows('settings')) {
            unset($data['role_id'], $data['permissions']);
        }
        if (($data['phone'] ?? null) !== $this->record->phone) {
            $data['phone_verified_at'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->record->is_active) {
            $this->record->tokens()->delete();
        }
    }
}
