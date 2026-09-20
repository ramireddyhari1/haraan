<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardProgramResource;
use App\Models\AdminAction;
use App\Models\RewardProgram;
use Filament\Resources\Pages\CreateRecord;

class CreateRewardProgram extends CreateRecord
{
    protected static string $resource = RewardProgramResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['kind'] ?? null) !== RewardProgram::KIND_SPONSORED) {
            $data['sponsor_id'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var RewardProgram $program */
        $program = $this->getRecord();
        AdminAction::log('reward_program.created', [
            'program_id' => $program->id, 'name' => $program->name, 'kind' => $program->kind, 'status' => $program->status,
        ], $program);
    }

    protected function getRedirectUrl(): string
    {
        // Straight to the edit page, where the program's rules are added.
        return RewardProgramResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
