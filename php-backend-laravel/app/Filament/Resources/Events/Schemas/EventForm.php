<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Filament\Forms\Components\ClockTimePicker;
use App\Filament\Forms\OrganizationSelect;
use App\Services\EventCopyAI;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Guided event creation. A 5-step wizard — the four that actually matter to go
 * live (Basics → When & Where → Tickets → Publish), then a single optional
 * "Extras" step (Good to Know · Lineup · Gallery) that a host can fill or leave
 * blank. Required steps come first so the host reaches the Create button as soon
 * as the real work is done; everything after it is enrichment.
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
                    self::ticketsStep(),
                    self::couponsStep(),
                    self::extrasStep(),
                    self::publishStep(),
                ])
                    ->columnSpanFull(),
                // NB: deliberately NOT ->skippable(). A skippable wizard lets a host
                // jump straight to the last step past unfilled required steps; clicking
                // Create then fails validation on those earlier steps, but Filament
                // renders the errors on a hidden step and neither navigates to it nor
                // flags the step header — so the button appears to do nothing. Keeping
                // the wizard sequential validates each step on "Next", surfacing any
                // error on the step the host is actually looking at. It also means the
                // final "Extras" step (all-optional) is only reachable once every
                // required step has passed — so the Create button there never silently
                // fails on a hidden required field.
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
                    // One-tap AI first draft from the title + category (and whatever's
                    // already in the box). Server-side via EventCopyAI; if there's no
                    // API key or the call fails it quietly tells the host to write their
                    // own rather than blanking the field.
                    ->hintAction(
                        Action::make('draftDescription')
                            ->label('Draft with AI')
                            ->icon('heroicon-m-sparkles')
                            ->action(function (Get $get, Set $set): void {
                                $title = trim((string) $get('title'));
                                if ($title === '') {
                                    Notification::make()
                                        ->warning()
                                        ->title('Add an event title first')
                                        ->body('The AI drafts from your title and category — fill those in and try again.')
                                        ->send();

                                    return;
                                }

                                $draft = app(EventCopyAI::class)->draftDescription([
                                    'title'    => $title,
                                    'category' => (string) $get('category'),
                                    'existing' => (string) $get('description'),
                                ]);

                                if ($draft === null) {
                                    Notification::make()
                                        ->warning()
                                        ->title('Couldn’t draft it just now')
                                        ->body('The AI writer isn’t available — write a couple of lines yourself and you’re good to go.')
                                        ->send();

                                    return;
                                }

                                $set('description', $draft);

                                Notification::make()
                                    ->success()
                                    ->title('Draft added')
                                    ->body('Give it a read and tweak anything — it’s just a starting point.')
                                    ->send();
                            }),
                    )
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
                // Tap-to-select analog clock (like the phone time picker) instead of
                // Filament's 24-hour spinner. Hosts tap the hour, then minutes, then
                // AM/PM. Stores the same "7:00 PM" string the rest of the app expects.
                // A real ->default() (not just a placeholder) avoids the old required-
                // field trap where the field looked pre-filled but was actually empty.
                ClockTimePicker::make('time')
                    ->label('Start time')
                    ->required()
                    ->default('7:00 PM'),
                // Google Places search + draggable pin. Auto-fills venue, area/address,
                // the maps link and the lat/lng below. Falls back to a plain pin-picker
                // when no API key is set. See filament/places-picker.blade.php.
                ViewField::make('place_picker')
                    ->hiddenLabel()
                    ->view('filament.event-place-picker')
                    ->dehydrated(false)
                    ->columnSpanFull(),
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
                    ->placeholder('e.g. Quake Arena')
                    ->helperText('Filled by the search above — or type it.'),
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
                    ->helperText('Set automatically when you pick a place above. Or paste one: Google Maps → Share → Copy link. Powers the "Directions" button.')
                    ->columnSpanFull(),
                // Set by the picker; kept visible + editable as a fallback (right-click
                // a spot in Google Maps for "lat, lng"). These persist to the event.
                TextInput::make('latitude')
                    ->numeric()
                    ->step('0.0000001')
                    ->minValue(-90)
                    ->maxValue(90)
                    ->live()
                    ->placeholder('17.4239')
                    ->helperText('Set by the search/pin above.'),
                TextInput::make('longitude')
                    ->numeric()
                    ->step('0.0000001')
                    ->minValue(-180)
                    ->maxValue(180)
                    ->live()
                    ->placeholder('78.4738')
                    ->helperText('Set by the search/pin above.'),
            ]);
    }

    /**
     * "Good to Know" — the scannable attribute chips (language, age, layout…)
     * plus the real T&C bullets and any custom rows. All optional; whatever the
     * host leaves blank simply doesn't render on the app's event detail screen.
     * Returns the field list so it can sit inside the combined "Extras" step.
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    private static function goodToKnowFields(): array
    {
        return [
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
        ];
    }

    /**
     * "Who takes the stage" — the performer lineup rendered as a coverflow
     * carousel on the app's event detail screen. Each card is an image + name +
     * subtitle (role / genre / hit track). All optional. Returns the field list
     * so it can sit inside the combined "Extras" step.
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    private static function lineupFields(): array
    {
        return [
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
        ];
    }

    /**
     * The combined optional final step: Good to Know, Lineup and Gallery grouped
     * as collapsible sections. All-optional, so a host who just wants to publish
     * can land here and hit Create; those who want a richer event page fill it in.
     */
    private static function extrasStep(): Step
    {
        return Step::make('Extras')
            ->description('Optional — richer event page')
            ->icon('heroicon-o-square-3-stack-3d')
            ->schema([
                Section::make('Good to Know')
                    ->description('Language, age, seating, T&C — whatever helps attendees decide.')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->collapsed()
                    ->schema(self::goodToKnowFields()),
                Section::make('Lineup')
                    ->description('Who takes the stage — shown as a swipeable carousel.')
                    ->icon('heroicon-o-microphone')
                    ->collapsible()
                    ->collapsed()
                    ->schema(self::lineupFields()),
                Section::make('Gallery')
                    ->description('Showcase photos beyond the poster.')
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->collapsed()
                    ->schema(self::galleryFields()),
                Section::make('FAQs')
                    ->description('Frequently asked questions displayed last on your event page.')
                    ->icon('heroicon-o-question-mark-circle')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Repeater::make('faqs')
                            ->hiddenLabel()
                            ->addActionLabel('Add FAQ')
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => ($q = trim((string) ($state['question'] ?? ''))) !== '' ? $q : 'New FAQ')
                            ->helperText('Answer the questions attendees ask most — shown as an expandable list at the bottom of the event page.')
                            ->schema([
                                TextInput::make('question')
                                    ->label('Question')
                                    ->required()
                                    ->placeholder('e.g. What should I wear to the event?')
                                    ->columnSpanFull(),
                                Textarea::make('answer')
                                    ->label('Answer')
                                    ->required()
                                    ->rows(2)
                                    ->placeholder('Enter the answer…')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function ticketsStep(): Step
    {
        return Step::make('Tickets')
            ->description('Sessions, capacity & pricing')
            ->icon('heroicon-o-ticket')
            ->schema([
                // "Same for all slots" vs "Customize per slot". When on, each tier can
                // be pinned to a specific session (done after saving, once sessions have
                // ids — see the "Session" field on the ticket card).
                // On create this lives inside the Ticket Studio's "Same for all slots /
                // Customize per slot" selector; on edit it stays a plain toggle beside the
                // Ticket Types relation manager.
                // The Ticket Studio's "Same for all sessions / Customize per session"
                // selector owns this on both create and edit now, so the standalone
                // toggle is retired. Kept dehydrated(false) so it never overwrites the
                // column — EditEvent/CreateEvent set it from the studio's mode.
                Toggle::make('tickets_per_slot')
                    ->label('Different tickets for each session')
                    ->live()
                    ->default(false)
                    ->hidden()
                    ->dehydrated(false)
                    ->columnSpanFull(),

                // Sessions ("time slots"). Every event has at least one; add more to run
                // the same event across multiple showtimes/days, each with its own
                // capacity. Saved via the `slots` relationship.
                Repeater::make('slots')
                    ->relationship()
                    ->label('Sessions / time slots')
                    ->addActionLabel('Add another session')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->reorderable()
                    ->orderColumn('sort')
                    ->collapsible()
                    // Hidden on both create and edit — the Ticket Studio owns the ticket
                    // step now. A single default session is seeded on create from the
                    // event's date/time (CreateEvent::afterCreate); existing sessions stay
                    // put on edit because Filament skips saving a hidden relationship by
                    // default (shouldSaveRelationshipsWhenHidden = false), so bookings that
                    // reference a slot are never orphaned.
                    ->hidden()
                    ->itemLabel(fn (array $state): ?string => self::slotItemLabel($state))
                    ->schema([
                        TextInput::make('label')
                            ->label('Session name')
                            ->placeholder('e.g. Day 1 · Evening')
                            ->helperText('Optional — defaults to the date & time.')
                            ->columnSpanFull(),
                        DateTimePicker::make('starts_at')
                            ->label('Starts')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('D, d M Y · g:i A'),
                        DateTimePicker::make('ends_at')
                            ->label('Ends (optional)')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('D, d M Y · g:i A'),
                        TextInput::make('capacity')
                            ->label('Session capacity')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Leave blank to share the overall event capacity.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // Ticket tiers (Early Bird / VIP / …) are their own records, edited in
                // the "Ticket Types" panel below the form — which only exists once the
                // event is saved. So on the create wizard there is nothing to show; on
                // edit we surface the saved tiers here (read-only) so the Tickets step
                // isn't misread as "my tiers vanished", with a pointer to where they live.
                Placeholder::make('ticket_tiers_summary')
                    ->label('Ticket tiers')
                    // Retired: the Ticket Studio below now shows the live, editable tiers on
                    // edit too, so this read-only summary would only duplicate it.
                    ->visible(false)
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
                // CREATE: the bespoke "Ticket Studio" — the District-style ticket-setup UI
                // (mode selector, unified-config banner, ticket cards). Its state is
                // reconciled into new TicketType rows in CreateEvent::afterCreate(). On EDIT
                // the richer Ticket Types relation manager (presets, dynamic pricing, sold
                // counts) owns tier editing, so the studio is create-only — it never risks
                // existing tiers that already have bookings.
                // The bespoke "Ticket Studio" — the District-style ticket-setup UI (mode
                // selector, seating/phases config, ticket cards). On CREATE its state is
                // reconciled into new TicketType rows (CreateEvent::afterCreate); on EDIT it
                // hydrates from the event's existing tiers and is reconciled back on save,
                // preserving tiers that already have sold tickets (EditEvent).
                ViewField::make('ticketStudio')
                    ->view('filament.forms.ticket-studio')
                    ->dehydrated()
                    ->default(fn (): array => self::studioDefault())
                    ->columnSpanFull(),
                // Total capacity + base price are no longer authored here — capacity is
                // derived from the ticket seats and the price comes from the tiers (see
                // CreateEvent). Order fees (Convenience fee, Gateway fee, …). Admin-only; each row is
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
                // Seating is now owned by the Ticket Studio's "Seating Layout" card on both
                // create and edit, so these standalone controls are hidden. dehydrated(false)
                // keeps any existing seat_rows/seats_per_row values intact; seat_selection is
                // set from the studio's seating toggle in CreateEvent/EditEvent.
                Toggle::make('seat_selection')
                    ->label('Assigned seating (reserved seats)')
                    ->live()
                    ->default(false)
                    ->hidden()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                TextInput::make('seat_rows')
                    ->label('Seat rows')
                    ->numeric()
                    ->minValue(1)
                    ->hidden()
                    ->dehydrated(false),
                TextInput::make('seats_per_row')
                    ->label('Seats per row')
                    ->numeric()
                    ->minValue(1)
                    ->hidden()
                    ->dehydrated(false),

                // ── Payment gateway fee (admin only — a platform setting) ──────
                Section::make('Payment gateway fee')
                    ->description('Small processing fee charged on each transaction.')
                    ->icon('heroicon-o-credit-card')
                    ->visible(self::adminOnly())
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('gateway_fee_payer')
                            ->hiddenLabel()
                            ->options(['customer' => 'Customer pays', 'host' => 'Host pays'])
                            ->default('customer')
                            ->inline()
                            ->grouped()
                            ->columnSpanFull(),
                        Select::make('gateway_fee_type')
                            ->label('Fee type')
                            ->options(['none' => 'No fee', 'flat' => 'Flat ₹ amount', 'percent' => 'Percentage of subtotal'])
                            ->default('none')
                            ->native(false)
                            ->live(),
                        TextInput::make('gateway_fee_value')
                            ->label(fn (Get $get): string => $get('gateway_fee_type') === 'percent' ? 'Percentage' : 'Amount')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix(fn (Get $get): ?string => $get('gateway_fee_type') === 'flat' ? '₹' : null)
                            ->suffix(fn (Get $get): ?string => $get('gateway_fee_type') === 'percent' ? '%' : null)
                            ->visible(fn (Get $get): bool => in_array($get('gateway_fee_type'), ['flat', 'percent'], true)),
                    ]),

                // ── Platform fee (admin only) ──────────────────────────────────
                Section::make('Platform fee')
                    ->description('The platform’s share of each booking.')
                    ->icon('heroicon-o-building-office-2')
                    ->visible(self::adminOnly())
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('platform_fee_payer')
                            ->hiddenLabel()
                            ->options(['customer' => 'Customer pays', 'host' => 'Host pays'])
                            ->default('customer')
                            ->inline()
                            ->grouped()
                            ->columnSpanFull(),
                        Select::make('platform_fee_type')
                            ->label('Fee type')
                            ->options(['none' => 'No fee', 'flat' => 'Flat ₹ amount', 'percent' => 'Percentage of subtotal'])
                            ->default('none')
                            ->native(false)
                            ->live(),
                        TextInput::make('platform_fee_value')
                            ->label(fn (Get $get): string => $get('platform_fee_type') === 'percent' ? 'Percentage' : 'Amount')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix(fn (Get $get): ?string => $get('platform_fee_type') === 'flat' ? '₹' : null)
                            ->suffix(fn (Get $get): ?string => $get('platform_fee_type') === 'percent' ? '%' : null)
                            ->visible(fn (Get $get): bool => in_array($get('platform_fee_type'), ['flat', 'percent'], true)),
                    ]),
            ]);
    }

    /**
     * A dedicated "Coupons" wizard step — its own top-level section so it's never
     * buried inside Tickets. The relationship repeater saves the codes right after
     * the event is created (works on both create and edit).
     */
    private static function couponsStep(): Step
    {
        return Step::make('Coupons')
            ->description('Discounts & offers')
            ->icon('heroicon-o-ticket')
            ->schema([
                // The bespoke "Coupon Studio" — a premium 6-step coupon builder (Code ·
                // Discount · Eligibility · Limits · Restrictions · Review). Its state is
                // reconciled into the event's `coupons` on save (CreateEvent::afterCreate /
                // EditEvent::afterSave), preserving each code's redeemed `uses` count.
                ViewField::make('couponStudio')
                    ->view('filament.forms.coupon-studio')
                    ->dehydrated()
                    ->default(fn (): array => self::couponStudioDefault())
                    ->columnSpanFull(),
            ]);
    }

    /** The empty state the Coupon Studio starts from. */
    public static function couponStudioDefault(): array
    {
        return ['coupons' => []];
    }

    /**
     * Map one Coupon Studio card to a Coupon attribute row (studio-owned columns only,
     * so `uses` survives an edit), or null when the code is blank.
     *
     * @return array<string, mixed>|null
     */
    public static function couponRowAttributes(mixed $c): ?array
    {
        if (! is_array($c)) {
            return null;
        }
        $code = strtoupper(trim((string) ($c['code'] ?? '')));
        if ($code === '') {
            return null;
        }

        $type = ($c['type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
        $cap  = ($c['maxCap'] ?? null);
        $per  = trim((string) ($c['perCustomer'] ?? ''));
        $exp  = trim((string) ($c['expiresAt'] ?? ''));

        return [
            'code'               => $code,
            'type'               => $type,
            'discount'           => max(0, (float) ($c['discount'] ?? 0)),
            'max_discount'       => ($type === 'percent' && $cap !== null && $cap !== '' && (float) $cap > 0) ? (float) $cap : null,
            'min_order'          => max(0, (float) ($c['minOrder'] ?? 0)),
            'per_customer_limit' => $per !== '' ? max(1, (int) $per) : null,
            'expires_at'         => $exp !== '' ? $exp : null,
            'active'             => (bool) ($c['active'] ?? true),
            'multi_event'        => (bool) ($c['multiEvent'] ?? false),
            'eligibility'        => ($c['eligibility'] ?? 'all') === 'phones' ? 'phones' : 'all',
            'phone_numbers'      => is_array($c['phones'] ?? null) ? array_values(array_filter(array_map('trim', $c['phones']))) : null,
            'restrict_dates'     => (bool) ($c['restrictDates'] ?? false),
            'valid_dates'        => is_array($c['dates'] ?? null) ? array_values($c['dates']) : null,
            'restrict_times'     => (bool) ($c['restrictTimes'] ?? false),
            'valid_times'        => is_array($c['times'] ?? null) ? array_values($c['times']) : null,
        ];
    }

    /**
     * Clean Coupon attribute rows for the studio's cards (blank codes dropped).
     *
     * @return list<array<string, mixed>>
     */
    public static function couponRows(mixed $studio): array
    {
        $coupons = is_array($studio) ? ($studio['coupons'] ?? []) : [];
        if (! is_array($coupons)) {
            return [];
        }

        $rows = [];
        foreach ($coupons as $c) {
            $attrs = self::couponRowAttributes($c);
            if ($attrs !== null) {
                $rows[] = $attrs;
            }
        }

        return $rows;
    }

    /**
     * Reverse: build the Coupon Studio's state from an event's existing coupons so the
     * edit page opens with them loaded. Each card carries its `id` for save-time matching.
     *
     * @return array<string, mixed>
     */
    public static function couponStudioFromEvent(\App\Models\Event $event): array
    {
        $coupons = $event->coupons()
            ->orderBy('id')
            ->get()
            ->map(fn (\App\Models\Coupon $c): array => [
                'id'            => (int) $c->id,
                'active'        => (bool) $c->active,
                'code'          => (string) $c->code,
                'type'          => $c->type === 'percent' ? 'percent' : 'fixed',
                'discount'      => (float) $c->discount,
                'maxCap'        => $c->max_discount !== null ? (float) $c->max_discount : null,
                'minOrder'      => (float) ($c->min_order ?? 0),
                'eligibility'   => $c->eligibility === 'phones' ? 'phones' : 'all',
                'phones'        => is_array($c->phone_numbers) ? array_values($c->phone_numbers) : [],
                'perCustomer'   => $c->per_customer_limit !== null ? (string) $c->per_customer_limit : '',
                'expiresAt'     => $c->expires_at !== null ? $c->expires_at->format('Y-m-d') : '',
                'multiEvent'    => (bool) $c->multi_event,
                'restrictDates' => (bool) $c->restrict_dates,
                'dates'         => is_array($c->valid_dates) ? array_values($c->valid_dates) : [],
                'restrictTimes' => (bool) $c->restrict_times,
                'times'         => is_array($c->valid_times) ? array_values($c->valid_times) : [],
            ])
            ->values()
            ->all();

        return ['coupons' => $coupons];
    }

    /** The empty state the Ticket Studio starts from on a fresh event. */
    public static function studioDefault(): array
    {
        return ['mode' => 'unified', 'seating' => false, 'slotCount' => 1, 'tickets' => [], 'phases' => []];
    }

    /**
     * The cleaned list of release-phase definitions ([{name}]) authored in the
     * studio, ready to store on events.release_phases. Blank names are dropped.
     *
     * @return list<array{name: string}>
     */
    public static function studioReleasePhases(mixed $studio): array
    {
        $phases = is_array($studio) ? ($studio['phases'] ?? []) : [];
        if (! is_array($phases)) {
            return [];
        }

        $out = [];
        foreach ($phases as $p) {
            $name = trim((string) (is_array($p) ? ($p['name'] ?? '') : ''));
            $out[] = ['name' => $name !== '' ? $name : ('Phase ' . (count($out) + 1))];
        }

        return $out;
    }

    /**
     * Turn the Ticket Studio's state into clean TicketType attribute rows, ready to
     * persist (see CreateEvent::afterCreate). Blank-named cards are dropped; capacity
     * -1/blank means unlimited (null); a "free" card is priced at 0.
     *
     * @return list<array<string, mixed>>
     */
    public static function studioTicketRows(mixed $studio): array
    {
        $tickets = is_array($studio) ? ($studio['tickets'] ?? []) : [];
        if (! is_array($tickets)) {
            return [];
        }

        $rows = [];
        $sort = 0;

        foreach ($tickets as $t) {
            $attrs = self::studioRowAttributes($t, $sort);
            if ($attrs === null) {
                continue;
            }
            $rows[] = $attrs;
            $sort++;
        }

        return $rows;
    }

    /**
     * Map one Ticket Studio card to a TicketType attribute row (the fields the studio
     * owns), or null when the card is blank. Shared by the create path (bulk insert)
     * and the edit reconciliation (per-row update/create) so both price tiers the same.
     *
     * NB: only studio-managed columns are returned — `sold`, `pricing_phases`,
     * `sales_start/end`, `event_slot_id` etc. are deliberately absent so an update
     * with these attrs never clobbers inventory or advanced pricing on an edit.
     *
     * @return array<string, mixed>|null
     */
    public static function studioRowAttributes(mixed $t, int $sort): ?array
    {
        if (! is_array($t)) {
            return null;
        }
        $name = trim((string) ($t['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $free  = (bool) ($t['free'] ?? false);
        $seats = $t['seats'] ?? -1;
        $cap   = ($seats === null || $seats === '' || (int) $seats < 0) ? null : (int) $seats;
        $bulk  = ! $free && (bool) ($t['bulk'] ?? false);

        return [
            'name'          => $name,
            'description'   => trim((string) ($t['description'] ?? '')) ?: null,
            'kind'          => 'standard',
            'price'         => $free ? 0 : max(0, (float) ($t['price'] ?? 0)),
            'capacity'      => $cap,
            'visible'       => (bool) ($t['visible'] ?? true),
            'release_phase' => max(0, (int) ($t['phase'] ?? 0)),
            'bulk_booking'  => $bulk,
            'min_per_order' => $bulk ? max(1, (int) ($t['minPer'] ?? 1)) : null,
            'max_per_order' => $bulk && ! empty($t['maxPer']) ? max(1, (int) $t['maxPer']) : null,
            'sort'          => $sort,
        ];
    }

    /**
     * The event's overall capacity derived from the studio's tier seats: the sum of the
     * capped tiers, or a high cap when any tier is unlimited (or none are capped) so the
     * per-tier limits do the gating. Shared by create and edit.
     */
    public static function studioCapacity(mixed $studio): int
    {
        $sum = 0;
        $hasUnlimited = false;

        foreach ((is_array($studio) ? ($studio['tickets'] ?? []) : []) as $t) {
            if (! is_array($t) || trim((string) ($t['name'] ?? '')) === '') {
                continue;
            }
            $seats = $t['seats'] ?? -1;
            if ($seats === null || $seats === '' || (int) $seats < 0) {
                $hasUnlimited = true;
            } else {
                $sum += max(0, (int) $seats);
            }
        }

        return ($hasUnlimited || $sum <= 0) ? 100000 : $sum;
    }

    /**
     * Reverse of {@see studioTicketRows}: build the Ticket Studio's state from an
     * existing event so the edit page opens with the real tiers, sessions count,
     * seating mode and release phases already loaded. Each ticket carries its `id`
     * so the save-time reconciliation can update the right row in place.
     *
     * @return array<string, mixed>
     */
    public static function studioStateFromEvent(\App\Models\Event $event): array
    {
        $tickets = $event->ticketTypes()
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(function (\App\Models\TicketType $t): array {
                $price = (float) $t->price;

                return [
                    'id'          => (int) $t->id,
                    'name'        => (string) $t->name,
                    'description' => (string) ($t->description ?? ''),
                    'price'       => $price,
                    'free'        => $price <= 0.0,
                    'seats'       => $t->capacity === null ? -1 : (int) $t->capacity,
                    'visible'     => (bool) $t->visible,
                    'bulk'        => (bool) $t->bulk_booking,
                    'minPer'      => (int) ($t->min_per_order ?? 1),
                    'maxPer'      => $t->max_per_order !== null ? (int) $t->max_per_order : null,
                    'phase'       => (int) $t->release_phase,
                ];
            })
            ->values()
            ->all();

        $phases = is_array($event->release_phases)
            ? array_values(array_map(fn ($p): array => ['name' => (string) (is_array($p) ? ($p['name'] ?? '') : '')], $event->release_phases))
            : [];

        return [
            'mode'      => $event->tickets_per_slot ? 'per_slot' : 'unified',
            'seating'   => (bool) $event->seat_selection,
            'slotCount' => max(1, $event->slots()->count()),
            'tickets'   => $tickets,
            'phases'    => $phases,
        ];
    }

    /** Card label for a session row — the host's label, else "d M · g:i A", else "Session". */
    private static function slotItemLabel(array $state): string
    {
        $label = trim((string) ($state['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $starts = trim((string) ($state['starts_at'] ?? ''));
        if ($starts !== '') {
            try {
                return \Illuminate\Support\Carbon::parse($starts)->format('d M · g:i A');
            } catch (\Throwable) {
                // fall through
            }
        }

        return 'Session';
    }

    /** Card label for a ticket row — name + price/Free, so collapsed cards read well. */
    private static function ticketItemLabel(array $state): string
    {
        $name  = trim((string) ($state['name'] ?? '')) ?: 'New ticket';
        $price = (float) ($state['price'] ?? 0);
        $tag   = ($price <= 0 || ! empty($state['free'])) ? 'Free' : '₹'.number_format($price);

        return $name.' · '.$tag;
    }

    private static function publishStep(): Step
    {
        return Step::make('Publish')
            ->description('Media & visibility')
            ->icon('heroicon-o-rocket-launch')
            ->schema([
                // Live "how your card looks on the app" preview. Reads the details the
                // host already entered on earlier steps plus the poster set right here.
                // image_urls is ->live() below so pasting a poster URL updates it at once.
                Placeholder::make('card_preview')
                    ->label('Preview')
                    ->content(fn (Get $get): HtmlString => self::previewCard($get))
                    ->columnSpanFull(),
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
                    ->live()
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
                // Publish controls (visibility · status · which app rails) are a
                // platform/editorial decision, so they live in /control only. A
                // partner fills in the event's content; Haraan staff review it and
                // set these before it goes live. Hidden AND ->dehydrated(false) for
                // partners so a partner's own edit to a live event can never rewrite
                // them (edits stay live — see CreateEvent for the pending gate).
                Select::make('visibility')
                    ->options([
                        'PUBLIC'  => 'Public — anyone can find it',
                        'PRIVATE' => 'Private — access code only',
                    ])
                    ->required()
                    ->native(false)
                    ->live()
                    ->default('PUBLIC')
                    ->visible(self::adminOnly())
                    ->dehydrated(self::adminOnly()),
                TextInput::make('access_code')
                    ->label('Access code')
                    ->visible(fn (Get $get): bool => self::adminOnly() && $get('visibility') === 'PRIVATE')
                    ->dehydrated(self::adminOnly())
                    ->helperText('Attendees enter this to unlock a private event.'),
                Select::make('status')
                    ->options([
                        'pending'   => 'Pending review — submitted by host',
                        'draft'     => 'Draft — not visible yet',
                        'published' => 'Published — live now',
                    ])
                    ->required()
                    ->native(false)
                    ->default('draft')
                    ->visible(self::adminOnly())
                    ->dehydrated(self::adminOnly()),
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
                    ->visible(self::adminOnly())
                    ->dehydrated(self::adminOnly())
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
                // Partner-only: explain the review gate, since the partner no
                // longer sees a "publish" control (that's staff-only, above).
                Placeholder::make('review_note')
                    ->hiddenLabel()
                    ->visible(fn (): bool => ! self::adminOnly())
                    ->content(new HtmlString(
                        '<div style="display:flex;gap:10px;padding:12px 14px;border-radius:12px;'
                        . 'background:#eef4ff;border:1px solid #d3e2ff;color:#1e3a8a;font-size:.85rem;line-height:1.45;">'
                        . '<span style="font-size:1.1rem;line-height:1;">🚀</span>'
                        . '<span><strong>Submitted for review.</strong> Haraan checks new events before '
                        . 'they go live — once approved it appears in the app automatically. You can keep '
                        . 'editing it here anytime; your changes stay live after approval.</span></div>'
                    ))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * A live "event card" preview for the Publish step: renders the details the
     * host has entered the way the app's Events list shows them (poster, title,
     * category chip, city · date line, price). Purely presentational — reads the
     * current form state via {@see Get} and never mutates it. Falls back to
     * neutral placeholders for anything not yet filled.
     */
    private static function previewCard(Get $get): HtmlString
    {
        $title    = trim((string) $get('title'));
        $category = trim((string) $get('category'));
        $city     = trim((string) $get('city'));
        $venue    = trim((string) $get('venue'));
        $date     = trim((string) $get('date'));
        $price    = $get('price');

        // Poster: first pasted URL wins for the preview (uploaded files aren't a
        // resolvable public URL until saved, so we can't reliably show those here).
        $urls = self::cleanStrings($get('image_urls') ?? []);
        $poster = $urls[0] ?? null;

        $categoryLabel = $category !== '' ? (self::CATEGORIES[$category] ?? $category) : null;

        // "12 Jul 2026 · Hyderabad" when we have the pieces.
        $when = null;
        if ($date !== '') {
            try {
                $when = \Illuminate\Support\Carbon::parse($date)->format('d M Y');
            } catch (\Throwable) {
                $when = $date;
            }
        }
        $whereBits = array_values(array_filter([$when, $city !== '' ? $city : ($venue !== '' ? $venue : null)]));
        $whenWhere = $whereBits === [] ? 'Date · City' : implode(' · ', $whereBits);

        $priceLabel = ($price === null || $price === '' || (float) $price <= 0)
            ? 'Free'
            : '₹' . number_format((float) $price);

        $posterHtml = $poster !== null
            ? '<img src="' . e($poster) . '" alt="" style="width:76px;height:102px;object-fit:cover;border-radius:12px;flex:none;background:#eef1f6" onerror="this.style.display=\'none\'">'
            : '<div style="width:76px;height:102px;border-radius:12px;flex:none;background:linear-gradient(135deg,#e8ecf3,#d7deea);display:flex;align-items:center;justify-content:center;color:#9aa6b8;font-size:11px">Poster</div>';

        $chip = $categoryLabel !== null
            ? '<span style="display:inline-block;font-size:11px;font-weight:600;color:#1d4ed8;background:#e6efff;border-radius:999px;padding:2px 8px;margin-top:6px">' . e($categoryLabel) . '</span>'
            : '';

        $html = '<div style="display:flex;gap:14px;align-items:stretch;max-width:420px;padding:12px;border:1px solid #e6e9f0;border-radius:16px;background:#fff;box-shadow:0 8px 24px -16px rgba(11,18,32,.4)">'
            . $posterHtml
            . '<div style="display:flex;flex-direction:column;min-width:0;flex:1">'
            . '<div style="font-size:15px;font-weight:700;color:#0b1220;line-height:1.25;overflow:hidden;text-overflow:ellipsis">' . e($title !== '' ? $title : 'Your event title') . '</div>'
            . '<div style="font-size:12.5px;color:#6b7280;margin-top:4px">' . e($whenWhere) . '</div>'
            . $chip
            . '<div style="flex:1"></div>'
            . '<div style="font-size:14px;font-weight:700;color:#1d4ed8;margin-top:8px">' . e($priceLabel) . '</div>'
            . '</div></div>'
            . '<div style="font-size:11.5px;color:#9aa6b8;margin-top:6px">This is roughly how your event appears in the app’s Events list.</div>';

        return new HtmlString($html);
    }

    /**
     * The gallery upload + paste-URL fields, shared by the wizard's Extras step
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
