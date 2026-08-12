<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBlocks\Pages;

use App\Filament\Resources\VenueBlocks\VenueBlockResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVenueBlock extends EditRecord
{
    protected static string $resource = VenueBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Removing this block puts the court-hour back on sale immediately.'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
