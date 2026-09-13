<x-filament-panels::page>
    @php
        $profile = $this->profile;
        $user = auth()->user();
    @endphp

    <div class="space-y-6">
        {{-- Identity Header Banner --}}
        <div class="hrn-card p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-2xl">
                        {{ strtoupper(substr($user->name ?? 'E', 0, 2)) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-[var(--hrn-ink)]">{{ $user->name }}</h2>
                        <div class="flex flex-wrap items-center gap-2 mt-1 text-xs text-[var(--hrn-ink-2)]">
                            <span class="font-mono font-semibold px-2 py-0.5 rounded bg-[var(--hrn-track)] text-[var(--hrn-ink)]">{{ $profile?->employee_code }}</span>
                            <span>&bull;</span>
                            <span>{{ $profile?->designation?->name ?? 'Staff' }}</span>
                            <span>&bull;</span>
                            <span>{{ $profile?->department?->name ?? 'Operations' }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
                        {{ $profile?->employment_status ?? 'Active' }}
                    </span>
                </div>
            </div>
        </div>

        <form wire:submit="saveDetails" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Official & Personal Information --}}
            <div class="hrn-card p-6 space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-[var(--hrn-border)]">
                    <x-heroicon-o-identification class="w-5 h-5 text-emerald-600" />
                    <h3 class="text-sm font-bold text-[var(--hrn-ink)]">Official &amp; Bio Information</h3>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-[var(--hrn-ink-3)] block">Email Address</span>
                        <span class="font-semibold text-[var(--hrn-ink)]">{{ $user->email }}</span>
                    </div>
                    <div>
                        <span class="text-[var(--hrn-ink-3)] block">Contact Number</span>
                        <span class="font-semibold text-[var(--hrn-ink)]">{{ $user->phone ?? 'Not registered' }}</span>
                    </div>
                    <div>
                        <span class="text-[var(--hrn-ink-3)] block">Joining Date</span>
                        <span class="font-semibold text-[var(--hrn-ink)]">{{ $profile?->joining_date?->format('M d, Y') ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-[var(--hrn-ink-3)] block">Assigned Venue</span>
                        <span class="font-semibold text-[var(--hrn-ink)]">{{ $profile?->venue?->name ?? 'HQ / Mobile' }}</span>
                    </div>
                </div>

                <div class="pt-3 border-t border-[var(--hrn-border)] grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Blood Group</label>
                        <select wire:model="bloodGroup" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5">
                            <option value="">-- Select --</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Marital Status</label>
                        <select wire:model="maritalStatus" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5">
                            <option value="">-- Select --</option>
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Residential Address</label>
                    <textarea wire:model="residentialAddress" rows="2" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5"></textarea>
                </div>
            </div>

            {{-- Emergency Contact & KYC --}}
            <div class="hrn-card p-6 space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-[var(--hrn-border)]">
                    <x-heroicon-o-shield-check class="w-5 h-5 text-emerald-600" />
                    <h3 class="text-sm font-bold text-[var(--hrn-ink)]">Emergency Contact &amp; Statutory KYC</h3>
                </div>

                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Contact Name</label>
                            <input type="text" wire:model="emergencyContactName" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Relationship</label>
                            <input type="text" wire:model="emergencyContactRelation" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" placeholder="e.g. Spouse, Parent">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Emergency Phone Number</label>
                        <input type="text" wire:model="emergencyContactPhone" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5">
                    </div>
                </div>

                <div class="pt-3 border-t border-[var(--hrn-border)]">
                    <span class="text-xs font-bold text-[var(--hrn-ink)] block mb-2">Verified Government KYC</span>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="p-2.5 rounded-xl bg-[var(--hrn-track)]/40 border border-[var(--hrn-border)]">
                            <span class="text-[10px] text-[var(--hrn-ink-3)] block">PAN Card Number</span>
                            <span class="font-mono font-bold text-[var(--hrn-ink)]">{{ $profile?->pan_number ? '•••• ' . substr($profile->pan_number, -4) : 'Verified' }}</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-[var(--hrn-track)]/40 border border-[var(--hrn-border)]">
                            <span class="text-[10px] text-[var(--hrn-ink-3)] block">Aadhaar UID</span>
                            <span class="font-mono font-bold text-[var(--hrn-ink)]">{{ $profile?->aadhaar_number ? '•••• ' . substr($profile->aadhaar_number, -4) : 'Verified' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Banking & Direct Deposit Details --}}
            <div class="hrn-card p-6 lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[var(--hrn-border)]">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-credit-card class="w-5 h-5 text-emerald-600" />
                        <h3 class="text-sm font-bold text-[var(--hrn-ink)]">Payroll Direct Deposit &amp; Bank Account</h3>
                    </div>
                    <span class="text-xs text-[var(--hrn-ink-3)]">Used for salary bank transfer</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Bank Name</label>
                        <input type="text" wire:model="bankName" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" placeholder="e.g. HDFC Bank">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Account Number</label>
                        <input type="text" wire:model="bankAccountNo" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">IFSC Code</label>
                        <input type="text" wire:model="bankIfsc" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" placeholder="HDFC0001234">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">UPI ID (Optional)</label>
                        <input type="text" wire:model="bankUpiId" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" placeholder="name@upi">
                    </div>
                </div>

                <div class="flex justify-end pt-3">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="py-2.5 px-6 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 shadow-md shadow-emerald-600/20 transition-all">
                        Save Profile Details
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-filament-panels::page>
