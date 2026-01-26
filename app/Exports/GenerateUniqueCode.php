<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function generateUniqueCode(string $table, string $column = 'code', int $length = 10): string
{
    do {
        $code = Str::random($length);

        // Keep only numeric characters
        $code = preg_replace('/[^0-9]/', '', $code);

        // Pad with random numbers if it's shorter than desired length
        while (strlen($code) < $length) {
            $code .= rand(0, 9);
        }

        $exists = DB::table($table)->where($column, $code)->exists();
    } while ($exists);

    return $code;
}
