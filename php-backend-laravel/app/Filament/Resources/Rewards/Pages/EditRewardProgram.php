<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardProgramResource;
use App\Models\AdminAction;
use App\Models\RewardProgram;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRewardProgram extends EditRecord
{
    protected static string $resource = RewardProgramResource::class;

    private ?string $statusBefore = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->statusBefore = $this->getRecord()->status;
        if (($data['kind'] ?? null) !== RewardProgram::KIND_SPONSORED) {
            $data['sponsor_id'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var RewardProgram $program */
        $program = $this->getRecord();
        if ($this->statusBefore !== null && $this->statusBefore !== $program->status) {
            AdminAction::log('reward_program.'.($program->status === 'live' ? 'published' : $program->status), [
                'program_id' => $program->id, 'name' => $program->name, 'from' => $this->statusBefore, 'to' => $program->status,
            ], $program);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            // A program that has given rewards is history the ledger points at: end it instead.
            DeleteAction::make()
                ->visible(fn (RewardProgram $record): bool => $record->grants_count === 0)
                ->after(fn (RewardProgram $record) => AdminAction::log('reward_program.deleted', ['program_id' => $record->id, 'name' => $record->name])),
        ];
    }
}
