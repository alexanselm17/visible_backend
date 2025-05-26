<?php

namespace App\Filament\Resources\BankingResource\Pages;

use App\Filament\Resources\BankingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankings extends ListRecords
{
  protected static string $resource = BankingResource::class;

  protected function getHeaderActions(): array
  {
    return []; // This removes the create button
  }
}
