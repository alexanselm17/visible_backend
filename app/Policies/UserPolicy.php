<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Categories of permissions for better organization
     */
    const CATEGORIES = [
        'Shifts Operation',
        'Bankings',
        'Authentication',
        'Customers',
        'Setup',
    ];

    /**
     * Determine if user has super admin privileges
     */
    public function before(User $user, string $ability)
    {
        if ($user->isAdmin() || $user->isDeveloper()) {
            return true;
        }
    }

    /**
     * Shift Operations
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermission('view_reports');
    }

    public function recordSales(User $user): bool
    {
        return $user->hasPermission('record_sales');
    }

    public function managePumps(User $user): bool
    {
        return $user->hasPermission('reverse_pump');
    }

    public function manageInvoices(User $user): bool
    {
        return $user->hasPermission('approve_invoice');
    }

    public function manageExpenses(User $user): bool
    {
        return $user->hasPermission('approve_expenses');
    }

    public function manageDips(User $user): bool
    {
        return $user->hasPermission('record_dips');
    }

    public function manageShifts(User $user): bool
    {
        return $user->hasPermission('shift');
    }

    public function managePumpReadings(User $user): bool
    {
        return $user->hasPermission('pump_session');
    }

    public function assignStation(User $user): bool
    {
        return $user->hasPermission('assign_station');
    }

    public function manageLubeShopSales(User $user): bool
    {
        return $user->hasPermission('sale_in_station');
    }

    public function manageDiscounts(User $user): bool
    {
        return $user->hasPermission('approve_discount') ||
          $user->hasPermission('shift_discount');
    }

    public function manageStationTransfers(User $user): bool
    {
        return $user->hasPermission('station_transfers');
    }

    public function managePurchases(User $user): bool
    {
        return $user->hasPermission('record_purchases');
    }

    /**
     * Banking Operations
     */
    public function manageBankings(User $user): bool
    {
        return $user->hasPermission('reset_bankings') ||
          $user->hasPermission('cashier_approvals');
    }

    /**
     * Authentication & User Management
     */
    public function manageUsers(User $user): bool
    {
        return $user->hasPermission('manage_users') ||
          $user->hasPermission('users_roles');
    }

    /**
     * Customer Operations
     */
    public function manageCustomers(User $user): bool
    {
        return $user->hasPermission('customer_operations');
    }

    public function manageCustomerBalance(User $user): bool
    {
        return $user->hasPermission('reconcile_customer');
    }

    public function manageCustomerDiscounts(User $user): bool
    {
        return $user->hasPermission('discount_customer');
    }

    public function manageCustomerRepayments(User $user): bool
    {
        return $user->hasPermission('customer_repaymen');
    }

    /**
     * Setup Operations
     */
    public function manageBasicSetup(User $user): bool
    {
        return $user->hasPermission('basic_setup_operations');
    }

    public function manageProducts(User $user): bool
    {
        return $user->hasPermission('products_operations');
    }

    public function manageCompanySetup(User $user): bool
    {
        return $user->hasPermission('company_setup');
    }

    public function managePermissions(User $user): bool
    {
        return $user->hasPermission('manage_users');
    }
}
