<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Concerns\HiddenFromPartnerConsole;
use App\Filament\Concerns\RefreshesOnContentUpdate;
use App\Support\FormatsSummaries;
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
    use FormatsSummaries;

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
}
