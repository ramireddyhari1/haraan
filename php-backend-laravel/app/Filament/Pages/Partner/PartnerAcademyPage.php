<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

/** /partner/academy — coaching batches and attendance, the app's Academy tool. */
class PartnerAcademyPage extends PartnerAppOnlyPage
{
    protected static ?string $slug = 'academy';

    protected static ?string $title = 'Academy';

    protected static ?string $permission = 'pricing';
}
