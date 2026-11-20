<?php

namespace App\Support;

class Money
{
    /** Format cents as "R 1 250" or "R 99.50". */
    public static function format(?int $cents, string $symbol = 'R'): string
    {
        if ($cents === null) {
            return '';
        }

        $decimals = $cents % 100 === 0 ? 0 : 2;

        return $symbol.' '.number_format($cents / 100, $decimals, '.', ' ');
    }
}
