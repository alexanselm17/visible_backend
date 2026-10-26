<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class CountyResource extends CatalogResource
{
    protected static ?string $model = \App\Models\Counties::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Counties';

    protected static string $area = 'settings';

    protected static bool $editable = true;

    protected static array $fields = [['name', 'County', 'unique'], ['capital', 'Capital', 'string'], ['code', 'Code', 'unique']];

    protected static array $exportColumns = ['name', 'capital', 'code'];

    public static function getPages(): array
    {
        return ['index' => CountyResource\Pages\ManageCountys::route('/')];
    }
}
