<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Number formatting for the summary panels (x-summary-panel): Indian-grouped
 * rupees, honest percentages ("—" when there's nothing to divide by) and
 * split-bar parts. Shared by ListSummaryWidget and the GameHub pages.
 */
trait FormatsSummaries
{
    /** ₹18,42,900 — Indian grouping, whole rupees. */
    protected static function inr(float $n): string
    {
        $n = (int) round($n);
        $sign = $n < 0 ? '-' : '';
        $str = (string) abs($n);

        if (strlen($str) <= 3) {
            return $sign . '₹' . $str;
        }

        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3));

        return $sign . '₹' . $rest . ',' . substr($str, -3);
    }

    /** "12.5%", or "—" when there is nothing to divide by. */
    protected static function pct(int | float $part, int | float $whole): string
    {
        return $whole > 0 ? rtrim(rtrim(number_format($part / $whole * 100, 1), '0'), '.') . '%' : '—';
    }

    /**
     * Turn [name => amount] into bar parts, largest first, dropping zeros.
     *
     * @param  array<string, float|int>  $amounts
     * @return list<array{name: string, value: string, pct: float}>
     */
    protected static function parts(array $amounts, bool $money = true): array
    {
        $amounts = array_filter($amounts, fn ($v): bool => $v > 0);
        arsort($amounts);
        $total = array_sum($amounts);

        return array_values(array_map(
            fn (string $name, $v): array => [
                'name' => $name,
                'value' => $money ? static::inr((float) $v) : number_format((int) $v),
                'pct' => $total > 0 ? round($v / $total * 100, 1) : 0.0,
            ],
            array_keys($amounts),
            $amounts,
        ));
    }
}
