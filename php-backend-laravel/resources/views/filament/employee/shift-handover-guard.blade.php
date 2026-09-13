@php
    $user = auth()->user();
    $profile = $user?->employeeProfile;
    $isCurrentlyClockedIn = false;
    if ($profile) {
        $todayAtt = \App\Models\Hrms\EmployeeAttendance::where('employee_profile_id', $profile->id)
            ->whereDate('date', \Illuminate\Support\Carbon::today())
            ->first();
        $isCurrentlyClockedIn = $todayAtt !== null && $todayAtt->clock_in_at !== null && $todayAtt->clock_out_at === null;
    }
@endphp

<div id="hrn-shift-guard-root"
     x-data="{
         isClockedIn: {{ $isCurrentlyClockedIn ? 'true' : 'false' }},
         showModal: false,
         pendingLogoutForm: null,
         init() {
             // Register Haptic Engine globally
             window.hrnHaptic = (type = 'tap') => {
                 if (!navigator.vibrate) return;
                 const patterns = {
                     tap: 15,
                     success: [30, 40, 60],
                     error: [80, 50, 80],
                     warning: [40, 30, 40]
                 };
                 try { navigator.vibrate(patterns[type] || 15); } catch (e) {}
             };

             // Intercept Filament logout forms
             document.addEventListener('submit', (e) => {
                 const form = e.target;
                 const action = form.getAttribute('action') || '';
                 if (action.includes('/logout') || action.includes('logout')) {
                     if (this.isClockedIn) {
                         e.preventDefault();
                         e.stopPropagation();
                         this.pendingLogoutForm = form;
                         this.showModal = true;
                         window.hrnHaptic('warning');
                     }
                 }
             }, true);
         },
         proceedLogout() {
             this.showModal = false;
             if (this.pendingLogoutForm) {
                 this.pendingLogoutForm.submit();
             }
         },
         clockOutAndLogout() {
             window.hrnHaptic('success');
             this.proceedLogout();
         },
         cancel() {
             this.showModal = false;
             this.pendingLogoutForm = null;
         }
     }">

    {{-- Shift Handover Guard Modal --}}
    <div x-show="showModal"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/75 backdrop-blur-sm p-4">
        
        <div @click.away="cancel()"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="w-full max-w-md rounded-2xl bg-slate-900 border border-slate-700/80 p-6 shadow-2xl text-white">
            
            <div class="flex items-center gap-3 pb-3 border-b border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-400 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-100">Active Shift Handover Alert</h3>
                    <p class="text-xs text-slate-400">Shift status check prior to sign-out</p>
                </div>
            </div>

            <div class="py-4 space-y-2.5 text-sm text-slate-300">
                <p>
                    You are currently <strong>clocked into your shift</strong> today.
                </p>
                <p class="text-xs text-slate-400 leading-relaxed">
                    If your shift is concluded, remember to clock out so work duration is accurately captured in payroll. If stepping away or sharing a terminal, you may remain on duty.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 pt-3 border-t border-slate-800">
                <button type="button"
                        @click="cancel()"
                        class="px-4 py-2.5 text-xs font-semibold text-slate-400 hover:text-slate-200 rounded-xl hover:bg-slate-800 transition-colors">
                    Cancel
                </button>
                <button type="button"
                        @click="proceedLogout()"
                        class="px-4 py-2.5 text-xs font-semibold text-amber-300 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 rounded-xl transition-colors">
                    Stay Clocked In &amp; Sign Out
                </button>
                <button type="button"
                        @click="clockOutAndLogout()"
                        class="px-4 py-2.5 text-xs font-bold text-slate-950 bg-gradient-to-r from-emerald-400 to-teal-400 hover:from-emerald-300 hover:to-teal-300 rounded-xl shadow-lg shadow-emerald-500/20 transition-all">
                    Clock Out &amp; Sign Out
                </button>
            </div>
        </div>
    </div>
</div>
