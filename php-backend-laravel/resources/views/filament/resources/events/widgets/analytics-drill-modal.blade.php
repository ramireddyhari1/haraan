{{-- Generic stat drill-down. $d = getDrillData(metric):
     ['subheading', 'summary'=>[[label,value]], 'rows'=>[[name,contact,email,qty,amount,status,date,extra]], 'extraLabel', 'empty'].
     Plain Blade only — no inline @php. --}}
<div>
    @if (! empty($d['subheading']))
        <p style="margin:0 0 14px;font-size:13px;color:var(--hrn-ink-2,#64748b);">{{ $d['subheading'] }}</p>
    @endif

    @if (! empty($d['summary']))
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
            @foreach ($d['summary'] as $s)
                <div style="border:1px solid var(--hrn-border,#e2e8f0);border-radius:10px;padding:12px 14px;">
                    <div style="font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:var(--hrn-ink-2,#64748b);">{{ $s['label'] }}</div>
                    <div style="font-size:18px;font-weight:700;color:var(--hrn-ink,#0f172a);margin-top:2px;">{{ $s['value'] }}</div>
                </div>
            @endforeach
        </div>
    @elseif (empty($d['rows']))
        <div style="text-align:center;padding:22px 12px;color:var(--hrn-ink-3,#94a3b8);font-size:13px;">
            {{ $d['empty'] }}
        </div>
    @else
        <div style="font-size:12px;color:var(--hrn-ink-2,#64748b);margin-bottom:8px;">
            {{ count($d['rows']) }} {{ \Illuminate\Support\Str::plural('record', count($d['rows'])) }}{{ count($d['rows']) === 200 ? ' (showing first 200)' : '' }}
            @if (! empty($d['masked']))
                · <span title="Only OPS, Finance and admins see full contact details.">contact details hidden for your role</span>
            @endif
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;color:var(--hrn-ink,#0f172a);">
                <thead>
                    <tr style="text-align:left;font-size:11px;letter-spacing:.05em;text-transform:uppercase;color:var(--hrn-ink-2,#64748b);">
                        <th style="padding:6px 10px;font-weight:700;">Name</th>
                        <th style="padding:6px 10px;font-weight:700;">Contact</th>
                        @if (! empty($d['extraLabel']))
                            <th style="padding:6px 10px;font-weight:700;">{{ $d['extraLabel'] }}</th>
                        @endif
                        <th style="padding:6px 10px;font-weight:700;">Status</th>
                        <th style="padding:6px 10px;font-weight:700;white-space:nowrap;">Booked</th>
                        <th style="padding:6px 10px;font-weight:700;text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($d['rows'] as $r)
                        <tr style="border-top:1px solid var(--hrn-border,#e2e8f0);">
                            <td style="padding:10px;font-weight:600;">{{ $r['name'] }}</td>
                            <td style="padding:10px;color:var(--hrn-ink-2,#64748b);">
                                @if (! empty($r['contact']))<div>{{ $r['contact'] }}</div>@endif
                                @if (! empty($r['email']))<div>{{ $r['email'] }}</div>@endif
                                @if (empty($r['contact']) && empty($r['email']))<span>—</span>@endif
                            </td>
                            @if (! empty($d['extraLabel']))
                                <td style="padding:10px;white-space:nowrap;">{{ $r['extra'] ?? '—' }}</td>
                            @endif
                            <td style="padding:10px;">{{ $r['status'] }}</td>
                            <td style="padding:10px;white-space:nowrap;color:var(--hrn-ink-2,#64748b);">{{ $r['date'] }}</td>
                            <td style="padding:10px;text-align:right;white-space:nowrap;font-weight:600;">{{ $r['amount'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
