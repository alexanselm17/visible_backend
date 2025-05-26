<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            //Shifts
            ['name' => 'View Reports', 'slug' => 'view_reports','category'=>'Shifts Operation'],
            ['name' => 'Record Sales', 'slug' => 'record_sales','category'=>'Shifts Operation'],
            ['name' => 'Reverse Pump', 'slug' => 'reverse_pump','category'=>'Shifts Operation'],
            ['name' => 'Approve Invoice', 'slug' => 'approve_invoice','category'=>'Shifts Operation'],
            ['name' => 'Approve Expenses', 'slug' => 'approve_expenses','category'=>'Shifts Operation'],
            ['name' => 'Record Dips', 'slug' => 'record_dips','category'=>'Shifts Operation'],
            ['name' => 'Start/End Shift', 'slug' => 'shift','category'=>'Shifts Operation'],
            ['name' => 'Record Pump Readings', 'slug' => 'pump_session','category'=>'Shifts Operation'],
            ['name' => 'Assign Station', 'slug' => 'assign_station','category'=>'Shifts Operation'],
            ['name' => 'Sale in Lube Shops', 'slug' => 'sale_in_station','category'=>'Shifts Operation'],
            ['name' => 'Approve Discounts', 'slug' => 'approve_discount','category'=>'Shifts Operation'],
            ['name' => 'Record Discounts', 'slug' => 'shift_discount','category'=>'Shifts Operation'],
            ['name' => 'Station Transefer', 'slug' => 'station_transfers','category'=>'Shifts Operation'],
            ['name' => 'Purchases', 'slug' => 'record_purchases','category'=>'Shifts Operation'],

            //Bankings
            ['name' => 'Reset Bankings', 'slug' => 'reset_bankings','category'=>'Bankings'],
            ['name' => 'Approve Bankings', 'slug' => 'cashier_approvals','category'=>'Bankings'],
            //Authentication
            ['name' => 'Manage Users', 'slug' => 'manage_users','category'=>'Authentication'],
            ['name' => 'Users[Roles,Account]', 'slug' => 'users_roles','category'=>'Authentication'],
            //Customers
            ['name' => 'Customers[Create/Update]', 'slug' => 'customer_operations','category'=>'Customers'],
            ['name' => 'Change Customers Bal.', 'slug' => 'reconcile_customer','category'=>'Customers'],
            ['name' => 'Discount Invoice Customers', 'slug' => 'discount_customer','category'=>'Customers'],
            ['name' => 'Record Customer Repayment', 'slug' => 'customer_repaymen','category'=>'Customers'],

            //Setup
            ['name' => 'Basic Setup[Tank/pump/station]', 'slug' => 'basic_setup_operations','category'=>'Setup'],
            ['name' => 'Products Operations', 'slug' => 'products_operations','category'=>'Setup'],
            ['name' => 'Company Setup[Company,Petrol Station]', 'slug' => 'company_setup','category'=>'Setup'],

        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}
