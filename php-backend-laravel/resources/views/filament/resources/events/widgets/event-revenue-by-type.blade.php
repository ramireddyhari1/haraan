<x-filament-widgets::widget>
    <x-filament::section collapsible>
        <x-slot name="heading">Revenue by Ticket Type</x-slot>
        <x-slot name="description">Paid revenue split across tiers</x-slot>

        @if (empty($rows))
            <p class="text-sm text-gray-500 dark:text-gray-400">No paid bookings yet.</p>
        @else
            <div class="space-y-4">
                @foreach ($rows as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-gray-950 dark:text-white">
                                {{ $row['name'] }}
                                <span class="text-gray-400 dark:text-gray-500">
                                    · {{ number_format($row['tickets']) }} tickets · {{ number_format($row['orders']) }} orders
                                </span>
                            </span>
                            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">
                                ₹{{ number_format($row['revenue']) }}
                                <span class="text-gray-400 dark:text-gray-500">({{ $row['pct'] }}%)</span>
                            </span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-2 rounded-full bg-primary-500" style="width: {{ max($row['pct'], 2) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
