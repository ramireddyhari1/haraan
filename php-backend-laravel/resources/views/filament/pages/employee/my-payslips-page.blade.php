<x-filament-panels::page>
    @php
        $payrolls = $this->payrolls;
        $activePayroll = $viewingPayrollId ? \App\Models\Hrms\EmployeePayroll::find($viewingPayrollId) : null;
    @endphp

    <div class="space-y-6">
        <div class="hrn-card overflow-hidden">
            <div class="p-5 border-b border-[var(--hrn-border)] flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-[var(--hrn-ink)]">Salary Statements Archive</h3>
                    <p class="text-xs text-[var(--hrn-ink-2)]">Download or print your official monthly earnings statements.</p>
                </div>
                <span class="text-xs font-mono text-[var(--hrn-ink-3)]">{{ count($payrolls) }} Statements</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-[var(--hrn-track)]/60 text-[var(--hrn-ink-2)] uppercase text-[10px] font-extrabold tracking-wider border-b border-[var(--hrn-border)]">
                        <tr>
                            <th class="p-3.5">Month</th>
                            <th class="p-3.5">Payslip Ref</th>
                            <th class="p-3.5">Gross Pay</th>
                            <th class="p-3.5">Deductions</th>
                            <th class="p-3.5">Net Take-Home</th>
                            <th class="p-3.5">Status</th>
                            <th class="p-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--hrn-border)] text-[var(--hrn-ink)]">
                        @forelse($payrolls as $pay)
                            <tr class="hover:bg-[var(--hrn-track)]/20 transition-colors">
                                <td class="p-3.5 font-bold">
                                    {{ $pay->formatted_month }}
                                </td>
                                <td class="p-3.5 font-mono text-[var(--hrn-ink-2)]">
                                    {{ $pay->payslip_number }}
                                </td>
                                <td class="p-3.5 font-mono">
                                    ₹{{ number_format((float)$pay->gross_earnings, 2) }}
                                </td>
                                <td class="p-3.5 font-mono text-rose-600 dark:text-rose-400">
                                    -₹{{ number_format((float)$pay->total_deductions, 2) }}
                                </td>
                                <td class="p-3.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    ₹{{ number_format((float)$pay->net_salary, 2) }}
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 uppercase">
                                        {{ $pay->status }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right space-x-2">
                                    <button wire:click="viewPayslip({{ $pay->id }})"
                                            class="px-2.5 py-1 rounded-lg text-[11px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-500/10 hover:bg-emerald-500/20 transition-all">
                                        View
                                    </button>
                                    <a href="{{ url('/payslips/' . $pay->id . '/print') }}"
                                       target="_blank"
                                       class="px-2.5 py-1 rounded-lg text-[11px] font-bold border border-[var(--hrn-border)] text-[var(--hrn-ink-2)] hover:bg-[var(--hrn-track)] transition-all">
                                        Print / PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-xs text-[var(--hrn-ink-3)] italic">
                                    No payslips found in archive.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Interactive Payslip Viewer Modal --}}
        @if($activePayroll)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto">
                <div class="w-full max-w-2xl rounded-2xl bg-[var(--hrn-surface)] border border-[var(--hrn-border)] p-6 shadow-2xl my-8">
                    <div class="flex items-center justify-between pb-3 border-b border-[var(--hrn-border)] mb-4">
                        <div>
                            <h4 class="text-base font-bold text-[var(--hrn-ink)]">Payslip Statement &bull; {{ $activePayroll->formatted_month }}</h4>
                            <span class="text-xs text-[var(--hrn-ink-2)]">Doc Ref: {{ $activePayroll->payslip_number }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="window.print()" class="px-3 py-1.5 rounded-lg border border-[var(--hrn-border)] text-xs font-semibold text-[var(--hrn-ink)] hover:bg-[var(--hrn-track)]">
                                Print
                            </button>
                            <button wire:click="closePayslip" class="text-[var(--hrn-ink-3)] hover:text-[var(--hrn-ink)]">
                                <x-heroicon-m-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4 text-xs">
                        <div class="grid grid-cols-2 gap-4 p-3 rounded-xl bg-[var(--hrn-track)]/50 border border-[var(--hrn-border)]">
                            <div>
                                <div class="text-[var(--hrn-ink-3)]">Name:</div>
                                <div class="font-bold text-[var(--hrn-ink)]">{{ $activePayroll->employee?->full_name }}</div>
                                <div class="text-[var(--hrn-ink-3)] mt-1">Employee ID:</div>
                                <div class="font-mono text-[var(--hrn-ink)]">{{ $activePayroll->employee?->employee_code }}</div>
                            </div>
                            <div>
                                <div class="text-[var(--hrn-ink-3)]">Department:</div>
                                <div class="font-semibold text-[var(--hrn-ink)]">{{ $activePayroll->employee?->department?->name ?? 'General' }}</div>
                                <div class="text-[var(--hrn-ink-3)] mt-1">Designation:</div>
                                <div class="font-semibold text-[var(--hrn-ink)]">{{ $activePayroll->employee?->designation?->name ?? 'Staff' }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="border border-[var(--hrn-border)] rounded-xl overflow-hidden">
                                <div class="p-2 bg-[var(--hrn-track)] font-bold text-[var(--hrn-ink)]">Earnings</div>
                                <div class="p-3 space-y-1.5">
                                    <div class="flex justify-between"><span>Basic:</span><span class="font-mono font-semibold">₹{{ number_format((float)$activePayroll->basic_salary, 2) }}</span></div>
                                    <div class="flex justify-between"><span>HRA:</span><span class="font-mono font-semibold">₹{{ number_format((float)$activePayroll->hra, 2) }}</span></div>
                                    <div class="flex justify-between"><span>Special Allowance:</span><span class="font-mono font-semibold">₹{{ number_format((float)$activePayroll->special_allowance, 2) }}</span></div>
                                    <div class="flex justify-between"><span>Bonus:</span><span class="font-mono font-semibold">₹{{ number_format((float)$activePayroll->performance_bonus, 2) }}</span></div>
                                    <div class="flex justify-between pt-2 border-t border-[var(--hrn-border)] font-bold">
                                        <span>Gross:</span><span class="font-mono">₹{{ number_format((float)$activePayroll->gross_earnings, 2) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="border border-[var(--hrn-border)] rounded-xl overflow-hidden">
                                <div class="p-2 bg-[var(--hrn-track)] font-bold text-[var(--hrn-ink)]">Deductions</div>
                                <div class="p-3 space-y-1.5">
                                    <div class="flex justify-between"><span>PF:</span><span class="font-mono font-semibold">₹{{ number_format((float)$activePayroll->pf_deduction, 2) }}</span></div>
                                    <div class="flex justify-between"><span>ESI:</span><span class="font-mono font-semibold">₹{{ number_format((float)$activePayroll->esi_deduction, 2) }}</span></div>
                                    <div class="flex justify-between"><span>Professional Tax:</span><span class="font-mono font-semibold">₹{{ number_format((float)$activePayroll->professional_tax, 2) }}</span></div>
                                    <div class="flex justify-between"><span>TDS:</span><span class="font-mono font-semibold">₹{{ number_format((float)$activePayroll->tds_deduction, 2) }}</span></div>
                                    <div class="flex justify-between pt-2 border-t border-[var(--hrn-border)] font-bold">
                                        <span>Total Deductions:</span><span class="font-mono">₹{{ number_format((float)$activePayroll->total_deductions, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-4 rounded-xl bg-emerald-500/15 border border-emerald-500/30">
                            <div>
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Net Take-Home Pay</span>
                                <div class="text-xs text-[var(--hrn-ink-2)]">Direct Deposit Verified</div>
                            </div>
                            <div class="text-2xl font-mono font-extrabold text-emerald-600 dark:text-emerald-400">
                                ₹ {{ number_format((float)$activePayroll->net_salary, 2) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button wire:click="closePayslip"
                                class="px-5 py-2 text-xs font-bold text-[var(--hrn-ink)] bg-[var(--hrn-track)] hover:bg-[var(--hrn-border)] rounded-xl transition-all">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
