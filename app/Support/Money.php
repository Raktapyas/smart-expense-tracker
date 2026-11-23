<?php

namespace App\Support;

final class Money
{
    /** Formats an amount as NPR with lakh/crore grouping, e.g. Rs 1,25,000.00 */
    public static function format(string|int|float|null $amount, string $symbol = 'Rs'): string
    {
        $value = number_format((float) $amount, 2, '.', '');
        [$int, $decimals] = explode('.', $value);

        $negative = str_starts_with($int, '-');
        $int = ltrim($int, '-');

        if (strlen($int) > 3) {
            $lastThree = substr($int, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3));
            $int = $rest.','.$lastThree;
        }

        return ($negative ? '-' : '').$symbol.' '.$int.'.'.$decimals;
    }
}
