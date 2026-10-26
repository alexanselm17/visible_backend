<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class SysMetaResource extends CatalogResource
{
    protected static ?string $model = \App\Models\SysMeta::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Reference data';

    protected static string $area = 'settings';

    protected static bool $editable = true;

    protected static array $fields = [['meta_index', 'Index', 'string'], ['meta_key', 'Key', 'string'], ['meta_value', 'Value', 'text'], ['meta_shortcode', 'Short code', 'string']];

    protected static array $exportColumns = ['meta_index', 'meta_key', 'meta_value', 'meta_shortcode'];

    public static function getPages(): array
    {
        return ['index' => SysMetaResource\Pages\ManageSysMetas::route('/')];
    }
}
