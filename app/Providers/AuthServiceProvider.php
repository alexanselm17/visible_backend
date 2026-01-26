<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        // Shifts Operation Gates
        Gate::define('view-reports', fn (User $user) => $user->hasPermission('view_reports'));
        Gate::define('record-sales', fn (User $user) => $user->hasPermission('record_sales'));
        Gate::define('reverse-pump', fn (User $user) => $user->hasPermission('reverse_pump'));
        Gate::define('approve-invoice', fn (User $user) => $user->hasPermission('approve_invoice'));
        Gate::define('approve-expenses', fn (User $user) => $user->hasPermission('approve_expenses'));
        Gate::define('record-dips', fn (User $user) => $user->hasPermission('record_dips'));
        Gate::define('manage-shifts', fn (User $user) => $user->hasPermission('shift'));
        Gate::define('pump-session', fn (User $user) => $user->hasPermission('pump_session'));
        Gate::define('assign-station', fn (User $user) => $user->hasPermission('assign_station'));
        Gate::define('sale-in-station', fn (User $user) => $user->hasPermission('sale_in_station'));
        Gate::define('approve-discount', fn (User $user) => $user->hasPermission('approve_discount'));
        Gate::define('shift-discount', fn (User $user) => $user->hasPermission('shift_discount'));
        Gate::define('station-transfers', fn (User $user) => $user->hasPermission('station_transfers'));
        Gate::define('record-purchases', fn (User $user) => $user->hasPermission('record_purchases'));

        // Banking Gates
        Gate::define('reset-bankings', fn (User $user) => $user->hasPermission('reset_bankings'));
        Gate::define('cashier-approvals', fn (User $user) => $user->hasPermission('cashier_approvals'));

        // Authentication Gates
        Gate::define('manage-users', fn (User $user) => $user->hasPermission('manage_users'));
        Gate::define('users-roles', fn (User $user) => $user->hasPermission('users_roles'));

        // Customer Gates
        Gate::define('customer-operations', fn (User $user) => $user->hasPermission('customer_operations'));
        Gate::define('reconcile-customer', fn (User $user) => $user->hasPermission('reconcile_customer'));
        Gate::define('discount-customer', fn (User $user) => $user->hasPermission('discount_customer'));
        Gate::define('customer-repayment', fn (User $user) => $user->hasPermission('customer_repaymen'));

        // Setup Gates
        Gate::define('basic-setup-operations', fn (User $user) => $user->hasPermission('basic_setup_operations'));
        Gate::define('products-operations', fn (User $user) => $user->hasPermission('products_operations'));
        Gate::define('company-setup', fn (User $user) => $user->hasPermission('company_setup'));
        Gate::define('manage-permissions', [UserPolicy::class, 'managePermissions']);
    }
}
