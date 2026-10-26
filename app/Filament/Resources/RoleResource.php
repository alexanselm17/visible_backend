<?php

namespace App\Filament\Resources;

use App\Filament\Support\OperationsResource;
use App\Models\RolesModel;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Table;

class RoleResource extends OperationsResource
{
    protected static ?string $modelLabel = 'Role';

    protected static ?string $navigationLabel = 'Roles';

    protected static ?string $model = RolesModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Settings';

    protected static string $area = 'settings';

    protected static bool $editable = true;

    public static function form(Form $form): Form
    {
        return $form->schema([
            F\TextInput::make('name')->required()->maxLength(255),
            F\TextInput::make('slug')->label('Role key')->unique(ignoreRecord: true)->required()->regex('/^[a-z0-9_]+$/')
                ->disabled(fn ($record) => $record !== null)->dehydrated(fn ($record) => $record === null)
                ->helperText('Existing role keys are fixed because authorization depends on them.'),
            F\Select::make('permissions')->relationship('permissions', 'name')->multiple()->searchable()->preload()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->columns([
            C\TextColumn::make('name')->searchable(), C\TextColumn::make('slug')->badge(),
            C\TextColumn::make('permissions.name')->badge()->wrap(),
        ])->actions([A\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => RoleResource\Pages\ManageRoles::route('/')];
    }
}
