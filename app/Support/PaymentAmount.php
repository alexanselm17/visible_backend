<?php

namespace App\Support;

use InvalidArgumentException;

class PaymentAmount
{
    public static function minor(string $amount): int
    {
        if (! preg_match('/^([0-9]{1,13})(?:\.([0-9]{1,2}))?$/D', $amount, $parts)) {
            throw new InvalidArgumentException('Invalid payment amount.');
        }

        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function decimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
