<?php

namespace App\Filament\Resources\Venues\Schemas;

use App\Filament\Forms\OrganizationSelect;
use App\Models\User;
use App\Models\Venue;
use App\Support\Membership\MembershipSettings;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class VenueForm
{
    /** True while the request is being served by the partner console. */
    private static function isPartnerPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'partner';
    }

    /** The sports the app knows how to icon + filter. Category and courts draw from this too. */
    private const SPORTS = [
        'Cricket' => 'Cricket',
        'Football' => 'Football',
        'Badminton' => 'Badminton',
        'Basketball' => 'Basketball',
        'Tennis' => 'Tennis',
        'Volleyball' => 'Volleyball',
    ];

    /**
     * The canonical amenities the app has an icon for. The label is what's stored; the keyword
     * list matches legacy/free-text values back onto the canonical label on edit (so old data
     * like "Washrooms" ticks the "Washroom" box and normalises on the next save). Mirrors the
     * app's amenityIcon() matcher so every ticked amenity is guaranteed an icon.
     *
     * @var array<string, array<int, string>>
     */
    private const AMENITIES = [
        'Parking' => ['park'],
        'Washroom' => ['wash', 'toilet', 'restroom'],
        'Shower' => ['shower'],
        'Changing room' => ['chang', 'locker'],
        'Café' => ['cafe', 'coffee', 'canteen'],
        'Restaurant' => ['food', 'restaurant', 'kitchen'],
        'Drinking water' => ['water', 'drink'],
        'Floodlights' => ['light', 'flood'],
        'AC' => ['a/c', ' ac ', 'air-con', 'aircon', 'air cond', 'conditioner', 'conditioning', 'cooling'],
        'WiFi' => ['wifi', 'wi-fi', 'internet'],
        'CCTV / Security' => ['cctv', 'secur', 'guard'],
        'Seating' => ['seat', 'gallery'],
        'Equipment rental' => ['equip', 'gear', 'kit', 'rental'],
    ];

    /** Common "Good to know" presets offered as quick-tick chips (plus free-text for the rest). */
    private const RULE_PRESETS = [
        'Non-marking shoes mandatory',
        'Carry your own racket / gear',
        'No smoking',
        'No alcohol',
        'No outside food',
        'No pets',
        'ID proof required',
        'Advance booking recommended',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // On create, courts + slots (relation-manager tabs) don't exist yet — tell the
                // admin where they'll go so they don't finish thinking the venue is fully set up.
                Placeholder::make('setup_hint')
                    ->hiddenLabel()
                    ->visibleOn('create')
                    ->content(new HtmlString(
                        '<div style="padding:12px 14px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:13px;line-height:1.5">'
                        .'<strong>2-step setup (Draft by default).</strong> Fill this form and press <strong>Create</strong>. '
                        .'The new venue will be saved as <strong>Draft</strong> (hidden from users). '
                        .'You will be taken to the edit screen where you can add <strong>Courts</strong> and <strong>Time slots</strong>, add a photo, then set <strong>Status</strong> to <strong>Live</strong>.'
                        .'</div>'
                    ))
                    ->columnSpanFull(),

                Placeholder::make('lifecycle_banner')
                    ->hiddenLabel()
                    ->visibleOn('edit')
                    ->content(function (?Venue $record): ?HtmlString {
                        if (! $record) {
                            return null;
                        }
                        $status = $record->lifecycleStatus();
                        if ($status === 'published') {
                            return new HtmlString(
                                '<div style="padding:12px 16px;border-radius:10px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;font-size:13px;line-height:1.5;display:flex;align-items:center;gap:8px;">'
                                .'<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#10b981;"></span>'
                                .($record->is_bookable
                                    ? '<strong>Status: Live.</strong> Visible to customers and taking bookings.'
                                    : '<strong>Status: Live — bookings paused.</strong> Visible to customers, but no new bookings until Status is set back to Live.')
                                .'</div>'
                            );
                        }
                        if ($status === 'ready') {
                            return new HtmlString(
                                '<div style="padding:12px 16px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;line-height:1.5;display:flex;align-items:center;gap:8px;">'
                                .'<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#f59e0b;"></span>'
                                .'<strong>Status: Ready to Publish.</strong> Everything a customer needs is set up. Set <strong>Status</strong> (under Visibility &amp; ownership) to <strong>Live</strong> and save.'
                                .'</div>'
                            );
                        }
                        $errors = $record->readinessErrors();
                        $list = implode('', array_map(fn ($e) => '<li style="margin-left:18px;">' . htmlspecialchars($e) . '</li>', $errors));
                        return new HtmlString(
                            '<div style="padding:12px 16px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:13px;line-height:1.5;">'
                            .'<div style="font-weight:bold;margin-bottom:4px;display:flex;align-items:center;gap:6px;">'
                            .'<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;"></span>'
                            .'Setup Incomplete (Draft) — Cannot Go Live Yet'
                            .'</div>'
                            .'<div style="font-size:12px;color:#7f1d1d;">Complete these remaining items before publishing:</div>'
                            .'<ul style="margin:6px 0 0 0;padding:0;font-size:12px;">' . $list . '</ul>'
                            .'</div>'
                        );
                    })
                    ->columnSpanFull(),

                Section::make('Basics')
                    ->description('Name and the sports played here.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('category')
                            ->label('Primary sport')
                            ->options(self::SPORTS)
                            ->required()
                            ->native(false)
                            ->default('Badminton')
                            ->helperText('The main sport — shown as the card badge and used by the sport filter.'),
                        Select::make('sports')
                            ->label('All sports offered')
                            ->multiple()
                            ->options(self::SPORTS)
                            ->native(false)
                            ->helperText('Every game playable here. The primary sport is always included. Shown as icons on the card (first two + a count).'),
                        TextInput::make('tagline')
                            ->placeholder('6 wooden indoor courts')
                            ->helperText('Short one-liner under the venue name on the browse card.'),
                        Textarea::make('about')
                            ->label('About this venue')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Location')
                    ->description('Where it is — coordinates power the live "X km away" distance.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('location')
                            ->required()
                            ->label('Area / locality')
                            ->helperText('Short label on the card (e.g. "Bandra").'),
                        TextInput::make('city')
                            ->label('City')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. Chennai, Hyderabad, Bengaluru')
                            ->helperText('City where the venue is located — auto-filled by the place picker or typed manually.'),
                        TextInput::make('distance')
                            ->label('Fallback distance')
                            ->helperText('Only used when coordinates are missing. Leave blank once lat/lng are set.'),
                        TextInput::make('address')
                            ->label('Full address')
                            ->maxLength(255)
                            ->placeholder('123 MG Road, Bandra West, Mumbai, Maharashtra 400050')
                            ->helperText('Street, colony, city, state, PIN — shown in full under the timing.')
                            ->columnSpanFull(),
                        TextInput::make('map_link')
                            ->label('Google Maps link')
                            ->url()
                            ->maxLength(600)
                            ->placeholder('https://maps.app.goo.gl/…')
                            ->helperText('Maps → Share → Copy link. Powers "Show in Map" / "Get directions".')
                            ->columnSpanFull(),
                        ViewField::make('map_picker')
                            ->hiddenLabel()
                            ->view('filament.venue-place-picker')
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        // Which Google listing this venue was picked from. Written by
                        // the picker only — a hand-typed id would point at the wrong
                        // business, so it never gets a visible input.
                        Hidden::make('place_id'),
                        TextInput::make('latitude')
                            ->numeric()
                            ->step('0.0000001')
                            ->minValue(-90)
                            ->maxValue(90)
                            ->live()
                            ->placeholder('19.0596')
                            ->helperText('Set by the pin above — or type it. Right-click the spot in Google Maps for "lat, lng".'),
                        TextInput::make('longitude')
                            ->numeric()
                            ->step('0.0000001')
                            ->minValue(-180)
                            ->maxValue(180)
                            ->live()
                            ->placeholder('72.8295')
                            ->helperText('Set by the pin above — or type it.'),
                    ]),

                Section::make('Operating hours')
                    ->description('Add a row per open day. Days you don\'t list are treated as closed. Bookable start-times are generated from these hours. Open past midnight? Set the real closing time (e.g. Mon 6:00 PM → 1:00 AM): the hours after 12 AM are sold on the next day\'s date, so a customer books Monday night\'s last hour as Tuesday 12 AM.')
                    ->schema([
                        Repeater::make('hours_rows')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('day')
                                    ->options([
                                        'Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday',
                                        'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday',
                                    ])
                                    ->required()
                                    ->native(false),
                                TimePicker::make('open')
                                    ->label('Opens')
                                    ->seconds(false)->format('H:i')->displayFormat('h:i A')
                                    ->required(),
                                TimePicker::make('close')
                                    ->label('Closes')
                                    ->seconds(false)->format('H:i')->displayFormat('h:i A')
                                    ->required()
                                    ->helperText('Earlier than “Opens” = closes after midnight.')
                                    // Equal times used to save and then silently produce no slots.
                                    ->rules([
                                        fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get): void {
                                            if ($value !== null && substr((string) $value, 0, 5) === substr((string) $get('open'), 0, 5)) {
                                                $fail('Closing time can’t be the same as opening time.');
                                            }
                                        },
                                    ]),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add a day')
                            ->reorderable(false)
                            ->defaultItems(0),
                        Select::make('slot_minutes')
                            ->label('Slot length')
                            ->options([30 => '30 minutes', 60 => '1 hour', 90 => '1.5 hours', 120 => '2 hours'])
                            ->default(60)
                            ->native(false)
                            ->helperText('Start-times are generated every this-many minutes between open and close.'),
                    ]),

                Section::make('Cancellation policy')
                    ->description('Shown in the app\'s "Good to know". Leave blank if you don\'t offer refunds.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('cancel_free_hours')
                            ->label('Free cancellation window (hours before)')
                            ->numeric()
                            ->suffix('hrs')
                            ->placeholder('e.g. 24'),
                        TextInput::make('cancel_refund_percent')
                            ->label('Refund after that (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)->maxValue(100)
                            ->placeholder('e.g. 50'),
                    ]),

                Section::make('Pricing')
                    ->description('The base "from" price. Actual booking price comes from each court.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('price')
                            ->label('Base price per hour')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('₹')
                            ->helperText('The "from" price on the card. Individual courts can set their own rate in the Courts tab.'),
                        TextInput::make('price_note')
                            ->label('Pricing note')
                            ->placeholder('Pricing is subject to change and is controlled by the venue')
                            ->helperText('Small disclaimer shown near the price.'),
                        // Charged on top of the slot price at checkout (app + website), via
                        // Venue::convenienceFeeFor(). Haraan sets it: the inputs exist only in
                        // /control, and a partner sees what is charged, read-only. Hidden
                        // fields aren't saved, so a partner's save can never change it.
                        Placeholder::make('convenience_fee_readonly')
                            ->label('Convenience fee')
                            ->visible(fn (): bool => self::isPartnerPanel())
                            ->content(fn (?Venue $record): string => match ($record?->convenience_fee_type) {
                                'flat'    => '₹' . number_format((float) $record->convenience_fee_value, 2) . ' per booking — set by Haraan',
                                'percent' => rtrim(rtrim(number_format((float) $record->convenience_fee_value, 2), '0'), '.') . '% of the slot price — set by Haraan',
                                default   => 'None — set by Haraan',
                            }),
                        Select::make('convenience_fee_type')
                            ->visible(fn (): bool => ! self::isPartnerPanel())
                            ->label('Convenience fee')
                            ->options([
                                'none'    => 'No fee',
                                'flat'    => 'Flat ₹ per booking',
                                'percent' => '% of the slot price',
                            ])
                            ->default('none')
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Added to the customer’s total at checkout, on the app and the website. Paid to the venue.'),
                        TextInput::make('convenience_fee_value')
                            ->label(fn ($get): string => $get('convenience_fee_type') === 'percent' ? 'Fee (%)' : 'Fee (₹)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn ($get): int => $get('convenience_fee_type') === 'percent' ? 50 : 5000)
                            ->prefix(fn ($get): ?string => $get('convenience_fee_type') === 'flat' ? '₹' : null)
                            ->suffix(fn ($get): ?string => $get('convenience_fee_type') === 'percent' ? '%' : null)
                            ->default(0)
                            ->required(fn ($get): bool => in_array($get('convenience_fee_type'), ['flat', 'percent'], true))
                            ->visible(fn ($get): bool => ! self::isPartnerPanel()
                                && in_array($get('convenience_fee_type'), ['flat', 'percent'], true)),

                        // Any other fee, named by Haraan ("Floodlight charge", "Maintenance fee"),
                        // each its own line on the customer's bill. Venue::feeRules() adds these
                        // after the convenience fee, so every checkout (app, website, WhatsApp)
                        // charges them. Admin-only, like the fee above.
                        Placeholder::make('fees_readonly')
                            ->label('Other fees')
                            ->visible(fn (?Venue $record): bool => self::isPartnerPanel() && ! empty($record?->fees))
                            ->content(fn (?Venue $record): string => collect($record?->fees ?? [])
                                ->filter(fn ($f): bool => ! empty($f['label']) && (float) ($f['value'] ?? 0) > 0)
                                ->map(fn ($f): string => $f['label'] . ': ' . (($f['type'] ?? '') === 'percent'
                                    ? rtrim(rtrim(number_format((float) $f['value'], 2), '0'), '.') . '% of the slot price'
                                    : '₹' . number_format((float) $f['value'], 2) . ' per booking'))
                                ->implode(' · ') . ' — set by Haraan'),
                        Repeater::make('fees')
                            ->label('Other fees')
                            ->visible(fn (): bool => ! self::isPartnerPanel())
                            ->addActionLabel('Add a fee')
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => trim((string) ($state['label'] ?? '')) ?: 'New fee')
                            ->helperText('Type any fee name — it shows as its own line on the customer’s bill, after the convenience fee. Charged on the app, the website and WhatsApp bookings, and paid to the venue with the booking.')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Fee name (shown to customers)')
                                    ->placeholder('Floodlight charge')
                                    ->required()
                                    ->maxLength(40),
                                Select::make('type')
                                    ->label('Charged as')
                                    ->options([
                                        'flat'    => 'Flat ₹ per booking',
                                        'percent' => '% of the slot price',
                                    ])
                                    ->default('flat')
                                    ->required()
                                    ->native(false)
                                    ->live(),
                                TextInput::make('value')
                                    ->label(fn ($get): string => $get('type') === 'percent' ? 'Fee (%)' : 'Fee (₹)')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(fn ($get): int => $get('type') === 'percent' ? 50 : 5000)
                                    ->prefix(fn ($get): ?string => $get('type') === 'flat' ? '₹' : null)
                                    ->suffix(fn ($get): ?string => $get('type') === 'percent' ? '%' : null)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Photos')
                    ->description('The first image is the hero; users swipe through the rest.')
                    ->schema([
                        FileUpload::make('images')
                            ->label('Upload photos')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(15360)
                            ->imageEditor()
                            ->disk('public')
                            ->directory('venues')
                            ->visibility('public')
                            ->helperText('Drag to reorder. JPG, PNG or WebP, up to 15 MB each. Or paste links below.')
                            ->columnSpanFull(),
                        TagsInput::make('image_urls')
                            ->label('…or paste image URLs')
                            ->placeholder('https://…/photo.jpg — press Enter')
                            ->helperText('Use instead of (or alongside) uploads. Uploaded photos come first.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Amenities')
                    ->description('Tick what this venue has — each gets its own icon on the venue page.')
                    ->schema([
                        CheckboxList::make('amenities_known')
                            ->hiddenLabel()
                            ->options(array_combine(array_keys(self::AMENITIES), array_keys(self::AMENITIES)))
                            ->columns(3)
                            ->gridDirection('row')
                            ->bulkToggleable(),
                        TagsInput::make('amenities_other')
                            ->label('Other amenities')
                            ->placeholder('Anything not listed above — press Enter')
                            ->helperText('Free-form extras. These show without a dedicated icon.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Good to know')
                    ->description('House rules & policies — each becomes a bullet on the venue page.')
                    ->schema([
                        CheckboxList::make('rules_known')
                            ->hiddenLabel()
                            ->options(array_combine(self::RULE_PRESETS, self::RULE_PRESETS))
                            ->columns(2)
                            ->gridDirection('row')
                            ->bulkToggleable(),
                        TagsInput::make('rules_other')
                            ->label('Other rules')
                            ->placeholder('Anything specific to this venue — press Enter')
                            ->columnSpanFull(),
                    ]),

                // Ownership, merchandising and org placement are Haraan's calls, not the
                // tenant's — a partner must never be able to reassign their venue to
                // somebody else or feature themselves. They keep the two switches that
                // are genuinely operational: whether the listing is live, and whether it
                // is currently taking bookings. See VenueResource::canCreate().
                Section::make('Visibility & ownership')
                    ->columns(2)
                    ->schema([
                        // One status instead of three switches (status / is_active / is_bookable)
                        // that could contradict each other. Applied by EditVenue::afterSave via
                        // Venue::applyVisibilityState(), which writes all three together and
                        // refuses to go live until the venue is ready. New venues start as Draft.
                        Select::make('visibility_state')
                            ->label('Status')
                            ->options(Venue::STATES)
                            ->visibleOn('edit')
                            ->native(false)
                            ->required()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Venue $record): string => $record?->visibilityState() ?? Venue::STATE_DRAFT)
                            ->helperText('Live needs at least one active court, a time slot, a price and a photo — if anything is missing, the venue stays as it was and you’re told what to add. “Bookings paused” keeps the venue visible but stops new bookings (existing ones stand).')
                            ->columnSpanFull(),
                        TextInput::make('booking_window_days')
                            ->label('Booking window (days ahead)')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->placeholder((string) MembershipSettings::int('venue_booking_window_days'))
                            ->helperText('How far ahead customers can book. Leave blank for the default in Finance → Membership settings. Pro and Hero members get their priority days on top.'),
                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false)
                            ->visible(fn (): bool => ! self::isPartnerPanel()),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers show first.')
                            ->visible(fn (): bool => ! self::isPartnerPanel()),
                        Select::make('partner_id')
                            ->label('Owner / partner')
                            ->relationship(
                                'partner',
                                'name',
                                // Only real partner accounts on the venue lane — assigning a
                                // venue to a consumer member would put a listing behind a
                                // login that cannot open the partner console.
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('role', 'PARTNER')
                                    ->where(fn (Builder $q) => $q
                                        ->whereRaw("lower(coalesce(partner_type, 'venue')) = 'venue'"))
                                    ->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (User $record): string => trim($record->name . ' · ' . ($record->email ?? '')),
                            )
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->placeholder('Platform-owned (no partner)')
                            ->helperText('The venue owner who manages this in the partner console. Only venue-lane partner accounts are listed. Leave blank for platform-owned.')
                            ->visible(fn (): bool => ! self::isPartnerPanel()),
                        OrganizationSelect::make()
                            ->visible(fn (): bool => ! self::isPartnerPanel()),
                    ]),

                // Read-only: the rating is worked out from real customer reviews
                // (Venue::refreshRating()). It used to be typed in here, which is how a venue
                // with no reviews showed 4.2 ★ from 120 ratings.
                Section::make('Ratings & Reviews')
                    ->visibleOn('edit')
                    ->schema([
                        Placeholder::make('rating_summary')
                            ->hiddenLabel()
                            ->content(fn (?Venue $record): string => $record !== null && (int) $record->ratings_count > 0
                                ? number_format((float) $record->rating, 1) . ' ★ from ' . (int) $record->ratings_count
                                    . ' review' . ((int) $record->ratings_count === 1 ? '' : 's')
                                    . ' — worked out from customer reviews (Reviews tab).'
                                : 'No reviews yet — customers see no rating until the first review arrives.'),
                    ]),
            ]);
    }

    /**
     * Fold the `image_urls` helper field into the `images` column on save: uploaded files
     * first, pasted URLs after. The helper key is removed so it never hits the model.
     * Shared by the Create and Edit pages.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeImageSources(array $data): array
    {
        $files = array_values(array_filter(
            (array) ($data['images'] ?? []),
            static fn ($i): bool => is_string($i) && trim($i) !== '',
        ));
        $urls = array_values(array_filter(
            array_map(static fn ($u) => trim((string) $u), (array) ($data['image_urls'] ?? [])),
            static fn ($u): bool => $u !== '',
        ));

        $data['images'] = array_values(array_merge($files, $urls));
        unset($data['image_urls']);

        return $data;
    }

    /**
     * Inverse of {@see mergeImageSources()} for the Edit form: split stored `images` back
     * into uploaded files (FileUpload) and pasted http(s) URLs (TagsInput), so the
     * FileUpload never chokes on a remote URL it can't resolve as a local file.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function splitImageSources(array $data): array
    {
        $all = array_values(array_filter(
            (array) ($data['images'] ?? []),
            static fn ($i): bool => is_string($i) && trim($i) !== '',
        ));

        $data['images'] = array_values(array_filter(
            $all,
            static fn ($i): bool => ! preg_match('#^https?://#i', $i),
        ));
        $data['image_urls'] = array_values(array_filter(
            $all,
            static fn ($i): bool => (bool) preg_match('#^https?://#i', $i),
        ));

        return $data;
    }

    /**
     * Fold the amenities checklist (`amenities_known`) + free-text extras (`amenities_other`)
     * into the `amenities` column. Ticked canonical labels come first (they carry icons),
     * extras after. Helper keys are removed so they never reach the model.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeAmenities(array $data): array
    {
        $known = array_values((array) ($data['amenities_known'] ?? []));
        $other = array_values(array_filter(
            array_map(static fn ($a) => trim((string) $a), (array) ($data['amenities_other'] ?? [])),
            static fn ($a): bool => $a !== '',
        ));

        $data['amenities'] = array_values(array_unique(array_merge($known, $other)));
        unset($data['amenities_known'], $data['amenities_other']);

        return $data;
    }

    /**
     * Split the stored `amenities` into ticked canonical labels + free-text extras for the form.
     * Legacy/free values are matched onto a canonical label by keyword (so "Washrooms" ticks
     * "Washroom"); anything unrecognised falls to the extras box.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function splitAmenities(array $data): array
    {
        $known = [];
        $other = [];

        foreach ((array) ($data['amenities'] ?? []) as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $canonical = self::canonicalAmenity($value);
            if ($canonical !== null) {
                $known[$canonical] = true;
            } else {
                $other[] = $value;
            }
        }

        $data['amenities_known'] = array_keys($known);
        $data['amenities_other'] = array_values(array_unique($other));

        return $data;
    }

    /**
     * Fold the rule presets (`rules_known`) + free-text extras (`rules_other`) into `rules`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeRules(array $data): array
    {
        $known = array_values((array) ($data['rules_known'] ?? []));
        $other = array_values(array_filter(
            array_map(static fn ($r) => trim((string) $r), (array) ($data['rules_other'] ?? [])),
            static fn ($r): bool => $r !== '',
        ));

        $data['rules'] = array_values(array_unique(array_merge($known, $other)));
        unset($data['rules_known'], $data['rules_other']);

        return $data;
    }

    /**
     * Split stored `rules` into ticked presets + free-text extras. Exact preset matches tick
     * their box; everything else goes to the extras box.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function splitRules(array $data): array
    {
        $presets = self::RULE_PRESETS;
        $known = [];
        $other = [];

        foreach ((array) ($data['rules'] ?? []) as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            if (in_array($value, $presets, true)) {
                $known[] = $value;
            } else {
                $other[] = $value;
            }
        }

        $data['rules_known'] = array_values(array_unique($known));
        $data['rules_other'] = array_values(array_unique($other));

        return $data;
    }

    /**
     * Fold the hours repeater (`hours_rows`) into the `hours_json` map keyed by weekday.
     * Rows without a full day/open/close are dropped; days not listed are treated as closed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeHours(array $data): array
    {
        $map = [];
        foreach ((array) ($data['hours_rows'] ?? []) as $row) {
            $day = $row['day'] ?? null;
            $open = $row['open'] ?? null;
            $close = $row['close'] ?? null;
            if ($day && $open && $close) {
                $map[$day] = ['open' => substr((string) $open, 0, 5), 'close' => substr((string) $close, 0, 5)];
            }
        }

        $data['hours_json'] = $map === [] ? null : $map;
        unset($data['hours_rows']);

        return $data;
    }

    /**
     * Split the stored `hours_json` map into repeater rows (one per open weekday, in order).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function splitHours(array $data): array
    {
        $hours = is_array($data['hours_json'] ?? null) ? $data['hours_json'] : [];
        $order = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $rows = [];

        foreach ($order as $key) {
            $day = $hours[$key] ?? null;
            if (is_array($day) && empty($day['closed']) && ! empty($day['open']) && ! empty($day['close'])) {
                $rows[] = ['day' => $key, 'open' => $day['open'], 'close' => $day['close']];
            }
        }

        $data['hours_rows'] = $rows;

        return $data;
    }

    /** Map a free-text amenity onto a canonical label by keyword, or null when unrecognised. */
    private static function canonicalAmenity(string $value): ?string
    {
        $needle = ' '.strtolower($value).' ';

        foreach (self::AMENITIES as $label => $keywords) {
            if (strtolower($label) === strtolower($value)) {
                return $label;
            }
            foreach ($keywords as $kw) {
                if (str_contains($needle, $kw)) {
                    return $label;
                }
            }
        }

        return null;
    }
}
