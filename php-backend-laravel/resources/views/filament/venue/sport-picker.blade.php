{{-- Venue form → sports offered + main sport, as pressable court tiles. Each tile is the sport's
     drawn court (court-pitch). Tapping adds/removes the sport; "Make main" sets `category`, which
     is always kept inside `sports`. The last sport can't be removed — a venue needs one. --}}
@php
    $statePath = $getStatePath();
    $catPath = preg_replace('/sports$/', 'category', $statePath);
    $known = $sports ?? [];
    // Keep any sport already saved on the venue that the list doesn't know, so editing never drops it.
    $saved = array_filter(array_merge((array) $getState(), [$getRecord()?->category]));
    foreach ($saved as $s) {
        if (is_string($s) && $s !== '' && ! in_array(strtolower($s), array_map('strtolower', $known), true)) {
            $known[] = $s;
        }
    }
    $notes = [
        'cricket' => 'Box net or turf pitch',
        'football' => '5 / 7-a-side turf',
        'badminton' => 'Indoor · 20 × 44 ft',
        'pickleball' => '20 × 44 ft · 7 ft kitchen',
        'basketball' => 'Half or full court',
        'tennis' => 'Hard or clay court',
        'volleyball' => 'Indoor or sand',
    ];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="vf-sp"
        x-data="{
            sports: $wire.$entangle('{{ $statePath }}'),
            main: $wire.$entangle('{{ $catPath }}'),
            nudge: null,
            lc(s) { return String(s || '').toLowerCase() },
            list() { return Array.isArray(this.sports) ? this.sports : (this.sports ? [this.sports] : []) },
            isMain(s) { return this.lc(this.main) === this.lc(s) },
            on(s) { return this.isMain(s) || this.list().some(x => this.lc(x) === this.lc(s)) },
            buzz() { try { navigator.vibrate && navigator.vibrate(8) } catch (e) {} },
            toggle(s) {
                this.buzz();
                if (! this.on(s)) {
                    this.sports = [...this.list(), s];
                    if (! this.main) this.main = s;
                    return;
                }
                const rest = this.list().filter(x => this.lc(x) !== this.lc(s));
                if (this.isMain(s)) {
                    if (! rest.length) { this.nudge = s; setTimeout(() => this.nudge = null, 450); return; }
                    this.main = rest[0];
                }
                this.sports = rest;
            },
            makeMain(s) {
                this.buzz();
                this.main = s;
                if (! this.list().some(x => this.lc(x) === this.lc(s))) this.sports = [...this.list(), s];
            },
        }"
        x-init="if (main && ! list().some(x => lc(x) === lc(main))) sports = [main, ...list()]"
    >
        <div class="vf-sp-grid">
            @foreach ($known as $sport)
                <div
                    role="button" tabindex="0" wire:key="vf-sp-{{ $sport }}"
                    class="vf-sp-tile"
                    :class="{ 'is-on': on(@js($sport)), 'is-main': isMain(@js($sport)), 'is-nudge': nudge === @js($sport) }"
                    :aria-pressed="on(@js($sport)).toString()"
                    x-on:click="toggle(@js($sport))"
                    x-on:keydown.enter.prevent="toggle(@js($sport))"
                    x-on:keydown.space.prevent="toggle(@js($sport))"
                >
                    <div class="vf-sp-art">
                        @include('filament.venue.court-pitch', ['sport' => $sport, 'kind' => null])
                        <span class="vf-sp-tick" aria-hidden="true">
                            <svg viewBox="0 0 16 16" width="12" height="12" fill="none"><path d="M3.5 8.5l3 3 6-7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    </div>
                    <div class="vf-sp-meta">
                        <div class="vf-sp-name">{{ $sport }}</div>
                        <div class="vf-sp-note">{{ $notes[strtolower($sport)] ?? 'Court sport' }}</div>
                    </div>
                    <div class="vf-sp-foot">
                        <span class="vf-sp-badge" x-show="isMain(@js($sport))">Main sport</span>
                        <button type="button" class="vf-sp-make" x-show="on(@js($sport)) && ! isMain(@js($sport))" x-on:click.stop="makeMain(@js($sport))">Make main</button>
                        <span class="vf-sp-add" x-show="! on(@js($sport))">+ Add</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-dynamic-component>
