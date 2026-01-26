<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Http\Controllers\AuthController;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoSection::make('Employee Information')
                    ->icon('heroicon-o-user')
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('fullname')
                            ->label('Full Name')
                            ->weight(FontWeight::Bold),
                        TextEntry::make('email')
                            ->icon('heroicon-m-envelope'),
                        TextEntry::make('phone')
                            ->icon('heroicon-m-phone'),
                        TextEntry::make('national_id')
                            ->label('National ID')
                            ->icon('heroicon-m-identification'),
                        TextEntry::make('card_number')
                            ->icon('heroicon-m-credit-card'),
                        TextEntry::make('petrolStation.name')
                            ->label('Assigned Station')
                            ->icon('heroicon-m-building-office-2'),
                    ]),

                InfoSection::make('Account Status')
                    ->icon('heroicon-o-shield-check')
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('role.name')
                            ->label('Current Role')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'danger'),
                        TextEntry::make('is_active')
                            ->label('Account Status')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'warning')
                            ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),
                        TextEntry::make('is_verified')
                            ->label('Verification Status')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'warning')
                            ->formatStateUsing(fn ($state) => $state ? 'Verified' : 'Unverified'),
                        TextEntry::make('created_at')
                            ->label('Member Since')
                            ->dateTime('d M Y'),
                    ]),

                // InfoSection::make('Permissions')
                //   ->icon('heroicon-o-key')
                //   ->collapsible()
                //   ->schema([
                //     TextEntry::make('permissions')
                //       ->bulleted()
                //       ->formatStateUsing(function () {
                //         $userPermissions = app(AuthController::class)->getUserPermissions($this->record->id)->getData();

                //         $permissions = collect($userPermissions->permissions)
                //           ->where('is_permitted', true)
                //           ->pluck('name')
                //           ->map(fn($name) => str($name)->title()->toString());

                //         return $permissions;
                //       })
                //   ])
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->icon('heroicon-m-pencil-square')
                ->color('warning')
                ->url(fn () => $this->getResource()::getUrl('edit', ['record' => $this->getRecord()])),
            // ->visible(fn() => auth()->user()->can('update', $this->getRecord())),

            Action::make('manageRole')
                ->icon('heroicon-m-user-group')
                ->color('success')
                ->modalWidth('lg')
                ->form([
                    Section::make('Role Assignment')
                        ->description('Assign or change the user\'s role in the system')
                        ->schema([
                            Select::make('role_id')
                                ->label('Select Role')
                                ->options(function () {
                                    return \App\Models\RolesModel::query()
                                        ->when(! auth()->user()->role?->slug === 'dev', function ($query) {
                                            $query->where('slug', '!=', 'dev');
                                        })
                                        ->pluck('name', 'id');
                                })
                                ->default(fn () => $this->record->role_id)
                                ->required()
                                ->searchable()
                                ->preload()
                                ->native(false),
                        ]),
                ])
                ->action(function (array $data) {
                    $request = new \App\Http\Requests\AssignRoleRequest;
                    $request->merge([
                        'user_id' => $this->record->id,
                        'role_id' => $data['role_id'],
                    ]);

                    $response = app(\App\Http\Controllers\AuthController::class)->assignRole($request);
                    $responseData = $response->getData();

                    if ($responseData->ok === true) {
                        Notification::make()
                            ->title('Role Updated')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Error')
                            ->danger()
                            ->body($responseData->message ?? 'Failed to update role')
                            ->send();
                    }
                })
                ->visible(
                    fn () => in_array(auth()->user()->role?->slug, ['dev', 'admin']) &&
                      $this->record->id !== auth()->id()
                ),

            Action::make('managePermissions')
                ->icon('heroicon-m-key')
                ->color('info')
                ->modalWidth('4xl')
                ->form(function () {
                    $userPermissions = app(AuthController::class)->getUserPermissions($this->record->id)->getData();
                    $permittedSlugs = collect($userPermissions->permissions)
                        ->where('is_permitted', true)
                        ->pluck('slug')
                        ->toArray();

                    return [
                        Section::make('Permission Management')
                            ->description('Manage user permissions and access rights')
                            ->schema([
                                Grid::make(2)->schema([
                                    Tabs::make('Permissions')
                                        ->tabs([
                                            Tabs\Tab::make('Shifts Operation')
                                                ->icon('heroicon-m-clock')
                                                ->schema([
                                                    CheckboxList::make('shifts_permissions')
                                                        ->options(function () {
                                                            return \App\Models\Permission::where('category', 'Shifts Operation')
                                                                ->pluck('name', 'slug')
                                                                ->toArray();
                                                        })
                                                        ->bulkToggleable()
                                                        ->columns(2)
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, $set) {
                                                            $selectedPermissions = collect($state)
                                                                ->filter(function ($value) {
                                                                    return $value === true || $value === 1 || $value === '1';
                                                                })
                                                                ->keys()
                                                                ->toArray();

                                                            $set('selected_shifts', $selectedPermissions);
                                                        })
                                                        ->default(function () use ($permittedSlugs) {
                                                            return \App\Models\Permission::where('category', 'Shifts Operation')
                                                                ->whereIn('slug', $permittedSlugs)
                                                                ->pluck('slug')
                                                                ->toArray();
                                                        }),
                                                ]),

                                            Tabs\Tab::make('Bankings')
                                                ->icon('heroicon-m-banknotes')
                                                ->schema([
                                              CheckboxList::make('banking_permissions')
                                                        ->options(function () {
                                                            return \App\Models\Permission::where('category', 'Bankings')
                                                                ->pluck('name', 'slug')
                                                                ->toArray();
                                                        })
                                                        ->columns(2)
                                                        ->bulkToggleable()
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, $set) {
                                                            $selectedPermissions = collect($state)
                                                                ->filter(function ($value) {
                                                                    return $value === true || $value === 1 || $value === '1';
                                                                })
                                                                ->keys()
                                                                ->toArray();
                                                            $set('selected_banking', $selectedPermissions);
                                                        })
                                                        ->default(function () use ($permittedSlugs) {
                                                            return \App\Models\Permission::where('category', 'Bankings')
                                                                ->whereIn('slug', $permittedSlugs)
                                                                ->pluck('slug')
                                                                ->toArray();
                                                        }),
                                          ]),

                                            Tabs\Tab::make('Authentication')
                                                ->icon('heroicon-m-user-group')
                                                ->schema([
                                              CheckboxList::make('auth_permissions')
                                                        ->options(function () {
                                                            return \App\Models\Permission::where('category', 'Authentication')
                                                                ->pluck('name', 'slug')
                                                                ->toArray();
                                                        })
                                                        ->columns(2)
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, $set) {
                                                            $selectedPermissions = collect($state)
                                                                ->filter(function ($value) {
                                                                    return $value === true || $value === 1 || $value === '1';
                                                                })
                                                                ->keys()
                                                                ->toArray();
                                                            $set('selected_auth', $selectedPermissions);
                                                        })
                                                        ->default(function () use ($permittedSlugs) {
                                                            return \App\Models\Permission::where('category', 'Authentication')
                                                                ->whereIn('slug', $permittedSlugs)
                                                                ->pluck('slug')
                                                                ->toArray();
                                                        }),
                                          ]),

                                            Tabs\Tab::make('Customers')
                                                ->icon('heroicon-m-users')
                                                ->schema([
                                              CheckboxList::make('customer_permissions')
                                                        ->options(function () {
                                                            return \App\Models\Permission::where('category', 'Customers')
                                                                ->pluck('name', 'slug')
                                                                ->toArray();
                                                        })
                                                        ->columns(2)
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, $set) {
                                                            $selectedPermissions = collect($state)
                                                                ->filter(function ($value) {
                                                                    return $value === true || $value === 1 || $value === '1';
                                                                })
                                                                ->keys()
                                                                ->toArray();
                                                            $set('selected_customers', $selectedPermissions);
                                                        })
                                                        ->default(function () use ($permittedSlugs) {
                                                            return \App\Models\Permission::where('category', 'Customers')
                                                                ->whereIn('slug', $permittedSlugs)
                                                                ->pluck('slug')
                                                                ->toArray();
                                                        }),
                                          ]),

                                            Tabs\Tab::make('Setup')
                                                ->icon('heroicon-m-cog-6-tooth')
                                                ->schema([
                                              CheckboxList::make('setup_permissions')
                                                        ->options(function () {
                                                            return \App\Models\Permission::where('category', 'Setup')
                                                                ->pluck('name', 'slug')
                                                                ->toArray();
                                                        })
                                                        ->columns(2)
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, $set) {
                                                            $selectedPermissions = collect($state)
                                                                ->filter(function ($value) {
                                                                    return $value === true || $value === 1 || $value === '1';
                                                                })
                                                                ->keys()
                                                                ->toArray();
                                                            $set('selected_setup', $selectedPermissions);
                                                        })
                                                        ->default(function () use ($permittedSlugs) {
                                                            return \App\Models\Permission::where('category', 'Setup')
                                                                ->whereIn('slug', $permittedSlugs)
                                                                ->pluck('slug')
                                                                ->toArray();
                                                        }),
                                          ]),
                                        ])
                                        ->columnSpanFull(),

                                ]),
                            ]),
                    ];
                })
                ->action(function (array $data) {

                    $selectedPermissions = collect([
                        ...($data['shifts_permissions'] ?? []),
                        ...($data['banking_permissions'] ?? []),
                        ...($data['auth_permissions'] ?? []),
                        ...($data['customer_permissions'] ?? []),
                        ...($data['setup_permissions'] ?? []),
                    ])->unique()->values()->all();

                    try {
                        $request = new Request;
                        $token = session('api_token') ?? Auth::user()?->createToken('api-token')->plainTextToken;
                        $request->headers->set('Authorization', 'Bearer '.$token);
                        $request->merge([
                            'user_id' => $this->record->id,
                            'permissions' => array_values($selectedPermissions),
                        ]);

                        $response = app(AuthController::class)->assignPermissionsToUser($request);
                        $responseData = $response->getData();

                        if ($responseData->ok === true) {
                            Notification::make()->title('Permissions Updated Successfully')->success()->send();
                        } else {
                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->body($responseData->message)
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->danger()
                            ->body($e)
                            ->send();
                    }
                })
                ->visible(fn () => auth()->user()->hasPermission('manage_users')),
        ];
    }
}
