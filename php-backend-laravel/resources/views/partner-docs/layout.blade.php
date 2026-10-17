<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title')</title>
<style>
    :root { --ink:#0f172a; --ink2:#334155; --muted:#64748b; --hair:#e2e8f0; --brand:#2563eb; --wash:#f1f5fd; --bad:#b91c1c; }
    * { box-sizing: border-box; }
    html { -webkit-text-size-adjust: 100%; }
    body { margin: 0; background: #eef1f6; color: var(--ink); font: 13px/1.5 -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }
    .bar { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; gap: 10px; padding: 10px 16px; background: #fff; border-bottom: 1px solid var(--hair); }
    .bar b { flex: 1; font-size: 14px; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bar a, .bar button { appearance: none; border: 0; border-radius: 10px; padding: 9px 14px; font: 600 13px/1 inherit; font-family: inherit; cursor: pointer; text-decoration: none; }
    .bar .pdf { background: var(--brand); color: #fff; }
    .bar .alt { background: var(--wash); color: var(--brand); }
    .sheet { position: relative; width: 210mm; max-width: 100%; min-height: 297mm; margin: 16px auto; padding: 16mm 15mm 14mm; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,.08), 0 8px 24px rgba(15,23,42,.06); overflow: hidden; }
    header.top { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 14px; border-bottom: 2px solid var(--brand); }
    header.top img { height: 30px; display: block; margin-bottom: 8px; }
    header.top .who { font-size: 11.5px; color: var(--ink2); max-width: 300px; }
    header.top .who strong { display: block; color: var(--ink); font-size: 13px; }
    header.top .doc { text-align: right; }
    header.top h1 { margin: 0 0 6px; font-size: 19px; letter-spacing: -.01em; }
    dl.meta { display: grid; grid-template-columns: auto auto; gap: 2px 12px; margin: 0; font-size: 11.5px; justify-content: end; }
    dl.meta dt { color: var(--muted); text-align: right; }
    dl.meta dd { margin: 0; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; }
    .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 18px 0; }
    .party h3, h2 { margin: 0 0 6px; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
    .party p { margin: 0; font-size: 12px; color: var(--ink2); }
    .party p strong { color: var(--ink); font-size: 13px; }
    h2 { margin-top: 22px; }
    table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
    th { text-align: left; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); padding: 8px 8px; border-bottom: 1px solid var(--hair); background: #f8fafc; }
    td { padding: 8px; border-bottom: 1px solid var(--hair); vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    tr.total td { font-weight: 700; border-bottom: 0; border-top: 1.5px solid var(--ink); font-size: 14px; }
    tr.sub td { color: var(--ink2); }
    .small td, .small th { font-size: 11px; padding: 6px 6px; }
    .net { display: flex; justify-content: space-between; align-items: baseline; margin-top: 14px; padding: 12px 14px; background: var(--wash); border-radius: 8px; }
    .net span { color: var(--ink2); font-weight: 600; }
    .net b { font-size: 20px; color: var(--brand); font-variant-numeric: tabular-nums; }
    .note { margin-top: 10px; font-size: 11.5px; color: var(--muted); }
    footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid var(--hair); font-size: 10.5px; color: var(--muted); display: flex; justify-content: space-between; gap: 16px; }
    .void { position: absolute; top: 42%; left: 50%; transform: translate(-50%, -50%) rotate(-24deg); font-size: 82px; font-weight: 800; letter-spacing: .1em; color: rgba(185,28,28,.12); border: 6px solid rgba(185,28,28,.12); padding: 4px 24px; border-radius: 12px; pointer-events: none; white-space: nowrap; }
    @media (max-width: 640px) {
        .sheet { margin: 0; padding: 18px 16px; min-height: 0; box-shadow: none; }
        header.top { flex-direction: column; }
        header.top .doc, dl.meta dt, dl.meta dd { text-align: left; }
        dl.meta { justify-content: start; }
        .parties { grid-template-columns: 1fr; }
        .wide { overflow-x: auto; }
        .void { font-size: 48px; }
    }
    @page { size: A4; margin: 0; }
    @media print {
        body { background: #fff; }
        .bar { display: none; }
        .sheet { margin: 0; width: 210mm; max-width: none; min-height: 297mm; box-shadow: none; }
        header.top { flex-direction: row; }
        .parties { grid-template-columns: 1fr 1fr; }
        tr { break-inside: avoid; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
@if ($chrome)
    <div class="bar">
        <b>@yield('title')</b>
        @yield('bar-extra')
        <button type="button" class="pdf" onclick="window.print()">Save as PDF</button>
    </div>
@endif
<main class="sheet">
    @if ($cancelled)<div class="void" aria-hidden="true">CANCELLED</div>@endif
    <header class="top">
        <div>
            @if ($logo)<img src="{{ $logo }}" alt="Haraan">@endif
            <div class="who">
                <strong>{{ $issuer['legal_name'] ?? 'Haraan' }}</strong>
                @if (! empty($issuer['address'])){!! nl2br(e($issuer['address'])) !!}<br>@endif
                @if (! empty($issuer['gstin']))GSTIN {{ $issuer['gstin'] }}@endif
                @if (! empty($issuer['pan'])) · PAN {{ $issuer['pan'] }}@endif
            </div>
        </div>
        <div class="doc">
            <h1>@yield('heading')</h1>
            <dl class="meta">@yield('meta')</dl>
        </div>
    </header>

    @yield('body')

    <footer>
        <span>{{ $issuer['footer'] ?? '' }}</span>
        <span>Generated {{ \App\Support\BusinessClock::now()->format('d M Y, h:i A') }}</span>
    </footer>
</main>
</body>
</html>
