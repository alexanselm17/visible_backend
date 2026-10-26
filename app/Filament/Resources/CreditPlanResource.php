<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class CreditPlanResource extends CatalogResource
{
    protected static ?string $model = \App\Models\CreditPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Legacy records';

    protected static ?string $navigationLabel = 'Credit packages (legacy)';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['name', 'Name', 'string'], ['price', 'Price', 'number'], ['base_credits', 'Base credits', 'number'], ['bonus_credits', 'Bonus credits', 'number'], ['promoters_reach', 'Reach', 'number'], ['is_active', 'Active', 'bool']];

    protected static array $exportColumns = ['name', 'price', 'base_credits', 'bonus_credits', 'promoters_reach', 'is_active'];

    public static function getPages(): array
    {
        return ['index' => CreditPlanResource\Pages\ManageCreditPlans::route('/')];
    }
}
