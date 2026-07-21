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
        /* Gradient "hero" band — same blue aurora as the partner sign-in, so the
           console opens with the brand feel the login set up. Always-dark, so it
           reads identically in light and dark theme. */
        .pqa{position:relative;overflow:hidden;isolation:isolate;
            display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
            border-radius:18px;padding:20px 22px;
            background:
                radial-gradient(900px 380px at 12% -40%, rgba(59,130,246,.55), transparent 60%),
                radial-gradient(700px 340px at 106% 0%, rgba(99,102,241,.45), transparent 60%),
                linear-gradient(150deg,#0a1738 0%,#0b1c46 52%,#0a1230 100%);
            box-shadow:0 18px 40px -22px rgba(10,23,56,.7),0 0 0 1px rgba(255,255,255,.06);}
        .pqa::before{content:"";position:absolute;inset:0;z-index:-1;opacity:.16;
            background-image:linear-gradient(rgba(255,255,255,.5) 1px,transparent 1px),
                linear-gradient(90deg,rgba(255,255,255,.5) 1px,transparent 1px);
            background-size:40px 40px;
            -webkit-mask-image:radial-gradient(120% 100% at 20% 0%,#000 30%,transparent 72%);
            mask-image:radial-gradient(120% 100% at 20% 0%,#000 30%,transparent 72%);}
        .pqa-greet{font-size:19px;font-weight:800;letter-spacing:-.015em;color:#fff;}
        .pqa-sub{font-size:13px;color:rgba(224,232,255,.72);margin-top:3px;}
        .pqa-actions{display:flex;gap:10px;flex-wrap:wrap;}
        .pqa-btn{display:inline-flex;align-items:center;gap:7px;
            font-size:13.5px;font-weight:600;color:#eaf0ff;text-decoration:none;
            padding:10px 15px;border-radius:11px;
            background:rgba(255,255,255,.09);backdrop-filter:blur(6px);
            box-shadow:inset 0 0 0 1px rgba(255,255,255,.16);transition:background .15s,transform .05s;}
        .pqa-btn:hover{background:rgba(255,255,255,.16);}
        .pqa-btn:active{transform:translateY(1px);}
        .pqa-btn-primary{color:#fff;background-image:linear-gradient(180deg,#2f6bff,#1e50e6);
            box-shadow:0 8px 18px -8px rgba(37,99,235,.6);}
        .pqa-btn-primary:hover{background-image:linear-gradient(180deg,#3a74ff,#2456ea);}
        .pqa-ic{width:18px;height:18px;}

        @media (max-width:640px){
            .pqa{padding:16px;border-radius:16px;}
            .pqa-actions{width:100%;}
            .pqa-btn{flex:1 1 auto;justify-content:center;}
        }
        @media (prefers-reduced-motion:reduce){.pqa::before{opacity:.12;}}
    </style>
</x-filament-widgets::widget>
