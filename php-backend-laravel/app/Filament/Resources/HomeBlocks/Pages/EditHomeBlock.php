<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeBlocks\Pages;

use App\Filament\Resources\HomeBlocks\HomeBlockResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHomeBlock extends EditRecord
{
    protected static string $resource = HomeBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
