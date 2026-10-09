{{-- The week drawn from the Operating-hours rows as they are being edited. $rows (list of day/open/close), $slotMinutes. --}}
@php
    $order = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $toMin = function ($t): ?int {
        if (! is_string($t) || ! preg_match('/^(\d{1,2}):(\d{2})/', $t, $m)) { return null; }
        return (int) $m[1] * 60 + (int) $m[2];
    };
    $fmt = fn (int $min): string => \Illuminate\Support\Carbon::createFromTime(intdiv($min % 1440, 60), $min % 60)->format('g:i A');
    $byDay = array_fill_keys($order, []);
    foreach ((array) $rows as $r) {
        $d = $r['day'] ?? null; $o = $toMin($r['open'] ?? null); $c = $toMin($r['close'] ?? null);
        if ($d && isset($byDay[$d]) && $o !== null && $c !== null && $o !== $c) {
            $byDay[$d][] = [$o, $c <= $o ? $c + 1440 : $c];
        }
    }
    $openDays = count(array_filter($byDay));
    $weekMin = 0;
    foreach ($byDay as $spans) { foreach ($spans as [$o, $c]) { $weekMin += $c - $o; } }
    $L = 46; $W = 584; $rowH = 24; $top = 18;
    $x = fn (int $min): float => $L + min(1440, max(0, $min)) / 1440 * $W;
    $nowDay = \App\Support\BusinessClock::now()->format('D');
    $nowMin = \App\Support\BusinessClock::now()->hour * 60 + \App\Support\BusinessClock::now()->minute;
@endphp

<div class="vf-week">
    <div class="vf-week-cap">
        <span>
            @if ($openDays === 0)
                No open days yet — add a day below and the week fills in.
            @else
                Open <b>{{ $openDays }}</b> {{ $openDays === 1 ? 'day' : 'days' }} · <b>{{ rtrim(rtrim(number_format($weekMin / 60, 1), '0'), '.') }}</b> hours a week
            @endif
        </span>
        <span>Slots every <b>{{ $slotMinutes }} min</b></span>
    </div>
    <svg viewBox="0 0 640 {{ $top + 7 * $rowH + 6 }}" role="img" aria-label="Opening hours across the week">
        @foreach ([0, 360, 720, 1080, 1440] as $t)
            <line x1="{{ $x($t) }}" x2="{{ $x($t) }}" y1="{{ $top - 4 }}" y2="{{ $top + 7 * $rowH }}" stroke="var(--vf-line)" stroke-width="1" stroke-dasharray="{{ in_array($t, [0, 1440]) ? '0' : '2 3' }}"/>
            <text x="{{ $x($t) }}" y="10" text-anchor="middle" style="fill: var(--vf-ink-3); font-size: 10px">{{ [0 => '12 AM', 360 => '6 AM', 720 => '12 PM', 1080 => '6 PM', 1440 => '12 AM'][$t] }}</text>
        @endforeach

        @foreach ($order as $i => $d)
            @php $y = $top + $i * $rowH; @endphp
            <text x="0" y="{{ $y + 15 }}" style="fill: {{ $d === $nowDay ? 'var(--vf-blue)' : 'var(--vf-ink-2)' }}; font-size: 11.5px; font-weight: {{ $d === $nowDay ? 700 : 600 }}">{{ $d }}</text>
            <rect x="{{ $L }}" y="{{ $y + 4 }}" width="{{ $W }}" height="{{ $rowH - 8 }}" rx="5" fill="var(--vf-surface)" stroke="var(--vf-line-2)"/>
            @if (empty($byDay[$d]))
                <text x="{{ $L + $W / 2 }}" y="{{ $y + 15.5 }}" text-anchor="middle" style="fill: var(--vf-ink-3); font-size: 10.5px; letter-spacing: .08em">CLOSED</text>
            @endif
            @foreach ($byDay[$d] as [$o, $c])
                <rect x="{{ $x($o) }}" y="{{ $y + 5 }}" width="{{ max(3, $x($c) - $x($o)) }}" height="{{ $rowH - 10 }}" rx="4" fill="var(--vf-blue)">
                    <title>{{ $d }} {{ $fmt($o) }} – {{ $fmt($c) }}{{ $c > 1440 ? ' (next day)' : '' }}</title>
                </rect>
                <text x="{{ $x($o) + 6 }}" y="{{ $y + 15.5 }}" style="fill: #fff; font-size: 10px; font-weight: 600">{{ $fmt($o) }}</text>
                @if ($c > 1440)
                    {{-- Past midnight: the spill-over hours land at the start of the next day's row --}}
                    @php $ny = $top + (($i + 1) % 7) * $rowH; @endphp
                    <rect x="{{ $x(0) }}" y="{{ $ny + 5 }}" width="{{ max(3, $x($c - 1440) - $x(0)) }}" height="{{ $rowH - 10 }}" rx="4" fill="var(--vf-blue)" opacity=".45">
                        <title>{{ $d }} night, until {{ $fmt($c) }}</title>
                    </rect>
                @else
                    <text x="{{ $x($c) - 6 }}" y="{{ $y + 15.5 }}" text-anchor="end" style="fill: #fff; font-size: 10px; font-weight: 600">{{ $x($c) - $x($o) > 120 ? $fmt($c) : '' }}</text>
                @endif
            @endforeach
            @if ($d === $nowDay)
                <line x1="{{ $x($nowMin) }}" x2="{{ $x($nowMin) }}" y1="{{ $y + 2 }}" y2="{{ $y + $rowH - 2 }}" stroke="var(--vf-amber)" stroke-width="2" stroke-linecap="round"><title>Now</title></line>
            @endif
        @endforeach
    </svg>
</div>
