<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberPlans\Pages;

use App\Filament\Resources\MemberPlans\MemberPlanResource;
use App\Models\AdminAction;
use App\Models\MemberPlan;
use App\Services\Membership\MemberCatalog;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMemberPlan extends EditRecord
{
    protected static string $resource = MemberPlanResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        /** @var MemberPlan $plan */
        $plan = parent::resolveRecord($key);

        // A feature added in code since this plan was last edited appears in the grid, OFF.
        app(MemberCatalog::class)->ensureComplete($plan);

        return $plan;
    }

    protected function afterSave(): void
    {
        /** @var MemberPlan $plan */
        $plan = $this->getRecord();

        AdminAction::log('member_plan.updated', [
            'plan' => $plan->code,
            'entitlements' => $plan->entitlements()->get(['feature_key', 'enabled', 'limit_value'])->toArray(),
        ]);
    }
}
