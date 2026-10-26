<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PhoneVerificationOtpsRelationManager extends RelationManager
{
    protected static string $relationship = 'phoneVerificationOtps';

    protected static ?string $title = 'Phone OTP history';

    public function table(Table $table): Table
    {
        return \App\Filament\Resources\PhoneVerificationOtpResource::table($table)
            ->headerActions([])->actions([])->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
