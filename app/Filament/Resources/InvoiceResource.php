<?php

namespace App\Filament\Resources;

class InvoiceResource extends \App\Filament\Support\CatalogResource
{
    protected static ?string $model = \App\Models\Invoice::class;

    protected static ?string $navigationGroup = 'Legacy records';

    protected static ?string $navigationLabel = 'Advert payments (legacy)';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static string $area = 'finance';

    protected static array $fields = [['type', 'Type', 'string'], ['amount', 'Amount', 'number'], ['processedByUser.fullname', 'Member', 'string'], ['advert_id', 'Advert', 'string'], ['customer_balance', 'Historical balance', 'number'], ['created_at', 'Recorded', 'date']];

    protected static array $exportColumns = ['type', 'amount', 'processedByUser.fullname', 'advert_id', 'customer_balance', 'created_at'];

    public static function getPages(): array
    {
        return ['index' => InvoiceResource\Pages\ManageInvoices::route('/')];
    }
}
