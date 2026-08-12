{{-- Partner Reviews: rating summary → filters → feed.
     Self-contained markup + inline CSS (no Vite rebuild), matching the Payouts /
     Earnings console language. Read-only: partners never edit customer words. --}}
@php
    $isEvents = $this->isEventLane();
    $summary = $this->getSummary();
    $venues = $isEvents ? collect() : $this->venues();
    $reviews = $this->getReviews();
    $ratedEvents = $this->getRatedEvents();
@endphp

<x-filament-panels::page>
    <div class="prv">

        {{-- ---------- Rating summary ---------- --}}
        <section class="prv-hero">
            <div class="prv-hero-bg" aria-hidden="true"></div>
            <div class="prv-hero-in">
                <div class="prv-score">
                    <span class="prv-score-lab">Overall rating</span>
                    <div class="prv-score-val">
                        {{ $summary['total'] > 0 ? $summary['average'] : '—' }}
                        <span class="prv-score-out">/ 5</span>
                    </div>
                    <div class="prv-stars prv-stars-lg" aria-label="{{ $summary['average'] }} out of 5">
                        @for ($i = 1; $i <= 5; $i++)
                            <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"
                                class="{{ $i <= round((float) $summary['average']) ? 'is-on' : '' }}">
                                <path d="M12 3.2l2.6 5.5 6 .85-4.35 4.2 1.03 5.95L12 16.9l-5.28 2.8 1.03-5.95L3.4 9.55l6-.85L12 3.2z"
                                    fill="currentColor"/>
                            </svg>
                        @endfor
                    </div>
                    <span class="prv-score-sub">
                        {{ number_format($summary['total']) }}
                        {{ $isEvents ? \Illuminate\Support\Str::plural('rating', $summary['total']) : \Illuminate\Support\Str::plural('review', $summary['total']) }}
                        {{ $isEvents ? 'across your events' : 'across your venues' }}
                    </span>
                </div>

                @if (! $isEvents && $summary['total'] > 0)
                    {{-- Breakdown doubles as a filter: tap a bar to see only those stars. --}}
                    <div class="prv-dist">
                        @foreach ($summary['distribution'] as $row)
                            <button type="button" class="prv-dist-row {{ $this->stars === $row['stars'] ? 'is-on' : '' }}"
                                wire:click="filterStars({{ $row['stars'] }})"
                                aria-label="{{ $row['stars'] }} star reviews">
                                <span class="prv-dist-lab">{{ $row['stars'] }}★</span>
                                <span class="prv-dist-track">
                                    <span class="prv-dist-fill" style="width:{{ $row['percent'] }}%"></span>
                                </span>
                                <span class="prv-dist-num">{{ $row['count'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- ---------- Venue filter ---------- --}}
        @if (! $isEvents && $venues->count() > 1)
            <div class="prv-chips">
                <button type="button" class="prv-chip {{ $this->subjectId === null ? 'is-on' : '' }}"
                    wire:click="filterSubject(null)">All venues</button>
                @foreach ($venues as $venue)
                    <button type="button" class="prv-chip {{ $this->subjectId === $venue->id ? 'is-on' : '' }}"
                        wire:click="filterSubject({{ $venue->id }})">{{ $venue->name }}</button>
                @endforeach
            </div>
        @endif

        {{-- ---------- Feed ---------- --}}
        <section class="prv-card">
            <div class="prv-card-head">
                <div>
                    <h2 class="prv-card-title">{{ $isEvents ? 'Event ratings' : 'What customers said' }}</h2>
                    <p class="prv-card-sub">
                        @if ($isEvents)
                            How each of your events scored with attendees.
                        @elseif ($this->stars)
                            Showing {{ $this->stars }}-star reviews only.
                        @else
                            Newest first, across every venue you run.
                        @endif
                    </p>
                </div>

                @if (! $isEvents && $this->stars)
                    <button type="button" class="prv-btn-ghost" wire:click="filterStars({{ $this->stars }})">
                        Clear filter
                    </button>
                @endif
            </div>

            @if ($isEvents)
                @forelse ($ratedEvents as $event)
                    <div class="prv-erow">
                        <span class="prv-escore">{{ $event['ratingLabel'] }}</span>
                        <span class="prv-emeta">
                            <span class="prv-etitle">{{ $event['title'] }}</span>
                            <span class="prv-esub">
                                @if ($event['date']) {{ $event['date'] }} @endif
                                @if ($event['city']) · {{ $event['city'] }} @endif
                                · {{ number_format($event['count']) }}
                                {{ \Illuminate\Support\Str::plural('rating', $event['count']) }}
                            </span>
                        </span>
                        <span class="prv-stars" aria-hidden="true">
                            @for ($i = 1; $i <= 5; $i++)
                                <svg viewBox="0 0 24 24" width="14" height="14"
                                    class="{{ $i <= round($event['rating']) ? 'is-on' : '' }}">
                                    <path d="M12 3.2l2.6 5.5 6 .85-4.35 4.2 1.03 5.95L12 16.9l-5.28 2.8 1.03-5.95L3.4 9.55l6-.85L12 3.2z"
                                        fill="currentColor"/>
                                </svg>
                            @endfor
                        </span>
                    </div>
                @empty
                    <div class="prv-empty">
                        <span class="prv-empty-ic">
                            <svg viewBox="0 0 24 24" fill="none" width="22" height="22" aria-hidden="true">
                                <path d="M12 3.5l2.7 5.7 6.3.9-4.5 4.4 1.06 6.2L12 17.8l-5.56 2.9L7.5 14.5 3 10.1l6.3-.9L12 3.5z"
                                    stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <p class="prv-empty-t">No ratings yet</p>
                        <p class="prv-empty-s">
                            Once your events are rated, every score shows up here with how many people rated it.
                        </p>
                    </div>
                @endforelse
            @else
                @forelse ($reviews as $review)
                    <article class="prv-item">
                        @if ($review['avatar'])
                            <img src="{{ $review['avatar'] }}" alt="{{ $review['name'] }}" class="prv-av prv-av-img">
                        @else
                            <span class="prv-av" style="background:hsl({{ $review['hue'] }} 52% 46%)">
                                {{ $review['initial'] }}
                            </span>
                        @endif

                        <div class="prv-body">
                            <div class="prv-item-top">
                                <span class="prv-name">{{ $review['name'] }}</span>
                                <span class="prv-stars" aria-label="{{ $review['rating'] }} out of 5">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"
                                            class="{{ $i <= $review['rating'] ? 'is-on' : '' }}">
                                            <path d="M12 3.2l2.6 5.5 6 .85-4.35 4.2 1.03 5.95L12 16.9l-5.28 2.8 1.03-5.95L3.4 9.55l6-.85L12 3.2z"
                                                fill="currentColor"/>
                                        </svg>
                                    @endfor
                                </span>
                            </div>

                            <p class="prv-text">{{ $review['text'] }}</p>

                            <p class="prv-item-sub">
                                @if ($review['venue']) {{ $review['venue'] }} @endif
                                @if ($review['venue'] && $review['ago']) · @endif
                                @if ($review['ago']) {{ $review['ago'] }} @endif
                            </p>
                        </div>
                    </article>
                @empty
                    <div class="prv-empty">
                        <span class="prv-empty-ic">
                            <svg viewBox="0 0 24 24" fill="none" width="22" height="22" aria-hidden="true">
                                <path d="M4 5h16v11H9l-5 4V5z" stroke="currentColor" stroke-width="1.7"
                                    stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <p class="prv-empty-t">
                            {{ $this->stars ? 'No ' . $this->stars . '-star reviews' : 'No reviews yet' }}
                        </p>
                        <p class="prv-empty-s">
                            @if ($this->stars)
                                Nothing at this rating for the current selection.
                            @else
                                When customers review your venues, their words land here — rating, comment and all.
                            @endif
                        </p>
                    </div>
                @endforelse
            @endif
        </section>
    </div>

    <style>
        .prv{display:flex;flex-direction:column;gap:16px;}

        /* ---- hero ---- */
        .prv-hero{position:relative;overflow:hidden;border-radius:20px;
            box-shadow:0 18px 40px -22px rgba(21,71,170,.55);
            animation:prv-in .45s cubic-bezier(.22,.61,.36,1) both;}
        .prv-hero-bg{position:absolute;inset:0;
            background:radial-gradient(120% 140% at 0% 0%,#3d9bff 0%,#2563eb 44%,#1a4fd0 100%);}
        .prv-hero-bg::after{content:"";position:absolute;inset:0;
            background:radial-gradient(80% 120% at 100% 0%,rgba(255,255,255,.22),transparent 60%);}
        .prv-hero-in{position:relative;padding:20px 22px 22px;color:#fff;
            display:flex;align-items:center;gap:26px;flex-wrap:wrap;}
        .prv-score{min-width:170px;}
        .prv-score-lab{font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;
            color:rgba(255,255,255,.82);}
        .prv-score-val{font-size:40px;font-weight:800;letter-spacing:-.035em;line-height:1.05;
            margin:8px 0 6px;font-variant-numeric:tabular-nums;}
        .prv-score-out{font-size:17px;font-weight:700;color:rgba(255,255,255,.7);letter-spacing:0;}
        .prv-score-sub{display:block;font-size:12.5px;color:rgba(255,255,255,.8);margin-top:7px;}

        /* ---- stars ---- */
        .prv-stars{display:inline-flex;align-items:center;gap:2px;color:#d7dced;}
        .prv-stars svg.is-on{color:#f7b955;}
        .prv-stars svg{color:rgba(255,255,255,.32);}
        .prv-stars-lg svg{color:rgba(255,255,255,.3);}
        .prv-stars-lg svg.is-on{color:#ffd27d;}

        /* ---- distribution ---- */
        .prv-dist{display:flex;flex-direction:column;gap:5px;flex:1;min-width:220px;}
        .prv-dist-row{display:flex;align-items:center;gap:9px;border:0;background:transparent;
            cursor:pointer;padding:2px 4px;border-radius:8px;transition:background .15s;width:100%;}
        .prv-dist-row:hover{background:rgba(255,255,255,.1);}
        .prv-dist-row.is-on{background:rgba(255,255,255,.18);}
        .prv-dist-lab{font-size:12px;font-weight:700;color:rgba(255,255,255,.85);width:26px;flex:none;
            text-align:left;}
        .prv-dist-track{flex:1;height:7px;border-radius:999px;background:rgba(255,255,255,.22);
            overflow:hidden;}
        .prv-dist-fill{display:block;height:100%;border-radius:999px;background:#ffd27d;}
        .prv-dist-num{font-size:12px;font-weight:700;color:rgba(255,255,255,.85);width:28px;flex:none;
            text-align:right;font-variant-numeric:tabular-nums;}

        /* ---- venue chips ---- */
        .prv-chips{display:flex;flex-wrap:wrap;gap:7px;}
        .prv-chip{border:0;cursor:pointer;font-size:12.5px;font-weight:700;color:#475467;
            padding:7px 13px;border-radius:999px;background:#f1f4f9;
            box-shadow:inset 0 0 0 1px #e5e9f1;transition:background .15s,color .15s;}
        .prv-chip:hover{background:#e7ebf2;}
        .prv-chip.is-on{background:linear-gradient(180deg,#2f6bff,#1e50e6);color:#fff;
            box-shadow:0 8px 18px -10px rgba(37,99,235,.6);}

        /* ---- card ---- */
        .prv-card{background:#fff;border:1px solid #ebedf2;border-radius:16px;padding:16px 18px 18px;
            box-shadow:0 1px 2px rgba(11,18,32,.05);
            animation:prv-in .45s cubic-bezier(.22,.61,.36,1) both;animation-delay:.06s;}
        .prv-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
            margin-bottom:14px;}
        .prv-card-title{font-size:15px;font-weight:800;color:#0b1220;letter-spacing:-.01em;margin:0;}
        .prv-card-sub{font-size:12.5px;color:#98a2b3;margin:3px 0 0;}
        .prv-btn-ghost{border:0;cursor:pointer;font-size:12.5px;font-weight:700;padding:7px 13px;
            border-radius:10px;background:#f1f4f9;color:#475467;box-shadow:inset 0 0 0 1px #e5e9f1;}
        .prv-btn-ghost:hover{background:#e7ebf2;}

        /* ---- review item ---- */
        .prv-item{display:flex;gap:12px;padding:14px 4px;border-top:1px solid #f0f2f6;}
        .prv-item:first-of-type{border-top:0;padding-top:2px;}
        .prv-av{width:36px;height:36px;border-radius:50%;flex:none;display:flex;align-items:center;
            justify-content:center;color:#fff;font-weight:700;font-size:14px;overflow:hidden;}
        .prv-av-img{object-fit:cover;display:block;}
        .prv-body{min-width:0;flex:1;}
        .prv-item-top{display:flex;align-items:center;gap:9px;flex-wrap:wrap;}
        .prv-name{font-size:14px;font-weight:800;color:#0b1220;}
        .prv-text{font-size:13.5px;color:#475467;line-height:1.55;margin:6px 0 0;
            overflow-wrap:anywhere;}
        .prv-item-sub{font-size:12px;color:#98a2b3;margin:6px 0 0;}

        /* ---- event rating row ---- */
        .prv-erow{display:flex;align-items:center;gap:12px;padding:12px 4px;
            border-top:1px solid #f0f2f6;}
        .prv-erow:first-of-type{border-top:0;padding-top:2px;}
        .prv-escore{display:inline-flex;align-items:center;justify-content:center;width:44px;height:38px;
            border-radius:11px;flex:none;font-size:15px;font-weight:800;color:#b06d09;background:#fff6e8;
            box-shadow:inset 0 0 0 1px rgba(194,121,10,.18);font-variant-numeric:tabular-nums;}
        .prv-emeta{display:flex;flex-direction:column;min-width:0;flex:1;}
        .prv-etitle{font-size:14px;font-weight:700;color:#0b1220;
            overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .prv-esub{font-size:12px;color:#98a2b3;margin-top:2px;
            overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

        /* ---- empty ---- */
        .prv-empty{text-align:center;padding:22px 12px 8px;}
        .prv-empty-ic{display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;
            border-radius:14px;color:#98a2b3;background:#f4f6fa;box-shadow:inset 0 0 0 1px #e9edf4;}
        .prv-empty-t{font-size:14px;font-weight:800;color:#0b1220;margin:11px 0 0;}
        .prv-empty-s{font-size:12.5px;color:#98a2b3;margin:4px auto 0;max-width:36ch;line-height:1.5;}

        @keyframes prv-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
        @media (prefers-reduced-motion:reduce){.prv-hero,.prv-card{animation:none;}}
        @media (max-width:640px){
            .prv-hero-in{gap:16px;}
            .prv-score-val{font-size:34px;}
            .prv-dist{min-width:100%;}
            .prv-card-head{flex-direction:column;}
        }
    </style>
</x-filament-panels::page>
