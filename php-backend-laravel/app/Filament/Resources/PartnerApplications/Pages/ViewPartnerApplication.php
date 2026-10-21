<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerApplications\Pages;

use App\Filament\Resources\PartnerApplications\PartnerApplicationActions;
use App\Filament\Resources\PartnerApplications\PartnerApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPartnerApplication extends ViewRecord
{
    protected static string $resource = PartnerApplicationResource::class;

    public function getTitle(): string
    {
        return (string) $this->getRecord()->business_name;
    }

    public function getSubheading(): ?string
    {
        $r = $this->getRecord();

        return $r->reference . ' · ' . $r->businessTypeLabel() . ' · ' . $r->city;
    }

    protected function getHeaderActions(): array
    {
        return [
            PartnerApplicationActions::call(),
            PartnerApplicationActions::whatsapp(),
            PartnerApplicationActions::notes(),
            PartnerApplicationActions::markContacted(),
            PartnerApplicationActions::reject(),
            PartnerApplicationActions::reopen(),
            PartnerApplicationActions::approve(),
            PartnerApplicationActions::createVenue(),
            DeleteAction::make()->after(fn () => \App\Models\AdminAction::log('partner_application.deleted', ['reference' => $this->getRecord()->reference])),
        ];
    }
}
