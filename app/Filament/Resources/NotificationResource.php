<?php

namespace App\Filament\Resources;

use App\Filament\Support\Fields;
use App\Filament\Support\OperationsResource;
use App\Models\Notification;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters as T;
use Filament\Tables\Table;

class NotificationResource extends OperationsResource
{
    protected static ?string $model = Notification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'Members';

    protected static string $area = 'users';

    protected static bool $editable = true;

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Fields::user()->required(), F\TextInput::make('title')->required()->maxLength(255),
            F\Textarea::make('message')->required()->maxLength(2000),
            F\Select::make('type')->options(['info' => 'Information', 'success' => 'Success', 'warning' => 'Warning', 'error' => 'Error'])->default('info')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with('user'))->columns([
            C\TextColumn::make('user.fullname')->label('Member')->searchable(),
            C\TextColumn::make('title')->searchable()->weight('semibold'),
            C\TextColumn::make('message')->wrap()->limit(120),
            C\TextColumn::make('type')->badge(), C\IconColumn::make('is_read')->boolean()->label('Read'),
            C\TextColumn::make('created_at')->since()->sortable(),
        ])->filters([T\TernaryFilter::make('is_read')])
            ->actions([A\ViewAction::make()->form(fn (Form $form) => static::form($form))]);
    }

    public static function getPages(): array
    {
        return ['index' => NotificationResource\Pages\ManageNotifications::route('/')];
    }
}
