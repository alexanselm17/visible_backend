<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Http\Controllers\AuthController;
use App\Models\User;
use App\Repositories\Auth\AuthRepositoryInterface;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('fullname')
                                    ->label('Full Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Enter full name'),

                                TextInput::make('username')
                                    ->label('Username')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->placeholder('Enter username'),

                                TextInput::make('email')
                                    ->label('Email Address')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->placeholder('Enter email address'),

                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->maxLength(255)
                                    ->placeholder('Enter phone number'),

                                TextInput::make('national_id')
                                    ->label('National ID')
                                    ->maxLength(255)
                                    ->placeholder('Enter national ID'),

                                TextInput::make('card_number')
                                    ->label('Card Number')
                                    ->maxLength(255)
                                    ->placeholder('Enter card number'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('gender')
                                    ->label('Gender')
                                    ->options([
                                        'male' => 'Male',
                                        'female' => 'Female',
                                        'other' => 'Other',
                                    ])
                                    ->placeholder('Select gender'),

                                TextInput::make('occupation')
                                    ->label('Occupation')
                                    ->maxLength(255)
                                    ->placeholder('Enter occupation'),

                                TextInput::make('location')
                                    ->label('Location')
                                    ->maxLength(255)
                                    ->placeholder('Enter location'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('town')
                                    ->label('Town')
                                    ->maxLength(255)
                                    ->placeholder('Enter town'),

                                TextInput::make('estate')
                                    ->label('Estate')
                                    ->maxLength(255)
                                    ->placeholder('Enter estate'),

                                TextInput::make('county')
                                    ->label('County')
                                    ->maxLength(255)
                                    ->placeholder('Enter county'),
                            ]),
                    ])
                    ->columns(2),

                Section::make('Account Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->required(fn (string $context): bool => $context === 'create')
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->minLength(8)
                                    ->placeholder('Enter password'),

                                Select::make('role_id')
                                    ->label('Role')
                                    ->relationship('role', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->placeholder('Select role'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Whether the user account is active'),

                                Toggle::make('is_verified')
                                    ->label('Verified')
                                    ->default(false)
                                    ->helperText('Whether the user email is verified'),

                                Toggle::make('is_logged_in')
                                    ->label('Currently Logged In')
                                    ->default(false)
                                    ->helperText('Whether the user is currently logged in'),
                            ]),
                    ])
                    ->columns(2),

                Section::make('Permissions')
                    ->schema([
                        Select::make('permissions')
                            ->label('Additional Permissions')
                            ->multiple()
                            ->relationship('permissions', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Select additional permissions for this user (beyond role permissions)'),
                    ])
                    ->collapsible(),

                Section::make('Technical Information')
                    ->schema([
                        TextInput::make('fcm_token')
                            ->label('FCM Token')
                            ->maxLength(255)
                            ->placeholder('Firebase Cloud Messaging token'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fullname')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->icon('heroicon-m-phone'),

                TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Admin' => 'danger',
                        'Developer' => 'purple',
                        'Manager' => 'warning',
                        'Cashier' => 'info',
                        'Customer Champion' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('location')
                    ->label('Location')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([

            SelectFilter::make('is_logged_in')
                    ->label('Login Status')
                    ->options([
                        true => 'Online',
                        false => 'Offline',
                    ]),

            SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                        'other' => 'Other',
                    ]),
        ])
            ->actions([
            Tables\Actions\Action::make('viewReport')
                    ->label('View Report')
                    ->icon('heroicon-m-chart-bar')
                    ->color('info')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')
                            ->label('From Date')
                            ->required()
                            ->default(now()->subMonth()),

                        Forms\Components\DatePicker::make('to_date')
                            ->label('To Date')
                            ->required()
                            ->default(now()),
                    ])
                    ->action(function (array $data, $record) {
                        return redirect()->route('user.report', [
                            'processed_by' => $record->id,
                            'from_date' => $data['from_date'],
                            'to_date' => $data['to_date'],
                        ]);
                    })
                    ->modalHeading('Generate User Report')
                    ->modalSubmitActionLabel('Generate Report'),

            Tables\Actions\Action::make('toggleAccountStatus')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate Account' : 'Activate Account')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->action(function ($record) {
                        try {
                            $request = new Request;
                            $request->merge([
                                'user_id' => $record->id,
                            ]);

                            // Call the AuthController method
                            $authRepository = app(AuthRepositoryInterface::class);
                            $response = $authRepository->AccountActivationCard($request);

                            $responseData = $response->getData();

                            if (isset($responseData->ok) && $responseData->ok === true) {
                                Notification::make()
                                    ->title('Account status updated successfully')
                                    ->body($record->is_active ? 'Account has been activated' : 'Account has been deactivated')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Failed to update account status')
                                    ->body('Please try again later')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error updating account status')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => $record->is_active ? 'Deactivate User Account' : 'Activate User Account')
                    ->modalDescription(
                        fn ($record) => $record->is_active
                          ? 'Are you sure you want to deactivate this user account? The user will not be able to login until reactivated.'
                          : 'Are you sure you want to activate this user account? The user will be able to login immediately.'
                    )
                    ->modalSubmitActionLabel(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate'),

            ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
            ])
                    ->label('More')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('secondary')
                    ->button()
                    ->outlined()
                    ->size('sm'),
        ])
            ->bulkActions([
            Tables\Actions\BulkActionGroup::make([

                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Users')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            try {
                                foreach ($records as $record) {
                                    $request = new \Illuminate\Http\Request;
                                    $request->merge([
                                        'user_id' => $record->id,
                                    ]);

                                    $authRepository = app(\App\Repositories\Auth\AuthRepositoryInterface::class);
                                    $response = $authRepository->AccountActivationCard($request);

                                    $responseData = $response->getData();

                                    if (! isset($responseData->ok) || $responseData->ok !== true) {
                                        throw new \Exception('Failed to update one or more accounts.');
                                    }
                                }

                                Notification::make()
                                    ->title('Accounts Activated')
                                    ->body(count($records).' user accounts have been activated successfully.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error Activating Accounts')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Activate Selected User Accounts')
                        ->modalDescription('Are you sure you want to activate the selected user accounts? They will be able to login immediately.')
                        ->modalSubmitActionLabel('Activate'),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Users')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            try {
                                foreach ($records as $record) {
                                    $request = new \Illuminate\Http\Request;
                                    $request->merge([
                                        'user_id' => $record->id,
                                    ]);

                                    $authRepository = app(\App\Repositories\Auth\AuthRepositoryInterface::class);
                                    $response = $authRepository->AccountActivationCard($request);

                                    $responseData = $response->getData();

                                    if (! isset($responseData->ok) || $responseData->ok !== true) {
                                        throw new \Exception('Failed to update one or more accounts.');
                                    }
                                }

                                Notification::make()
                                    ->title('Accounts Deactivated')
                                    ->body(count($records).' user accounts have been deactivated successfully.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error Deactivating Accounts')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Selected User Accounts')
                        ->modalDescription('Are you sure you want to deactivate the selected user accounts? They will not be able to login until reactivated.')
                        ->modalSubmitActionLabel('Deactivate'),

            ]),

        ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Personal Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('fullname')
                                    ->label('Full Name')
                                    ->weight(FontWeight::Bold),

                                Infolists\Components\TextEntry::make('username')
                                    ->label('Username')
                                    ->badge()
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('email')
                                    ->label('Email')
                                    ->copyable()
                                    ->icon('heroicon-m-envelope'),

                                Infolists\Components\TextEntry::make('phone')
                                    ->label('Phone')
                                    ->copyable()
                                    ->icon('heroicon-m-phone'),

                                Infolists\Components\TextEntry::make('national_id')
                                    ->label('National ID'),

                                Infolists\Components\TextEntry::make('card_number')
                                    ->label('Card Number'),

                                Infolists\Components\TextEntry::make('gender')
                                    ->label('Gender')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('occupation')
                                    ->label('Occupation'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Location Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('location')
                                    ->label('Location'),

                                Infolists\Components\TextEntry::make('town')
                                    ->label('Town'),

                                Infolists\Components\TextEntry::make('estate')
                                    ->label('Estate'),

                                Infolists\Components\TextEntry::make('county')
                                    ->label('County'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Account Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('role.name')
                                    ->label('Role')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Admin' => 'danger',
                                        'Developer' => 'purple',
                                        'Manager' => 'warning',
                                        'Cashier' => 'info',
                                        'Customer Champion' => 'success',
                                        default => 'gray',
                                    }),

                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Active')
                                    ->boolean(),

                                Infolists\Components\IconEntry::make('is_verified')
                                    ->label('Verified')
                                    ->boolean(),

                                Infolists\Components\IconEntry::make('is_logged_in')
                                    ->label('Currently Logged In')
                                    ->boolean(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Permissions')
                    ->schema([
                    Infolists\Components\RepeatableEntry::make('permissions')
                        ->label('Additional Permissions')
                        ->schema([
                            Infolists\Components\TextEntry::make('name')
                                    ->badge()
                                    ->color('primary'),
                        ])
                        ->columns(3),
                ])
                    ->collapsible(),

                Infolists\Components\Section::make('System Information')
                    ->schema([
                    Infolists\Components\Grid::make(2)
                        ->schema([
                            Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),

                            Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),

                            Infolists\Components\TextEntry::make('deleted_at')
                                    ->label('Deleted')
                                    ->dateTime()
                                    ->placeholder('Not deleted'),
                        ]),
                ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PermissionsRelationManager::class,
            RelationManagers\NotificationsRelationManager::class,
            RelationManagers\ScreenshotsRelationManager::class,
            RelationManagers\ReferredUsersRelationManager::class,

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['role']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'fullname',
            'username',
            'email',
            'phone',
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
