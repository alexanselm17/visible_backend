<?php

namespace App\Filament\Resources;

use App\Filament\Support\OperationsResource;
use App\Models\CampaignOwnerProfile;
use App\Support\AdminWorkflow;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CampaignOwnerProfileResource extends OperationsResource
{
    protected static ?string $model = CampaignOwnerProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Members';

    protected static ?string $navigationLabel = 'Campaign owners';

    protected static string $area = 'campaigns';

    protected static bool $editable = true;

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            F\Section::make('Business profile')->columns(2)->schema([
                \App\Filament\Support\Fields::user()->required()->unique(ignoreRecord: true)
                    ->disabled(fn ($record) => $record !== null)->dehydrated(fn ($record) => $record === null),
                F\TextInput::make('business_name')->required()->maxLength(255),
                F\TextInput::make('business_category')->maxLength(255),
                F\TextInput::make('mpesa_phone')->tel()->required()->maxLength(255),
                F\TextInput::make('website')->url()->maxLength(255),
                F\Select::make('account_status')->options(['PENDING' => 'Pending', 'ACTIVE' => 'Active', 'SUSPENDED' => 'Suspended'])->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with('user'))->columns([
            C\ImageColumn::make('logo_path')->disk('admin_media')->label('Logo')->size(48),
            C\TextColumn::make('business_name')->searchable()->weight('semibold'),
            C\TextColumn::make('user.fullname')->label('Owner')->searchable(),
            C\TextColumn::make('business_category')->searchable(), C\TextColumn::make('mpesa_phone')->copyable(),
            \App\Filament\Support\Fields::status('account_status'),
        ])->filters([SelectFilter::make('account_status')->options(['PENDING' => 'Pending', 'ACTIVE' => 'Active', 'SUSPENDED' => 'Suspended'])])
            ->actions([
                A\EditAction::make(),
                A\Action::make('upload_logo')->label('Add logo')->form([
                    F\FileUpload::make('logo')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(4096)->storeFiles(false)->required(),
                    F\Toggle::make('set_primary')->default(true),
                ])->action(fn ($record, array $data) => AdminWorkflow::run(\App\Http\Controllers\Api\CampaignOwner\CampaignOwnerProfileController::class, 'uploadLogo', ['set_primary' => $data['set_primary']], ['profileId' => $record->id], ['logo' => $data['logo']])),
                A\Action::make('logos')->url(fn ($record) => CampaignOwnerLogoResource::getUrl('index', ['tableFilters' => ['profile_id' => ['value' => $record->id]]])),
                A\Action::make('wallet')->visible(fn () => \App\Support\AdminAccess::allows('finance'))
                    ->url(fn ($record) => TokenWalletResource::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $record->user_id]]])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => CampaignOwnerProfileResource\Pages\ManageCampaignOwnerProfiles::route('/')];
    }
}
