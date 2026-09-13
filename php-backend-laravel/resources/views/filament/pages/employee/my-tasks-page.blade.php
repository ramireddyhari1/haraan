<x-filament-panels::page>
    @php
        $tasks = $this->tasks;
    @endphp

    <div class="space-y-6">
        <div class="hrn-card p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-[var(--hrn-ink)]">Task Assignment Board</h3>
                <p class="text-xs text-[var(--hrn-ink-2)]">Manage your daily checklist, floor instructions, and task progress.</p>
            </div>

            <div class="flex items-center gap-1.5 p-1 bg-[var(--hrn-track)] rounded-xl text-xs font-bold">
                <button wire:click="$set('currentFilter', 'all')"
                        class="px-3 py-1.5 rounded-lg transition-all {{ $currentFilter === 'all' ? 'bg-[var(--hrn-surface)] text-[var(--hrn-ink)] shadow-xs' : 'text-[var(--hrn-ink-2)]' }}">
                    All
                </button>
                <button wire:click="$set('currentFilter', 'todo')"
                        class="px-3 py-1.5 rounded-lg transition-all {{ $currentFilter === 'todo' ? 'bg-[var(--hrn-surface)] text-[var(--hrn-ink)] shadow-xs' : 'text-[var(--hrn-ink-2)]' }}">
                    To-Do
                </button>
                <button wire:click="$set('currentFilter', 'in_progress')"
                        class="px-3 py-1.5 rounded-lg transition-all {{ $currentFilter === 'in_progress' ? 'bg-[var(--hrn-surface)] text-[var(--hrn-ink)] shadow-xs' : 'text-[var(--hrn-ink-2)]' }}">
                    In Progress
                </button>
                <button wire:click="$set('currentFilter', 'completed')"
                        class="px-3 py-1.5 rounded-lg transition-all {{ $currentFilter === 'completed' ? 'bg-[var(--hrn-surface)] text-[var(--hrn-ink)] shadow-xs' : 'text-[var(--hrn-ink-2)]' }}">
                    Done
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($tasks as $task)
                <div class="hrn-card p-5 flex flex-col justify-between hover:border-emerald-500/30 transition-all">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            @if($task->priority === 'urgent')
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-rose-500/15 text-rose-600 dark:text-rose-400">Urgent</span>
                            @elseif($task->priority === 'high')
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-amber-500/15 text-amber-600 dark:text-amber-400">High Priority</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-slate-500/15 text-slate-600 dark:text-slate-400">{{ $task->priority }}</span>
                            @endif

                            @if($task->due_date)
                                <span class="text-[11px] font-mono text-[var(--hrn-ink-3)]">Due: {{ \Illuminate\Support\Carbon::parse($task->due_date)->format('M d') }}</span>
                            @endif
                        </div>

                        <h4 class="text-sm font-bold text-[var(--hrn-ink)] mb-1 {{ $task->status === 'completed' ? 'line-through opacity-60' : '' }}">
                            {{ $task->title }}
                        </h4>

                        @if($task->description)
                            <p class="text-xs text-[var(--hrn-ink-2)] mb-3 leading-relaxed">{{ $task->description }}</p>
                        @endif
                    </div>

                    <div class="pt-3 border-t border-[var(--hrn-border)] mt-3">
                        <div class="flex items-center justify-between text-[11px] text-[var(--hrn-ink-3)] mb-2">
                            <span>From: {{ $task->assigner?->name ?? 'Manager' }}</span>
                            <span class="font-bold text-[var(--hrn-ink)] uppercase">{{ str_replace('_', ' ', $task->status) }}</span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            @if($task->status !== 'completed')
                                <button wire:click="updateStatus({{ $task->id }}, 'completed')"
                                        class="flex-1 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 transition-all">
                                    Mark Done
                                </button>
                                @if($task->status === 'todo')
                                    <button wire:click="updateStatus({{ $task->id }}, 'in_progress')"
                                            class="py-1.5 px-3 rounded-lg text-xs font-bold border border-[var(--hrn-border)] text-[var(--hrn-ink-2)] hover:bg-[var(--hrn-track)] transition-all">
                                        Start
                                    </button>
                                @endif
                            @else
                                <button wire:click="updateStatus({{ $task->id }}, 'todo')"
                                        class="flex-1 py-1.5 rounded-lg text-xs font-bold border border-[var(--hrn-border)] text-[var(--hrn-ink-3)] hover:bg-[var(--hrn-track)] transition-all">
                                    Re-open
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full hrn-card p-12 text-center text-xs text-[var(--hrn-ink-3)] italic">
                    No tasks found matching current filter.
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
