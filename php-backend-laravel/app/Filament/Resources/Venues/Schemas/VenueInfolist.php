<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\Schemas;

use App\Models\PartnerPayoutAccount;
use App\Models\PartnerSubscription;
use App\Models\Venue;
use App\Models\VenueCourt;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\DB;

/**
 * Venue 360 — the read side.
 *
 * Deliberately NOT the User 360's information architecture. A person is read
 * identity-first: who they are, then what they did. A venue is read
 * inventory-first: what is on sale, at what price, when it is open, and who
 * gets paid — because every operator question about a venue ("why can't this
 * be booked?", "why did it cost that?", "who do I call?") is answered by one
 * of those four, in that order.
 *
 * Every entry below reads a column that exists. Where a number is derived
 * rather than stored, the label says so.
 */
class VenueInfolist
{
    /** Booking statuses that represent money actually taken. Status casing is mixed in prod. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            self::availabilityBanner(),
            self::identity(),
            self::facilities(),
            self::pricing(),
            self::calendar(),
            self::partnerAccount(),
            self::gameHub(),
            self::integrity(),
        ]);
    }

    // -------------------------------------------------------------------------

    /**
     * The one thing an operator opening this page most often wants to know: is it
     * sellable right now, and if not, which switch is down. Shown only when
     * something is actually wrong, so it never becomes wallpaper.
     */
    private static function availabilityBanner(): Section
    {
        return Section::make('Not currently sellable')
            ->icon('heroicon-s-exclamation-triangle')
            ->visible(fn (Venue $record): bool => self::blockers($record) !== [])
            ->schema([
                TextEntry::make('sell_blockers')
                    ->label('')
                    ->state(fn (Venue $record): string => implode("\n", array_map(
                        static fn (string $line): string => '• ' . $line,
                        self::blockers($record),
                    )))
                    ->color('danger')
                    ->wrap(),
            ]);
    }

    /**
     * Why this venue cannot take a booking today, in the order the booking flow
     * itself checks. An empty array means it is on sale.
     */
    public static function blockers(Venue $record): array
    {
        $blockers = [];

        if (! $record->is_active) {
            $blockers[] = 'Listing is switched off — it is hidden from the app, the site and search.';
        }

        if (! $record->is_bookable) {
            $blockers[] = 'Bookings are switched off — the listing is visible but the book button is not.';
        }

        if ($record->courts()->where('is_active', true)->doesntExist()) {
            $blockers[] = 'No active courts. Bookings are taken against a court, so there is nothing to sell.';
        }

        if ($record->slots()->where('is_available', true)->doesntExist()) {
            $blockers[] = 'No available slots. Set operating hours and save — slots regenerate from them.';
        }

        if (! $record->isOpenOn(now())) {
            $blockers[] = 'Closed today under the configured operating hours.';
        }

        if ($record->blockedDates()->whereDate('date', now()->toDateString())->exists()) {
            $blockers[] = 'Today is a blocked date.';
        }

        if ($record->partner_id === null) {
            $blockers[] = 'No owner assigned. Nobody can manage this venue from the partner console.';
        }

        return $blockers;
    }

    // -------------------------------------------------------------------------

