<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueReviews;

use App\Filament\Resources\VenueReviews\Pages\ListVenueReviews;
use App\Filament\Resources\VenueReviews\Tables\VenueReviewsTable;
use App\Models\VenueReview;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Every customer review of a venue — from the app's and the website's RATE VENUE, and
 * from the post-booking link (/r/{code}). Until this page existed, /control had no view
 * of them at all: nothing could be hidden, removed or brought back.
 *
 * Admins moderate, they never edit: a customer's words are theirs. Hide keeps the row
 * (and its rating) out of the venue page and the average; Delete is a soft delete that
 * "Deleted" / "With deleted" shows again and Restore undoes; only Delete forever purges.
 */
class VenueReviewResource extends Resource
{
    protected static ?string $model = VenueReview::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?string $navigationLabel = 'Reviews';

    protected static ?string $modelLabel = 'review';

    protected static ?string $pluralModelLabel = 'reviews';

    protected static ?int $navigationSort = 6;

    /** Venue reviews only (event ratings share the table), deleted rows included for the filter. */
    public static function getEloquentQuery(): Builder
    {
        return VenueReview::query()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->whereNotNull('venue_id')
            ->with(['venue:id,name,location', 'user:id,name,email']);
    }

    /** Moderation is Haraan's job — the partner panel shows reviews read-only elsewhere. */
    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->canManage('gamehub') ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = VenueReview::query()->whereNotNull('venue_id')->where('created_at', '>=', now()->subDay())->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'New in the last 24 hours';
    }

    public static function table(Table $table): Table
    {
        return VenueReviewsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenueReviews::route('/'),
        ];
    }
}
