{{--
    Every payment attributed to one shift, oldest first — the trail an owner reads
    when a drawer comes up short.

    Cash is what the count has to account for, so it is weighted; UPI and card are
    shown dimmed because they never touched the drawer.
--}}
@php
    $inr = function (float $n): string {
        $sign = $n < 0 ? '-' : '';
        $str = (string) (int) round(abs($n));
        if (strlen($str) <= 3) {
            return $sign . '₹' . $str;
        }
        $last3 = substr($str, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3));

        return $sign . '₹' . $rest . ',' . $last3;
    };
@endphp

<div class="divide-y divide-gray-200 dark:divide-white/10">
    @foreach ($payments as $payment)
        @php
            $isCash = in_array($payment->method, \App\Models\ShiftSession::DRAWER_METHODS, true);
            $isRefund = (float) $payment->amount < 0;
        @endphp
        <div class="flex items-center gap-3 px-1 py-2.5">
            <div class="w-16 shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                {{ $payment->collected_at?->format('g:i A') }}
            </div>

            <div class="min-w-0 flex-1">
                <p @class([
                    'truncate text-sm',
                    'font-medium text-gray-950 dark:text-white' => $isCash,
                    'text-gray-500 dark:text-gray-400' => ! $isCash,
                ])>
                    {{ $isRefund ? 'Refund' : ucfirst($payment->method) }}
                    @if ($payment->booking)
                        · {{ $payment->booking->guest_name ?: ($payment->booking->slot_label ?: 'Booking #' . $payment->booking->id) }}
                    @endif
                </p>
                @if ($payment->reference || $payment->note)
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ collect([$payment->reference, $payment->note])->filter()->implode(' · ') }}
                    </p>
                @endif
            </div>

            @unless ($isCash)
                <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">not in drawer</span>
            @endunless

            <div @class([
                'shrink-0 text-sm tabular-nums',
                'font-semibold text-gray-950 dark:text-white' => $isCash,
                'text-gray-400 dark:text-gray-500' => ! $isCash,
            ])>
                {{ $inr((float) $payment->amount) }}
            </div>
        </div>
    @endforeach

    <div class="space-y-1.5 px-1 pt-3">
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500 dark:text-gray-400">Opening float</span>
            <span class="tabular-nums text-gray-950 dark:text-white">{{ $inr((float) $shift->opening_float) }}</span>
        </div>
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500 dark:text-gray-400">Cash taken this shift</span>
            <span class="tabular-nums text-gray-950 dark:text-white">{{ $inr($shift->cashMovement()) }}</span>
        </div>
        <div class="flex items-center justify-between border-t border-gray-200 pt-1.5 text-sm dark:border-white/10">
            <span class="font-medium text-gray-950 dark:text-white">Expected in drawer</span>
            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $inr($shift->expectedCash()) }}</span>
        </div>

        @if (! $shift->isOpen())
            @php $variance = $shift->currentVariance(); @endphp
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">Counted</span>
                <span class="tabular-nums text-gray-950 dark:text-white">{{ $inr((float) $shift->counted_cash) }}</span>
            </div>
            <div @class([
                'flex items-center justify-between text-sm font-semibold',
                'text-success-600 dark:text-success-400' => $variance !== null && abs($variance) < 0.01,
                'text-danger-600 dark:text-danger-400' => $variance !== null && $variance < -0.01,
                'text-warning-600 dark:text-warning-400' => $variance !== null && $variance > 0.01,
            ])>
                <span>{{ $shift->varianceLabel() }}</span>
                <span class="tabular-nums">{{ $variance === null ? '—' : $inr(abs($variance)) }}</span>
            </div>
        @endif
    </div>
</div>
