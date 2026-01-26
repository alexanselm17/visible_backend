<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (! function_exists('generateUniqueCode')) {
    function generateUniqueCode(string $table, string $column = 'code', int $length = 10): string
    {
        do {
            $code = Str::random($length);
            $code = preg_replace('/[^0-9]/', '', $code);
            while (strlen($code) < $length) {
                $code .= rand(0, 9);
            }
            $exists = DB::table($table)->where($column, $code)->exists();
        } while ($exists);

        return $code;
    }
}
