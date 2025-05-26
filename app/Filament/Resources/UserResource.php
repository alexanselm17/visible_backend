<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource


{


  protected static ?string $model = User::class;

  protected static ?string $navigationIcon = 'heroicon-o-users';

  protected static ?string $navigationGroup = 'User Management';

  protected static ?string $navigationLabel = 'Employees';

  protected static ?string $modelLabel = 'Employee';

  protected static ?string $pluralModelLabel = 'Employees';

  protected static ?int $navigationSort = 1;

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Forms\Components\Section::make('Employee Information')
          ->schema([
            TextInput::make('fullname')
              ->required()
              ->maxLength(255)
              ->helperText('Enter employee\'s full legal name'),

            TextInput::make('username')
              ->required()
              ->maxLength(255)
              ->unique(ignorable: fn($record) => $record)
              ->helperText('Choose a unique username for login'),

            TextInput::make('email')
              ->email()
              ->required()
              ->maxLength(255)
              ->unique(ignorable: fn($record) => $record)
              ->helperText('Enter a valid email address'),

            TextInput::make('phone')
              ->tel()
              ->prefix('+254')
              ->required()
              ->maxLength(9)
              ->dehydrateStateUsing(function ($state) {
                // Remove +254 if present
                return str_replace('+254', '', $state);
              })
              ->formatStateUsing(function ($state) {
                // Remove +254 if present when displaying
                return str_replace('+254', '', $state);
              })
              ->helperText('Enter active contact number'),

            TextInput::make('national_id')
              ->required()
              ->maxLength(255)
              ->unique(ignorable: fn($record) => $record)
              ->helperText('Enter valid national ID number'),

            TextInput::make('card_number')
              ->maxLength(255)
              ->unique(ignorable: fn($record) => $record)
              ->helperText('Optional: Enter employee card number if applicable'),
          ])->columns(2),

        Forms\Components\Section::make('Access & Roles')
          ->schema([
            Select::make('petrol_id')
              ->label('Petrol Station')
              ->relationship('petrolStation', 'name', function ($query) {
                $query->where('company_id', auth()->user()->company_id);
              })
              ->required()
              ->helperText('Assign employee to a specific petrol station'),

            TextInput::make('password')
              ->password()
              ->dehydrateStateUsing(fn($state) => filled($state) ? $state : null)
              ->required(fn(string $operation): bool => $operation === 'create')
              ->helperText('Leave blank to keep existing password when editing'),

            Toggle::make('is_active')
              ->label('Active Status')
              ->default(true)
              ->helperText('Toggle to enable/disable employee account access'),

            Toggle::make('is_verified')
              ->label('Verified Status')
              ->default(false)
              ->helperText('Toggle to verify employee account credentials'),
          ])->columns(2),
      ]);
  }
  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        TextColumn::make('fullname')
          ->searchable()
          ->sortable(),
        TextColumn::make('email')
          ->searchable()
          ->sortable(),
        TextColumn::make('phone')
          ->searchable(),

        TextColumn::make('role.name')
          ->label('Role')
          ->sortable()
          ->formatStateUsing(fn($state) => $state ? $state : 'Unassigned')
          ->badge()
          ->color(fn($state) => $state === 'Unassigned' ? 'danger' : 'success'),


        TextColumn::make('petrolStation.name')
          ->label('Petrol Station')
          ->sortable(),

        TextColumn::make('created_at')
          ->dateTime()
          ->sortable()
          ->toggleable(isToggledHiddenByDefault: true),
        TextColumn::make('updated_at')
          ->dateTime()
          ->sortable()
          ->toggleable(isToggledHiddenByDefault: true),
      ])
      ->filters([
        SelectFilter::make('role')
          ->relationship('role', 'name'),
        SelectFilter::make('petrol_station')
          ->relationship('petrolStation', 'name', function ($query) {
            $query->where('company_id', auth()->user()->company_id);
          }),
        TrashedFilter::make(),
      ])
      ->actions([
        ViewAction::make(),
        // ->visible(fn($record) => auth()->user()->can('view', $record)),
        EditAction::make()
        // ->visible(fn($record) => auth()->user()->can('update', $record)),

        // Action::make('assignRole')
        //   ->label(fn($record) => $record->role ? 'Change Role' : 'Assign Role')
        //   ->icon('heroicon-o-user-group')
        //   ->modalHeading(fn($record) => $record->role ? "Change {$record->fullname}'s Role" : "Assign Role to {$record->fullname}")
        //   ->form([
        //     Select::make('role_id')
        //       ->label('Role')
        //       ->options(function () {
        //         return \App\Models\RolesModel::query()
        //           ->when(!auth()->user()->role?->slug === 'dev', function ($query) {
        //             $query->where('slug', '!=', 'dev');
        //           })
        //           ->pluck('name', 'id');
        //       })
        //       ->default(function ($record) {
        //         return $record->role_id;
        //       })
        //       ->required()
        //       ->searchable()
        //       ->preload()
        //       ->placeholder('Select a role')
        //       ->helperText(function ($record) {
        //         return $record->role
        //           ? "Current Role: {$record->role->name}"
        //           : 'No role currently assigned';
        //       }),
        //   ])
        //   ->modalDescription(
        //     fn($record) =>
        //     $record->role
        //       ? "You are about to change {$record->fullname}'s role from {$record->role->name}."
        //       : "You are about to assign a role to {$record->fullname}."
        //   )
        //   ->action(function (array $data, $record) {
        //     $request = new \App\Http\Requests\AssignRoleRequest();
        //     $request->merge([
        //       'user_id' => $record->id,
        //       'role_id' => $data['role_id'],
        //     ]);

        //     $response = app(\App\Http\Controllers\AuthController::class)->assignRole($request);
        //     $responseData = $response->getData();

        //     if ($responseData->ok === true) {
        //       Notification::make()
        //         ->title($responseData->message ?? ($record->role
        //           ? 'Role updated successfully'
        //           : 'Role assigned successfully'))
        //         ->success()
        //         ->send();
        //     } else {
        //       Notification::make()
        //         ->title($responseData->message ?? 'Failed to assign role')
        //         ->danger()
        //         ->send();
        //     }
        //   })
        //   ->modalSubmitActionLabel(fn($record) => $record->role ? 'Change Role' : 'Assign Role')
        //   ->color('success')
        //   ->visible(
        //     fn($record) =>
        //     in_array(auth()->user()->role?->slug, ['dev', 'admin']) &&
        //       $record->id !== auth()->id()
        //   ),
      ])
      ->bulkActions([]);
  }

  public static function getRelations(): array
  {
    return [
      //
    ];
  }

  public static function getPages(): array
  {
    return [
      'index' => Pages\ListUsers::route('/'),
      'create' => Pages\CreateUser::route('/create'),
      'edit' => Pages\EditUser::route('/{record}/edit'),
      'view' => Pages\ViewUser::route('/{record}'),
    ];
  }

  public static function getEloquentQuery(): Builder
  {
    return parent::getEloquentQuery()
      ->where('company_id', auth()->user()->company_id)
      ->where('id', '!=', auth()->user()->id)
      ->withoutGlobalScopes([
        SoftDeletingScope::class,
      ]);
  }
}
