<x-filament-widgets::widget>
    @php
        $kpi = $this->latestKpi;
    @endphp

    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 transition-all flex flex-col justify-between h-full">
        <div>
            {{-- Header --}}
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 mb-3.5">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 shrink-0">
                        <x-heroicon-o-star class="w-5 h-5" />
                    </span>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">Performance &amp; Scorecard</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Quarterly review cycle</p>
                    </div>
                </div>

                @if($kpi)
                    <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-200">
                        {{ $kpi->period }}
                    </span>
                @endif
            </div>

            @if($kpi)
                {{-- Overall Score Display (Flat, no nested box) --}}
                <div class="py-2 flex items-baseline justify-between border-b border-slate-100 pb-3 mb-3">
                    <div>
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 block">Overall Standing</span>
                        <span class="text-xs text-slate-500">Appraised by {{ $kpi->reviewer?->name ?? 'Management' }}</span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-3xl sm:text-4xl font-black text-slate-900 font-mono">{{ number_format((float)$kpi->overall_score, 1) }}</span>
                        <span class="text-xs font-bold text-slate-400">/ 5.0 ★</span>
                    </div>
                </div>

                {{-- Dimension Bars --}}
                <div class="space-y-2.5 text-xs">
                    @php
                        $metrics = [
                            'Punctuality & Discipline' => (float)$kpi->punctuality_rating,
                            'Task Execution Quality' => (float)$kpi->task_completion_rating,
                            'Customer / Guest Service' => (float)$kpi->customer_service_rating,
                            'Team Collaboration' => (float)$kpi->teamwork_rating,
                        ];
                    @endphp

                    @foreach($metrics as $label => $val)
                        <div>
                            <div class="flex justify-between text-[11px] mb-1">
                                <span class="text-slate-700 font-medium">{{ $label }}</span>
                                <span class="font-bold text-slate-800 font-mono">{{ number_format($val, 1) }} ★</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-slate-700 h-1.5 rounded-full transition-all duration-500" style="width: {{ ($val / 5) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($kpi->manager_feedback)
                    <div class="mt-3.5 py-2 text-xs text-slate-500 italic border-t border-slate-100">
                        &ldquo;{{ $kpi->manager_feedback }}&rdquo;
                    </div>
                @endif
            @else
                <div class="p-6 text-center text-xs text-slate-400 italic">
                    Performance review cycle underway.
                </div>
            @endif
        </div>

        <div class="pt-3 border-t border-slate-100 mt-3 text-right">
            <span class="text-[11px] text-slate-400">HARAAN Excellence Standard</span>
        </div>
    </div>
</x-filament-widgets::widget>
