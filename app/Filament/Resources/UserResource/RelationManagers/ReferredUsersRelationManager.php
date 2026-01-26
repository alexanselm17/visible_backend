<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReferredUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'referredUsers';

    protected static ?string $title = 'Referred Users';

    protected static ?string $modelLabel = 'Referred User';

    protected static ?string $pluralModelLabel = 'Referred Users';

    protected function getTableQuery(): Builder
    {
        // Get the current user's my_code
        $user = $this->getOwnerRecord();
        $code = $user->my_code;

        // If user has no my_code, return empty query
        if (empty($code)) {
            return \App\Models\User::whereRaw('1 = 0'); // Empty result
        }

        // Fetch users who have this user's code as their referal_code
        return \App\Models\User::where('referal_code', $code)
            ->orderByDesc('created_at');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('fullname')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('fullname')
            ->columns([
                Tables\Columns\TextColumn::make('fullname')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Role')
                    ->relationship('role', 'name'),
            ])
            ->headerActions([
                // Remove create action since referred users shouldn't be created manually
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Add bulk actions if needed
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]))
            ->defaultPaginationPageOption(15)
            ->paginationPageOptions([15, 25, 50, 100])
            ->emptyStateHeading('No Referred Users')
            ->emptyStateDescription(function () {
                $user = $this->getOwnerRecord();
                $code = $user->my_code;

                if (empty($code)) {
                    return 'This user has no referral code set up yet.';
                }

                return "No users have used referral code: {$code}";
            })
            ->emptyStateIcon('heroicon-o-user-plus');
    }
}
