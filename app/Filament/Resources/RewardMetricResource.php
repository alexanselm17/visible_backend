<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class RewardMetricResource extends CatalogResource
{
    protected static ?string $model = \App\Models\RewardMetric::class;

    protected static ?string $navigationIcon = 'heroicon-o-variable';

    protected static ?string $navigationGroup = 'Performance earnings';

    protected static ?string $navigationLabel = 'Metric registry';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['name', 'Metric', 'string'], ['code', 'Code', 'string'], ['evaluator_key', 'Evaluator', 'string'], ['is_active', 'Active', 'bool']];

    protected static array $exportColumns = ['name', 'code', 'evaluator_key', 'is_active'];

    public static function getPages(): array
    {
        return ['index' => RewardMetricResource\Pages\ManageRewardMetrics::route('/')];
    }
}
