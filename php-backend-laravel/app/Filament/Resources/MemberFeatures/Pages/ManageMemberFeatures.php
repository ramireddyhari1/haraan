<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberFeatures\Pages;

use App\Filament\Resources\MemberFeatures\MemberFeatureResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMemberFeatures extends ManageRecords
{
    protected static string $resource = MemberFeatureResource::class;
}
