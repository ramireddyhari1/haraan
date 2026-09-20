<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardCodePoolResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRewardCodePool extends CreateRecord
{
    protected static string $resource = RewardCodePoolResource::class;

    protected function getRedirectUrl(): string
    {
        // Straight to the pool, where codes are imported.
        return RewardCodePoolResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
