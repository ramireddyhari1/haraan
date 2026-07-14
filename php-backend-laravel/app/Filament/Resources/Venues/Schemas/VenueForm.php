<?php

namespace App\Filament\Resources\Venues\Schemas;

use App\Filament\Forms\OrganizationSelect;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class VenueForm
{
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
                        .'<strong>2-step setup.</strong> Fill this form and press <strong>Create</strong>. '
                        .'Then, on the edit screen, add <strong>Courts</strong> (each with its sports &amp; price) and <strong>Time slots</strong> from the tabs — that\'s what powers booking.'
                        .'</div>'
                    ))
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
                        TextInput::make('hours')
                            ->label('Operating hours')
                            ->placeholder('6:00 AM – 11:00 PM')
                            ->helperText('Shown with a clock icon under the venue name.'),
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
                        TextInput::make('latitude')
                            ->numeric()
                            ->step('0.0000001')
                            ->minValue(-90)
                            ->maxValue(90)
                            ->placeholder('19.0596')
                            ->helperText('In Google Maps, right-click the spot → the first row is "lat, lng". Copy the first number.'),
                        TextInput::make('longitude')
                            ->numeric()
                            ->step('0.0000001')
                            ->minValue(-180)
                            ->maxValue(180)
                            ->placeholder('72.8295')
                            ->helperText('…and the second number here.'),
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

                Section::make('Visibility & ownership')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active (visible in the app)')
                            ->default(true),
                        Toggle::make('is_bookable')
                            ->label('Open for booking')
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers show first.'),
                        Select::make('partner_id')
                            ->label('Owner / partner')
                            ->relationship('partner', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Platform-owned (no partner)')
                            ->helperText('The venue owner who manages this in the partner console. Leave blank for platform-owned.'),
                        OrganizationSelect::make(),
                    ]),

                Section::make('Ratings (starter values)')
                    ->description('Optional seed numbers shown until real reviews arrive — real customer reviews override these on the venue page.')
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextInput::make('rating')
                            ->numeric()
                            ->step('0.1')
                            ->default('4.5'),
                        TextInput::make('ratings_count')
                            ->numeric()
                            ->default(0),
                        TextInput::make('reviews_count')
                            ->numeric()
                            ->default(0),
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
