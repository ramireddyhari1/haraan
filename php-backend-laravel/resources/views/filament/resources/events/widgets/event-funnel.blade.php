@php($f = $this->getFunnel())
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Conversion funnel</x-slot>
        <x-slot name="description">Viewed → checkout → paid, with drop-off at each step</x-slot>

        <style>
            /* Ink + surfaces from the panel-wide theme (--hrn-*). */
            .efn{--efn-t:var(--hrn-ink);--efn-b:var(--hrn-ink-2);--efn-track:var(--hrn-track);}
            .efn-conv{display:flex;align-items:baseline;gap:8px;margin-bottom:16px;}
            .efn-conv b{font-size:26px;font-weight:800;color:var(--hrn-ok);letter-spacing:-.02em;}
            .efn-conv span{font-size:12.5px;color:var(--efn-b);}
            .efn-stage{margin-bottom:14px;}
            .efn-top{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;}
            .efn-lbl{font-size:13px;font-weight:700;color:var(--efn-t);}
            .efn-cnt{font-size:13px;color:var(--efn-t);font-weight:700;}
            .efn-note{font-size:11px;color:var(--efn-b);font-weight:500;margin-left:6px;}
            .efn-barwrap{height:26px;border-radius:8px;background:var(--efn-track);overflow:hidden;}
            .efn-bar{height:100%;border-radius:8px;background:linear-gradient(90deg,#2563eb,#12b76a);
                display:flex;align-items:center;justify-content:flex-end;padding-right:9px;color:#fff;
                font-size:11.5px;font-weight:700;min-width:34px;transition:width .5s ease;}
            .efn-drop{font-size:11px;color:var(--hrn-down);font-weight:600;margin-top:3px;text-align:right;}
        </style>

        @if (empty($f['stages']))
            <div style="text-align:center;padding:22px 12px;color:var(--hrn-ink-3);font-size:13px;">
                No funnel data yet.
            </div>
        @else
            <div class="efn">
                @if (! is_null($f['conversion']))
                    <div class="efn-conv">
                        <b>{{ $f['conversion'] }}%</b>
                        <span>overall conversion (viewed → paid)</span>
                    </div>
                @else
                    <div class="efn-conv">
                        <span>📈 View tracking started recently — conversion % becomes accurate once tracked views catch up to order volume.</span>
                    </div>
                @endif

                @foreach ($f['stages'] as $s)
                    <div class="efn-stage">
                        <div class="efn-top">
                            <span class="efn-lbl">{{ $s['label'] }}<span class="efn-note">{{ $s['note'] }}</span></span>
                            <span class="efn-cnt">{{ number_format($s['count']) }}</span>
                        </div>
                        <div class="efn-barwrap">
                            <div class="efn-bar" style="width:{{ max(3, $s['pctTop']) }}%;">{{ $s['pctTop'] }}%</div>
                        </div>
                        @if (! is_null($s['drop']) && $s['drop'] > 0)
                            <div class="efn-drop">▼ {{ $s['drop'] }}% drop-off from previous step</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
