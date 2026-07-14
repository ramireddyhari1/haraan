<?php

namespace App\Filament\Resources\Venues\Schemas;

use App\Filament\Forms\OrganizationSelect;
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

                Section::make('Amenities & rules')
                    ->schema([
                        TagsInput::make('amenities')
                            ->placeholder('Floodlights, Parking, Washrooms…')
                            ->helperText('One chip per amenity. Known names (parking, washroom, shower, cafe, water, floodlights, AC, wifi, security, seating, equipment) get their own icon.')
                            ->columnSpanFull(),
                        TagsInput::make('rules')
                            ->label('Good to know')
                            ->placeholder('Carry your own racket, Shoes mandatory…')
                            ->helperText('House rules — each chip is a bullet in the "Good to know" list.')
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
}
