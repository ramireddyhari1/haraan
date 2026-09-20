<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardSponsorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRewardSponsors extends ManageRecords
{
    protected static string $resource = RewardSponsorResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add sponsor')];
    }
}
