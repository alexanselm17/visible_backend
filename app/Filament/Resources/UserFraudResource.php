<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class UserFraudResource extends CatalogResource
{
    protected static ?string $model = \App\Models\UserFraud::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Trust & verification';

    protected static ?string $navigationLabel = 'Member fraud flags';

    protected static string $area = 'fraud';

    protected static bool $editable = false;

    protected static array $fields = [['user.fullname', 'Member', 'string'], ['reason', 'Reason', 'string'], ['details', 'Details', 'string'], ['flagged_at', 'Flagged', 'date'], ['reporter.fullname', 'Reported by', 'string']];

    protected static array $exportColumns = ['user.fullname', 'reason', 'details', 'flagged_at', 'reporter.fullname'];

    public static function getPages(): array
    {
        return ['index' => UserFraudResource\Pages\ManageUserFrauds::route('/')];
    }
}
