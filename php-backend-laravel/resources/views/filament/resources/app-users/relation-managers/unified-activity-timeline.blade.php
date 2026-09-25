<div class="space-y-4">
    {{-- Filter Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-xs">
        {{-- Category Pills --}}
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            @php
                $categories = [
                    'all'        => ['label' => 'All Events', 'icon' => 'heroicon-m-list-bullet'],
                    'admin'      => ['label' => 'Security & Admin', 'icon' => 'heroicon-m-shield-check'],
                    'booking'    => ['label' => 'Bookings', 'icon' => 'heroicon-m-calendar-days'],
                    'match'      => ['label' => 'GameHub', 'icon' => 'heroicon-m-trophy'],
                    'support'    => ['label' => 'Support', 'icon' => 'heroicon-m-chat-bubble-left-right'],
                    'reward'     => ['label' => 'Rewards', 'icon' => 'heroicon-m-gift'],
                    'moderation' => ['label' => 'Moderation', 'icon' => 'heroicon-m-flag'],
                    'note'       => ['label' => 'Staff Notes', 'icon' => 'heroicon-m-document-text'],
                ];
            @endphp

            @foreach ($categories as $key => $cat)
                <button
                    type="button"
                    wire:click="$set('category', '{{ $key }}')"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg font-medium transition-colors cursor-pointer {{ $category === $key ? 'bg-primary-600 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                >
                    <x-filament::icon :icon="$cat['icon']" class="w-3.5 h-3.5" />
                    <span>{{ $cat['label'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Search input --}}
        <div class="relative w-full md:w-64">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search activity stream…"
                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white pl-8 pr-7 py-1.5 focus:border-primary-500 focus:ring-primary-500"
            />
            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="w-3.5 h-3.5" />
            </div>
            @if(!empty($search))
                <button
                    type="button"
                    wire:click="$set('search', '')"
                    class="absolute inset-y-0 right-0 pr-2 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                >
                    <x-filament::icon icon="heroicon-m-x-mark" class="w-3.5 h-3.5" />
                </button>
            @endif
        </div>
    </div>

    {{-- Timeline Stream --}}
    @if ($timeline->isEmpty())
        <div class="py-12 text-center bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl">
            <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto text-gray-400 mb-3">
                <x-filament::icon icon="heroicon-o-clock" class="w-6 h-6" />
            </div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">No activity found</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                No events match the selected category or search filters for this user.
            </p>
        </div>
    @else
        <div class="relative pl-6 space-y-6 before:absolute before:left-3 before:top-2 before:bottom-2 before:w-0.5 before:bg-gray-200 dark:before:bg-gray-800">
            @foreach ($timeline as $event)
                <div class="relative group">
                    {{-- Timeline Bullet Node --}}
                    @php
                        $colorClasses = match($event['badge_color']) {
                            'success' => 'bg-emerald-500 ring-emerald-100 dark:ring-emerald-950 text-white',
                            'danger'  => 'bg-rose-500 ring-rose-100 dark:ring-rose-950 text-white',
                            'warning' => 'bg-amber-500 ring-amber-100 dark:ring-amber-950 text-white',
                            'info'    => 'bg-sky-500 ring-sky-100 dark:ring-sky-950 text-white',
                            'primary' => 'bg-indigo-500 ring-indigo-100 dark:ring-indigo-950 text-white',
                            default   => 'bg-gray-400 ring-gray-100 dark:ring-gray-900 text-white',
                        };
                    @endphp

                    <div class="absolute -left-6 top-1.5 w-6 h-6 rounded-full {{ $colorClasses }} ring-4 flex items-center justify-center shadow-xs">
                        <x-filament::icon :icon="$event['icon']" class="w-3.5 h-3.5" />
                    </div>

                    {{-- Event Card --}}
                    <div class="p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-xs hover:border-gray-300 dark:hover:border-gray-700 transition-colors">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wider
                                    {{ match($event['badge_color']) {
                                        'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
                                        'danger'  => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
                                        'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
                                        'info'    => 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
                                        'primary' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300',
                                        default   => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                    } }}">
                                    {{ $event['category_label'] }}
                                </span>
                                <h5 class="text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ $event['title'] }}
                                </h5>
                            </div>

                            <time class="text-[11px] text-gray-500 dark:text-gray-400 font-mono" title="{{ $event['occurred_at']->format('d M Y, H:i:s') }}">
                                {{ $event['occurred_at']->diffForHumans() }}
                            </time>
                        </div>

                        <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                            {{ $event['description'] }}
                        </p>

                        <div class="mt-2.5 pt-2 border-t border-gray-100 dark:border-gray-800/80 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <x-filament::icon icon="heroicon-m-user" class="w-3 h-3 text-gray-400" />
                                {{ $event['actor'] }}
                            </span>

                            @if (!empty($event['action_url']))
                                <a
                                    href="{{ $event['action_url'] }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-primary-600 dark:text-primary-400 hover:underline font-medium"
                                >
                                    <span>Inspect</span>
                                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="w-3 h-3" />
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Detailed Audit Table (Filament Table Integration) --}}
    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-800">
        <div class="mb-3">
            <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Audit Trail Ledger</h4>
        </div>
        {{ $this->table }}
    </div>
</div>
