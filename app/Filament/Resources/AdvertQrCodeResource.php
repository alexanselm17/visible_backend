<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class AdvertQrCodeResource extends CatalogResource
{
    protected static ?string $model = \App\Models\AdvertQrCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'Trust & verification';

    protected static ?string $navigationLabel = 'Image ownership tracking';

    protected static string $area = 'campaigns';

    protected static bool $editable = false;

    protected static array $fields = [['user.fullname', 'Member', 'string'], ['advert.name', 'Advert', 'string'], ['generated_at', 'Generated', 'date'], ['last_verified_at', 'Last verified', 'date']];

    protected static array $exportColumns = ['user.fullname', 'advert.name', 'generated_at', 'last_verified_at'];

    public static function getPages(): array
    {
        return ['index' => AdvertQrCodeResource\Pages\ManageAdvertQrCodes::route('/')];
    }
}
