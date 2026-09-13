<x-filament-panels::page>
    @php
        $staffMembers = $this->staff;
    @endphp

    <div class="space-y-6">
        <div class="hrn-card p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-[var(--hrn-ink)]">Venue Staff Performance Reviews</h3>
                <p class="text-xs text-[var(--hrn-ink-2)]">Submit monthly star ratings and feedback for your venue desk and ground staff.</p>
            </div>

            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-[var(--hrn-ink-2)]">Review Cycle:</label>
                <input type="month"
                       wire:model.live="reviewPeriod"
                       class="text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] px-3 py-2 font-bold">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($staffMembers as $s)
                @php
                    $latestKpi = $s->kpis->where('period', $reviewPeriod)->first();
                @endphp

                <div class="hrn-card p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-xs font-mono font-bold text-[var(--hrn-ink-3)]">{{ $s->employee_code }}</span>
                            @if($latestKpi)
                                <span class="px-2 py-0.5 rounded text-xs font-extrabold bg-amber-500/15 text-amber-600 dark:text-amber-400">
                                    {{ number_format((float)$latestKpi->overall_score, 1) }} ★
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-500/15 text-slate-500 uppercase">
                                    Unreviewed
                                </span>
                            @endif
                        </div>

                        <h4 class="text-sm font-bold text-[var(--hrn-ink)] mb-0.5">{{ $s->full_name }}</h4>
                        <div class="text-xs text-[var(--hrn-ink-2)] mb-3">{{ $s->designation?->name ?? 'Floor Staff' }}</div>

                        @if($latestKpi && $latestKpi->manager_feedback)
                            <p class="text-xs text-[var(--hrn-ink-2)] italic bg-[var(--hrn-track)]/30 p-2.5 rounded-xl border border-[var(--hrn-border)] mb-3">
                                &ldquo;{{ $latestKpi->manager_feedback }}&rdquo;
                            </p>
                        @endif
                    </div>

                    <div class="pt-3 border-t border-[var(--hrn-border)] mt-3">
                        <button wire:click="openReviewModal({{ $s->id }})"
                                class="w-full py-2 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 transition-all">
                            {{ $latestKpi ? 'Update Appraisal' : 'Submit Review' }}
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full hrn-card p-12 text-center text-xs text-[var(--hrn-ink-3)] italic">
                    No active staff assigned to your venues.
                </div>
            @endforelse
        </div>

        {{-- Review Modal --}}
        @if($showReviewModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                <div class="w-full max-w-md rounded-2xl bg-[var(--hrn-surface)] border border-[var(--hrn-border)] p-6 shadow-2xl">
                    <div class="flex items-center justify-between pb-3 border-b border-[var(--hrn-border)] mb-4">
                        <h4 class="text-sm font-bold text-[var(--hrn-ink)]">Staff Appraisal &bull; {{ $reviewPeriod }}</h4>
                        <button wire:click="closeReviewModal" class="text-[var(--hrn-ink-3)] hover:text-[var(--hrn-ink)]">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit="submitReview" class="space-y-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Punctuality &amp; Discipline (1 to 5 Stars)</label>
                            <input type="number" step="0.1" min="1" max="5" wire:model="punctuality" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Task Execution (1 to 5 Stars)</label>
                            <input type="number" step="0.1" min="1" max="5" wire:model="execution" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Customer / Guest Service (1 to 5 Stars)</label>
                            <input type="number" step="0.1" min="1" max="5" wire:model="service" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Teamwork &amp; Reliability (1 to 5 Stars)</label>
                            <input type="number" step="0.1" min="1" max="5" wire:model="teamwork" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Manager Notes &amp; Appraisal Feedback</label>
                            <textarea wire:model="feedback" rows="3" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" placeholder="Share qualitative feedback or commendations..."></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" wire:click="closeReviewModal" class="px-4 py-2 text-xs font-bold text-[var(--hrn-ink-2)] rounded-xl hover:bg-[var(--hrn-track)]">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 rounded-xl">
                                Save Appraisal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
