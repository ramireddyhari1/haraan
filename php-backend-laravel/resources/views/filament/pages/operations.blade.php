<x-filament-panels::page>
    {{-- Live state first: an admin opening this page mid-incident needs to see what is off. --}}
    <x-filament::section>
        @if (count($active) === 0)
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Everything is running normally. Nothing below is switched on.
            </p>
        @else
            <p class="text-sm font-semibold text-danger-600 dark:text-danger-400">
                {{ count($active) === 1 ? '1 switch is on' : count($active) . ' switches are on' }}
            </p>
            <ul class="mt-2 list-disc ps-5 text-sm text-gray-700 dark:text-gray-200">
                @foreach ($active as $label)
                    <li>{{ $label }}</li>
                @endforeach
            </ul>
        @endif
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            AI calls today: {{ number_format($aiUsed) }}{{ $aiBudget > 0 ? ' of ' . number_format($aiBudget) : ' (no daily cap)' }}.
            Fees, limits and the rest live under Platform rules.
        </p>
    </x-filament::section>

    <form wire:submit="save">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
