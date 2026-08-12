{{--
    Payment history for one booking — the ledger, read back.

    Refunds are negative rows, so they render red and signed; collections render
    green. The running total at the bottom is the same SUM that derives
    bookings.amount_paid, shown so a partner can reconcile by eye.
--}}
@php
    $inr = function (float $n): string {
        $sign = $n < 0 ? '-' : '';
        $n = (int) round(abs($n));
        $str = (string) $n;
        if (strlen($str) <= 3) {
            return $sign . '₹' . $str;
        }
        $last3 = substr($str, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3));

        return $sign . '₹' . $rest . ',' . $last3;
    };
@endphp

<div class="fi-ta-ctn divide-y divide-gray-200 dark:divide-white/10">
    @foreach ($payments as $payment)
        @php $isRefund = (float) $payment->amount < 0; @endphp
        <div class="flex items-center gap-3 px-1 py-3">
            <div @class([
                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400' => ! $isRefund,
                'bg-danger-50 text-danger-600 dark:bg-danger-500/10 dark:text-danger-400' => $isRefund,
            ])>
                <x-filament::icon
                    :icon="$isRefund ? 'heroicon-m-arrow-uturn-left' : 'heroicon-m-banknotes'"
                    class="h-4 w-4"
                />
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-950 dark:text-white">
                    {{ $isRefund ? 'Refunded' : 'Collected' }}
                    <span class="font-normal text-gray-500 dark:text-gray-400">
                        · {{ ucfirst($payment->method) }}
                    </span>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $payment->collected_at?->format('d M Y, g:i A') }}
                    @if ($payment->collector)
                        · taken by {{ $payment->collector->name }}
                    @endif
                    @if ($payment->reference)
                        · {{ $payment->reference }}
                    @endif
                </p>
                @if ($payment->note)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $payment->note }}</p>
                @endif
            </div>

            <div @class([
                'shrink-0 text-sm font-semibold tabular-nums',
                'text-success-600 dark:text-success-400' => ! $isRefund,
                'text-danger-600 dark:text-danger-400' => $isRefund,
            ])>
                {{ $inr((float) $payment->amount) }}
            </div>
        </div>
    @endforeach

    <div class="flex items-center justify-between px-1 pt-3">
        <span class="text-sm text-gray-500 dark:text-gray-400">Collected of {{ $inr((float) $booking->total_amount) }}</span>
        <span class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">
            {{ $inr((float) $booking->amount_paid) }}
        </span>
    </div>

    @if ($booking->hasBalanceDue())
        <div class="flex items-center justify-between px-1 pt-3">
            <span class="text-sm font-medium text-warning-600 dark:text-warning-400">Still due</span>
            <span class="text-sm font-semibold tabular-nums text-warning-600 dark:text-warning-400">
                {{ $inr($booking->balanceDue()) }}
            </span>
        </div>
    @endif
</div>
