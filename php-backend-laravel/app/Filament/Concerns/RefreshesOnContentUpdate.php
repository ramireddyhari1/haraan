<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Livewire\Attributes\On;

/**
 * Makes a Filament widget / page re-render (and therefore refetch its data) when the panel's
 * realtime bridge (public/js/filament-realtime.js) receives a Reverb `content.updated`
 * broadcast and dispatches the global `haraan-content-updated` Livewire event.
 *
 * The listener body is intentionally empty: handling any Livewire message re-renders the
 * component, and widgets compute their data during render (getStats / getData / table query),
 * so a no-op handler is enough to pull fresh numbers. No broadcast payload is trusted.
 */
trait RefreshesOnContentUpdate
{
    #[On('haraan-content-updated')]
    public function refreshOnContentUpdate(): void
    {
        // no-op — the re-render triggered by receiving this event refetches the data.
    }
}
