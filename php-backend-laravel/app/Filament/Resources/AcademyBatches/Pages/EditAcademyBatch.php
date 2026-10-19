<?php

declare(strict_types=1);

namespace App\Filament\Resources\AcademyBatches\Pages;

use App\Filament\Resources\AcademyBatches\AcademyBatchResource;
use App\Models\AdminAction;
use App\Models\Venue;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditAcademyBatch extends EditRecord
{
    protected static string $resource = AcademyBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [AcademyBatchResource::pauseAction()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A batch can only sit at one of its own partner's venues.
        if (! empty($data['venue_id'])
            && ! Venue::query()->where('partner_id', $this->record->partner_id)->whereKey($data['venue_id'])->exists()) {
            throw ValidationException::withMessages(['data.venue_id' => 'That venue belongs to another partner.']);
        }

        $data['days'] = array_values($data['days'] ?? []) ?: null;
        if (! empty($data['start_time']) && ! empty($data['end_time']) && $data['end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages(['data.end_time' => 'The batch must end after it starts.']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $changes = collect($this->record->getChanges())->except(['updated_at'])->all();
        if ($changes !== []) {
            AdminAction::log('academy_batch.updated', [
                'partner_id' => $this->record->partner_id,
                'changes' => $changes,
            ], $this->record);
        }
    }
}
