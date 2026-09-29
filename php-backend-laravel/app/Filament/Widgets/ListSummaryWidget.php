<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Concerns\HiddenFromPartnerConsole;
use App\Filament\Concerns\RefreshesOnContentUpdate;
use Filament\Widgets\Widget;

/**
 * The summary strip above a list page: a few figures, an optional split and a
 * short list — every one of them read from the database.
 *
 * These replaced the "executive hero" widgets, which padded empty tables with
 * made-up numbers (a ₹32,60,400 GMV, cashiers called Rajesh M., a "96% AI
 * confidence" forecast). The rule here is the opposite: a figure that isn't
 * known is shown as "—" or left out, and an empty list says it's empty.
 *
 * Still hidden from the partner console: the figures are platform-wide, and a
 * partner's own list page is already scoped to them.
 */
abstract class ListSummaryWidget extends Widget
{
    use HiddenFromPartnerConsole;
    use RefreshesOnContentUpdate;

    protected string $view = 'filament.widgets.list-summary';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Paid-for booking statuses, compared lower-cased (status casing is mixed). */
    protected const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    protected const CANCELLED = ['cancelled', 'canceled', 'refunded'];

    /**
     * @return array{
     *     title: string,
     *     window?: string,
     *     stats: list<array{label: string, value: string, sub?: string, tone?: string}>,
     *     split?: array{label: string, parts: list<array{name: string, value: string, pct: float}>},
     *     list?: array{title: string, rows: list<array{primary: string, secondary?: string, trailing?: string}>, empty: string},
     * }
     */
    abstract public function getSummary(): array;

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
