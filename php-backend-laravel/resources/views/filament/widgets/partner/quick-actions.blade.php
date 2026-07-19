{{-- Partner home action bar: greeting + a row of lane-aware quick-launch buttons.
     Inline styles (theme-agnostic, dark-aware) keep it self-contained like the
     other bespoke summary strips in this panel. --}}
<x-filament-widgets::widget>
    <div class="pqa">
        <div class="pqa-hi">
            <div class="pqa-greet">{{ $this->getGreeting() }} 👋</div>
            <div class="pqa-sub">Here’s your business at a glance.</div>
        </div>

        <div class="pqa-actions">
            @foreach ($this->getActions() as $action)
                <a href="{{ $action['url'] }}" @class(['pqa-btn', 'pqa-btn-primary' => $action['primary'] ?? false])>
                    <x-filament::icon :icon="$action['icon']" class="pqa-ic" />
                    <span>{{ $action['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <style>
        .pqa{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
            background:#fff;border-radius:16px;padding:16px 18px;
            box-shadow:0 1px 3px rgba(11,18,32,.06),0 0 0 1px rgba(120,120,120,.11);}
        .pqa-greet{font-size:18px;font-weight:800;letter-spacing:-.01em;color:#111827;}
        .pqa-sub{font-size:13px;color:#6b7280;margin-top:2px;}
        .pqa-actions{display:flex;gap:10px;flex-wrap:wrap;}
        .pqa-btn{display:inline-flex;align-items:center;gap:7px;
            font-size:13.5px;font-weight:600;color:#111827;text-decoration:none;
            padding:9px 14px;border-radius:10px;background:#f3f4f6;
            box-shadow:inset 0 0 0 1px rgba(120,120,120,.14);transition:background .15s,transform .05s;}
        .pqa-btn:hover{background:#e9ebef;}
        .pqa-btn:active{transform:translateY(1px);}
        .pqa-btn-primary{color:#fff;background:#2563eb;box-shadow:none;}
        .pqa-btn-primary:hover{background:#1d4ed8;}
        .pqa-ic{width:18px;height:18px;}

        .dark .pqa{background:#1a2130;box-shadow:0 0 0 1px rgba(255,255,255,.08);}
        .dark .pqa-greet{color:#f3f4f6;}
        .dark .pqa-sub{color:#9aa4b5;}
        .dark .pqa-btn{color:#e5e7eb;background:#232c3d;box-shadow:inset 0 0 0 1px rgba(255,255,255,.09);}
        .dark .pqa-btn:hover{background:#2a3446;}
        .dark .pqa-btn-primary{color:#fff;background:#2563eb;}

        @media (max-width:640px){
            .pqa{padding:14px;}
            .pqa-actions{width:100%;}
            .pqa-btn{flex:1 1 auto;justify-content:center;}
        }
    </style>
</x-filament-widgets::widget>
