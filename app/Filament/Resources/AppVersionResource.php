<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class AppVersionResource extends CatalogResource
{
    protected static ?string $model = \App\Models\AppVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'App versions';

    protected static string $area = 'settings';

    protected static bool $editable = true;

    protected static array $fields = [['versions', 'Required app version', 'string']];

    protected static array $exportColumns = ['versions'];

    public static function getPages(): array
    {
        return ['index' => AppVersionResource\Pages\ManageAppVersions::route('/')];
    }
}
