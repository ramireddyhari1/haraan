<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardCodePoolResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRewardCodePools extends ListRecords
{
    protected static string $resource = RewardCodePoolResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New code pool')];
    }
}
