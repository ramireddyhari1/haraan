<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenuePackages\Pages;

use App\Filament\Resources\VenuePackages\VenuePackageResource;
use App\Models\AdminAction;
use App\Models\Venue;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditVenuePackage extends EditRecord
{
    protected static string $resource = VenuePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [VenuePackageResource::pauseAction()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // An offer can only be locked to one of its own partner's venues.
        if (! empty($data['venue_id'])
            && ! Venue::query()->where('partner_id', $this->record->partner_id)->whereKey($data['venue_id'])->exists()) {
            throw ValidationException::withMessages(['data.venue_id' => 'That venue belongs to another partner.']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $changes = collect($this->record->getChanges())->except(['updated_at'])->all();
        if ($changes !== []) {
            AdminAction::log('package.updated', [
                'partner_id' => $this->record->partner_id,
                'changes' => $changes,
            ], $this->record);
        }
    }
}
