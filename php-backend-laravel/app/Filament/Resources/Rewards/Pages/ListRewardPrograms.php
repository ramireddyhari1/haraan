<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRewardPrograms extends ListRecords
{
    protected static string $resource = RewardProgramResource::class;

    public function getSubheading(): ?string
    {
        return 'What players can win after a match. Coupons, sponsor codes and membership trials always stay locked until the result is verified. '
            .'Limits, rewarded ads and notifications are in Platform rules → Post-match rewards.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New program')];
    }
}
