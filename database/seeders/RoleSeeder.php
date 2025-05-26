<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RolesModel; // Ensure this matches your model's namespace

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        RolesModel::create([
            'name' => 'Developer',
            'slug' => 'dev',
        ]);

        RolesModel::create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        RolesModel::create([
            'name' => 'Customer Champion',
            'slug' => 'salesman',
        ]);


    }
}
