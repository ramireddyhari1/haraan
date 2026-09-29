{{-- Reports: a date range, what happened in it, and the bookings CSV. --}}
@php $rows = $this->rowCount(); @endphp

<x-filament-panels::page>
    <div style="display: grid; gap: 16px">
        <section class="hrp">
            <div class="hrp-range">
                <label>
                    <span>From</span>
                    <input type="date" wire:model.live="from" max="{{ now()->toDateString() }}">
                </label>
                <label>
                    <span>To</span>
                    <input type="date" wire:model.live="to" max="{{ now()->toDateString() }}">
                </label>
            </div>
            <div class="hrp-export">
                <div>
                    <div class="hrp-export-t">Bookings sheet (CSV)</div>
                    <div class="hrp-export-d">
                        One row per booking made in the range: customer, venue or event, slot, amount,
                        amount paid, status and check-in. {{ number_format($rows) }} {{ \Illuminate\Support\Str::plural('row', $rows) }}.
                    </div>
                </div>
                <x-filament::button wire:click="download" icon="heroicon-o-arrow-down-tray" :disabled="$rows === 0">
                    Download CSV
                </x-filament::button>
            </div>
        </section>

        @foreach ($this->getPanels() as $panel)
            <x-summary-panel :s="$panel" />
        @endforeach
    </div>

    <style>
        .hrp{display:grid;grid-template-columns:auto minmax(0,1fr);gap:18px 28px;align-items:center;background:#fff;
            border:1px solid #e6e9f0;border-radius:16px;padding:16px 18px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .hrp-range{display:flex;gap:12px;flex-wrap:wrap;}
        .hrp-range label{display:flex;flex-direction:column;gap:5px;}
        .hrp-range span{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;}
        .hrp-range input{height:40px;border:1px solid #d9dee8;border-radius:10px;padding:0 10px;font-size:14px;color:#0f172a;background:#fff;}
        .hrp-range input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.18);}
        .hrp-export{display:flex;align-items:center;justify-content:space-between;gap:16px;padding-left:28px;border-left:1px solid #eef1f6;}
        .hrp-export-t{font-size:14px;font-weight:680;color:#0f172a;}
        .hrp-export-d{font-size:12.5px;color:#64748b;margin-top:3px;line-height:1.5;max-width:62ch;}
        @media (max-width:900px){
            .hrp{grid-template-columns:1fr;}
            .hrp-export{padding-left:0;border-left:0;padding-top:14px;border-top:1px solid #eef1f6;flex-wrap:wrap;}
        }
        .dark .hrp{background:#111827;border-color:#1f2937;}
        .dark .hrp-export-t{color:#f8fafc;}
        .dark .hrp-range input{background:#0f172a;border-color:#334155;color:#f8fafc;}
    </style>
</x-filament-panels::page>
