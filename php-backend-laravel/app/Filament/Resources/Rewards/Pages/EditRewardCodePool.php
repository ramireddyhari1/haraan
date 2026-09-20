<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardCodePoolResource;
use App\Models\AdminAction;
use App\Models\RewardCodePool;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRewardCodePool extends EditRecord
{
    protected static string $resource = RewardCodePoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A pool with codes already given out is history: those winners still need them.
            DeleteAction::make()
                ->visible(fn (RewardCodePool $record): bool => ! $record->codes()->whereNotNull('grant_id')->exists())
                ->after(fn (RewardCodePool $record) => AdminAction::log('reward_pool.deleted', ['pool_id' => $record->id, 'name' => $record->name])),
        ];
    }
}
