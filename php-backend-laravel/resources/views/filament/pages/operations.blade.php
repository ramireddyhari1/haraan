<x-filament-panels::page>
    {{-- Live state first: an admin opening this page mid-incident needs to see what is off. --}}
    <div @class(['ops-state', 'is-on' => count($active) > 0])>
        <x-hrn-illo name="switchboard" :size="112"
            :style="count($active) > 0 ? '--illo-accent: #dc2626' : null" />
        <div>
            @if (count($active) === 0)
                <p class="ops-title">Everything is running normally</p>
                <p class="ops-sub">No emergency switch is on. Customers can book, pay, create matches and use AI.</p>
            @else
                <p class="ops-title">{{ count($active) === 1 ? '1 switch is on' : count($active) . ' switches are on' }}</p>
                <ul class="ops-list">
                    @foreach ($active as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            @endif
            <p class="ops-foot">
                AI calls today: {{ number_format($aiUsed) }}{{ $aiBudget > 0 ? ' of ' . number_format($aiBudget) : ' (no daily cap)' }}.
                Fees, limits and the rest live under <a href="{{ \App\Filament\Pages\PlatformRulesPage::getUrl() }}">Platform rules</a>.
            </p>
        </div>
    </div>

    <form wire:submit="save">
        {{ $this->form }}
    </form>

    <style>
        .ops-state { display: flex; align-items: center; gap: 18px; padding: 16px 20px; border-radius: 16px;
            background: #fff; border: 1px solid #e6e9f0; }
        .ops-state.is-on { border-color: #fecaca; background: #fff7f7; }
        .ops-title { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; }
        .ops-state.is-on .ops-title { color: #b91c1c; }
        .ops-sub { margin: 4px 0 0; font-size: 13px; color: #475569; }
        .ops-list { margin: 6px 0 0; padding-left: 18px; font-size: 13px; color: #334155; list-style: disc; }
        .ops-foot { margin: 10px 0 0; font-size: 12px; color: #64748b; }
        .ops-foot a { text-decoration: underline; font-weight: 600; }
        .dark .ops-state { background: #111827; border-color: #1f2937; }
        .dark .ops-state.is-on { background: #2a1215; border-color: #7f1d1d; }
        .dark .ops-title { color: #f8fafc; }
        .dark .ops-state.is-on .ops-title { color: #fca5a5; }
        .dark .ops-sub, .dark .ops-list, .dark .ops-foot { color: #94a3b8; }
        @media (max-width: 640px) { .ops-state { flex-direction: column; align-items: flex-start; } }
    </style>
</x-filament-panels::page>
