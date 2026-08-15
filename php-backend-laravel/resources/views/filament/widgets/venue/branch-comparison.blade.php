@php
    $rows = $this->getRows();
    $headline = $this->getHeadline();
    $resourceLabel = $this->getResourceLabel();
@endphp

<x-filament-widgets::widget>
    <div class="hrn-cmp">
        <div class="hrn-cmp-head">
            <div>
                <h3 class="hrn-cmp-title">Branches</h3>
                <p class="hrn-cmp-sub">{{ $this->getWindowLabel() }} · ranked by utilisation</p>
            </div>
            <span class="hrn-cmp-count">{{ count($rows) }} outlets</span>
        </div>

        @if ($headline)
            {{-- The whole point of the table, said in one line for the person who
                 only reads one line. --}}
            <div class="hrn-cmp-alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                    <path d="M12 9v4M12 17h.01"/>
                </svg>
                <span>{{ $headline }}</span>
            </div>
        @endif

        {{-- Wide content scrolls inside its own box; the page never scrolls sideways. --}}
        <div class="hrn-cmp-scroll">
            <table class="hrn-cmp-table">
                <thead>
                    <tr>
                        <th scope="col">Branch</th>
                        <th scope="col" class="hrn-num">Revenue</th>
                        <th scope="col" class="hrn-num">Bookings</th>
                        <th scope="col" class="hrn-num">{{ $resourceLabel }}</th>
                        <th scope="col" class="hrn-util">Utilisation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr @class(['is-soft' => $row['is_soft']])>
                            <th scope="row" class="hrn-cmp-name">
                                <span class="hrn-cmp-branch">{{ $row['branch'] }}</span>
                                @if ($row['code'])
                                    <span class="hrn-cmp-code">{{ $row['code'] }}</span>
                                @endif
                                @unless ($row['is_active'])
                                    <span class="hrn-cmp-tag">inactive</span>
                                @endunless
                            </th>
                            <td class="hrn-num">{{ $this->formatMoney($row['revenue']) }}</td>
                            <td class="hrn-num">{{ number_format($row['bookings']) }}</td>
                            <td class="hrn-num hrn-muted">{{ $row['resources'] }}</td>
                            <td class="hrn-util">
                                {{-- Never colour alone: the bar carries a number, and
                                     a soft branch is also labelled in words. --}}
                                <div class="hrn-bar-row">
                                    <div class="hrn-bar" role="img"
                                         aria-label="{{ $row['utilisation'] }} percent utilised">
                                        <span style="width: {{ max(2, $row['utilisation']) }}%"></span>
                                    </div>
                                    <span class="hrn-bar-val">{{ $row['utilisation'] }}%</span>
                                </div>
                                @if ($row['is_soft'])
                                    <span class="hrn-cmp-soft">{{ $row['share'] }}% of your best</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="hrn-cmp-empty">
                                No branch activity in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .hrn-cmp{background:#fff;border-radius:18px;padding:18px 18px 6px;
            box-shadow:0 1px 2px rgba(15,23,42,.04),0 0 0 1px #e9edf4;}
        .hrn-cmp-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
            margin-bottom:14px;}
        .hrn-cmp-title{font-size:15px;font-weight:700;color:#0b1220;margin:0;}
        .hrn-cmp-sub{font-size:12px;color:#6b7688;margin:2px 0 0;}
        .hrn-cmp-count{font-size:11.5px;font-weight:600;color:#1e3a6b;background:#f2f6fd;
            border-radius:999px;padding:4px 10px;white-space:nowrap;}

        .hrn-cmp-alert{display:flex;align-items:flex-start;gap:8px;margin-bottom:14px;
            padding:10px 12px;border-radius:12px;background:#fff8ed;
            box-shadow:inset 0 0 0 1px #f6e0bd;font-size:12.5px;color:#8a5a08;line-height:1.45;}
        .hrn-cmp-alert svg{width:15px;height:15px;flex:none;margin-top:1px;}

        .hrn-cmp-scroll{overflow-x:auto;margin:0 -18px;padding:0 18px;}
        .hrn-cmp-table{width:100%;border-collapse:collapse;font-size:13px;min-width:520px;}
        .hrn-cmp-table th,.hrn-cmp-table td{padding:10px 8px;text-align:left;
            border-bottom:1px solid #f1f4f9;vertical-align:middle;}
        .hrn-cmp-table thead th{font-size:11px;font-weight:600;color:#6b7688;
            text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #e9edf4;}
        .hrn-cmp-table tbody tr:last-child th,.hrn-cmp-table tbody tr:last-child td{border-bottom:0;}
        .hrn-cmp-table .hrn-num{text-align:right;font-variant-numeric:tabular-nums;
            font-weight:600;color:#0b1220;white-space:nowrap;}
        .hrn-cmp-table .hrn-muted{font-weight:500;color:#6b7688;}
        .hrn-cmp-table .hrn-util{width:34%;min-width:170px;}

        .hrn-cmp-name{font-weight:600;color:#0b1220;}
        .hrn-cmp-branch{display:block;}
        .hrn-cmp-code,.hrn-cmp-tag{display:inline-block;font-size:10.5px;font-weight:600;
            color:#6b7688;margin-top:2px;}
        .hrn-cmp-tag{color:#b4530a;}

        .hrn-bar-row{display:flex;align-items:center;gap:8px;}
        .hrn-bar{flex:1;height:7px;border-radius:999px;background:#eef1f6;overflow:hidden;}
        .hrn-bar span{display:block;height:100%;border-radius:999px;background:#0A66FF;}
        .hrn-bar-val{font-size:12px;font-weight:700;color:#0b1220;
            font-variant-numeric:tabular-nums;min-width:34px;text-align:right;}
        .hrn-cmp-soft{display:inline-block;margin-top:4px;font-size:11px;font-weight:600;color:#b4530a;}
        tr.is-soft .hrn-bar span{background:#E08706;}

        .hrn-cmp-empty{text-align:center;color:#6b7688;padding:22px 8px;}

        .dark .hrn-cmp{background:#151b26;box-shadow:0 0 0 1px #2a3446;}
        .dark .hrn-cmp-title,.dark .hrn-cmp-name,.dark .hrn-cmp-table .hrn-num,
        .dark .hrn-bar-val{color:#eef2f8;}
        .dark .hrn-cmp-sub,.dark .hrn-cmp-table thead th,.dark .hrn-cmp-table .hrn-muted,
        .dark .hrn-cmp-code,.dark .hrn-cmp-empty{color:#9aa6b8;}
        .dark .hrn-cmp-count{color:#dbe6fb;background:#1d2a44;}
        .dark .hrn-cmp-table th,.dark .hrn-cmp-table td{border-bottom-color:#232c3b;}
        .dark .hrn-cmp-table thead th{border-bottom-color:#2a3446;}
        .dark .hrn-bar{background:#232c3b;}
        .dark .hrn-cmp-alert{background:#2a2011;box-shadow:inset 0 0 0 1px #4a3a1c;color:#f0c479;}
        .dark .hrn-cmp-soft,.dark .hrn-cmp-tag{color:#f0a94a;}
    </style>
</x-filament-widgets::widget>
