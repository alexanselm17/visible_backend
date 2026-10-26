<?php

namespace App\Filament\Resources;

class BankingResource extends \App\Filament\Support\CatalogResource
{
    protected static ?string $model = \App\Models\Banking::class;

    protected static ?string $navigationGroup = 'Legacy records';

    protected static ?string $navigationLabel = 'Banking history (legacy)';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static string $area = 'finance';

    protected static array $fields = [['reference', 'Reference', 'string'], ['amount', 'Amount', 'number'], ['name', 'Name', 'string'], ['processedBy.fullname', 'Member', 'string'], ['approval_status', 'Approved', 'bool'], ['approvedBy.fullname', 'Approved by', 'string'], ['created_at', 'Recorded', 'date']];

    protected static array $exportColumns = ['reference', 'amount', 'name', 'processedBy.fullname', 'approval_status', 'approvedBy.fullname', 'created_at'];

    public static function getPages(): array
    {
        return ['index' => BankingResource\Pages\ManageBankings::route('/')];
    }
}
