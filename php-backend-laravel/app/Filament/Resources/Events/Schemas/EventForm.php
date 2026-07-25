<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Filament\Forms\OrganizationSelect;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
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
        // Poster: a single image. Uploaded file wins over a pasted URL; only the
        // first survives so `images` never carries stray gallery photos.
        $posterFiles = self::cleanStrings($data['images'] ?? []);
        $posterUrls  = self::cleanStrings($data['image_urls'] ?? []);
        $poster = array_merge($posterFiles, $posterUrls);
        $data['images'] = $poster === [] ? [] : [reset($poster)];
        unset($data['image_urls']);

        // Gallery: the showcase photos, uploaded files then pasted URLs.
        $galleryFiles = self::cleanStrings($data['gallery'] ?? []);
        $galleryUrls  = self::cleanStrings($data['gallery_urls'] ?? []);
        $data['gallery'] = array_values(array_merge($galleryFiles, $galleryUrls));
        unset($data['gallery_urls']);

        return $data;
    }

    /**
     * Trim + drop empties from a mixed array of image paths/URLs.
     *
     * @return list<string>
     */
    private static function cleanStrings(mixed $value): array
    {
        return array_values(array_filter(
            array_map(static fn ($v) => is_string($v) ? trim($v) : '', (array) $value),
            static fn ($v): bool => $v !== '',
        ));
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
        // Poster → local file (FileUpload) vs pasted URL (TagsInput).
        [$data['images'], $data['image_urls']] = self::partitionUrls($data['images'] ?? []);

        // Gallery → the same split for its own two fields.
        [$data['gallery'], $data['gallery_urls']] = self::partitionUrls($data['gallery'] ?? []);

        return $data;
    }

    /**
     * Partition a stored image list into [localFiles, httpUrls] so the FileUpload
     * never chokes on a remote URL it can't resolve as a local file.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function partitionUrls(mixed $value): array
    {
        $all = self::cleanStrings($value);

        return [
            array_values(array_filter($all, static fn ($i): bool => ! preg_match('#^https?://#i', $i))),
            array_values(array_filter($all, static fn ($i): bool => (bool) preg_match('#^https?://#i', $i))),
        ];
    }

    /**
     * Whether platform-only controls should show. These fields — the host/organizer
     * assignment, the editorial rating figures and the platform convenience fee —
     * belong to Haraan staff in /control, not to the organiser filling in their own
     * event in /partner. False in the partner console, true everywhere else. On
     * create the partner's ownership is stamped server-side (CreateEvent), and the
     * hidden fields keep their sensible defaults (fee "none", rating blank).
     */
    private static function adminOnly(): bool
    {
        return Filament::getCurrentPanel()?->getId() !== 'partner';
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
                    self::galleryStep(),
                ])
                    ->columnSpanFull(),
                // NB: deliberately NOT ->skippable(). A skippable wizard lets a host
                // jump straight to "Publish" past unfilled required steps; clicking
                // Create then fails validation on those earlier steps, but Filament
                // renders the errors on a hidden step and neither navigates to it nor
                // flags the step header — so the button appears to do nothing. Keeping
                // the wizard sequential validates each step on "Next", surfacing any
                // error on the step the host is actually looking at.
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
                    // A real default (not just a placeholder). Previously "7:00 PM"
                    // was only placeholder text, so the field looked pre-filled but
                    // was actually empty — a required-field validation trap that
                    // silently blocked Create for hosts who left it untouched.
                    ->default('7:00 PM')
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
                // Ticket tiers (Early Bird / VIP / …) are their own records, edited in
                // the "Ticket Types" panel below the form — which only exists once the
                // event is saved. So on the create wizard there is nothing to show; on
                // edit we surface the saved tiers here (read-only) so the Tickets step
                // isn't misread as "my tiers vanished", with a pointer to where they live.
                Placeholder::make('ticket_tiers_summary')
                    ->label('Ticket tiers')
                    ->visible(fn (?\App\Models\Event $record): bool => $record !== null)
                    ->content(function (?\App\Models\Event $record): ?\Illuminate\Support\HtmlString {
                        if ($record === null) {
                            return null;
                        }

                        $tiers = $record->ticketTypes()->get();

                        if ($tiers->isEmpty()) {
                            return new \Illuminate\Support\HtmlString(
                                '<div style="color:#6b7280">No ticket tiers yet. Add Early Bird, VIP, '
                                . 'and other tiers in the <strong>Ticket Types</strong> panel below the form.</div>'
                            );
                        }

                        $items = $tiers->map(function ($t): string {
                            $cap = ($t->capacity === null || $t->capacity === '')
                                ? 'unlimited'
                                : (string) (int) $t->capacity;

                            return '<li style="margin:2px 0">'
                                . '<strong>' . e($t->name) . '</strong> — ₹' . number_format((float) $t->price, 2)
                                . ' · capacity ' . $cap
                                . ' · sold ' . (int) $t->sold
                                . '</li>';
                        })->implode('');

                        return new \Illuminate\Support\HtmlString(
                            '<ul style="margin:0;padding-left:1rem;list-style:disc">' . $items . '</ul>'
                            . '<div style="margin-top:6px;color:#6b7280">Manage these in the '
                            . '<strong>Ticket Types</strong> panel below the form.</div>'
                        );
                    })
                    ->columnSpanFull(),
                // While CREATING an event there is no saved record yet, so the
                // "Ticket Types" relation-manager panel can't exist. This repeater lets
                // the host define tiers (Early Bird / VIP / Group…) right here during
                // creation; ->relationship() saves them as TicketType rows once the event
                // is created. On edit it's hidden — the richer Ticket Types panel (presets,
                // dynamic pricing, sold counts) owns tier editing there, and hiding this on
                // edit also means it never touches existing tiers.
                Repeater::make('ticketTypes')
                    ->relationship()
                    ->label('Ticket tiers')
                    ->visibleOn('create')
                    ->addActionLabel('Add ticket tier')
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'New tier')
                    ->helperText('Optional. Add tiers like Early Bird, VIP or Group now, or add and fine-tune them after saving.')
                    // Same preset picker + fields as the post-save "Ticket Types" panel.
                    ->schema(\App\Filament\Resources\Events\Schemas\TicketTypeFields::components())
                    ->columnSpanFull(),
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
                // Order fees (Convenience fee, Gateway fee, …). Admin-only; each row is
                // charged on top of the ticket subtotal and shown as its own line to
                // buyers. Stored as the `fees` JSON array on the event.
                Repeater::make('fees')
                    ->label('Fees')
                    ->visible(self::adminOnly())
                    ->addActionLabel('Add fee')
                    ->defaultItems(0)
                    ->reorderable()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'New fee')
                    ->helperText('Each fee is added on top of the ticket subtotal at checkout and shown as its own line to buyers.')
                    ->schema([
                        TextInput::make('label')
                            ->label('Fee label (shown to buyers)')
                            ->required()
                            ->maxLength(40)
                            ->placeholder('Convenience fee / Gateway fee')
                            ->columnSpan(2),
                        Select::make('type')
                            ->label('Type')
                            ->options([
                                'flat'    => 'Flat ₹ amount',
                                'percent' => 'Percentage of subtotal',
                            ])
                            ->default('flat')
                            ->native(false)
                            ->required()
                            ->live(),
                        TextInput::make('value')
                            ->label(fn (Get $get): string => $get('type') === 'percent' ? 'Percentage' : 'Amount')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->prefix(fn (Get $get): ?string => $get('type') === 'flat' ? '₹' : null)
                            ->suffix(fn (Get $get): ?string => $get('type') === 'percent' ? '%' : null),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Select::make('tax_type')
                    ->label('Tax')
                    ->options([
                        'none'    => 'No tax',
                        'flat'    => 'Flat ₹ amount',
                        'percent' => 'Percentage of subtotal',
                    ])
                    ->default('none')
                    ->native(false)
                    ->live()
                    // Tax (e.g. GST) — set by Haraan staff in /control, hidden from organisers.
                    ->visible(self::adminOnly())
                    ->helperText('Not charged yet — stored on the event for now.'),
                TextInput::make('tax_value')
                    ->label(fn (Get $get): string => $get('tax_type') === 'percent' ? 'Tax percentage' : 'Tax amount')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix(fn (Get $get): ?string => $get('tax_type') === 'flat' ? '₹' : null)
                    ->suffix(fn (Get $get): ?string => $get('tax_type') === 'percent' ? '%' : null)
                    ->visible(fn (Get $get): bool => self::adminOnly() && in_array($get('tax_type'), ['flat', 'percent'], true)),
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
                    ->label('Event poster — upload')
                    ->image()
                    ->disk('public')
                    ->directory('events')
                    ->imageEditor()
                    ->helperText('One poster — the image on the event card AND the page hero. Upload a file, OR paste a link below (an uploaded file wins). Size: portrait 1080×1440px (3:4) shows uncropped everywhere; other shapes get cropped, so keep the title away from the edges. JPG or PNG · under 20MB. Extra showcase photos go in the Gallery step.')
                    ->columnSpanFull(),
                TagsInput::make('image_urls')
                    ->label('…or paste a poster URL')
                    ->placeholder('https://…/poster.jpg  — press Enter')
                    ->helperText('Paste a direct image link instead of uploading. The link must end in the image itself.')
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
                // Host assignment + editorial ratings are platform controls: hidden in
                // /partner (a partner's own ownership is stamped server-side on create,
                // see CreateEvent), shown only to Haraan staff in /control.
                Select::make('partner_id')
                    ->label('Host / organizer')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(self::adminOnly())
                    ->default(fn () => auth()->id()),
                TextInput::make('rating')
                    ->label('Rating')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(5)
                    ->step(0.1)
                    ->placeholder('e.g. 4.8')
                    ->visible(self::adminOnly())
                    ->helperText('Shown on the event detail row. Leave blank to hide it.'),
                TextInput::make('ratings_count')
                    ->label('Number of ratings')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->visible(self::adminOnly())
                    ->helperText('How many people rated it (shown as "(123)" next to the star).'),
            ]);
    }

    /**
     * Gallery — the showcase photos beyond the poster. These render as the
     * "Gallery" section on the event detail page. Optional; folds into the
     * `gallery` column via {@see mergeImageSources()}.
     */
    private static function galleryStep(): Step
    {
        return Step::make('Gallery')
            ->description('Showcase photos')
            ->icon('heroicon-o-photo')
            ->schema(self::galleryFields());
    }

    /**
     * The gallery upload + paste-URL fields, shared by the wizard's Gallery step
     * and the per-event "Gallery" quick-manage modal (ManageGalleryAction).
     *
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function galleryFields(): array
    {
        return [
            FileUpload::make('gallery')
                ->label('Gallery photos — upload')
                ->image()
                ->multiple()
                ->reorderable()
                ->appendFiles()
                ->disk('public')
                ->directory('events/gallery')
                ->imageEditor()
                ->helperText('Extra photos shown in the "Gallery" section on the event page — past-edition shots, venue vibe, stage setup. Drag to reorder. These do NOT replace the poster. JPG or PNG · under 20MB each.')
                ->columnSpanFull(),
            TagsInput::make('gallery_urls')
                ->label('…or paste gallery image URL(s)')
                ->placeholder('https://…/photo.jpg  — press Enter')
                ->helperText('Paste direct image links to add to the gallery. Each link must end in the image itself.')
                ->columnSpanFull(),
        ];
    }

    /**
     * Split a stored `gallery` column into the modal/step's two fields
     * (uploaded files vs pasted URLs) for pre-filling.
     *
     * @return array{gallery: list<string>, gallery_urls: list<string>}
     */
    public static function splitGalleryData(mixed $gallery): array
    {
        [$files, $urls] = self::partitionUrls($gallery);

        return ['gallery' => $files, 'gallery_urls' => $urls];
    }

    /**
     * Fold the gallery fields back into a single ordered list for the `gallery`
     * column — uploaded files first, then pasted URLs.
     *
     * @return list<string>
     */
    public static function mergeGalleryData(array $data): array
    {
        return array_values(array_merge(
            self::cleanStrings($data['gallery'] ?? []),
            self::cleanStrings($data['gallery_urls'] ?? []),
        ));
    }
}
