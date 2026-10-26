<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Support\AdminAccess;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    use \App\Filament\Support\NotifiesWorkflowErrors;
    use \App\Filament\Support\RefreshesWorkflowRecords;

    protected static string $resource = UserResource::class;

    protected static ?string $title = 'All users';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('all_users')->label('All users')->icon('heroicon-o-users')
                ->url(UserResource::getUrl('index', ['activeTab' => 'all'])),
            Actions\CreateAction::make()->label('Create user'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All users'),
            'active' => Tab::make('Active')->modifyQueryUsing(fn ($query) => $query->where('is_active', true)),
            'inactive' => Tab::make('Inactive')->modifyQueryUsing(fn ($query) => $query->where('is_active', false)),
            'unverified' => Tab::make('Account unverified')->modifyQueryUsing(fn ($query) => $query->where('is_verified', false)),
            'phone_unverified' => Tab::make('Phone unverified')->modifyQueryUsing(fn ($query) => $query->whereNull('phone_verified_at')),
        ];
        if (AdminAccess::allows('settings')) {
            $tabs['deleted'] = Tab::make('Deleted')->modifyQueryUsing(fn ($query) => $query->onlyTrashed());
        }

        return $tabs;
    }

    public function updatedActiveTab(): void
    {
        // Keep the deletion filter aligned with its dedicated tab.
        $this->tableFilters['account_scope']['value'] = $this->activeTab === 'deleted' ? 'deleted' : 'current';
        $this->resetPage();
        $this->flushCachedTableRecords();
    }
}
