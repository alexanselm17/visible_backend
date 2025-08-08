<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;
use App\Models\User;
use Filament\Resources\Components\Tab;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label('All Users')
                ->icon('heroicon-o-users')
                ->badge(User::count())
                ->extraAttributes([
                    'style' => 'margin-right: 18px;', // ✅ spacing between tabs
                ]),

            'active' => Tab::make()
                ->label('Active')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn($query) => $query->where('is_active', true))
                ->badge(User::where('is_active', true)->count())
                ->extraAttributes([
                    'style' => 'margin-right: 18px;',
                ]),

            'inactive' => Tab::make()
                ->label('Inactive')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn($query) => $query->where('is_active', false))
                ->badge(User::where('is_active', false)->count())
                ->extraAttributes([
                    'style' => 'margin-right: 18px;',
                ]),
        ];
    }
}
