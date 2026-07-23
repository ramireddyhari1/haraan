<x-filament-panels::page>
    @if (! empty($insights))
        @php
            $v = $insights['views'];
            $f = $insights['followers'];
            $r = $insights['rating'];
            $max = max($v['daily'] ?: [0]) ?: 1;
        @endphp
        <div class="hpi">
            <div class="hpi-stats">
                <div class="hpi-stat"><span class="hpi-n">{{ number_format($v['total']) }}</span><span class="hpi-l">Page views</span></div>
                <div class="hpi-stat"><span class="hpi-n">{{ number_format($v['last7']) }}</span><span class="hpi-l">Views · 7d</span></div>
                <div class="hpi-stat"><span class="hpi-n">{{ number_format($f['total']) }}</span><span class="hpi-l">Followers</span></div>
                <div class="hpi-stat"><span class="hpi-n hpi-up">+{{ number_format($f['new7']) }}</span><span class="hpi-l">New · 7d</span></div>
                <div class="hpi-stat"><span class="hpi-n">{{ $r['avg'] !== null ? '★ '.number_format($r['avg'],1) : '—' }}</span><span class="hpi-l">Rating ({{ number_format($r['count']) }})</span></div>
            </div>
            <div class="hpi-spark" title="Views · last 14 days">
                @foreach ($v['daily'] as $d)
                    <span class="hpi-bar" style="height:{{ max(6, (int) round($d / $max * 100)) }}%"></span>
                @endforeach
            </div>
        </div>
        <style>
            .hpi{display:flex;gap:18px;align-items:center;justify-content:space-between;flex-wrap:wrap;
                padding:16px 18px;border-radius:14px;background:#f4f7fb;box-shadow:inset 0 0 0 1px #e9edf4;margin-bottom:6px;}
            .hpi-stats{display:flex;gap:22px;flex-wrap:wrap;}
            .hpi-stat{display:flex;flex-direction:column;gap:2px;}
            .hpi-n{font-size:20px;font-weight:800;color:#0b1220;letter-spacing:-.02em;font-variant-numeric:tabular-nums;}
            .hpi-up{color:#0a7d4e;}
            .hpi-l{font-size:11.5px;color:#6b7382;font-weight:600;}
            .hpi-spark{display:flex;align-items:flex-end;gap:3px;height:44px;min-width:150px;}
            .hpi-bar{flex:1;background:linear-gradient(180deg,#5aa2f5,#2f6bff);border-radius:3px 3px 1px 1px;min-height:4px;}
            .dark .hpi{background:#141b28;box-shadow:inset 0 0 0 1px #1e2633;}
            .dark .hpi-n{color:#eef1f6;} .dark .hpi-l{color:#8b94a5;}
            @media (max-width:640px){.hpi-stats{gap:16px;}.hpi-n{font-size:17px;}}
        </style>
    @endif

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Save profile
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
