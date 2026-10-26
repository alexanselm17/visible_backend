<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Support\OperationsResource;
use App\Models\Counties;
use App\Models\SubCounty;
use App\Models\User;
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

    protected static ?string $navigationLabel = 'Members & staff';

    protected static string $area = 'users';

    protected static bool $editable = true;

    protected static array $exportColumns = ['id', 'fullname', 'username', 'email', 'phone', 'role.name', 'is_active', 'phone_verified_at', 'created_at'];

    public static function canCreate(): bool
    {
        return AdminAccess::allows('settings');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && (AdminAccess::allows('settings') || ! $record->hasRole(['admin', 'dev', 'manager']));
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::allows('settings') && $record->id !== auth()->id();
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
            F\Section::make('Member details')->columns(2)->schema([
                F\TextInput::make('fullname')->maxLength(255)->required(),
                F\TextInput::make('username')->required()->unique(ignoreRecord: true)->maxLength(255),
                F\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                F\TextInput::make('phone')->tel()->required()->unique(ignoreRecord: true)->helperText('Use international format, e.g. +254712345678.'),
                F\TextInput::make('occupation')->required()->maxLength(255),
                F\Select::make('gender')->options(['Male' => 'Male', 'Female' => 'Female'])->required(),
                F\Select::make('county_id')->label('County')->options(fn () => Counties::pluck('name', 'id'))->searchable()->live()
                    ->afterStateUpdated(fn (\Filament\Forms\Set $set) => $set('subcounty_id', null)),
                F\Select::make('subcounty_id')->label('Subcounty')->options(fn (Get $get) => SubCounty::where('county_id', $get('county_id'))->pluck('name', 'id'))->searchable(),
                F\TextInput::make('referal_code')->label('Referred by code')->maxLength(255),
                F\TextInput::make('my_code')->label('Personal identifier')->disabled()->dehydrated(false)->helperText('Used to bind personalized images and referrals.'),
                F\DateTimePicker::make('phone_verified_at')->disabled()->dehydrated(false)->helperText('Set by successful phone OTP verification.'),
            ]),
            F\Section::make('Account access')->columns(2)->schema([
                F\TextInput::make('password')->password()->revealable()->minLength(8)
                    ->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))
                    ->helperText('Leave blank to keep the current password.'),
                F\Toggle::make('is_active')->label('Account active')->default(true)->helperText('Deactivation also revokes API sessions.'),
                F\Select::make('role_id')->relationship('role', 'name')->searchable()->preload()
                    ->visible(fn () => AdminAccess::allows('settings'))->dehydrated(fn () => AdminAccess::allows('settings')),
                F\Select::make('permissions')->relationship('permissions', 'name')->multiple()->searchable()->preload()
                    ->visible(fn () => AdminAccess::allows('settings'))->dehydrated(fn () => AdminAccess::allows('settings')),
            ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            I\Section::make('Member profile')->columns(3)->schema([
                I\TextEntry::make('fullname'), I\TextEntry::make('username'), I\TextEntry::make('role.name')->badge(),
                I\TextEntry::make('phone')->copyable(), I\TextEntry::make('email')->copyable(),
                I\TextEntry::make('phone_verified_at')->dateTime()->placeholder('Not verified'),
                I\TextEntry::make('county.name')->label('County'), I\TextEntry::make('subCounty.name')->label('Subcounty'),
                I\TextEntry::make('occupation'), I\TextEntry::make('gender'),
                I\TextEntry::make('my_code')->label('Identifier')->copyable(),
                I\TextEntry::make('referal_code')->label('Referrer code')->placeholder('None'),
                I\IconEntry::make('is_active')->boolean(), I\TextEntry::make('created_at')->dateTime(),
                I\TextEntry::make('permissions.name')->badge()->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with('role', 'county'))->columns([
            C\TextColumn::make('fullname')->label('Member')->searchable()->sortable()->weight('semibold')->description(fn ($record) => '@'.$record->username),
            C\TextColumn::make('phone')->searchable()->copyable(), C\TextColumn::make('email')->searchable()->toggleable(),
            C\TextColumn::make('role.name')->badge()->placeholder('Member'),
            C\IconColumn::make('is_active')->boolean()->label('Active'),
            C\TextColumn::make('phone_verified_at')->dateTime()->placeholder('Unverified')->label('Phone verified'),
            C\TextColumn::make('county.name')->label('County')->toggleable(),
            C\TextColumn::make('created_at')->date()->sortable(),
        ])->filters([
            T\TrashedFilter::make()->visible(fn () => AdminAccess::allows('settings')),
            T\TernaryFilter::make('is_active')->label('Active account'),
            T\Filter::make('phone_unverified')->label('Awaiting phone verification')->query(fn ($query) => $query->whereNull('phone_verified_at')),
            T\SelectFilter::make('role_id')->relationship('role', 'name')->label('Role'),
        ])->actions([
            A\ViewAction::make(), A\EditAction::make(),
            A\ActionGroup::make([
                A\DeleteAction::make()->label('Archive account')
                    ->before(fn ($record) => $record->tokens()->delete()),
                A\RestoreAction::make(),
                A\Action::make('resend_otp')->label('Resend signup OTP')->requiresConfirmation()
                    ->visible(fn ($record) => ! $record->phone_verified_at && ! $record->trashed())
                    ->modalDescription('Sends an SMS to the member’s saved phone number. Normal resend limits apply.')
                    ->action(function ($record) {
                        app(\App\Services\PhoneOtpService::class)->sendSignupOtp($record);
                        Notification::make()->title('Verification code sent')->success()->send();
                    }),
                A\Action::make('payout_frequency')->label('Set payout frequency')->visible(fn () => AdminAccess::allows('finance'))
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
            ]),
        ])->bulkActions([
            A\BulkAction::make('activate')->label('Activate selected')->requiresConfirmation()
                ->visible(fn () => static::canViewAny())
                ->action(function ($records) {
                    foreach ($records as $record) {
                        abort_unless(static::canEdit($record), 403);
                    }
                    foreach ($records as $record) {
                        $record->update(['is_active' => true]);
                    }
                })->deselectRecordsAfterCompletion(),
            A\BulkAction::make('deactivate')->label('Deactivate selected')->color('danger')->requiresConfirmation()
                ->action(function ($records) {
                    foreach ($records as $record) {
                        abort_unless(static::canEdit($record) && $record->id !== auth()->id(), 403);
                    }
                    foreach ($records as $record) {
                        $record->update(['is_active' => false]);
                        $record->tokens()->delete();
                    }
                })->deselectRecordsAfterCompletion(),
        ]);
    }

    public static function getRelations(): array
    {
        return [UserResource\RelationManagers\ScreenshotsRelationManager::class,
            UserResource\RelationManagers\ReferredUsersRelationManager::class,
            UserResource\RelationManagers\NotificationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUsers::route('/'), 'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'), 'edit' => Pages\EditUser::route('/{record}/edit')];
    }
}
