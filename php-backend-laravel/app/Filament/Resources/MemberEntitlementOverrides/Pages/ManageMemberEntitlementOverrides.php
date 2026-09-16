<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberEntitlementOverrides\Pages;

use App\Filament\Resources\MemberEntitlementOverrides\MemberEntitlementOverrideResource;
use App\Models\AdminAction;
use App\Models\MemberEntitlementOverride;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMemberEntitlementOverrides extends ManageRecords
{
    protected static string $resource = MemberEntitlementOverrideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add override')
                ->mutateDataUsing(fn (array $data): array => $data + ['granted_by' => auth()->id()])
                ->after(fn (MemberEntitlementOverride $record) => AdminAction::log('member_override.created', [
                    'override_id' => $record->id, 'user_id' => $record->user_id, 'feature' => $record->feature_key,
                    'enabled' => $record->enabled, 'limit' => $record->limit_value,
                ])),
        ];
    }
}
