<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        do {
            $data['my_code'] = (string) random_int(1000000000, 9999999999);
        } while (\App\Models\User::where('my_code', $data['my_code'])->exists());

        return $data;
    }
}
