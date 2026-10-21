<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerApplications;

use App\Filament\Resources\PartnerApplications\Pages\ListPartnerApplications;
use App\Filament\Resources\PartnerApplications\Pages\ViewPartnerApplication;
use App\Models\PartnerApplication;
use BackedEnum;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applications from the public /become-a-partner form.
 *
 * Work the list top to bottom: call, mark contacted, then either create the partner
 * account from the application ({@see PartnerApplicationActions::approve()}) or close it.
 * The form itself — on/off, copy, the sports list, the call-back promise — is in
 * Platform rules → Partner applications form.
 */
class PartnerApplicationResource extends Resource
{
    protected static ?string $model = PartnerApplication::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Partner applications';

    protected static ?string $modelLabel = 'partner application';

    protected static ?string $recordTitleAttribute = 'business_name';

    /** Same door as Partners: creating a partner account is a super-admin act. */
    public static function canAccess(): bool
    {
        // Both panels discover this directory; applications only exist in /control.
        if (\Filament\Facades\Filament::getCurrentPanel()?->getId() === 'partner') {
            return false;
        }

        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDeleteAny(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PartnerApplication::query()->where('status', PartnerApplication::STATUS_NEW)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Applications nobody has called yet';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['business_name', 'contact_name', 'phone', 'reference', 'city'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('business_name')
                    ->label('Business')
                    ->weight('medium')
                    ->description(fn (PartnerApplication $r): string => $r->businessTypeLabel())
                    ->searchable(['business_name', 'reference']),

                TextColumn::make('city')
                    ->label('Where')
                    ->description(fn (PartnerApplication $r): ?string => $r->area)
                    ->searchable(['city', 'area'])
                    ->sortable(),

                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->description(fn (PartnerApplication $r): string => $r->phone
                        . ($r->call_time ? ' · ' . (PartnerApplication::CALL_TIMES[$r->call_time] ?? $r->call_time) : ''))
                    ->searchable(['contact_name', 'phone', 'email']),

                TextColumn::make('sports')
                    ->label('Sports')
                    ->badge()
                    ->separator(',')
                    ->limitList(3)
                    ->toggleable(),

                TextColumn::make('courts')
                    ->label('Courts')
                    ->placeholder('—')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PartnerApplication::STATUSES[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        PartnerApplication::STATUS_NEW => 'warning',
                        PartnerApplication::STATUS_CONTACTED => 'info',
                        PartnerApplication::STATUS_APPROVED => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Applied')
                    ->since()
                    ->sortable()
                    ->description(fn (PartnerApplication $r): string => $r->reference),
            ])
            ->filters([
                Filter::make('open')
                    ->label('Still to work')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->open()),

                SelectFilter::make('status')->options(PartnerApplication::STATUSES),
                SelectFilter::make('business_type')->label('Runs')->options(PartnerApplication::BUSINESS_TYPES),
            ])
            ->recordUrl(fn (PartnerApplication $r): string => static::getUrl('view', ['record' => $r]))
            ->recordActions([
                PartnerApplicationActions::call(),
                PartnerApplicationActions::markContacted(),
                PartnerApplicationActions::approve(),
                PartnerApplicationActions::createVenue(),
            ])
            ->emptyStateHeading('No applications to work')
            ->emptyStateDescription('Owners who apply at /become-a-partner show up here. Clear the “Still to work” filter to see closed ones.')
            ->emptyStateIcon('heroicon-o-inbox-arrow-down');
    }

    public static function infolist(Schema $schema): Schema
    {
        $place = fn (PartnerApplication $record): bool => $record->hasPlace();

        // Same order as the /control venue form, so checking an application reads like
        // checking the venue it will become.
        return $schema->columns(3)->components([
            Section::make('Basics')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    TextEntry::make('business_name')->label('Name'),
                    TextEntry::make('business_type')->label('Runs')
                        ->formatStateUsing(fn (?string $state): string => PartnerApplication::BUSINESS_TYPES[$state] ?? (string) $state),
                    TextEntry::make('sports')->label('Sports (first = main)')->badge()->placeholder('None picked'),
                    TextEntry::make('tagline')->label('One-liner')->placeholder('—'),
                    TextEntry::make('about')->label('About')->placeholder('—')->prose()->columnSpanFull(),
                ]),

            Section::make('Status')
                ->columnSpan(1)
                ->schema([
                    TextEntry::make('reference')->copyable()->weight('bold'),
                    TextEntry::make('status')->badge()
                        ->formatStateUsing(fn (?string $state): string => PartnerApplication::STATUSES[$state] ?? (string) $state)
                        ->color(fn (?string $state): string => match ($state) {
                            PartnerApplication::STATUS_NEW => 'warning',
                            PartnerApplication::STATUS_CONTACTED => 'info',
                            PartnerApplication::STATUS_APPROVED => 'success',
                            default => 'gray',
                        }),
                    TextEntry::make('created_at')->label('Applied')->dateTime('j M Y, g:i A'),
                    TextEntry::make('contacted_at')->label('Contacted')->dateTime('j M Y, g:i A')->placeholder('Not yet'),
                    TextEntry::make('reviewer.name')->label('Reviewed by')->placeholder('—'),
                    TextEntry::make('partner.name')->label('Partner account')
                        ->placeholder('Not created')
                        ->url(fn (PartnerApplication $record): ?string => $record->partner_id
                            ? \App\Filament\Resources\Partners\PartnerResource::getUrl('view', ['record' => $record->partner_id])
                            : null),
                    TextEntry::make('venue.name')->label('Venue')
                        ->placeholder('Not created')
                        ->visible($place)
                        ->url(fn (PartnerApplication $record): ?string => $record->venue_id
                            ? \App\Filament\Resources\Venues\VenueResource::getUrl('edit', ['record' => $record->venue_id])
                            : null),
                ]),

            Section::make('Location')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    TextEntry::make('area')->label('Area / locality')->placeholder('—'),
                    TextEntry::make('city'),
                    TextEntry::make('address')->label('Full address')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('map_link')->label('Google Maps link')->placeholder('—')
                        ->url(fn (PartnerApplication $record): ?string => $record->map_link, shouldOpenInNewTab: true)
                        ->limit(60),
                    TextEntry::make('pin')->label('Pin')
                        ->state(fn (PartnerApplication $record): ?string => $record->latitude !== null
                            ? number_format($record->latitude, 6) . ', ' . number_format($record->longitude, 6)
                            : null)
                        ->url(fn (PartnerApplication $record): ?string => $record->latitude !== null
                            ? 'https://www.google.com/maps?q=' . $record->latitude . ',' . $record->longitude
                            : null, shouldOpenInNewTab: true)
                        ->placeholder('Not pinned'),
                ]),

            Section::make('Contact')
                ->columnSpan(1)
                ->schema([
                    TextEntry::make('contact_name')->label('Name')
                        ->suffix(fn (PartnerApplication $record): string => $record->contact_role
                            ? ' · ' . (PartnerApplication::CONTACT_ROLES[$record->contact_role] ?? $record->contact_role)
                            : ''),
                    TextEntry::make('phone')->copyable()->url(fn (PartnerApplication $record): string => 'tel:' . $record->phone),
                    TextEntry::make('email')->placeholder('—')->copyable(),
                    TextEntry::make('call_time')->label('Best time')
                        ->formatStateUsing(fn (?string $state): string => PartnerApplication::CALL_TIMES[$state] ?? (string) $state)
                        ->placeholder('Any time'),
                    TextEntry::make('gstin')->label('GSTIN')->placeholder('—')->copyable(),
                ]),

            // Masked here; the full number reaches finance in Settlement accounts once the
            // partner is created (PayoutAccountEditor), where it is verified before any transfer.
            Section::make('Payouts')
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextEntry::make('payout_method')->label('Paid by')
                        ->formatStateUsing(fn (?string $state): string => PartnerApplication::PAYOUT_METHODS[$state] ?? '—')
                        ->placeholder('Not asked'),
                    TextEntry::make('bank_name')->label('Bank')->placeholder('—')
                        ->suffix(fn (PartnerApplication $record): string => $record->ifsc_code ? ' · IFSC ' . $record->ifsc_code : ''),
                    TextEntry::make('account_holder')->label('Account holder')->placeholder('—'),
                    TextEntry::make('masked_account')->label('Account / UPI')
                        ->state(fn (PartnerApplication $record): ?string => implode(' · ', array_filter([$record->maskedAccount(), $record->maskedUpi()])) ?: null)
                        ->helperText(fn (PartnerApplication $record): ?string => $record->hasPayoutDetails()
                            ? ($record->partner_id ? 'Full details are in Finance → Settlement accounts.' : 'Create partner copies these into their settlement account, unverified.')
                            : null)
                        ->placeholder('—'),
                ]),

            Section::make('Courts & pricing')
                ->visible($place)
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('base_price')->label('From (per hour)')
                        ->formatStateUsing(fn ($state): string => '₹' . number_format((int) $state))
                        ->placeholder('No price given'),
                    TextEntry::make('courts')->label('Courts / tables')->placeholder('—'),
                    TextEntry::make('court_rows')->label('Each one')
                        ->state(fn (PartnerApplication $record): array => array_map(
                            fn (array $c): string => $c['name']
                                . (! empty($c['sports']) ? ' · ' . implode(', ', $c['sports']) : '')
                                . (! empty($c['price']) ? ' · ₹' . number_format((int) $c['price']) . '/hr' : ''),
                            $record->courtList(),
                        ))
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('—'),
                ]),

            Section::make('Hours & cancellation')
                ->visible($place)
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('hours')->label('Open')
                        ->state(fn (PartnerApplication $record): ?string => $record->hoursLabel())
                        ->placeholder('—'),
                    TextEntry::make('slot_minutes')->label('Slot length')
                        ->formatStateUsing(fn ($state): string => PartnerApplication::SLOT_LENGTHS[(int) $state] ?? (string) $state)
                        ->placeholder('—'),
                    TextEntry::make('cancellation')->label('Cancellation')
                        ->state(fn (PartnerApplication $record): ?string => $record->cancellationLabel())
                        ->placeholder('No refunds'),
                ]),

            Section::make('Photos')
                ->visible($place)
                ->columnSpanFull()
                ->schema([
                    ImageEntry::make('images')->hiddenLabel()
                        ->disk('public')
                        ->imageHeight(140)
                        ->placeholder('No photos sent — ask for them on the call.'),
                ]),

            Section::make('Amenities & good to know')
                ->visible($place)
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('amenities')->badge()->placeholder('None picked'),
                    TextEntry::make('rules')->label('Good to know')->listWithLineBreaks()->bulleted()->placeholder('None picked'),
                ]),

            Section::make('How it runs today')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('booking_channels')->label('Bookings come from')->badge()
                        ->formatStateUsing(fn (?string $state): string => PartnerApplication::BOOKING_CHANNELS[$state] ?? (string) $state)
                        ->placeholder('Not said'),
                    TextEntry::make('monthly_bookings')->label('A usual month')
                        ->formatStateUsing(fn (?string $state): string => PartnerApplication::MONTHLY_BOOKINGS[$state] ?? (string) $state)
                        ->placeholder('Not said'),
                    TextEntry::make('goals')->label('Wants help with')->badge()
                        ->formatStateUsing(fn (?string $state): string => PartnerApplication::GOALS[$state] ?? (string) $state)
                        ->placeholder('Not said'),
                ]),

            Section::make('Notes')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('note')->label('From the applicant')->placeholder('—')->prose(),
                    TextEntry::make('admin_notes')->label('Team notes')->placeholder('—')->prose(),
                    TextEntry::make('source')->label('Came from')->placeholder('Direct'),
                    TextEntry::make('user.name')->label('Signed in as')->placeholder('Not signed in'),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['reviewer:id,name', 'partner:id,name', 'venue:id,name']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerApplications::route('/'),
            'view' => ViewPartnerApplication::route('/{record}'),
        ];
    }
}
