<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Schemas\EventForm;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    /** Fold pasted image URLs into the images column before the record is created. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = EventForm::mergeImageSources($data);

        // Seed available seats from the total capacity. The form only asks for
        // total_slots; without this available_slots keeps its DB default of 0, so a
        // brand-new event would show "sold out" immediately.
        if (! isset($data['available_slots']) || (int) $data['available_slots'] <= 0) {
            $data['available_slots'] = (int) ($data['total_slots'] ?? 0);
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
