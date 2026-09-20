<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardGrantResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRewardGrants extends ManageRecords
{
    protected static string $resource = RewardGrantResource::class;

    public function getSubheading(): ?string
    {
        return 'Every reward given, newest first. Bonus XP here is separate from competitive XP and never affects leaderboards.';
    }
}
