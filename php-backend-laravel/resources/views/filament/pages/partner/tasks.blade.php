<x-filament-panels::page>
    @php
        $tasks = $this->tasks;
    @endphp

    <div class="space-y-6">
        <div class="hrn-card p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-[var(--hrn-ink)]">Floor Task Delegation</h3>
                <p class="text-xs text-[var(--hrn-ink-2)]">Assign court checks, maintenance duties, and equipment checks to your venue staff.</p>
            </div>

            <button wire:click="openCreateModal"
                    type="button"
                    class="py-2 px-4 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 shadow-md shadow-blue-600/20 transition-all flex items-center gap-1.5">
                <x-heroicon-m-plus class="w-4 h-4" />
                <span>+ Delegate Task</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @forelse($tasks as $task)
                <div class="hrn-card p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            @if($task->priority === 'urgent')
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-rose-500/15 text-rose-600 dark:text-rose-400">Urgent</span>
                            @elseif($task->priority === 'high')
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-amber-500/15 text-amber-600 dark:text-amber-400">High</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-slate-500/15 text-slate-600 dark:text-slate-400">{{ $task->priority }}</span>
                            @endif

                            <span class="text-[11px] font-mono text-[var(--hrn-ink-3)]">Due: {{ \Illuminate\Support\Carbon::parse($task->due_date)->format('M d') }}</span>
                        </div>

                        <h4 class="text-sm font-bold text-[var(--hrn-ink)] mb-1">{{ $task->title }}</h4>
                        @if($task->description)
                            <p class="text-xs text-[var(--hrn-ink-2)] mb-3 leading-relaxed">{{ $task->description }}</p>
                        @endif
                    </div>

                    <div class="pt-3 border-t border-[var(--hrn-border)] mt-3 flex items-center justify-between text-xs">
                        <div>
                            <span class="text-[10px] text-[var(--hrn-ink-3)] block">Assignee</span>
                            <span class="font-bold text-[var(--hrn-ink)]">{{ $task->employee?->full_name }}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $task->status === 'completed' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-slate-500/15 text-slate-700 dark:text-slate-300' }}">
                            {{ $task->status }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="col-span-full hrn-card p-12 text-center text-xs text-[var(--hrn-ink-3)] italic">
                    No active tasks assigned yet. Click "+ Delegate Task" to assign floor duties.
                </div>
            @endforelse
        </div>

        {{-- Create Task Modal --}}
        @if($showCreateModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                <div class="w-full max-w-md rounded-2xl bg-[var(--hrn-surface)] border border-[var(--hrn-border)] p-6 shadow-2xl">
                    <div class="flex items-center justify-between pb-3 border-b border-[var(--hrn-border)] mb-4">
                        <h4 class="text-sm font-bold text-[var(--hrn-ink)]">Delegate Floor Task</h4>
                        <button wire:click="closeCreateModal" class="text-[var(--hrn-ink-3)] hover:text-[var(--hrn-ink)]">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit="createTask" class="space-y-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Task Title</label>
                            <input type="text" wire:model="taskTitle" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" placeholder="e.g. Clean Badminton Courts 1-4" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Assign To</label>
                            <select wire:model="assigneeId" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" required>
                                <option value="">-- Choose Staff Member --</option>
                                @foreach($this->staff as $s)
                                    <option value="{{ $s->id }}">{{ $s->full_name }} ({{ $s->employee_code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Priority</label>
                                <select wire:model="taskPriority" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Due Date</label>
                                <input type="date" wire:model="taskDueDate" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[var(--hrn-ink)] mb-1">Instructions / Description</label>
                            <textarea wire:model="taskDesc" rows="3" class="w-full text-xs rounded-xl border border-[var(--hrn-border)] bg-[var(--hrn-surface)] text-[var(--hrn-ink)] p-2.5" placeholder="Provide checklist or execution steps..."></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" wire:click="closeCreateModal" class="px-4 py-2 text-xs font-bold text-[var(--hrn-ink-2)] rounded-xl hover:bg-[var(--hrn-track)]">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 rounded-xl">
                                Assign Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
