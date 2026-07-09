<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Filament\Forms\OrganizationSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

/**
 * Guided event creation. A 4-step wizard (Basics → When & Where → Tickets →
 * Publish) so a host fills a handful of sensible fields per step instead of one
 * flat wall of inputs. Ticket tiers are added afterwards on the Edit page.
 */
class EventForm
{
    /** Canonical categories — mirrors EventsController::categories(). */
    private const CATEGORIES = [
        'MUSIC'      => 'Music / Concert',
        'COMEDY'     => 'Comedy / Standup',
        'SPORTS'     => 'Sports',
        'WORKSHOP'   => 'Workshop',
        'ADVENTURE'  => 'Adventure',
        'FOOD'       => 'Food & Drink',
        'NIGHTLIFE'  => 'Nightlife',
        'FESTIVAL'   => 'Festival',
        'THEATER'    => 'Theatre',
        'EXHIBITION' => 'Exhibition',
    ];

    /**
     * City dropdown options sourced from the same `public/data/cities.json` the
     * app/website pickers use, keyed by name so the stored value matches what
     * the event page displays (e.g. "Hyderabad"). Falls back to a small default
     * set if the file is missing or unreadable.
     *
     * @return array<string, string>
     */
    private static function cityOptions(): array
    {
        $path = public_path('data/cities.json');
        $cities = is_file($path)
            ? json_decode((string) file_get_contents($path), true)
            : null;

        if (! is_array($cities)) {
            return ['Hyderabad' => 'Hyderabad', 'Bengaluru' => 'Bengaluru', 'Chennai' => 'Chennai'];
        }

        $options = [];
        foreach ($cities as $c) {
            $name = is_array($c) ? trim((string) ($c['name'] ?? '')) : '';
            if ($name !== '') {
                $options[$name] = $name;
            }
        }

        return $options === [] ? ['Hyderabad' => 'Hyderabad'] : $options;
    }

