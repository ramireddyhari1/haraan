<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeedRestrictions\Pages;

use App\Filament\Resources\FeedRestrictions\FeedRestrictionResource;
use App\Models\AdminAction;
use App\Models\FeedRestriction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFeedRestrictions extends ManageRecords
{
    protected static string $resource = FeedRestrictionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Restrict a player')
                ->mutateDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id()])
                ->after(fn (FeedRestriction $record) => AdminAction::log('home_feed.restriction_added', [
                    'user_id' => $record->user_id, 'type' => $record->type, 'until' => $record->until?->toIso8601String(),
                ], $record)),
        ];
    }
}
