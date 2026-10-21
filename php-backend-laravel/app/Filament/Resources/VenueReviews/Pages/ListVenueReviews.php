<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueReviews\Pages;

use App\Filament\Resources\VenueReviews\VenueReviewResource;
use App\Models\VenueReview;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVenueReviews extends ListRecords
{
    protected static string $resource = VenueReviewResource::class;

    /** The four states an admin asks about, one tap each — Deleted is the "see deleted" view. */
    public function getTabs(): array
    {
        $base = fn () => VenueReview::query()->withTrashed()->whereNotNull('venue_id');

        return [
            'all' => Tab::make('Live')
                ->badge($base()->whereNull('deleted_at')->where('is_active', true)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('deleted_at')->where('is_active', true)),
            'hidden' => Tab::make('Hidden')
                ->badge($base()->whereNull('deleted_at')->where('is_active', false)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('deleted_at')->where('is_active', false)),
            'low' => Tab::make('1–2 stars')
                ->badge($base()->whereNull('deleted_at')->where('rating', '<=', 2)->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('deleted_at')->where('rating', '<=', 2)),
            'deleted' => Tab::make('Deleted')
                ->badge($base()->whereNotNull('deleted_at')->count() ?: null)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('deleted_at')),
        ];
    }
}
