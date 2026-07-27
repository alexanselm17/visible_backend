<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CreditPlan;

class CreditPlanSeeder extends Seeder
{
    public function run(): void
    {
        $peoplePerCredit = 10;

        $plans = [
            [
                'name' => '50 Credits',
                'price' => 700.00,
                'base_credits' => 50,
                'bonus_credits' => 0,
            ],
            [
                'name' => '120 Credits',
                'price' => 1500.00,
                'base_credits' => 100,
                'bonus_credits' => 20,
            ],
            [
                'name' => '250 Credits',
                'price' => 3000.00,
                'base_credits' => 200,
                'bonus_credits' => 50,
            ],
            [
                'name' => '700 Credits',
                'price' => 7500.00,
                'base_credits' => 500,
                'bonus_credits' => 200,
            ],
            [
                'name' => '1500 Credits',
                'price' => 15000.00,
                'base_credits' => 1000,
                'bonus_credits' => 500,
            ],
        ];

        foreach ($plans as $plan) {
            $totalCredits = $plan['base_credits'] + $plan['bonus_credits'];
            $plan['promoters_reach'] = $totalCredits * $peoplePerCredit;

            CreditPlan::create($plan);
        }
    }
}
