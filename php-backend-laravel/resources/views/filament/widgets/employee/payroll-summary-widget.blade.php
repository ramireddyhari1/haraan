<x-filament-widgets::widget>
    @php
        $payroll = $this->latestPayroll;
    @endphp

    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 transition-all flex flex-col justify-between h-full">
        <div>
            {{-- Header --}}
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 mb-3.5">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 shrink-0">
                        <x-heroicon-o-banknotes class="w-5 h-5" />
                    </span>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">Salary &amp; Payslip</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Monthly compensation</p>
                    </div>
                </div>

                @if($payroll)
                    <button wire:click="openPayslipModal"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-all cursor-pointer active:scale-[0.99]">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <span>View Statement</span>
                    </button>
                @endif
            </div>

            @if($payroll)
                {{-- Clean Net Take-Home Hero --}}
                <div class="py-2 mb-3">
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                        Net Take-Home &bull; {{ $payroll->formatted_month }}
                    </div>
                    <div class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight font-mono my-1">
                        ₹ {{ number_format((float)$payroll->net_salary, 2) }}
                    </div>
                    <div class="flex items-center justify-between text-xs text-slate-500 font-medium pt-2 border-t border-slate-100">
                        <span>Gross: <strong class="text-slate-800 font-mono">₹{{ number_format((float)$payroll->gross_earnings, 0) }}</strong></span>
                        <span>Deductions: <strong class="text-slate-800 font-mono">₹{{ number_format((float)$payroll->total_deductions, 0) }}</strong></span>
                    </div>
                </div>

                {{-- Key Telemetry Strip (Flat, no nested card boxes) --}}
                <div class="grid grid-cols-2 gap-4 py-2 text-xs border-t border-slate-100">
                    <div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block mb-0.5">Present Days</span>
                        <span class="font-extrabold text-slate-800 text-sm font-mono">{{ $payroll->present_days }} / {{ $payroll->working_days }} days</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block mb-0.5">Disbursement</span>
                        <span class="inline-flex items-center gap-1 text-emerald-700 font-bold text-xs uppercase">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            {{ $payroll->status }}
                        </span>
                    </div>
                </div>
            @else
                <div class="p-6 text-center text-xs text-slate-400 italic">
                    No payroll record generated for this cycle.
                </div>
            @endif
        </div>

        <div class="pt-3 border-t border-slate-100 mt-3 flex items-center justify-between">
            <span class="text-[11px] text-slate-400">Direct Bank Deposit</span>
            <a href="{{ route('filament.employee.pages.my-payslips-page') }}" class="text-xs font-bold text-slate-700 hover:text-slate-900 hover:underline">
                Past Payslips &rarr;
            </a>
        </div>

        {{-- Digital Payslip Modal --}}
        @if($showPayslipModal && $payroll)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
                <div class="w-full max-w-2xl rounded-2xl bg-white border border-slate-200 p-6 shadow-2xl my-8">
                    <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 mb-4">
                        <div>
                            <h4 class="text-base font-black text-slate-900 tracking-tight">Digital Payslip Statement</h4>
                            <span class="text-xs text-slate-500">{{ $payroll->formatted_month }} &bull; Ref: {{ $payroll->payslip_number }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    onclick="window.print()"
                                    class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-all cursor-pointer">
                                Print
                            </button>
                            <button wire:click="closePayslipModal" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                                <x-heroicon-m-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4 text-xs">
                        {{-- Employee Details --}}
                        <div class="grid grid-cols-2 gap-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div>
                                <div class="text-[11px] text-slate-400">Employee Name:</div>
                                <div class="font-bold text-slate-900 text-xs">{{ $payroll->employee?->full_name }}</div>
                                <div class="text-[11px] text-slate-400 mt-1">Designation:</div>
                                <div class="font-semibold text-slate-700">{{ $payroll->employee?->designation?->name ?? 'Staff' }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-400">Department:</div>
                                <div class="font-semibold text-slate-700">{{ $payroll->employee?->department?->name ?? 'Operations' }}</div>
                                <div class="text-[11px] text-slate-400 mt-1">Payable Days:</div>
                                <div class="font-semibold text-slate-700 font-mono">{{ $payroll->present_days + $payroll->paid_leave_days }} / {{ $payroll->working_days }}</div>
                            </div>
                        </div>

                        {{-- Earnings vs Deductions Table --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="border border-slate-200 rounded-xl overflow-hidden">
                                <div class="p-2.5 bg-slate-50 font-extrabold text-slate-800 border-b border-slate-200">Earnings</div>
                                <div class="p-3 space-y-2">
                                    <div class="flex justify-between text-slate-600"><span>Basic Salary:</span><span class="font-mono font-semibold text-slate-900">₹{{ number_format((float)$payroll->basic_salary, 2) }}</span></div>
                                    <div class="flex justify-between text-slate-600"><span>HRA:</span><span class="font-mono font-semibold text-slate-900">₹{{ number_format((float)$payroll->hra, 2) }}</span></div>
                                    <div class="flex justify-between text-slate-600"><span>Special Allowance:</span><span class="font-mono font-semibold text-slate-900">₹{{ number_format((float)$payroll->special_allowance, 2) }}</span></div>
                                    <div class="flex justify-between text-slate-600"><span>Bonus:</span><span class="font-mono font-semibold text-slate-900">₹{{ number_format((float)$payroll->performance_bonus, 2) }}</span></div>
                                    <div class="flex justify-between pt-2 border-t border-slate-100 font-extrabold text-slate-900">
                                        <span>Total Gross:</span><span class="font-mono">₹{{ number_format((float)$payroll->gross_earnings, 2) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="border border-slate-200 rounded-xl overflow-hidden">
                                <div class="p-2.5 bg-slate-50 font-extrabold text-slate-800 border-b border-slate-200">Deductions</div>
                                <div class="p-3 space-y-2">
                                    <div class="flex justify-between text-slate-600"><span>PF:</span><span class="font-mono font-semibold text-slate-900">₹{{ number_format((float)$payroll->pf_deduction, 2) }}</span></div>
                                    <div class="flex justify-between text-slate-600"><span>ESI:</span><span class="font-mono font-semibold text-slate-900">₹{{ number_format((float)$payroll->esi_deduction, 2) }}</span></div>
                                    <div class="flex justify-between text-slate-600"><span>Professional Tax:</span><span class="font-mono font-semibold text-slate-900">₹{{ number_format((float)$payroll->professional_tax, 2) }}</span></div>
                                    <div class="flex justify-between text-slate-600"><span>TDS / Tax:</span><span class="font-mono font-semibold text-slate-900">₹{{ number_format((float)$payroll->tds_deduction, 2) }}</span></div>
                                    <div class="flex justify-between pt-2 border-t border-slate-100 font-extrabold text-slate-900">
                                        <span>Total Deductions:</span><span class="font-mono">₹{{ number_format((float)$payroll->total_deductions, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Net Pay Callout --}}
                        <div class="flex items-center justify-between p-4 rounded-xl bg-slate-50 border border-slate-200">
                            <div>
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Net Take-Home Pay</span>
                                <div class="text-xs text-slate-500">Processed to {{ $payroll->employee?->bank_name ?? 'Primary Bank Account' }}</div>
                            </div>
                            <div class="text-2xl font-mono font-black text-slate-900">
                                ₹ {{ number_format((float)$payroll->net_salary, 2) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button wire:click="closePayslipModal"
                                class="px-5 py-2 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
