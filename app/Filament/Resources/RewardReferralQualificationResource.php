<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class RewardReferralQualificationResource extends CatalogResource
{
    protected static ?string $model = \App\Models\RewardReferralQualification::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Performance earnings';

    protected static ?string $navigationLabel = 'Qualified referrals';

    protected static string $area = 'finance';

    protected static bool $editable = false;

    protected static array $fields = [['referrer.fullname', 'Referrer', 'string'], ['referred.fullname', 'Referred member', 'string'], ['qualifying_advert_id', 'First completed advert', 'string'], ['qualified_at', 'Qualified', 'date'], ['evidence', 'Evidence', 'json']];

    protected static array $exportColumns = ['referrer.fullname', 'referred.fullname', 'qualifying_advert_id', 'qualified_at', 'evidence'];

    public static function getPages(): array
    {
        return ['index' => RewardReferralQualificationResource\Pages\ManageRewardReferralQualifications::route('/')];
    }
}
