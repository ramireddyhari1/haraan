<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberPlans\Pages;

use App\Filament\Resources\MemberPlans\MemberPlanResource;
use App\Models\AdminAction;
use App\Services\Membership\MemberCatalog;
use Filament\Resources\Pages\CreateRecord;

class CreateMemberPlan extends CreateRecord
{
    protected static string $resource = MemberPlanResource::class;

    protected function afterCreate(): void
    {
        app(MemberCatalog::class)->ensureComplete($this->getRecord());
        AdminAction::log('member_plan.created', ['plan' => $this->getRecord()->code]);
    }

    protected function getRedirectUrl(): string
    {
        // Straight to edit, where prices are added and linked to Razorpay.
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