    private static function identity(): Section
    {
        return Section::make('Listing & location')
            ->description('How this venue presents itself in the app, on the site, and in search.')
            ->icon('heroicon-o-building-storefront')
            ->columns(['default' => 1, 'md' => 4])
            ->schema([
                TextEntry::make('name')
                    ->label('Venue name')
                    ->weight('bold')
                    ->size(TextSize::Large)
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('branch_label')
                    ->label('Branch')
                    ->state(fn (Venue $record): string => $record->branchName()
                        . ($record->branch_code ? ' · ' . $record->branch_code : ''))
                    ->badge()
                    ->color('gray'),

                TextEntry::make('kind')
                    ->label('Venue kind')
                    ->badge()
                    ->placeholder('—'),

                TextEntry::make('sports')
                    ->label('Sports offered')
                    ->state(fn (Venue $record): array => $record->sportsList())
                    ->badge()
                    ->color('info')
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('rating')
                    ->label('Rating')
                    ->state(fn (Venue $record): string => $record->rating
                        ? number_format((float) $record->rating, 1) . ' ★ · '
                            . number_format((int) $record->reviews_count) . ' reviews'
                        : 'Not yet rated')
                    ->badge()
                    ->color(fn (Venue $record): string => $record->rating ? 'warning' : 'gray'),

                TextEntry::make('visibility')
                    ->label('Visibility')
                    ->state(fn (Venue $record): string => implode(' · ', array_filter([
                        $record->is_active ? 'Live' : 'Hidden',
                        $record->is_bookable ? 'Bookable' : 'Not bookable',
                        $record->is_featured ? 'Featured' : null,
                    ])))
                    ->badge()
                    ->color(fn (Venue $record): string => $record->is_active && $record->is_bookable ? 'success' : 'danger'),

                TextEntry::make('address')
                    ->label('Address')
                    ->icon('heroicon-m-map-pin')
                    ->state(fn (Venue $record): string => implode(', ', array_filter([
                        $record->address, $record->location, $record->city,
                    ])) ?: '—')
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('coordinates')
                    ->label('Map coordinates')
                    ->state(fn (Venue $record): string => ($record->latitude && $record->longitude)
                        ? number_format((float) $record->latitude, 6) . ', ' . number_format((float) $record->longitude, 6)
                        : 'Not geocoded — will not appear in distance sort')
                    ->copyable(fn (Venue $record): bool => (bool) ($record->latitude && $record->longitude))
                    ->color(fn (Venue $record): string => ($record->latitude && $record->longitude) ? 'gray' : 'danger'),

                TextEntry::make('place_id')
                    ->label('Google Place ID')
                    ->placeholder('Not linked')
                    ->copyable(),

                TextEntry::make('tagline')
                    ->label('Tagline')
                    ->placeholder('—')
                    ->columnSpan(['default' => 1, 'md' => 4]),
            ]);
    }

    // -------------------------------------------------------------------------

    private static function facilities(): Section
    {
        return Section::make('Facilities & inventory')
            ->description('The physical things being sold, and what the listing claims about them.')
            ->icon('heroicon-o-squares-2x2')
            ->collapsible()
            ->columns(['default' => 1, 'md' => 4])
            ->schema([
                TextEntry::make('courts_summary')
                    ->label('Bookable units')
                    ->state(function (Venue $record): string {
                        $total = $record->courts()->count();
                        $active = $record->courts()->where('is_active', true)->count();

                        if ($total === 0) {
                            return 'None — nothing can be booked';
                        }

                        $byKind = $record->courts()
                            ->select('kind', DB::raw('COUNT(*) as n'))
                            ->groupBy('kind')
                            ->pluck('n', 'kind')
                            ->map(fn ($n, $kind): string => $n . ' ' . (VenueCourt::KINDS[$kind] ?? ucfirst((string) $kind)))
                            ->implode(' · ');

                        return $active . ' of ' . $total . ' active' . ($byKind !== '' ? ' — ' . $byKind : '');
                    })
                    ->badge()
                    ->color(fn (Venue $record): string => $record->courts()->where('is_active', true)->exists() ? 'success' : 'danger')
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('capacity')
                    ->label('Total seats / capacity')
                    ->state(function (Venue $record): string {
                        $seats = (int) $record->courts()->sum('seats');

                        return $seats > 0 ? number_format($seats) . ' across all units' : 'Not recorded';
                    }),

                TextEntry::make('composite_courts')
                    ->label('Splittable units')
                    ->state(function (Venue $record): string {
                        $composite = $record->courts()->where('is_composite', true)->count();

                        return $composite > 0
                            ? $composite . ' ' . str('unit')->plural($composite) . ' split into partitions'
                            : 'None';
                    }),

                TextEntry::make('amenities')
                    ->label('Amenities')
                    ->state(fn (Venue $record): array => is_array($record->amenities) ? $record->amenities : [])
                    ->badge()
                    ->color('gray')
                    ->placeholder('None listed')
                    ->columnSpan(['default' => 1, 'md' => 4]),

                TextEntry::make('capabilities')
                    ->label('Operational capabilities')
                    ->state(fn (Venue $record): array => is_array($record->capabilities) ? $record->capabilities : [])
                    ->badge()
                    ->color('info')
                    ->placeholder('None set')
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('photos')
                    ->label('Photos')
                    ->state(function (Venue $record): string {
                        $count = is_array($record->images) ? count($record->images) : 0;

                        return $count === 0
                            ? 'No photos — listings without photos convert poorly'
                            : $count . ' ' . str('photo')->plural($count);
                    })
                    ->badge()
                    ->color(fn (Venue $record): string => (is_array($record->images) && count($record->images) >= 3) ? 'success' : 'warning'),

                TextEntry::make('rules')
                    ->label('House rules')
                    ->state(function (Venue $record): string {
                        $count = is_array($record->rules) ? count($record->rules) : 0;

                        return $count > 0 ? $count . ' ' . str('rule')->plural($count) . ' shown at checkout' : 'None';
                    }),
            ]);
    }

