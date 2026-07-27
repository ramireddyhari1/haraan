<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Models\EventSlot;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    /**
     * Move the primary Create / Cancel controls up into the page header (top-right,
     * beside the title) and drop the default footer bar, so the action is always in
     * view without scrolling to the bottom of a long wizard.
     */
    /** A human subheading under the title so the header reads designed, not default. */
    public function getSubheading(): ?string
    {
        return 'Set up your event in a few guided steps — you can fine-tune anything later.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create event')
                ->keyBindings(['mod+s'])
                ->action('create'),
            Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
        ];
    }

    /** Footer form actions removed — they now live in the header (see above). */
    protected function getFormActions(): array
    {
        return [];
    }

    /** The Ticket Studio state lifted out of the form data before the event is saved. */
    private array $studioState = [];

    /**
     * Seed the default session's start time from the event's date/time, then create
     * the ticket tiers authored in the Ticket Studio (create-only; edit uses the
     * Ticket Types relation manager).
     */
    protected function afterCreate(): void
    {
        $event = $this->record;
        $default = self::combineDateTime($event->date, $event->time);

        // The Sessions repeater is hidden on create, so seed one default session from
        // the event's date/time — every event needs at least one slot to book against.
        if ($event->slots()->count() === 0) {
            $event->slots()->create(['starts_at' => $default, 'sort' => 0]);
        } elseif ($default !== null) {
            $event->slots()
                ->whereNull('starts_at')
                ->get()
                ->each(function (EventSlot $slot) use ($default): void {
                    $slot->starts_at = $default;
                    $slot->save();
                });
        }

        foreach (EventForm::studioTicketRows($this->studioState) as $row) {
            $event->ticketTypes()->create($row);
        }
    }

    /**
     * The event's overall capacity, derived from the Ticket Studio seats: the sum of
     * the capped tiers, or a high cap when any tier is unlimited (or none are capped)
     * so the per-tier limits do the gating instead of the event total.
     */
    private static function derivedCapacity(array $studio): int
    {
        $sum = 0;
        $hasUnlimited = false;

        foreach (($studio['tickets'] ?? []) as $t) {
            if (! is_array($t) || trim((string) ($t['name'] ?? '')) === '') {
                continue;
            }
            $seats = $t['seats'] ?? -1;
            if ($seats === null || $seats === '' || (int) $seats < 0) {
                $hasUnlimited = true;
            } else {
                $sum += max(0, (int) $seats);
            }
        }

        return ($hasUnlimited || $sum <= 0) ? 100000 : $sum;
    }

    /** Combine a stored date and a "7:00 PM" time label into a Carbon, best-effort. */
    private static function combineDateTime($date, ?string $time): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $day = $date instanceof Carbon ? $date->copy() : Carbon::parse((string) $date);
        } catch (\Throwable) {
            return null;
        }

        $time = trim((string) $time);
        if ($time !== '') {
            $ts = strtotime($time);
            if ($ts !== false) {
                $day->setTime((int) date('G', $ts), (int) date('i', $ts));
            }
        }

        return $day;
    }

    /** Fold pasted image URLs into the images column before the record is created. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = EventForm::mergeImageSources($data);

        // Lift the Ticket Studio out of the payload — it isn't an event column. Its
        // mode/seating map onto real columns; its ticket cards become TicketType rows
        // in afterCreate(). Stored on the page so afterCreate() can read it.
        $this->studioState = is_array($data['ticketStudio'] ?? null) ? $data['ticketStudio'] : [];
        unset($data['ticketStudio']);
        $data['tickets_per_slot'] = ($this->studioState['mode'] ?? 'unified') === 'per_slot';
        $data['seat_selection']   = (bool) ($this->studioState['seating'] ?? false);
        $data['release_phases']   = EventForm::studioReleasePhases($this->studioState) ?: null;

        // "Total capacity" and "Base price" are no longer authored on the form —
        // capacity is derived from the ticket seats (unlimited/none → a high cap so
        // the per-tier limits govern), and the base price stays 0 (the tiers price the
        // event). available_slots then seeds from the derived capacity.
        [$capacity] = [self::derivedCapacity($this->studioState)];
        $data['total_slots'] = $capacity;
        $data['price'] = 0;
        if (! isset($data['available_slots']) || (int) $data['available_slots'] <= 0) {
            $data['available_slots'] = $capacity;
        }

        // In the partner console, stamp ownership so the new event is scoped to
        // (and visible to) its creating partner. See ScopesToOrganization.
        if (Filament::getCurrentPanel()?->getId() === 'partner') {
            $data['partner_id'] = auth()->user()?->effectivePartnerId();

            // Partners can't self-publish: a partner-created event lands as
            // "pending review" and shows up in /control, where Haraan staff set
            // the publish controls (status · visibility · app rails) and go live.
            // These three form fields are hidden + non-dehydrated in the partner
            // console (see EventForm), so we seed sensible values here — the admin
            // overrides them at review time. (Only on create: partner edits to an
            // already-live event never touch status, so those stay live.)
            $data['status']      = 'pending';
            $data['visibility']  = $data['visibility'] ?? 'PUBLIC';
            $data['placements']  = $data['placements'] ?? ['for_you', 'trending', 'nearby'];
        }

        return $data;
    }

    /**
     * After creating, land back on the events list (not the edit form). Filament's
     * default drops you on the new record's edit page, which reads as "nothing
     * happened" — the user expects the new event to show up in the list.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
