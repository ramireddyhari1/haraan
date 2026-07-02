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
                TextInput::make('venue')
                    ->label('Venue name')
                    ->required()
                    ->placeholder('e.g. Quake Arena')
                    ->columnSpanFull(),
                TextInput::make('location')
                    ->label('Area / address')
                    ->required()
                    ->placeholder('e.g. Kondapur, Hyderabad')
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
                            ->label('Photo')
                            ->image()
                            ->disk('public')
                            ->directory('events/lineup')
                            ->imageEditor()
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
                    ->label('Cover image(s)')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->disk('public')
                    ->directory('events')
                    ->imageEditor()
                    ->helperText('The first image is used as the poster.')
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
                        'DRAFT'     => 'Draft — not visible yet',
                        'PUBLISHED' => 'Published — live now',
                    ])
                    ->required()
                    ->native(false)
                    ->default('DRAFT'),
                Select::make('partner_id')
                    ->label('Host / organizer')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload()
                    ->default(fn () => auth()->id()),
            ]);
    }
}
