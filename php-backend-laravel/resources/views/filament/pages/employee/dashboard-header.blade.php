@php
    $user = auth()->user();
    $profile = $user?->employeeProfile;
    $role = $profile?->designation?->name ?? 'Team Member';
    $dept = $profile?->department?->name ?? 'Operations';
    $code = $profile?->employee_code ?? '';
    $venue = $profile?->venue?->name ?? 'HARAAN Arena - Central Hub';

    $hour = (int) date('H');
    if ($hour < 12) {
        $greeting = 'Good morning';
    } elseif ($hour < 17) {
        $greeting = 'Good afternoon';
    } else {
        $greeting = 'Good evening';
    }

    $initials = collect(explode(' ', $user?->name ?? 'H C'))
        ->map(fn($segment) => mb_substr($segment, 0, 1))
        ->take(2)
        ->join('');
@endphp

<div class="mb-6 space-y-4">
    {{-- Main Personalized Welcome Surface --}}
    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_2px_rgba(0,0,0,0.02)] p-6 sm:p-7 transition-all">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            {{-- Left: Avatar + Identity + Greeting --}}
            <div class="flex items-start sm:items-center gap-3.5 sm:gap-5 min-w-0">
                <div class="relative shrink-0">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-800 text-white font-black text-base sm:text-lg flex items-center justify-center shadow-sm shadow-emerald-600/20 ring-4 ring-emerald-50/80">
                        {{ strtoupper($initials) }}
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-emerald-500 ring-2 ring-white" title="Active Account"></span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-tight">
                            {{ $greeting }}, {{ $user?->name ?? 'Colleague' }}
                        </h1>
                        @if($code)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $code }}
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs sm:text-sm text-slate-500 font-medium">
                        <span class="text-slate-800 font-bold">{{ $role }}</span>
                        <span class="text-slate-300">•</span>
                        <span>{{ $dept }}</span>
                        <span class="text-slate-300">•</span>
                        <span class="inline-flex items-center gap-1.5 text-slate-600">
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                            <span class="truncate max-w-[200px] sm:max-w-xs">{{ $venue }}</span>
                        </span>
                    </div>
                </div>
            </div>

            {{-- Right: Date Pill & Status Badge --}}
            <div class="flex flex-wrap items-center gap-2.5 pt-3 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-50 text-slate-700 border border-slate-200/80 text-xs font-semibold">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    <span>{{ now()->format('l, d M Y') }}</span>
                </div>

                <div class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200/80 text-xs font-bold">
                    <span class="relative flex h-2 w-2">
                        <span class="motion-reduce:animate-none animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span>Employee Console</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions Touchpoint Bar (Mobile-friendly, touch target >= 48px) --}}
    <div class="flex items-center gap-2.5 overflow-x-auto pb-1 scrollbar-none no-scrollbar">
        <a href="#attendance-hero"
           class="inline-flex items-center gap-2 min-h-[48px] px-4 py-2.5 rounded-xl bg-white border border-slate-200/80 text-slate-800 text-xs font-bold shadow-xs hover:border-emerald-500 hover:text-emerald-700 hover:bg-emerald-50/30 transition-all shrink-0 active:scale-[0.99]">
            <span class="w-6 h-6 rounded-lg bg-emerald-100/70 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </span>
            <span>Attendance</span>
        </a>

        <a href="{{ route('filament.employee.pages.my-attendance-page') }}"
           class="inline-flex items-center gap-2 min-h-[48px] px-4 py-2.5 rounded-xl bg-white border border-slate-200/80 text-slate-800 text-xs font-bold shadow-xs hover:border-slate-400 hover:text-slate-900 hover:bg-slate-50 transition-all shrink-0 active:scale-[0.99]">
            <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
            </span>
            <span>My Shift</span>
        </a>

        <a href="{{ route('filament.employee.pages.my-tasks-page') }}"
           class="inline-flex items-center gap-2 min-h-[48px] px-4 py-2.5 rounded-xl bg-white border border-slate-200/80 text-slate-800 text-xs font-bold shadow-xs hover:border-slate-400 hover:text-slate-900 hover:bg-slate-50 transition-all shrink-0 active:scale-[0.99]">
            <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </span>
            <span>My Tasks</span>
        </a>

        <a href="{{ route('filament.employee.pages.my-leaves-page') }}"
           class="inline-flex items-center gap-2 min-h-[48px] px-4 py-2.5 rounded-xl bg-white border border-slate-200/80 text-slate-800 text-xs font-bold shadow-xs hover:border-slate-400 hover:text-slate-900 hover:bg-slate-50 transition-all shrink-0 active:scale-[0.99]">
            <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" /></svg>
            </span>
            <span>Apply Leave</span>
        </a>

        <a href="{{ route('filament.employee.pages.my-payslips-page') }}"
           class="inline-flex items-center gap-2 min-h-[48px] px-4 py-2.5 rounded-xl bg-white border border-slate-200/80 text-slate-800 text-xs font-bold shadow-xs hover:border-emerald-500 hover:text-emerald-700 hover:bg-emerald-50/30 transition-all shrink-0 active:scale-[0.99]">
            <span class="w-6 h-6 rounded-lg bg-emerald-100/70 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v10.5m0-10.5h19.5m0 0v10.5m0-10.5a.75.75 0 0 0-.75-.75h-.75m-18 0a.75.75 0 0 1 .75-.75h16.5m0 0a.75.75 0 0 1 .75.75v.75m0 9.75v.75c0 .754-.726 1.294-1.453 1.096a60.07 60.07 0 0 0-15.797-2.101" /></svg>
            </span>
            <span>Payslips</span>
        </a>
    </div>
</div>