    // -------------------------------------------------------------------------

    /**
     * The pricing matrix, in precedence order — which is the order
     * {@see VenueCourt::rateFor()} actually evaluates. Reading it any other way
     * makes the quoted price look arbitrary.
     */
    private static function pricing(): Section
    {
        return Section::make('Pricing matrix')
            ->description('Evaluated top-down: an active rule wins, then a peak window, then the court rate, then the venue default.')
            ->icon('heroicon-o-currency-rupee')
            ->collapsible()
            ->columns(['default' => 1, 'md' => 4])
            ->schema([
                TextEntry::make('active_rules')
                    ->label('1 · Dynamic rules')
                    ->state(function (Venue $record): string {
                        $total = $record->pricingRules()->count();

                        if ($total === 0) {
                            return 'None — rates fall through to peak and base';
                        }

                        $active = $record->pricingRules()->where('is_active', true)->count();

                        return $active . ' active of ' . $total . ' · highest priority wins';
                    })
                    ->badge()
                    ->color(fn (Venue $record): string => $record->pricingRules()->where('is_active', true)->exists() ? 'warning' : 'gray'),

                TextEntry::make('peak_windows')
                    ->label('2 · Peak windows')
                    ->state(function (Venue $record): string {
                        $peak = $record->courts()->whereNotNull('peak_price')->count();

                        return $peak > 0
                            ? $peak . ' ' . str('unit')->plural($peak) . ' carry a peak rate'
                            : 'None configured';
                    })
                    ->badge()
                    ->color(fn (Venue $record): string => $record->courts()->whereNotNull('peak_price')->exists() ? 'warning' : 'gray'),

                TextEntry::make('court_rates')
                    ->label('3 · Court rates')
                    ->state(function (Venue $record): string {
                        $min = $record->courts()->whereNotNull('price')->min('price');
                        $max = $record->courts()->whereNotNull('price')->max('price');

                        if ($min === null) {
                            return 'All units use the venue default';
                        }

                        return $min === $max
                            ? '₹' . number_format((int) $min) . '/hr'
                            : '₹' . number_format((int) $min) . '–₹' . number_format((int) $max) . '/hr';
                    }),

                TextEntry::make('price')
                    ->label('4 · Venue default rate')
                    ->state(fn (Venue $record): string => '₹' . number_format((int) $record->price) . '/hr')
                    ->badge()
                    ->color('success'),

                TextEntry::make('convenience_fee')
                    ->label('Booking fee charged to the customer')
                    ->state(fn (Venue $record): string => match ($record->convenience_fee_type) {
                        'flat' => '₹' . number_format((float) $record->convenience_fee_value, 2) . ' flat, on top of the slot subtotal',
                        'percent' => number_format((float) $record->convenience_fee_value, 2) . '% of the slot subtotal',
                        default => 'None — the customer pays the slot price only',
                    })
                    ->badge()
                    ->color(fn (Venue $record): string => in_array($record->convenience_fee_type, ['flat', 'percent'], true) ? 'info' : 'gray')
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('other_fees')
                    ->label('Other fees charged to the customer')
                    ->state(fn (Venue $record): array => collect($record->fees ?? [])
                        ->filter(fn ($f): bool => trim((string) ($f['label'] ?? '')) !== '' && (float) ($f['value'] ?? 0) > 0)
                        ->map(fn ($f): string => $f['label'] . ' — ' . (($f['type'] ?? '') === 'percent'
                            ? rtrim(rtrim(number_format((float) $f['value'], 2), '0'), '.') . '% of the slot subtotal'
                            : '₹' . number_format((float) $f['value'], 2) . ' flat'))
                        ->values()->all())
                    ->placeholder('None')
                    ->badge()
                    ->color('info')
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('fee_example')
                    ->label('Worked example on one default-rate hour')
                    ->state(function (Venue $record): string {
                        $base = (float) $record->price;
                        $fee = $record->convenienceFeeFor($base);

                        return '₹' . number_format($base) . ' + ₹' . number_format($fee, 2)
                            . ' fee = ₹' . number_format($base + $fee, 2) . ' collected';
                    })
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('price_note')
                    ->label('Price note shown to customers')
                    ->placeholder('—')
                    ->columnSpan(['default' => 1, 'md' => 4]),
            ]);
    }

