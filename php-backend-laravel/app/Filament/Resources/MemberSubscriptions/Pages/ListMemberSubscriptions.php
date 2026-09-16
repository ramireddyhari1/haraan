<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberSubscriptions\Pages;

use App\Filament\Resources\MemberSubscriptions\MemberSubscriptionActions;
use App\Filament\Resources\MemberSubscriptions\MemberSubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListMemberSubscriptions extends ListRecords
{
    protected static string $resource = MemberSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [MemberSubscriptionActions::grant()];
    }
}
