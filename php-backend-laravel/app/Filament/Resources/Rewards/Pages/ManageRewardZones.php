<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardZoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRewardZones extends ManageRecords
{
    protected static string $resource = RewardZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add zone')];
    }
}
