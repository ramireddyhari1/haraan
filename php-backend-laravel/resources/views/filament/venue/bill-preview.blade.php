{{-- What a customer pays for one hour at the base price, with the fees as checkout adds them
     (Venue::feeRules(): convenience fee first, then each named fee). $base, $rules (label/type/value). --}}
@php
    $lines = [];
    foreach ($rules as $r) {
        $amt = $r['type'] === 'percent' ? round($base * $r['value'] / 100, 2) : (float) $r['value'];
        $lines[] = [$r['label'].($r['type'] === 'percent' ? ' ('.rtrim(rtrim(number_format($r['value'], 2), '0'), '.').'%)' : ''), $amt];
    }
    $total = $base + array_sum(array_column($lines, 1));
    $money = fn (float $v): string => '₹'.(fmod($v, 1.0) == 0.0 ? number_format($v) : number_format($v, 2));
@endphp

<div class="vf-bill" aria-label="Example bill">
    <div class="vf-bill-paper">
        <div class="vf-bill-h"><span>EXAMPLE BILL</span><span>1 HOUR</span></div>
        @if ($base <= 0)
            <div class="vf-bill-row" style="padding: 10px 0">Set a base price above ₹0 to see what a customer pays.</div>
        @else
            <div class="vf-bill-row"><span>{{ $line ?? 'Court, 1 hour' }}</span><b>{{ $money($base) }}</b></div>
            @foreach ($lines as [$label, $amt])
                <div class="vf-bill-row"><span>{{ $label }}</span><b>{{ $money($amt) }}</b></div>
            @endforeach
            <div class="vf-bill-row vf-bill-tot"><span>Customer pays</span><b>{{ $money($total) }}</b></div>
        @endif
    </div>
    <svg class="vf-bill-edge" viewBox="0 0 320 10" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 0 H320 V2 {{ collect(range(0, 31))->map(fn ($i) => 'L'.(320 - $i * 10 - 5).' 9 L'.(320 - ($i + 1) * 10).' 2')->implode(' ') }} Z" fill="var(--vf-surface)" stroke="var(--vf-line)" stroke-width="1"/>
        <rect x="0" y="0" width="320" height="1.5" fill="var(--vf-surface)"/>
    </svg>
    <div class="vf-bill-note">Courts and slots can charge more than the base price.</div>
</div>
