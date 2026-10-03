<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

/** /partner/packages — prepaid session passes, the app's Packages tool. */
class PartnerPackagesPage extends PartnerAppOnlyPage
{
    protected static ?string $slug = 'packages';

    protected static ?string $title = 'Packages';

    protected static ?string $permission = 'pricing';
}
