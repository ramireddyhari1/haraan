<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts\Widgets;

use App\Filament\Widgets\ListSummaryWidget;
use App\Models\ShiftSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shifts list summary: the drawers open right now and what has gone through
 * them, today's closed shifts and their counted variance, and who is on duty.
 */
class ShiftSessionsExecutiveHeroWidget extends ListSummaryWidget
{
    private const METHOD_NAMES = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card'];

    public function getSummary(): array
    {
        $open = ShiftSession::open()->with(['staff:id,name', 'venue:id,name'])->orderBy('opened_at')->get();
        $openIds = $open->pluck('id');

        $taken = DB::table('booking_payments')
            ->whereIn('shift_session_id', $openIds)
            ->selectRaw('lower(method) as m, SUM(amount) as total')
            ->groupBy('m')
            ->pluck('total', 'm');

        $float = (float) $open->sum('opening_float');
        $cash = (float) ($taken['cash'] ?? 0);

        $closedToday = ShiftSession::whereNotNull('closed_at')->whereDate('closed_at', Carbon::today());
        $closedCount = (clone $closedToday)->count();
        $variance = (float) (clone $closedToday)->sum('variance');

        $perShift = DB::table('booking_payments')
            ->whereIn('shift_session_id', $openIds)
            ->selectRaw('shift_session_id, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('shift_session_id')
            ->get()
            ->keyBy('shift_session_id');

        return [
            'title' => 'Shifts & cash drawers',
            'stats' => [
                ['label' => 'Open drawers', 'value' => number_format($open->count())],
                ['label' => 'Cash expected', 'value' => self::inr($float + $cash), 'sub' => self::inr($float) . ' float + ' . self::inr($cash) . ' taken'],
                ['label' => 'Closed today', 'value' => number_format($closedCount)],
                ['label' => 'Variance today', 'value' => $closedCount > 0 ? self::inr($variance) : '—',
                    'sub' => $closedCount > 0 ? 'counted vs expected' : 'no shift closed yet',
                    'tone' => $closedCount > 0 && abs($variance) >= 1 ? 'warn' : null],
            ],
            'split' => [
                'label' => 'Taken in open shifts',
                'parts' => self::parts(collect($taken)
                    ->mapWithKeys(fn ($v, $m) => [self::METHOD_NAMES[$m] ?? ucfirst((string) $m) => (float) $v])
                    ->all()),
            ],
            'list' => [
                'title' => 'On duty',
                'rows' => $open->map(function (ShiftSession $s) use ($perShift): array {
                    $p = $perShift[$s->id] ?? null;

                    return [
                        'primary' => $s->staff?->name ?? 'Unassigned',
                        'secondary' => collect([$s->venue?->name, $s->opened_at ? 'since ' . $s->opened_at->format('g:i A') : null])->filter()->join(' · ') ?: null,
                        'trailing' => $p ? self::inr((float) $p->total) . ' · ' . $p->n . ' ' . str('payment')->plural((int) $p->n) : 'nothing taken yet',
                    ];
                })->all(),
                'empty' => 'No drawer is open right now.',
            ],
        ];
    }
}