    /**
     * Fold the `image_urls` helper field into the `images` column on save: real
     * uploads first, pasted URLs after (so an uploaded poster wins when both are
     * present). The helper key is removed so it never hits the model. Shared by
     * the Create and Edit pages.
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
     * Inverse of {@see mergeImageSources()} for the Edit form: split the stored
     * `images` back into uploaded files (shown in the FileUpload) and pasted
     * http(s) URLs (shown in the TagsInput), so the FileUpload never chokes on a
     * remote URL it can't resolve as a local file.
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

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    self::basicsStep(),
                    self::whenWhereStep(),
                    self::goodToKnowStep(),
                    self::lineupStep(),
                    self::ticketsStep(),
                    self::publishStep(),
                ])
                    ->columnSpanFull()
                    ->skippable(),
            ]);
    }

    private static function basicsStep(): Step
    {
        return Step::make('Basics')
            ->description('What is the event?')
            ->icon('heroicon-o-sparkles')
            ->schema([
                TextInput::make('title')
                    ->label('Event title')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Karthik Live in Hyderabad')
                    ->columnSpanFull(),
                Select::make('category')
                    ->options(self::CATEGORIES)
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->default('MUSIC'),
                OrganizationSelect::make(),
                Textarea::make('description')
                    ->required()
                    ->rows(4)
                    ->placeholder('Tell attendees what to expect — line-up, highlights, what to bring.')
                    ->columnSpanFull(),
            ]);
    }

    private static function whenWhereStep(): Step
    {
        return Step::make('When & Where')
            ->description('Date and location')
            ->icon('heroicon-o-map-pin')
            ->schema([
                DatePicker::make('date')
                    ->label('Event date')
                    ->required()
                    ->native(false)
                    ->minDate(now()->startOfDay())
                    ->displayFormat('D, d M Y'),
                TimePicker::make('time')
                    ->label('Start time')
                    ->required()
                    ->native(false)
                    ->seconds(false)
                    ->format('g:i A')       // store "7:00 PM" to match existing display strings
                    ->displayFormat('g:i A')
                    ->placeholder('7:00 PM'),
                Select::make('city')
                    ->label('City')
                    ->options(self::cityOptions())
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->helperText('Shown beside the date on the event page and drives city filtering.'),
                TextInput::make('venue')
                    ->label('Venue name')
                    ->required()
                    ->placeholder('e.g. Quake Arena'),
                TextInput::make('location')
                    ->label('Area / address')
                    ->required()
                    ->placeholder('e.g. Kondapur, Hyderabad')
                    ->columnSpanFull(),
                TextInput::make('map_link')
                    ->label('Google Maps link')
                    ->url()
                    ->maxLength(600)
                    ->placeholder('https://maps.app.goo.gl/…')
                    ->helperText('Open the venue in Google Maps → Share → Copy link, and paste it here. Powers the "Directions" button.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * "Good to Know" — the scannable attribute chips (language, age, layout…)
     * plus the real T&C bullets and any custom rows. All optional; whatever the
     * host leaves blank simply doesn't render on the app's event detail screen.
     */
    private static function goodToKnowStep(): Step
    {
        return Step::make('Good to Know')
            ->description('What attendees should know before they book')
            ->icon('heroicon-o-information-circle')
            ->schema([
                TagsInput::make('languages')
                    ->label('Language(s)')
                    ->placeholder('Add a language')
                    ->suggestions(['Telugu', 'Hindi', 'English', 'Tamil', 'Kannada', 'Malayalam'])
                    ->helperText('Language(s) the event is performed / conducted in.')
                    ->columnSpanFull(),
                Select::make('age_limit')
                    ->label('Age limit')
                    ->options([
                        'All ages' => 'All ages',
                        '5+'       => '5 yrs & above',
                        '13+'      => '13 yrs & above',
                        '16+'      => '16 yrs & above',
                        '18+'      => '18 yrs & above (adults only)',
                        '21+'      => '21 yrs & above',
                    ])
                    ->native(false)
                    ->placeholder('Not specified'),
                TextInput::make('duration')
                    ->label('Duration')
                    ->placeholder('e.g. 2h 30m'),
                Select::make('layout')
                    ->label('Layout')
                    ->options([
                        'Indoor'            => 'Indoor',
                        'Outdoor'           => 'Outdoor',
                        'Indoor & Outdoor'  => 'Indoor & Outdoor',
                    ])
                    ->native(false)
                    ->placeholder('Not specified'),
                Select::make('seating_type')
                    ->label('Seating')
                    ->options([
                        'Seated'   => 'Seated',
                        'Standing' => 'Standing',
                        'Mixed'    => 'Seated & Standing',
                    ])
                    ->native(false)
                    ->placeholder('Not specified'),
                TextInput::make('entry_note')
                    ->label('Entry allowed for')
                    ->placeholder('e.g. Ticket holders only')
                    ->columnSpanFull(),
                Select::make('kid_friendly')
                    ->label('Kid friendly?')
                    ->boolean()
                    ->native(false)
                    ->placeholder('Not specified'),
                Select::make('pet_friendly')
                    ->label('Pet friendly?')
                    ->boolean()
                    ->native(false)
                    ->placeholder('Not specified'),
                TagsInput::make('info_notes')
                    ->label('Important information (T&C bullets)')
                    ->placeholder('Add a note & press Enter')
                    ->helperText('Shown as a bulleted list, e.g. "Valid ID required at entry."')
                    ->columnSpanFull(),
                Repeater::make('good_to_know')
                    ->label('Extra "Good to Know" rows')
                    ->schema([
                        TextInput::make('label')->required()->placeholder('e.g. Parking'),
                        TextInput::make('value')->required()->placeholder('e.g. Free on-site'),
                    ])
                    ->columns(2)
                    ->addActionLabel('Add a custom row')
                    ->defaultItems(0)
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make('schedule')
                    ->label('Schedule / Run of show')
                    ->helperText('Shown when a user taps the "Doors Open" card.')
                    ->schema([
                        TextInput::make('time')
                            ->required()
                            ->placeholder('8:00 PM')
                            ->helperText('Start time of this item'),
                        TextInput::make('title')
                            ->required()
                            ->placeholder('Opening act'),
                        TextInput::make('note')
                            ->placeholder('Main Arena · 45 min set')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Add a schedule item')
                    ->defaultItems(0)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => trim(($state['time'] ?? '').' — '.($state['title'] ?? ''), ' —') ?: null)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * "Who takes the stage" — the performer lineup rendered as a coverflow
     * carousel on the app's event detail screen. Each card is an image + name +
     * subtitle (role / genre / hit track). All optional.
     */
    private static function lineupStep(): Step
    {
        return Step::make('Lineup')
            ->description('Who takes the stage')
            ->icon('heroicon-o-microphone')
            ->schema([
                Repeater::make('lineup')
                    ->label('Performers')
                    ->helperText('Shown as a swipeable "Who takes the stage" carousel in the app.')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Photo — upload')
                            ->image()
                            ->disk('public')
                            ->directory('events/lineup')
                            ->imageEditor()
                            ->helperText('Upload a square photo (600×600px, 1:1 · under 20MB), or paste an image URL below instead.')
                            ->columnSpanFull(),
                        TextInput::make('image_url')
                            ->label('…or image URL')
                            ->url()
                            ->placeholder('https://…/artist.jpg')
                            ->helperText('Used when no photo is uploaded above.')
                            ->columnSpanFull(),
                        TextInput::make('name')
                            ->required()
                            ->placeholder('e.g. Root 35'),
                        TextInput::make('subtitle')
                            ->placeholder('e.g. Headliner · Live band'),
                    ])
                    ->columns(2)
                    ->addActionLabel('Add a performer')
                    ->defaultItems(0)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->columnSpanFull(),
            ]);
    }

    private static function ticketsStep(): Step
    {
        return Step::make('Tickets')
            ->description('Capacity & pricing')
            ->icon('heroicon-o-ticket')
            ->schema([
                TextInput::make('total_slots')
                    ->label('Total capacity')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(100)
                    ->helperText('How many people can attend in total.'),
                TextInput::make('price')
                    ->label('Base price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('₹')
                    ->helperText('Starting price. Add Gold / VIP / Early-Bird tiers after saving.'),
                Select::make('convenience_fee_type')
                    ->label('Convenience fee')
                    ->options([
                        'none'    => 'No fee',
                        'flat'    => 'Flat ₹ amount',
                        'percent' => 'Percentage of subtotal',
                    ])
                    ->default('none')
                    ->native(false)
                    ->live()
                    ->helperText('Added on top of the ticket subtotal at checkout.'),
                TextInput::make('convenience_fee_value')
                    ->label(fn (Get $get): string => $get('convenience_fee_type') === 'percent' ? 'Fee percentage' : 'Fee amount')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix(fn (Get $get): ?string => $get('convenience_fee_type') === 'flat' ? '₹' : null)
                    ->suffix(fn (Get $get): ?string => $get('convenience_fee_type') === 'percent' ? '%' : null)
                    ->visible(fn (Get $get): bool => in_array($get('convenience_fee_type'), ['flat', 'percent'], true))
                    ->helperText('Buyers see this as a "Convenience fee" line on the order summary.'),
                Toggle::make('seat_selection')
                    ->label('Assigned seating (reserved seats)')
                    ->live()
                    ->default(false)
                    ->helperText('Off = general admission. On = attendees pick a seat.')
                    ->columnSpanFull(),
                TextInput::make('seat_rows')
                    ->label('Seat rows')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn (Get $get): bool => (bool) $get('seat_selection')),
                TextInput::make('seats_per_row')
                    ->label('Seats per row')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn (Get $get): bool => (bool) $get('seat_selection')),
            ]);
    }

    private static function publishStep(): Step
    {
        return Step::make('Publish')
            ->description('Media & visibility')
            ->icon('heroicon-o-rocket-launch')
            ->schema([
                FileUpload::make('images')
                    ->label('Cover image(s) — upload')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->disk('public')
                    ->directory('events')
                    ->imageEditor()
                    ->helperText('Upload files, OR paste image links below — use whichever you prefer (or both). The first image (uploaded, then pasted) is the poster. Recommended: portrait 1080×1350px (4:5) or landscape 1200×800px (3:2) · JPG or PNG · under 20MB.')
                    ->columnSpanFull(),
                TagsInput::make('image_urls')
                    ->label('…or paste image URL(s)')
                    ->placeholder('https://…/poster.jpg  — press Enter')
                    ->helperText('Paste direct image links instead of (or in addition to) uploading. Each link must end in the image itself.')
                    ->columnSpanFull(),
                Select::make('booking_format')
                    ->label('Format')
                    ->options([
                        'OFFLINE' => 'In person',
                        'ONLINE'  => 'Online',
                        'HYBRID'  => 'Hybrid',
                    ])
                    ->required()
                    ->native(false)
                    ->default('OFFLINE'),
                Select::make('visibility')
                    ->options([
                        'PUBLIC'  => 'Public — anyone can find it',
                        'PRIVATE' => 'Private — access code only',
                    ])
                    ->required()
                    ->native(false)
                    ->live()
                    ->default('PUBLIC'),
                TextInput::make('access_code')
                    ->label('Access code')
                    ->visible(fn (Get $get): bool => $get('visibility') === 'PRIVATE')
                    ->helperText('Attendees enter this to unlock a private event.'),
                Select::make('status')
                    ->options([
                        'draft'     => 'Draft — not visible yet',
                        'published' => 'Published — live now',
                    ])
                    ->required()
                    ->native(false)
                    ->default('draft'),
                Select::make('placements')
                    ->label('Show in sections')
                    ->multiple()
                    ->options([
                        'for_you'  => 'For You (featured hero)',
                        'trending' => 'Trending',
                        'nearby'   => 'Explore Nearby',
                    ])
                    ->default(['for_you', 'trending', 'nearby'])
                    ->native(false)
                    ->helperText('Which rails on the app\'s Events tab this event appears in. Leave all on to show it everywhere.'),
                Select::make('partner_id')
                    ->label('Host / organizer')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload()
                    ->default(fn () => auth()->id()),
                TextInput::make('rating')
                    ->label('Rating')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(5)
                    ->step(0.1)
                    ->placeholder('e.g. 4.8')
                    ->helperText('Shown on the event detail row. Leave blank to hide it.'),
                TextInput::make('ratings_count')
                    ->label('Number of ratings')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('How many people rated it (shown as "(123)" next to the star).'),
            ]);
    }
}
