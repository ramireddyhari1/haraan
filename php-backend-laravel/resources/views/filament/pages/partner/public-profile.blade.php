<x-filament-panels::page>
    @php
        $p = $this->preview();
    @endphp

    {{-- Live mirror of /host/{slug}: what an attendee sees, updated as the form
         below is filled in. Sits first so the page opens on the result. --}}
    <section class="hpv">
        <div class="hpv-bar">
            <span class="hpv-eyebrow">
                <x-filament::icon icon="heroicon-m-eye" class="hpv-eyebrow-ico" />
                Preview — how attendees see your page
            </span>
            <span class="hpv-pill {{ $p['isLive'] ? 'is-live' : 'is-draft' }}">
                {{ $p['isLive'] ? 'Live' : 'Draft' }}
            </span>
        </div>

        <div class="hpv-row">
        <div class="hpv-main">

        <article class="hpv-card">
            <div class="hpv-cover" @if ($p['cover']) style="background-image:url('{{ $p['cover'] }}')" @endif>
                @unless ($p['cover'])
                    <span class="hpv-cover-hint">Add a cover image</span>
                @endunless
            </div>

            <div class="hpv-body">
                <div class="hpv-logo">
                    @if ($p['logo'])
                        <img src="{{ $p['logo'] }}" alt="{{ $p['name'] }}">
                    @else
                        <span>{{ $p['initial'] }}</span>
                    @endif
                </div>

                <h2 class="hpv-name">
                    {{ $p['name'] }}
                    @if ($p['verified'])
                        <x-filament::icon icon="heroicon-s-check-badge" class="hpv-verified"
                            title="Verified {{ $p['isVenue'] ? 'venue' : 'organiser' }}" />
                    @endif
                </h2>

                @if (filled($p['tagline']))
                    <p class="hpv-tag">{{ $p['tagline'] }}</p>
                @else
                    <p class="hpv-tag hpv-muted">Add a tagline to say what you do.</p>
                @endif

                @if (filled($p['city']) || $p['socials'])
                    <div class="hpv-chips">
                        @if (filled($p['city']))
                            <span class="hpv-chip">
                                <x-filament::icon icon="heroicon-m-map-pin" class="hpv-chip-ico" />
                                {{ $p['city'] }}
                            </span>
                        @endif
                        @foreach ($p['socials'] as $s)
                            <span class="hpv-chip">{{ $s['label'] }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="hpv-stats">
                    <span><b>{{ number_format($p['followers']) }}</b> {{ \Illuminate\Support\Str::plural('Follower', $p['followers']) }}</span>
                </div>

                @if (filled($p['about']))
                    <p class="hpv-about">{{ \Illuminate\Support\Str::limit($p['about'], 240) }}</p>
                @else
                    <p class="hpv-about hpv-muted">Your “about” shows here — attendees read it before they book.</p>
                @endif
            </div>
        </article>

        <div class="hpv-foot">
            <code class="hpv-url" title="{{ $p['url'] }}">{{ $p['url'] }}</code>
            <div class="hpv-acts">
                <button type="button" class="hpv-btn" onclick="hpvCopy(this, @js($p['url']))">Copy link</button>
                @if ($p['isLive'])
                    <a href="{{ $p['url'] }}" target="_blank" rel="noopener" class="hpv-btn hpv-btn-primary">Open page</a>
                @endif
            </div>
        </div>

        @if (! $p['isLive'])
            <p class="hpv-todo">
                <x-filament::icon icon="heroicon-o-information-circle" class="hpv-todo-ico" />
                Not visible to attendees yet — {{ implode(', ', $p['missing']) }}.
            </p>
        @endif

        </div>{{-- /hpv-main --}}

        @if (! empty($insights))
            @php
                $v = $insights['views'];
                $f = $insights['followers'];
                $r = $insights['rating'];
                $max = max($v['daily'] ?: [0]) ?: 1;
            @endphp
            {{-- How the page is actually doing, parked beside the preview it
                 describes rather than stranded under it. --}}
            <aside class="hpi">
                <span class="hpi-title">Performance</span>
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
                    <span class="hpi-spark-l">Views · 14d</span>
                </div>
            </aside>
        @endif
        </div>{{-- /hpv-row --}}
    </section>

    <style>
        .hpv{border-radius:18px;padding:14px;background:#f4f7fb;box-shadow:inset 0 0 0 1px #e9edf4;}
        .hpv-bar{display:flex;align-items:center;justify-content:space-between;gap:10px;
            margin:0 4px 10px;}
        .hpv-eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:800;
            letter-spacing:.08em;text-transform:uppercase;color:#7a8394;}
        .hpv-eyebrow-ico{width:14px;height:14px;}
        .hpv-pill{font-size:11px;font-weight:800;letter-spacing:.04em;padding:3px 10px;border-radius:999px;}
        .hpv-pill.is-live{background:rgba(16,185,129,.14);color:#047857;}
        .hpv-pill.is-draft{background:rgba(148,163,184,.22);color:#52606d;}

        /* Preview and its numbers side by side; the rail wraps under on narrow screens. */
        .hpv-row{display:flex;gap:14px;align-items:stretch;}
        .hpv-main{flex:1 1 460px;min-width:0;display:flex;flex-direction:column;}

        .hpi{flex:0 0 216px;display:flex;flex-direction:column;gap:12px;padding:14px;
            border-radius:14px;background:#fff;box-shadow:0 0 0 1px #e7ecf6;}
        .hpi-title{font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#7a8394;}
        .hpi-stats{display:grid;grid-template-columns:1fr 1fr;gap:12px 10px;}
        .hpi-stat{display:flex;flex-direction:column;gap:1px;min-width:0;}
        .hpi-n{font-size:18px;font-weight:800;color:#0b1220;letter-spacing:-.02em;
            font-variant-numeric:tabular-nums;line-height:1.15;}
        .hpi-up{color:#0a7d4e;}
        .hpi-l{font-size:10.5px;color:#6b7382;font-weight:600;white-space:nowrap;
            overflow:hidden;text-overflow:ellipsis;}
        .hpi-spark{position:relative;display:flex;align-items:flex-end;gap:3px;height:46px;
            margin-top:auto;padding-bottom:14px;}
        .hpi-bar{flex:1;background:linear-gradient(180deg,#5aa2f5,#2f6bff);border-radius:3px 3px 1px 1px;min-height:4px;}
        .hpi-spark-l{position:absolute;left:0;bottom:0;font-size:10px;color:#9aa2b1;font-weight:600;}
        .dark .hpi{background:#0f1622;box-shadow:0 0 0 1px #1e2633;}
        .dark .hpi-n{color:#eef1f6;} .dark .hpi-l{color:#8b94a5;}

        .hpv-card{overflow:hidden;border-radius:14px;background:#fff;
            box-shadow:0 10px 24px -18px rgba(11,18,32,.5),0 0 0 1px #e7ecf6;}
        .hpv-cover{height:132px;background:linear-gradient(120deg,#dbe6fb,#eef4ff);
            background-size:cover;background-position:center;display:flex;align-items:center;
            justify-content:center;}
        .hpv-cover-hint{font-size:12px;font-weight:600;color:#8f9bb0;}
        .hpv-body{padding:0 18px 18px;}
        .hpv-logo{width:74px;height:74px;margin-top:-37px;border-radius:50%;overflow:hidden;
            background:#2f6bff;color:#fff;display:flex;align-items:center;justify-content:center;
            font-size:28px;font-weight:800;border:4px solid #fff;
            box-shadow:0 8px 18px -10px rgba(11,18,32,.55);}
        .hpv-logo img{width:100%;height:100%;object-fit:cover;display:block;}
        .hpv-name{display:flex;align-items:center;gap:6px;margin:10px 0 0;font-size:21px;font-weight:800;
            letter-spacing:-.03em;color:#0b1220;line-height:1.2;}
        .hpv-verified{width:18px;height:18px;color:#2f6bff;flex:none;}
        .hpv-tag{margin:5px 0 0;font-size:13.5px;color:#4b5565;line-height:1.5;}
        .hpv-muted{color:#9aa2b1;font-style:italic;}
        .hpv-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:11px;}
        .hpv-chip{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:600;
            color:#42506b;background:#eef2f9;padding:4px 10px;border-radius:999px;}
        .hpv-chip-ico{width:12px;height:12px;color:#7a8394;}
        .hpv-stats{margin-top:11px;font-size:12.5px;color:#6b7382;}
        .hpv-stats b{color:#0b1220;font-weight:800;font-variant-numeric:tabular-nums;}
        .hpv-about{margin:11px 0 0;font-size:13px;line-height:1.6;color:#4b5565;}

        .hpv-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;
            flex-wrap:wrap;margin:11px 4px 0;}
        .hpv-url{font-size:12px;color:#6b7382;background:#fff;padding:5px 10px;border-radius:8px;
            box-shadow:inset 0 0 0 1px #e7ecf6;max-width:100%;overflow:hidden;text-overflow:ellipsis;
            white-space:nowrap;}
        .hpv-acts{display:flex;gap:8px;}
        .hpv-btn{font-size:12.5px;font-weight:600;padding:6px 13px;border-radius:9px;border:0;
            cursor:pointer;text-decoration:none;color:#42506b;background:#fff;
            box-shadow:inset 0 0 0 1px #dfe5f0;transition:background .15s;}
        .hpv-btn:hover{background:#eef2f9;}
        .hpv-btn-primary{background:linear-gradient(180deg,#2f6bff,#1e50e6);color:#fff;box-shadow:none;}
        .hpv-btn-primary:hover{filter:brightness(1.06);background:linear-gradient(180deg,#2f6bff,#1e50e6);}
        .hpv-todo{display:flex;align-items:flex-start;gap:6px;margin:10px 4px 0;font-size:12.5px;
            line-height:1.5;color:#8a6d1f;}
        .hpv-todo-ico{width:15px;height:15px;flex:none;margin-top:1px;color:#b8860b;}

        .dark .hpv{background:#141b28;box-shadow:inset 0 0 0 1px #1e2633;}
        .dark .hpv-card{background:#0f1622;box-shadow:0 0 0 1px #1e2633;}
        .dark .hpv-name{color:#eef1f6;} .dark .hpv-tag,.dark .hpv-about{color:#aab3c2;}
        .dark .hpv-chip{background:#1a2331;color:#c3cbd8;}
        .dark .hpv-logo{border-color:#0f1622;}
        .dark .hpv-url,.dark .hpv-btn{background:#0f1622;color:#aab3c2;box-shadow:inset 0 0 0 1px #1e2633;}

        /* Below ~1000px the rail can't hold two number columns beside the card —
           drop it under the preview as a single row of stats. */
        @media (max-width:1000px){
            .hpv-row{flex-wrap:wrap;}
            .hpi{flex:1 1 100%;}
            .hpi-stats{grid-template-columns:repeat(auto-fit,minmax(84px,1fr));}
            .hpi-spark{margin-top:0;}
        }

        @media (max-width:640px){
            .hpv-cover{height:104px;}
            .hpv-logo{width:62px;height:62px;margin-top:-31px;font-size:23px;}
            .hpv-name{font-size:18px;}
            .hpv-foot{flex-direction:column;align-items:stretch;}
            .hpv-acts{justify-content:flex-end;}
        }
    </style>

    <script>
        window.hpvCopy = window.hpvCopy || function (btn, url) {
            var done = function () {
                var old = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = old; }, 1400);
            };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(done, done);
                return;
            }
            var t = document.createElement('textarea');
            t.value = url; document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(t); done();
        };
    </script>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Save profile
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
