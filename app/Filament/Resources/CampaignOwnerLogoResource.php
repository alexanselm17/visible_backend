<?php

namespace App\Filament\Resources;

use App\Filament\Support\OperationsResource;
use App\Models\CampaignOwnerLogo;
use App\Support\AdminWorkflow;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CampaignOwnerLogoResource extends OperationsResource
{
    protected static ?string $model = CampaignOwnerLogo::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = 'Members';

    protected static ?string $navigationLabel = 'Business logos';

    protected static string $area = 'campaigns';

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with('profile'))->columns([
            C\ImageColumn::make('logo_path')->disk('admin_media')->size(64),
            C\TextColumn::make('profile.business_name')->label('Business')->searchable(),
            C\IconColumn::make('is_primary')->boolean(), C\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([SelectFilter::make('profile_id')->relationship('profile', 'business_name')->searchable()])
            ->actions([
                A\Action::make('primary')->label('Make primary')->visible(fn ($record) => ! $record->is_primary)->requiresConfirmation()
                    ->action(fn ($record) => AdminWorkflow::run(\App\Http\Controllers\Api\CampaignOwner\CampaignOwnerProfileController::class, 'makeLogoPrimary', parameters: ['profileId' => $record->profile_id, 'logoId' => $record->id])),
                A\Action::make('remove')->color('danger')->requiresConfirmation()
                    ->action(fn ($record) => AdminWorkflow::run(\App\Http\Controllers\Api\CampaignOwner\CampaignOwnerProfileController::class, 'deleteLogo', parameters: ['profileId' => $record->profile_id, 'logoId' => $record->id])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => CampaignOwnerLogoResource\Pages\ManageCampaignOwnerLogos::route('/')];
    }
}
