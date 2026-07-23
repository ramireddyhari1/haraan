{{-- Repeat-fan drill-down. $fans is a pre-sorted list from the widget
     (richest / most-loyal first). Plain Blade only — no inline @php. --}}
@if (empty($fans))
    <div style="text-align:center;padding:22px 12px;color:var(--hrn-ink-3,#94a3b8);font-size:13px;">
        No repeat fans yet — everyone who booked this event is a first-timer.
    </div>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;color:var(--hrn-ink,#0f172a);">
            <thead>
                <tr style="text-align:left;font-size:11px;letter-spacing:.05em;text-transform:uppercase;color:var(--hrn-ink-2,#64748b);">
                    <th style="padding:6px 10px;font-weight:700;">Fan</th>
                    <th style="padding:6px 10px;font-weight:700;">Contact</th>
                    <th style="padding:6px 10px;font-weight:700;">Loyalty</th>
                    <th style="padding:6px 10px;font-weight:700;text-align:right;">This event</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($fans as $f)
                    <tr style="border-top:1px solid var(--hrn-border,#e2e8f0);">
                        <td style="padding:10px;font-weight:600;">{{ $f['name'] }}</td>
                        <td style="padding:10px;color:var(--hrn-ink-2,#64748b);">
                            @if ($f['email'])<div>{{ $f['email'] }}</div>@endif
                            @if ($f['phone'])<div>{{ $f['phone'] }}</div>@endif
                            @if (! $f['email'] && ! $f['phone'])<span>—</span>@endif
                        </td>
                        <td style="padding:10px;">{{ $f['past'] }} past {{ \Illuminate\Support\Str::plural('event', $f['past']) }}</td>
                        <td style="padding:10px;text-align:right;white-space:nowrap;">
                            ₹{{ number_format($f['spent']) }}
                            <span style="color:var(--hrn-ink-2,#64748b);">· {{ $f['tickets'] }} {{ \Illuminate\Support\Str::plural('ticket', $f['tickets']) }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
