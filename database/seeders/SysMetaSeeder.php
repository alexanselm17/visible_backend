<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SysMeta;

class SysMetaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sysConstants = [
            [
                'meta_index' => 1,
                'meta_key' => "deposit_account",
                'meta_value' => "Mpesa",
                'meta_shortcode' => "mpesa",
            ],
            [
                'meta_index' => 1,
                'meta_key' => "deposit_account",
                'meta_value' => "Cash",
                'meta_shortcode' => "cash",
            ],
            [
                'meta_index' => 2,
                'meta_key' => "deposit_account",
                'meta_value' => "Kcb",
                'meta_shortcode' => "kcb",
            ],
            [
                'meta_index' => 3,
                'meta_key' => "deposit_account",
                'meta_value' => "Shell Card",
                'meta_shortcode' => "shell_card",
            ],
            [
                'meta_index' => 4,
                'meta_key' => "deposit_account",
                'meta_value' => "Equity Bank",
                'meta_shortcode' => "equity_bank",
            ],

            [
                'meta_index' => 5,
                'meta_key' => "payment_method",
                'meta_value' => "Mpesa",
                'meta_shortcode' => "mpesa",
            ],
            [
                'meta_index' => 6,
                'meta_key' => "payment_method",
                'meta_value' => "Cash",
                'meta_shortcode' => "cash",
            ],
            [
                'meta_index' => 7,
                'meta_key' => "payment_method",
                'meta_value' => "Kcb",
                'meta_shortcode' => "kcb",
            ],
            [
                'meta_index' => 8,
                'meta_key' => "payment_method",
                'meta_value' => "Shell Card",
                'meta_shortcode' => "shell_card",
            ],
            [
                'meta_index' => 9,
                'meta_key' => "payment_method",
                'meta_value' => "Equity Bank",
                'meta_shortcode' => "equity_bank",
            ],
            [
                'meta_index' => 9,
                'meta_key' => "payment_method",
                'meta_value' => "Invoice",
                'meta_shortcode' => "invoice",
            ],
          
        ];

        foreach ($sysConstants as $sysConstant) {
            SysMeta::create($sysConstant);
        }
    }
}
