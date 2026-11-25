<?php

namespace App\Support;

final class Csv
{
    /** Stops spreadsheet apps from running a cell as a formula (CSV injection). */
    public static function safe(?string $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
