<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class PermissionResource extends CatalogResource
{
    protected static ?string $model = \App\Models\Permission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Permissions';

    protected static string $area = 'settings';

    protected static bool $editable = true;

    protected static array $fields = [['name', 'Name', 'string'], ['slug', 'Permission key', 'unique'], ['category', 'Category', 'string']];

    protected static array $exportColumns = ['name', 'slug', 'category'];

    public static function getPages(): array
    {
        return ['index' => PermissionResource\Pages\ManagePermissions::route('/')];
    }
}