    // -------------------------------------------------------------------------

    private static function calendar(): Section
    {
        return Section::make('Opening hours & slot calendar')
            ->description('Hours are the source of truth: saving them regenerates the bookable slot grid.')
            ->icon('heroicon-o-clock')
            ->collapsible()
            ->columns(['default' => 1, 'md' => 4])
            ->schema([
                TextEntry::make('hours_display')
                    ->label('Operating hours')
                    ->state(fn (Venue $record): string => $record->displayHours() ?: 'Not configured — the venue is treated as always open')
                    ->columnSpan(['default' => 1, 'md' => 3]),

                TextEntry::make('slot_minutes')
                    ->label('Slot length')
                    ->state(fn (Venue $record): string => ((int) ($record->slot_minutes ?: 60)) . ' minutes')
                    ->badge()
                    ->color('info'),

                TextEntry::make('slots_generated')
                    ->label('Generated slots')
                    ->state(function (Venue $record): string {
                        $total = $record->slots()->count();

                        if ($total === 0) {
                            return 'None generated';
                        }

                        $open = $record->slots()->where('is_available', true)->count();

                        return $open . ' available of ' . $total . ' weekly start times';
                    })
                    ->badge()
                    ->color(fn (Venue $record): string => $record->slots()->where('is_available', true)->exists() ? 'success' : 'danger'),

                TextEntry::make('booking_window')
                    ->label('Booking window')
                    ->state(fn (Venue $record): string => $record->bookingWindowDays() . ' days ahead'
                        . ($record->booking_window_days === null ? ' (platform default)' : ' (venue override)')),

                TextEntry::make('cancellation')
                    ->label('Cancellation policy')
                    ->state(fn (Venue $record): string => $record->cancellationText()
                        ?? 'No policy set — cancellations are handled case by case')
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('upcoming_blocks')
                    ->label('Time taken off the market')
                    ->state(function (Venue $record): string {
                        $blocks = $record->blocks()
                            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()->toDateString()))
                            ->count();
                        $dates = $record->blockedDates()
                            ->whereDate('date', '>=', now()->toDateString())
                            ->count();

                        if ($blocks === 0 && $dates === 0) {
                            return 'Nothing blocked ahead';
                        }

                        return implode(' · ', array_filter([
                            $blocks > 0 ? $blocks . ' active ' . str('block')->plural($blocks) : null,
                            $dates > 0 ? $dates . ' closed ' . str('date')->plural($dates) : null,
                        ]));
                    })
                    ->badge()
                    ->color(fn (Venue $record): string => ($record->blocks()->exists() || $record->blockedDates()->exists()) ? 'warning' : 'gray')
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('next_booking')
                    ->label('Next confirmed booking')
                    ->state(function (Venue $record): string {
                        $next = $record->bookings()
                            ->whereIn(DB::raw('lower(status)'), self::PAID)
                            ->whereDate('slot_date', '>=', now()->toDateString())
                            ->orderBy('slot_date')
                            ->orderBy('start_time')
                            ->first();

                        if ($next === null) {
                            return 'Nothing booked ahead';
                        }

                        $window = $next->slot_label
                            ?: implode('–', array_filter([$next->start_time, $next->end_time]));

                        return implode(' · ', array_filter([
                            $next->slot_date?->format('D d M Y'),
                            $window ?: null,
                        ]));
                    })
                    ->icon('heroicon-m-calendar-days')
                    ->columnSpan(['default' => 1, 'md' => 2]),
            ]);
    }

    // -------------------------------------------------------------------------

    private static function partnerAccount(): Section
    {
        return Section::make('Partner account & settlement')
            ->description('Who owns this listing, who can work it, and where the money lands.')
            ->icon('heroicon-o-identification')
            ->collapsible()
            ->columns(['default' => 1, 'md' => 4])
            ->schema([
                TextEntry::make('partner.name')
                    ->label('Owner')
                    ->state(fn (Venue $record): string => $record->partner?->name ?? 'Unassigned')
                    ->weight('bold')
                    ->color(fn (Venue $record): string => $record->partner ? 'gray' : 'danger'),

                TextEntry::make('partner_contact')
                    ->label('Owner contact')
                    ->state(function (Venue $record): string {
                        $partner = $record->partner;

                        if ($partner === null) {
                            return '—';
                        }

                        return implode(' · ', array_filter([$partner->email, $partner->phone])) ?: '—';
                    })
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('partner_type')
                    ->label('Partner lane')
                    ->state(fn (Venue $record): string => $record->partner?->partner_type
                        ? ucfirst((string) $record->partner->partner_type)
                        : '—')
                    ->badge(),

                TextEntry::make('assigned_staff')
                    ->label('Staff assigned to this branch')
                    ->state(function (Venue $record): string {
                        $names = $record->assignedStaff()->pluck('users.name')->all();

                        return $names === []
                            ? 'None — only the owner can work this branch'
                            : implode(', ', $names);
                    })
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('payout_account')
                    ->label('Payout destination')
                    ->state(function (Venue $record): string {
                        if ($record->partner_id === null) {
                            return 'No owner, so no payout account';
                        }

                        $account = PartnerPayoutAccount::where('partner_id', $record->partner_id)->first();

                        if ($account === null) {
                            return 'Not set up — settlements cannot be paid out';
                        }

                        $destination = $account->method === 'upi'
                            ? 'UPI · ' . self::maskTail((string) $account->upi_vpa)
                            : implode(' ', array_filter([$account->bank_name, self::maskTail((string) $account->account_number)]));

                        return $destination . ($account->verified_at ? ' · verified' : ' · unverified');
                    })
                    ->badge()
                    ->color(function (Venue $record): string {
                        if ($record->partner_id === null) {
                            return 'danger';
                        }

                        return PartnerPayoutAccount::where('partner_id', $record->partner_id)
                            ->whereNotNull('verified_at')
                            ->exists() ? 'success' : 'danger';
                    })
                    ->columnSpan(['default' => 1, 'md' => 2]),

                TextEntry::make('partner_plan')
                    ->label('Partner subscription')
                    ->state(function (Venue $record): string {
                        if ($record->partner_id === null) {
                            return '—';
                        }

                        $sub = PartnerSubscription::with('plan')
                            ->where('partner_id', $record->partner_id)
                            ->whereIn('status', ['active', 'authenticated'])
                            ->latest('id')
                            ->first();

                        if ($sub === null) {
                            return 'No active partner plan';
                        }

                        return ($sub->plan?->name ?? 'Plan')
                            . ($sub->current_period_end ? ' · renews ' . $sub->current_period_end->format('d M Y') : '');
                    })
                    ->badge()
                    ->color('info')
                    ->columnSpan(['default' => 1, 'md' => 2]),
            ]);
    }

    // -------------------------------------------------------------------------

    private static function gameHub(): Section
    {
        return Section::make('Game Hub integration')
            ->description('ActionBoard matches played on a booking taken here — joined by booking, never by venue name.')
            ->icon('heroicon-o-trophy')
            ->collapsible()
            ->columns(['default' => 1, 'md' => 4])
            ->schema([
                TextEntry::make('matches_played')
                    ->label('Matches played here')
                    ->state(fn (Venue $record): string => number_format($record->matches()->count()))
                    ->badge()
                    ->color(fn (Venue $record): string => $record->matches()->exists() ? 'success' : 'gray'),

                TextEntry::make('ranked_matches')
                    ->label('Ranked matches')
                    ->state(fn (Venue $record): string => number_format(
                        $record->matches()->where('live_matches.is_ranked', true)->count(),
                    ))
                    ->badge()
                    ->color('info'),

                TextEntry::make('live_now')
                    ->label('Live right now')
                    ->state(function (Venue $record): string {
                        $live = $record->matches()
                            ->whereIn(DB::raw('lower(live_matches.status)'), ['live', 'in_progress'])
                            ->count();

                        return $live > 0 ? $live . ' in progress' : 'None';
                    })
                    ->badge()
                    ->color(fn (Venue $record): string => $record->matches()
                        ->whereIn(DB::raw('lower(live_matches.status)'), ['live', 'in_progress'])
                        ->exists() ? 'danger' : 'gray'),

                TextEntry::make('match_sports')
                    ->label('Sports actually played')
                    ->state(fn (Venue $record): array => $record->matches()
                        ->whereNotNull('live_matches.sport')
                        ->distinct()
                        ->pluck('live_matches.sport')
                        ->all())
                    ->badge()
                    ->placeholder('No matches yet'),
            ]);
    }

    // -------------------------------------------------------------------------

    private static function integrity(): Section
    {
        return Section::make('Record integrity')
            ->icon('heroicon-o-shield-check')
            ->collapsible()
            ->collapsed()
            ->columns(['default' => 1, 'md' => 4])
            ->schema([
                TextEntry::make('id')
                    ->label('Venue ID')
                    ->badge()
                    ->copyable(),

                TextEntry::make('created_at')
                    ->label('Listed on')
                    ->dateTime('d M Y, g:i A'),

                TextEntry::make('updated_at')
                    ->label('Last modified')
                    ->state(fn (Venue $record): string => $record->updated_at
                        ? $record->updated_at->diffForHumans() . ' (' . $record->updated_at->format('d M Y, H:i') . ')'
                        : '—'),

                TextEntry::make('audited_changes')
                    ->label('Audited changes')
                    ->state(fn (Venue $record): string => number_format($record->auditTrail()->count()) . ' logged')
                    ->badge()
                    ->color('gray'),

                TextEntry::make('organization.name')
                    ->label('Organization unit')
                    ->placeholder('Unscoped'),

                IconEntry::make('is_featured')
                    ->label('Featured placement')
                    ->boolean(),

                TextEntry::make('sort_order')
                    ->label('Manual sort order')
                    ->placeholder('—'),

                TextEntry::make('map_link')
                    ->label('Map link')
                    ->placeholder('—')
                    ->url(fn (Venue $record): ?string => $record->map_link ?: null)
                    ->openUrlInNewTab(),
            ]);
    }

    // -------------------------------------------------------------------------

    /** Show only the last four characters of an account identifier. */
    private static function maskTail(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '—';
        }

        return strlen($value) <= 4 ? $value : '••••' . substr($value, -4);
    }
}
