@php
    $statePath = $getStatePath();
    $minuteStep = $getMinuteStep();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="clockTimePicker({
            state: $wire.$entangle('{{ $statePath }}'),
            minuteStep: {{ $minuteStep }},
        })"
        x-on:keydown.escape="open = false"
        @class([
            'ctp',
            'ctp--disabled' => $isDisabled(),
        ])
        {{ $attributes->merge($getExtraAttributes(), escape: false)->class(['relative']) }}
    >
        {{-- Readonly display + clock button --}}
        <button
            type="button"
            x-on:click="{{ $isDisabled() ? '' : 'toggle()' }}"
            :aria-expanded="open"
            @disabled($isDisabled())
            class="ctp-trigger"
        >
            <span class="ctp-trigger__value" x-text="display() || 'Select start time'"
                  :class="{ 'ctp-trigger__value--placeholder': ! display() }"></span>
            <svg class="ctp-trigger__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" />
            </svg>
        </button>

        {{-- Clock popover --}}
        <div x-show="open" x-cloak x-transition.origin.top
             x-on:click.outside="open = false"
             class="ctp-pop">
            {{-- Digital header --}}
            <div class="ctp-head">
                <div class="ctp-head__time">
                    <button type="button" class="ctp-seg"
                            :class="{ 'ctp-seg--active': mode === 'hours' }"
                            x-on:click="mode = 'hours'" x-text="hour"></button>
                    <span class="ctp-colon">:</span>
                    <button type="button" class="ctp-seg"
                            :class="{ 'ctp-seg--active': mode === 'minutes' }"
                            x-on:click="mode = 'minutes'" x-text="pad(minute)"></button>
                </div>
                <div class="ctp-head__period">
                    <button type="button" class="ctp-ap"
                            :class="{ 'ctp-ap--active': period === 'AM' }"
                            x-on:click="period = 'AM'; push()">AM</button>
                    <button type="button" class="ctp-ap"
                            :class="{ 'ctp-ap--active': period === 'PM' }"
                            x-on:click="period = 'PM'; push()">PM</button>
                </div>
            </div>

            {{-- Clock face --}}
            <div class="ctp-face">
                {{-- selector hand --}}
                <div class="ctp-hand" :style="handStyle()">
                    <span class="ctp-hand__knob"></span>
                </div>
                <span class="ctp-center"></span>

                {{-- hour numbers --}}
                <template x-if="mode === 'hours'">
                    <div>
                        <template x-for="h in 12" :key="'h'+h">
                            <button type="button" class="ctp-num"
                                    :class="{ 'ctp-num--sel': hour === h }"
                                    :style="numStyle(h * 30)"
                                    x-on:click="pickHour(h)" x-text="h"></button>
                        </template>
                    </div>
                </template>

                {{-- minute numbers (steps of 5 as labels; hand still lands on exact) --}}
                <template x-if="mode === 'minutes'">
                    <div>
                        <template x-for="m in 12" :key="'m'+m">
                            <button type="button" class="ctp-num"
                                    :class="{ 'ctp-num--sel': minute === ((m % 12) * 5) }"
                                    :style="numStyle(m * 30)"
                                    x-on:click="pickMinute((m % 12) * 5)"
                                    x-text="pad((m % 12) * 5)"></button>
                        </template>
                    </div>
                </template>
            </div>

            <div class="ctp-actions">
                <button type="button" class="ctp-btn ctp-btn--ghost" x-on:click="open = false">Cancel</button>
                <button type="button" class="ctp-btn ctp-btn--primary" x-on:click="push(); open = false">Done</button>
            </div>
        </div>
    </div>

    @once
        <script>
            function clockTimePicker(config) {
                    return {
                        state: config.state,
                        minuteStep: config.minuteStep || 5,
                        open: false,
                        mode: 'hours',
                        hour: 7,
                        minute: 0,
                        period: 'PM',

                        init() {
                            this.parse(this.state);
                            // keep local fields synced if the value is changed elsewhere
                            this.$watch('state', (v) => { if (! this.open) this.parse(v); });
                        },

                        parse(v) {
                            const m = /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i.exec((v || '').trim());
                            if (! m) return;
                            this.hour = Math.min(12, Math.max(1, parseInt(m[1], 10)));
                            this.minute = Math.min(59, Math.max(0, parseInt(m[2], 10)));
                            this.period = m[3].toUpperCase();
                        },

                        pad(n) { return String(n).padStart(2, '0'); },

                        display() {
                            if (! this.state) return '';
                            return this.hour + ':' + this.pad(this.minute) + ' ' + this.period;
                        },

                        push() {
                            this.state = this.hour + ':' + this.pad(this.minute) + ' ' + this.period;
                        },

                        toggle() {
                            this.open = ! this.open;
                            if (this.open) { this.mode = 'hours'; this.parse(this.state || '7:00 PM'); }
                        },

                        pickHour(h) {
                            this.hour = h;
                            this.push();
                            this.mode = 'minutes';
                        },

                        pickMinute(m) {
                            this.minute = m;
                            this.push();
                        },

                        // angle (deg, 0 = 12 o'clock) → absolute position of a number
                        numStyle(angleDeg) {
                            const r = 82; // px from centre
                            const a = (angleDeg - 90) * Math.PI / 180;
                            const x = 110 + r * Math.cos(a);
                            const y = 110 + r * Math.sin(a);
                            return `left:${x}px; top:${y}px;`;
                        },

                        handStyle() {
                            const angle = this.mode === 'hours'
                                ? this.hour * 30
                                : this.minute * 6;
                            return `transform: rotate(${angle}deg);`;
                        },
                    };
                }
            </script>
            <style>
                [x-cloak] { display: none !important; }
                .ctp-trigger {
                    display: flex; align-items: center; justify-content: space-between;
                    width: 100%; gap: .5rem;
                    padding: .5rem .75rem; min-height: 2.75rem;
                    border: 1px solid rgb(209 213 219); border-radius: .5rem;
                    background: #fff; color: rgb(17 24 39); text-align: left;
                    transition: border-color .15s, box-shadow .15s;
                }
                .dark .ctp-trigger { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.12); color: #fff; }
                .ctp-trigger:hover { border-color: rgb(59 130 246); }
                .ctp-trigger:focus-visible { outline: none; border-color: rgb(59 130 246); box-shadow: 0 0 0 2px rgba(59,130,246,.35); }
                .ctp--disabled .ctp-trigger { opacity: .6; cursor: not-allowed; }
                .ctp-trigger__value { font-variant-numeric: tabular-nums; font-size: .95rem; }
                .ctp-trigger__value--placeholder { color: rgb(156 163 175); }
                .ctp-trigger__icon { width: 1.15rem; height: 1.15rem; color: rgb(59 130 246); flex: none; }

                .ctp-pop {
                    position: absolute; z-index: 40; margin-top: .5rem;
                    width: 20rem; max-width: calc(100vw - 2rem);
                    background: #fff; border: 1px solid rgb(229 231 235);
                    border-radius: .9rem; box-shadow: 0 20px 45px -15px rgba(0,0,0,.35);
                    padding: 1rem;
                }
                .dark .ctp-pop { background: rgb(24 27 33); border-color: rgba(255,255,255,.1); }

                .ctp-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
                .ctp-head__time { display: flex; align-items: center; gap: .1rem; }
                .ctp-seg {
                    font-size: 2rem; font-weight: 600; line-height: 1; color: rgb(107 114 128);
                    padding: .1rem .35rem; border-radius: .4rem; font-variant-numeric: tabular-nums;
                }
                .ctp-seg--active { color: rgb(37 99 235); background: rgba(59,130,246,.12); }
                .dark .ctp-seg { color: rgb(148 163 184); }
                .dark .ctp-seg--active { color: rgb(96 165 250); background: rgba(59,130,246,.2); }
                .ctp-colon { font-size: 2rem; font-weight: 600; color: rgb(107 114 128); }
                .ctp-head__period { display: flex; flex-direction: column; gap: .25rem; }
                .ctp-ap {
                    font-size: .72rem; font-weight: 700; letter-spacing: .04em; padding: .25rem .55rem;
                    border: 1px solid rgb(209 213 219); border-radius: .4rem; color: rgb(107 114 128);
                }
                .ctp-ap--active { background: rgb(37 99 235); border-color: rgb(37 99 235); color: #fff; }
                .dark .ctp-ap { border-color: rgba(255,255,255,.15); color: rgb(148 163 184); }

                .ctp-face {
                    position: relative; width: 220px; height: 220px; margin: 0 auto;
                    border-radius: 50%; background: rgb(243 244 246);
                }
                .dark .ctp-face { background: rgba(255,255,255,.05); }
                .ctp-center {
                    position: absolute; left: 110px; top: 110px; width: 8px; height: 8px;
                    background: rgb(37 99 235); border-radius: 50%; transform: translate(-50%, -50%); z-index: 3;
                }
                .ctp-hand {
                    position: absolute; left: 110px; bottom: 110px; width: 2px; height: 82px;
                    background: rgb(37 99 235); transform-origin: bottom center; z-index: 2;
                }
                .ctp-hand__knob {
                    position: absolute; top: -14px; left: 50%; width: 30px; height: 30px;
                    transform: translateX(-50%); background: rgb(37 99 235); border-radius: 50%;
                }
                .ctp-num {
                    position: absolute; width: 34px; height: 34px; transform: translate(-50%, -50%);
                    display: flex; align-items: center; justify-content: center;
                    border-radius: 50%; font-size: .9rem; font-weight: 500; color: rgb(31 41 55);
                    font-variant-numeric: tabular-nums; z-index: 3; user-select: none;
                }
                .dark .ctp-num { color: rgb(226 232 240); }
                .ctp-num--sel { color: #fff; font-weight: 700; }

                .ctp-actions { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1rem; }
                .ctp-btn { font-size: .82rem; font-weight: 600; padding: .45rem .9rem; border-radius: .5rem; }
                .ctp-btn--ghost { color: rgb(107 114 128); }
                .ctp-btn--primary { background: rgb(37 99 235); color: #fff; }
                .ctp-btn--primary:hover { background: rgb(29 78 216); }
            </style>
    @endonce
</x-dynamic-component>
