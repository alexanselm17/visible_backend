<?php

namespace App\Filament\Resources\BankingResource\Pages;

use App\Filament\Resources\BankingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBanking extends EditRecord
{
    protected static string $resource = BankingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
