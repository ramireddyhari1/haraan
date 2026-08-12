<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * A tap-to-select analog clock time picker (like the phone / Material time
 * picker) instead of Filament's 24-hour spinner or a long dropdown of slots.
 *
 * Renders a readonly display with a clock button; clicking it opens a round
 * clock face where the host taps the hour, then the minutes, then AM / PM.
 * The stored state is the exact same "g:i A" string ("7:00 PM") the rest of
 * the app already expects, so nothing downstream changes.
 */
class ClockTimePicker extends Field
{
    protected string $view = 'filament.forms.components.clock-time-picker';

    /** Minute granularity offered on the clock face (default 5-minute steps). */
    protected int $minuteStep = 5;

    public function minuteStep(int $step): static
    {
        $this->minuteStep = max(1, min(60, $step));

        return $this;
    }

    public function getMinuteStep(): int
    {
        return $this->minuteStep;
    }
}
