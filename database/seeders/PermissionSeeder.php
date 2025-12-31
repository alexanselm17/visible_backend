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
          

            //Setup
            ['name' => 'Campaign Operation [Start/Update]', 'slug' => 'campaign_operations','category'=>'Campaign'],
            ['name' => 'Advert Operation [Start/Update]', 'slug' => 'advert_operations','category'=>'Campaign'],
            ['name' => 'Activation/Deactivation/Role Assignment', 'slug' => 'users_roles','category'=>'Authorization'],
            ['name' => 'Permission Assignment', 'slug' => 'manage_users','category'=>'Authorization'],
             ['name' => 'Payment', 'slug' => 'payment_operations','category'=>'Payment'],

        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}
