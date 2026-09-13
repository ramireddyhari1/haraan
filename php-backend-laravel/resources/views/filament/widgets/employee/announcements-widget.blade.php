<x-filament-widgets::widget>
    @php
        $notices = $this->announcements;
    @endphp

    @if(count($notices) > 0)
        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 transition-all">
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 shrink-0">
                        <x-heroicon-o-megaphone class="w-5 h-5" />
                    </span>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">Company Bulletins</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Broadcasts from leadership</p>
                    </div>
                </div>

                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">
                    {{ count($notices) }} {{ count($notices) === 1 ? 'Notice' : 'Notices' }}
                </span>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($notices as $notice)
                    @php
                        $isUrgent = $notice->priority === 'urgent';
                        $isHigh = $notice->priority === 'high';
                    @endphp

                    <div class="py-3.5 flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="p-2 rounded-lg shrink-0 mt-0.5 {{ $isUrgent ? 'bg-rose-50 text-rose-700' : ($isHigh ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                <x-heroicon-o-megaphone class="w-4 h-4" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <span class="text-xs font-black text-slate-900">{{ $notice->title }}</span>
                                    @if($isUrgent)
                                        <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-rose-500 text-white motion-reduce:animate-none animate-pulse">Urgent</span>
                                    @elseif($isHigh)
                                        <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-amber-500 text-white">Important</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed font-normal">{{ $notice->body }}</p>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-400 font-medium whitespace-nowrap sm:pt-0.5 pl-11 sm:pl-0 font-mono">
                            {{ \Illuminate\Support\Carbon::parse($notice->published_at)->diffForHumans() }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
