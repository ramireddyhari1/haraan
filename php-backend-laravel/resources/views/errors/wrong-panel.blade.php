@php
    /** @var \Filament\Panel $panel */
    /** @var \Filament\Panel|null $otherPanel */
    $panelName = $panel->getBrandName() ?: ucfirst($panel->getId());
    $otherName = $otherPanel?->getBrandName() ?: ($otherPanel ? ucfirst($otherPanel->getId()) : null);
    $logoutRoute = 'filament.' . $panel->getId() . '.auth.logout';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wrong console · Haraan</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
            font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
            background:#f3f4f6;color:#111827;}
        .card{width:100%;max-width:460px;background:#fff;border-radius:18px;padding:30px 28px;
            box-shadow:0 1px 3px rgba(11,18,32,.07),0 0 0 1px rgba(120,120,120,.1);}
        .ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;
            background:#fef3c7;color:#b45309;font-size:22px;margin-bottom:16px;}
        h1{font-size:19px;font-weight:800;letter-spacing:-.01em;margin:0 0 8px;}
        p{font-size:14px;line-height:1.55;color:#4b5563;margin:0 0 8px;}
        .who{font-size:13px;color:#6b7280;background:#f9fafb;border-radius:10px;padding:10px 12px;margin:16px 0 20px;
            box-shadow:inset 0 0 0 1px rgba(120,120,120,.12);}
        .who b{color:#111827;}
        .actions{display:flex;flex-direction:column;gap:10px;}
        .btn{display:block;width:100%;text-align:center;font-size:14px;font-weight:700;
            padding:11px 16px;border-radius:11px;border:0;cursor:pointer;text-decoration:none;}
        .btn-primary{background:#2563eb;color:#fff;}
        .btn-primary:hover{background:#1d4ed8;}
        .btn-ghost{background:#f3f4f6;color:#111827;box-shadow:inset 0 0 0 1px rgba(120,120,120,.16);}
        .btn-ghost:hover{background:#e9ebef;}
        form{margin:0;}
        @media (prefers-color-scheme:dark){
            body{background:#111827;color:#f3f4f6;}
            .card{background:#1a2130;box-shadow:0 0 0 1px rgba(255,255,255,.08);}
            p{color:#9aa4b5;} h1{color:#f3f4f6;}
            .who{background:#232c3d;color:#9aa4b5;box-shadow:inset 0 0 0 1px rgba(255,255,255,.08);}
            .who b{color:#f3f4f6;}
            .btn-ghost{background:#232c3d;color:#e5e7eb;box-shadow:inset 0 0 0 1px rgba(255,255,255,.1);}
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="ic">&#9888;</div>

        <h1>This isn’t your console</h1>
        <p>
            You’re signed in, but this account can’t open <b>{{ $panelName }}</b>.
        </p>

        <div class="who">
            Signed in as <b>{{ $user->name ?? 'Unknown' }}</b>@if(! empty($user->email)) · {{ $user->email }}@endif
        </div>

        <div class="actions">
            @if ($otherPanel)
                <a class="btn btn-primary" href="{{ $otherPanel->getUrl() }}">Go to {{ $otherName }}</a>
            @endif

            <form method="POST" action="{{ route($logoutRoute) }}">
                @csrf
                <button type="submit" class="btn {{ $otherPanel ? 'btn-ghost' : 'btn-primary' }}">
                    Sign out & use another account
                </button>
            </form>

            <a class="btn btn-ghost" href="{{ url('/') }}">Back to haraan.app</a>
        </div>
    </div>
</body>
</html>
