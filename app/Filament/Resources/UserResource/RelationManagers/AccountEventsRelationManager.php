<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'accountEvents';

    protected static ?string $title = 'Staff change history';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('action')->label('Action')->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->title())->badge(),
            TextColumn::make('actor.fullname')->label('Staff member')->placeholder('System / removed account'),
            TextColumn::make('reason')->wrap()->placeholder('—'),
            TextColumn::make('metadata.method')->label('Verification method')->placeholder('—')
                ->formatStateUsing(fn ($state) => $state === 'otp' ? 'OTP check' : 'Staff review')->badge(),
            TextColumn::make('metadata.changed_fields')->label('Fields changed')->listWithLineBreaks()->toggleable(),
            TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc')->paginated([10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
