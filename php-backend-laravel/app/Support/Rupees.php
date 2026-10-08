<?php

declare(strict_types=1);

namespace App\Support;

/** Indian-format rupee strings for the admin console. */
final class Rupees
{
    /** Indian-grouped rupees with no paise: ₹12,34,567. */
    public static function format(float $amount): string
    {
        $neg = $amount < 0;
        $n = (string) (int) round(abs($amount));
        if (strlen($n) > 3) {
            $last3 = substr($n, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($n, 0, -3));
            $n = $rest.','.$last3;
        }

        return ($neg ? '−' : '').'₹'.$n;
    }

    /** Compact rupees for chart axes: ₹950, ₹12K, ₹3.4L, ₹1.2Cr. */
    public static function short(float $amount): string
    {
        $a = abs($amount);
        $trim = fn (float $v): string => rtrim(rtrim(number_format($v, 1), '0'), '.');

        return match (true) {
            $a >= 1e7 => '₹'.$trim($a / 1e7).'Cr',
            $a >= 1e5 => '₹'.$trim($a / 1e5).'L',
            $a >= 1e3 => '₹'.$trim($a / 1e3).'K',
            default => '₹'.(int) round($a),
        };
    }
}
