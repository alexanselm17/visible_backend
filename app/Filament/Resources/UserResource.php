<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Support\OperationsResource;
use App\Filament\Support\UserAccountActions;
use App\Models\Counties;
use App\Models\SubCounty;
use App\Models\User;
use App\Services\AdminUserService;
use App\Services\RewardPeriodService;
use App\Support\AdminAccess;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components as I;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters as T;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends OperationsResource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Members';

    protected static ?string $navigationLabel = 'All users';

    protected static ?int $navigationSort = 1;

    protected static string $area = 'users';

    protected static bool $editable = true;

    protected static array $exportColumns = [
        'id', 'fullname', 'username', 'email', 'phone', 'role.name', 'is_active', 'is_verified',
        'phone_verified_at', 'email_verified_at', 'county.name', 'subCounty.name', 'occupation',
        'gender', 'my_code', 'referal_code', 'created_at', 'updated_at', 'deleted_at',
    ];

    public static function canCreate(): bool
    {
        return AdminAccess::allows('settings');
    }

    public static function canEdit(Model $record): bool
    {
        return ! $record->trashed() && AdminUserService::canManage($record);
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->trashed() && AdminAccess::allows('settings') && $record->id !== auth()->id();
    }

    public static function canRestore(Model $record): bool
    {
        return AdminAccess::allows('settings');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            F\Section::make('User details')->columns(2)->schema([
                F\TextInput::make('fullname')->label('Full name')->maxLength(255)->required(),
                F\TextInput::make('username')->required()->unique(ignoreRecord: true)->maxLength(255),
                F\TextInput::make('email')->email()->required()->unique(ignoreRecord: true)->maxLength(255)
                    ->helperText('Changing the address clears its email verification.'),
                F\TextInput::make('phone')->tel()->required()->unique(ignoreRecord: true)->maxLength(30)
                    ->helperText('Use international format. Changing it clears phone verification, expires old codes and revokes sessions.'),
                F\TextInput::make('occupation')->required()->maxLength(255),
                F\Select::make('gender')->options(['Male' => 'Male', 'Female' => 'Female'])->required(),
                F\Select::make('county_id')->label('County')->options(fn () => Counties::pluck('name', 'id'))->searchable()->live()
                    ->afterStateUpdated(fn (\Filament\Forms\Set $set) => $set('subcounty_id', null)),
                F\Select::make('subcounty_id')->label('Subcounty')->options(fn (Get $get) => SubCounty::where('county_id', $get('county_id'))->pluck('name', 'id'))->searchable(),
                F\TextInput::make('referal_code')->label('Referred by code')->maxLength(255),
                F\TextInput::make('my_code')->label('Personal identifier')->disabled()->dehydrated(false)
                    ->helperText('Generated once. Keeping this stable protects referral and image ownership history.'),
            ]),
            F\Section::make('Account access')->columns(2)->schema([
                F\TextInput::make('password')->password()->revealable()->minLength(8)
                    ->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))
                    ->helperText('Leave blank to keep the current password. A change revokes sessions.'),
                F\Toggle::make('is_active')->label('Account active')->default(true),
                F\Select::make('role_id')->label('Role')->relationship('role', 'name')->searchable()->preload()
                    ->visible(fn () => AdminAccess::allows('settings'))->dehydrated(fn () => AdminAccess::allows('settings')),
                F\Select::make('permissions')->label('Direct permissions')->relationship('permissions', 'name')->multiple()->searchable()->preload()
                    ->saveRelationshipsUsing(fn () => null)
                    ->visible(fn () => AdminAccess::allows('settings'))->dehydrated(fn () => AdminAccess::allows('settings')),
            ]),
            F\Section::make('Verification')->description('Use Manage account to verify an account, check a phone OTP or record a staff ownership check.')
                ->visible(fn ($record) => $record !== null)->columns(3)->schema([
                    F\Toggle::make('is_verified')->label('Account verified')->disabled()->dehydrated(false),
                    F\DateTimePicker::make('phone_verified_at')->label('Phone verified at')->disabled()->dehydrated(false),
                    F\DateTimePicker::make('email_verified_at')->label('Email verified at')->disabled()->dehydrated(false),
                ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            I\Section::make('Account status')->columns(4)->schema([
                I\TextEntry::make('fullname')->label('User')->weight('bold'),
                I\TextEntry::make('role.name')->label('Role')->badge()->placeholder('No role assigned'),
                I\IconEntry::make('is_active')->label('Active')->boolean(),
                I\IconEntry::make('is_verified')->label('Account verified')->boolean(),
                I\TextEntry::make('phone_verified_at')->label('Phone verified')->dateTime()->placeholder('Not verified'),
                I\TextEntry::make('email_verified_at')->label('Email verified')->dateTime()->placeholder('Not verified'),
                I\TextEntry::make('created_at')->label('Joined')->dateTime(),
                I\TextEntry::make('deleted_at')->label('Deleted')->dateTime()->placeholder('Not deleted'),
            ]),
            I\Tabs::make('User workspace')->columnSpanFull()->tabs([
                I\Tabs\Tab::make('Profile')->icon('heroicon-o-user')->schema([
                    I\Section::make('Personal details')->columns(3)->schema([
                        I\TextEntry::make('id')->label('User ID')->copyable(),
                        I\TextEntry::make('username')->copyable(), I\TextEntry::make('email')->copyable(),
                        I\TextEntry::make('phone')->copyable(), I\TextEntry::make('occupation'), I\TextEntry::make('gender'),
                        I\TextEntry::make('county.name')->label('County')->placeholder('Not set'),
                        I\TextEntry::make('subCounty.name')->label('Subcounty')->placeholder('Not set'),
                        I\TextEntry::make('my_code')->label('Personal / image identifier')->copyable(),
                        I\TextEntry::make('referal_code')->label('Referrer code')->placeholder('None'),
                        I\TextEntry::make('referrer.fullname')->label('Referred by')->placeholder('None'),
                        I\TextEntry::make('updated_at')->label('Last updated')->dateTime(),
                    ]),
                ]),
                I\Tabs\Tab::make('Posting & referrals')->icon('heroicon-o-megaphone')->schema([
                    I\Section::make('Performance activity')->columns(3)->schema([
                        I\TextEntry::make('started_adverts')->state(fn (User $record) => $record->screenshots()->where('number', 1)->count())->label('Posting tasks started'),
                        I\TextEntry::make('completed_adverts')->state(fn (User $record) => $record->screenshots()->where('number', 2)->count())->label('Posting tasks completed'),
                        I\TextEntry::make('verified_views')->state(fn (User $record) => $record->screenshots()->where('number', 2)->sum('views'))->numeric(),
                        I\TextEntry::make('referrals')->state(fn (User $record) => $record->referredUsers()->count())->label('Referred users'),
                        I\TextEntry::make('qualified_referrals')->state(fn (User $record) => $record->qualifiedReferrals()->count()),
                        I\TextEntry::make('fraud_flags')->state(fn (User $record) => $record->fraudFlags()->count()),
                    ]),
                ]),
                I\Tabs\Tab::make('Tokens & earnings')->icon('heroicon-o-wallet')->visible(fn () => AdminAccess::allows('finance'))->schema([
                    I\ViewEntry::make('finance')->hiddenLabel()->view('filament.users.finance-summary'),
                ]),
                I\Tabs\Tab::make('Business & campaigns')->icon('heroicon-o-building-storefront')->schema([
                    I\Section::make('Campaign owner profile')->columns(3)->schema([
                        I\TextEntry::make('campaignOwnerProfile.business_name')->label('Business')->placeholder('No business profile'),
                        I\TextEntry::make('campaignOwnerProfile.business_category')->label('Category'),
                        I\TextEntry::make('campaignOwnerProfile.account_status')->label('Business status')->badge(),
                        I\TextEntry::make('campaignOwnerProfile.mpesa_phone')->label('Business payment phone'),
                        I\TextEntry::make('campaignOwnerProfile.website')->label('Website'),
                        I\TextEntry::make('campaign_count')->label('Owned campaigns')->state(fn (User $record) => $record->ownedCampaigns()->count())
                            ->url(fn (User $record) => CampaignResource::getUrl('index', ['tableFilters' => ['owner_id' => ['value' => $record->id]]])),
                        I\TextEntry::make('submission_count')->label('Submitted adverts')->state(fn (User $record) => $record->advertSubmissions()->count())
                            ->url(fn (User $record) => AdvertSubmissionResource::getUrl('index', ['tableFilters' => ['submitted_by' => ['value' => $record->id]]])),
                        I\TextEntry::make('designed_count')->label('Designed adverts')->state(fn (User $record) => $record->designedSubmissions()->count())
                            ->url(fn (User $record) => AdvertSubmissionResource::getUrl('index', ['tableFilters' => ['designed_by' => ['value' => $record->id]]])),
                        I\TextEntry::make('reviewed_count')->label('Reviewed adverts')->state(fn (User $record) => $record->reviewedSubmissions()->count())
                            ->url(fn (User $record) => AdvertSubmissionResource::getUrl('index', ['tableFilters' => ['reviewed_by' => ['value' => $record->id]]])),
                    ]),
                ]),
                I\Tabs\Tab::make('Permissions & sessions')->icon('heroicon-o-shield-check')->schema([
                    I\Section::make('Access')->columns(2)->schema([
                        I\TextEntry::make('role.permissions.name')->label('Role permissions')->badge()->placeholder('None'),
                        I\TextEntry::make('permissions.name')->label('Direct permissions')->badge()->placeholder('None'),
                        I\TextEntry::make('api_session_count')->label('Issued API sessions')->state(fn (User $record) => $record->tokens()->count()),
                        I\IconEntry::make('is_logged_in')->label('Reported login state')->boolean(),
                        I\IconEntry::make('push_ready')->label('Push delivery configured')->state(fn (User $record) => filled($record->fcm_token))->boolean(),
                    ]),
                    I\ViewEntry::make('sessions')->hiddenLabel()->view('filament.users.session-summary'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with('role', 'county', 'subCounty'))->columns([
            C\TextColumn::make('fullname')->label('User')->searchable()->sortable()->weight('semibold')->description(fn ($record) => '@'.$record->username),
            C\TextColumn::make('username')->searchable()->toggleable(isToggledHiddenByDefault: true),
            C\TextColumn::make('id')->label('User ID')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
            C\TextColumn::make('phone')->searchable()->copyable(),
            C\TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
            C\TextColumn::make('role.name')->badge()->placeholder('No role')->sortable(),
            C\TextColumn::make('account_status')->state(fn (User $record) => $record->trashed() ? 'Deleted' : ($record->is_active ? 'Active' : 'Inactive'))
                ->badge()->color(fn ($state) => match ($state) {
                    'Active' => 'success', 'Deleted' => 'danger', default => 'gray'
                }),
            C\IconColumn::make('is_verified')->label('Account verified')->boolean(),
            C\IconColumn::make('phone_verified')->label('Phone verified')->state(fn (User $record) => (bool) $record->phone_verified_at)->boolean(),
            C\IconColumn::make('email_verified')->label('Email verified')->state(fn (User $record) => (bool) $record->email_verified_at)->boolean()->toggleable(),
            C\TextColumn::make('county.name')->label('County')->toggleable(isToggledHiddenByDefault: true),
            C\TextColumn::make('my_code')->label('Identifier')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
            C\TextColumn::make('referal_code')->label('Referrer code')->searchable()->toggleable(isToggledHiddenByDefault: true),
            C\TextColumn::make('created_at')->label('Joined')->date()->sortable(),
            C\TextColumn::make('updated_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            T\SelectFilter::make('role_id')->relationship('role', 'name')->label('Role')->searchable()->preload(),
            T\TernaryFilter::make('is_active')->label('Account status')->trueLabel('Active')->falseLabel('Inactive'),
            T\TernaryFilter::make('is_verified')->label('Account verification')->trueLabel('Verified')->falseLabel('Unverified'),
            T\TernaryFilter::make('phone_verified')->label('Phone verification')->queries(
                true: fn (Builder $query) => $query->whereNotNull('phone_verified_at'),
                false: fn (Builder $query) => $query->whereNull('phone_verified_at'),
                blank: fn (Builder $query) => $query,
            ),
            T\TernaryFilter::make('email_verified')->label('Email verification')->queries(
                true: fn (Builder $query) => $query->whereNotNull('email_verified_at'),
                false: fn (Builder $query) => $query->whereNull('email_verified_at'),
                blank: fn (Builder $query) => $query,
            ),
            T\SelectFilter::make('account_scope')->label('Account visibility')
                ->options(['current' => 'Current users', 'all' => 'Include deleted users', 'deleted' => 'Deleted users only'])->default('current')
                ->visible(fn () => AdminAccess::allows('settings'))
                ->query(function (Builder $query, array $data, $livewire) {
                    if (! AdminAccess::allows('settings')) {
                        return $query->whereNull('users.deleted_at');
                    }
                    $scope = $livewire instanceof Pages\ListUsers && $livewire->activeTab === 'deleted'
                        ? 'deleted' : ($data['value'] ?? 'current');

                    return match ($scope) {
                        'all' => $query,
                        'deleted' => $query->whereNotNull('users.deleted_at'),
                        default => $query->whereNull('users.deleted_at'),
                    };
                }),
            T\Filter::make('no_role')->label('No assigned role')->query(fn ($query) => $query->whereNull('role_id')),
            T\SelectFilter::make('county_id')->relationship('county', 'name')->label('County')->searchable(),
            T\Filter::make('flagged')->label('Has fraud flags')->query(fn ($query) => $query->whereHas('fraudFlags')),
        ], layout: FiltersLayout::AboveContent)->filtersFormColumns(3)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->paginated([15, 30, 60, 100, 'all'])
            ->actions([
                A\ViewAction::make()->label('Profile'), A\EditAction::make(),
                A\ActionGroup::make(UserAccountActions::make())->label('Manage account')->button(),
                A\ActionGroup::make([
                    A\Action::make('payout_frequency')->label('Set payout frequency')->visible(fn ($record) => ! $record->trashed() && AdminAccess::allows('finance'))
                        ->form([F\Select::make('frequency')->options(['weekly' => 'Weekly', 'monthly' => 'Monthly'])->required()])
                        ->action(function ($record, array $data) {
                            app(RewardPeriodService::class)->setPayoutFrequency($record->id, $data['frequency']);
                            Notification::make()->title('Payout preference saved for the next eligible period')->success()->send();
                        }),
                    A\Action::make('earnings')->label('Earnings & multipliers')->visible(fn () => AdminAccess::allows('finance'))
                        ->url(fn ($record) => RewardPeriodResource::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $record->id]]])),
                    A\Action::make('report')->label('Download posting report')->form([
                        F\DatePicker::make('from_date')->required()->default(now()->subMonth()),
                        F\DatePicker::make('to_date')->required()->default(now())->afterOrEqual('from_date'),
                    ])->action(fn ($record, array $data) => redirect()->route('user.report', ['user_id' => $record->id] + $data)),
                ])->label('Activity'),
            ])->bulkActions([
                static::bulkAction('activate', 'Activate selected'),
                static::bulkAction('deactivate', 'Deactivate selected', reason: true),
                static::bulkAction('verify', 'Verify selected accounts', sensitive: true, reason: true),
                static::bulkAction('unverify', 'Remove account verification', sensitive: true, reason: true),
                static::bulkAction('delete', 'Delete selected (recoverable)', sensitive: true, reason: true),
                static::bulkAction('restore', 'Restore selected as inactive', sensitive: true),
                A\BulkAction::make('assign_role')->label('Assign / remove role')->visible(fn () => AdminAccess::allows('settings'))
                    ->requiresConfirmation()->form([
                        F\Select::make('role_id')->options(fn () => \App\Models\RolesModel::pluck('name', 'id'))->searchable()->placeholder('No role')
                            ->helperText('Leave empty to remove the assigned role.'),
                    ])->action(function ($records, array $data, $livewire) {
                        app(AdminUserService::class)->bulk($records, 'role', roleId: $data['role_id'] ?? null);
                        $livewire->flushCachedTableRecords();
                        Notification::make()->title('Roles updated')->success()->send();
                    })->deselectRecordsAfterCompletion(),
            ]);
    }

    private static function bulkAction(string $name, string $label, bool $sensitive = false, bool $reason = false): A\BulkAction
    {
        return A\BulkAction::make($name)->label($label)->requiresConfirmation()
            ->color(in_array($name, ['delete', 'deactivate', 'unverify']) ? 'danger' : 'primary')
            ->visible(fn () => $sensitive ? AdminAccess::allows('settings') : static::canViewAny())
            ->form($reason ? [F\Textarea::make('reason')->required()->maxLength(1000)] : [])
            ->action(function ($records, array $data, $livewire) use ($name) {
                app(AdminUserService::class)->bulk($records, $name, $data['reason'] ?? null);
                $livewire->flushCachedTableRecords();
                Notification::make()->title('Selected accounts updated')->success()->send();
            })->deselectRecordsAfterCompletion();
    }

    public static function getRelations(): array
    {
        return [
            UserResource\RelationManagers\ScreenshotsRelationManager::class,
            UserResource\RelationManagers\ReferredUsersRelationManager::class,
            UserResource\RelationManagers\NotificationsRelationManager::class,
            UserResource\RelationManagers\PhoneVerificationOtpsRelationManager::class,
            UserResource\RelationManagers\ApiSessionsRelationManager::class,
            UserResource\RelationManagers\FraudFlagsRelationManager::class,
            UserResource\RelationManagers\AccountEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'), 'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'), 'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
