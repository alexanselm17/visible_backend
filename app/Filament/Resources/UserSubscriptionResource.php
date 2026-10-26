<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class UserSubscriptionResource extends CatalogResource
{
    protected static ?string $model = \App\Models\UserSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Legacy records';

    protected static ?string $navigationLabel = 'Subscriptions (legacy)';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['user.fullname', 'Owner', 'string'], ['plan.name', 'Plan', 'string'], ['status', 'Status', 'status'], ['starts_at', 'Starts', 'date'], ['ends_at', 'Ends', 'date'], ['auto_renew', 'Auto renew', 'bool']];

    protected static array $exportColumns = ['user.fullname', 'plan.name', 'status', 'starts_at', 'ends_at', 'auto_renew'];

    public static function getPages(): array
    {
        return ['index' => UserSubscriptionResource\Pages\ManageUserSubscriptions::route('/')];
    }
}
