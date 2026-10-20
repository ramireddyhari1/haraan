<x-filament-panels::page>
    {{-- Every figure comes from WorkforceToday::getPanels(); this only draws it. --}}
    <div style="display: grid; gap: 16px">
        @foreach ($this->getPanels() as $panel)
            <x-summary-panel :s="$panel" />
        @endforeach

        <section>
            <div class="hrn-sec-h"><h2>Go to</h2></div>
            <div class="wf-links">
                @foreach ([
                    ['players', 'Employees', 'Profiles, departments, managers and pay.', \App\Filament\Resources\Hrms\EmployeeResource::getUrl('index')],
                    ['calendar', 'Rosters & shifts', 'Who works which shift, and swap requests.', \App\Filament\Resources\Hrms\EmployeeRosterResource::getUrl('index')],
                    ['clipboard', 'Leave', 'Requests, approvals and balances.', \App\Filament\Resources\Hrms\EmployeeLeaveResource::getUrl('index')],
                    ['receipt', 'Payroll', 'Monthly payslips: earnings, deductions, net.', \App\Filament\Resources\Hrms\EmployeePayrollResource::getUrl('index')],
                ] as [$illo, $title, $sub, $url])
                    <a href="{{ $url }}" class="wf-link">
                        <x-hrn-illo :name="$illo" :size="76" />
                        <span>
                            <b>{{ $title }}</b>
                            <span>{{ $sub }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>

    <style>
        .wf-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
        .wf-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px 12px 10px; background: #fff;
            border: 1px solid #e6e9f0; border-radius: 14px; text-decoration: none; transition: border-color .15s, transform .15s; }
        .wf-link:hover { border-color: #93b4f8; transform: translateY(-1px); }
        .wf-link b { display: block; font-size: 14px; font-weight: 680; color: #0f172a; }
        .wf-link span span { display: block; margin-top: 2px; font-size: 12.5px; line-height: 1.45; color: #64748b; }
        .dark .wf-link { background: #111827; border-color: #1f2937; }
        .dark .wf-link b { color: #f8fafc; }
        .dark .wf-link span span { color: #94a3b8; }
        @media (prefers-reduced-motion: reduce) { .wf-link { transition: none; } .wf-link:hover { transform: none; } }
    </style>
</x-filament-panels::page>
