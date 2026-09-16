<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberSubscriptions\Pages;

use App\Filament\Resources\MemberSubscriptions\MemberSubscriptionActions;
use App\Filament\Resources\MemberSubscriptions\MemberSubscriptionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMemberSubscription extends ViewRecord
{
    protected static string $resource = MemberSubscriptionResource::class;

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return trim(($record->user?->name ?? 'Member') . ' · ' . ($record->plan?->name ?? 'Plan'));
    }

    protected function getHeaderActions(): array
    {
        return MemberSubscriptionActions::forRecord();
    }
}
