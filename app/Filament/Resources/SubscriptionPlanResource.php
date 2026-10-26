<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class SubscriptionPlanResource extends CatalogResource
{
    protected static ?string $model = \App\Models\SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Legacy records';

    protected static ?string $navigationLabel = 'Subscription plans (legacy)';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['name', 'Name', 'string'], ['code', 'Code', 'string'], ['billing_period', 'Billing period', 'string'], ['price', 'Price', 'number'], ['limits', 'Limits', 'json'], ['features', 'Features', 'json']];

    protected static array $exportColumns = ['name', 'code', 'billing_period', 'price', 'limits', 'features'];

    public static function getPages(): array
    {
        return ['index' => SubscriptionPlanResource\Pages\ManageSubscriptionPlans::route('/')];
    }
}
