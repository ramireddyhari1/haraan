<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\HostProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Where an organiser edits their public brand page (the one attendees see at
 * /host/{slug}). One profile per owner; event organisers only. The page stays
 * hidden until they flip it public and have filled a name + about.
 */
class PartnerPublicProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.partner.public-profile';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $title = 'Public profile';

    protected static ?string $navigationLabel = 'Public profile';

    protected static ?int $navigationSort = 7;

    public ?array $data = [];

    public ?int $profileId = null;

    /** View/follower analytics for the insights panel; empty until a profile exists. */
    public array $insights = [];

    /** Owners of an event-organiser account, in the partner console only. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'partner'
            && $user !== null
            && ! $user->isDeskStaff()
            && $user->partner_type === 'event';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $user = auth()->user();
        $profile = $user->hostProfile;

        $this->profileId = $profile?->id;

        if ($profile !== null) {
            $this->insights = [
                'views' => $profile->viewStats(),
                'followers' => $profile->followerGrowth(),
                'rating' => $profile->ratingSummary(),
                'live' => $profile->isLive(),
            ];
        }

        $this->form->fill([
            'display_name' => $profile->display_name ?? $user->name,
            'slug' => $profile->slug ?? Str::slug($user->name) . '-' . $user->id,
            'tagline' => $profile->tagline ?? null,
            'city' => $profile->city ?? null,
            'about' => $profile->about ?? null,
            'logo_path' => $profile->logo_path ?? null,
            'cover_path' => $profile->cover_path ?? null,
            'website' => $profile->website ?? null,
            'socials' => $profile->socials ?? [],
            'is_public' => (bool) ($profile->is_public ?? false),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->schema([
                        TextInput::make('display_name')
                            ->label('Public name')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('slug')
                            ->label('Page address')
                            ->required()
                            ->maxLength(80)
                            ->rule('alpha_dash')
                            ->prefix(rtrim(config('app.url'), '/') . '/host/')
                            ->rule(Rule::unique('host_profiles', 'slug')->ignore($this->profileId))
                            ->helperText('Lowercase letters, numbers and dashes.'),
                        TextInput::make('tagline')
                            ->label('Tagline')
                            ->maxLength(160)
                            ->placeholder('e.g. Live music nights across Hyderabad'),
                        TextInput::make('city')
                            ->label('City')
                            ->maxLength(80),
                    ])
                    ->columns(2),

                Section::make('Branding')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('host-profiles/logos'),
                        FileUpload::make('cover_path')
                            ->label('Cover image')
                            ->image()
                            ->disk('public')
                            ->directory('host-profiles/covers')
                            ->imageEditor()
                            ->helperText('Wide banner across the top of your page.'),
                    ])
                    ->columns(2),

                Section::make('About')
                    ->schema([
                        Textarea::make('about')
                            ->label('About')
                            ->rows(5)
                            ->maxLength(2000)
                            ->helperText('Tell attendees who you are. Required to go public.'),
                    ]),

                Section::make('Links')
                    ->schema([
                        TextInput::make('website')->label('Website')->url()->maxLength(200),
                        TextInput::make('socials.instagram')->label('Instagram')->maxLength(200),
                        TextInput::make('socials.x')->label('X (Twitter)')->maxLength(200),
                        TextInput::make('socials.youtube')->label('YouTube')->maxLength(200),
                        TextInput::make('socials.facebook')->label('Facebook')->maxLength(200),
                    ])
                    ->columns(2),

                Section::make('Visibility')
                    ->schema([
                        Toggle::make('is_public')
                            ->label('Make my page public')
                            ->helperText('Goes live at your page address once on — needs a public name and an about.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $user = auth()->user();

        $slug = Str::slug($state['slug'] ?? '') ?: (Str::slug($user->name) . '-' . $user->id);

        $socials = array_filter($state['socials'] ?? [], fn ($v): bool => filled($v));

        $profile = HostProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'slug' => $slug,
                'display_name' => $state['display_name'],
                'tagline' => $state['tagline'] ?? null,
                'city' => $state['city'] ?? null,
                'about' => $state['about'] ?? null,
                'logo_path' => $state['logo_path'] ?? null,
                'cover_path' => $state['cover_path'] ?? null,
                'website' => $state['website'] ?? null,
                'socials' => $socials ?: null,
                'is_public' => (bool) ($state['is_public'] ?? false),
            ],
        );

        $this->profileId = $profile->id;

        $message = $profile->isLive()
            ? 'Saved — your page is live.'
            : ($profile->is_public
                ? 'Saved. Add an about to go live.'
                : 'Saved as a draft.');

        Notification::make()->title($message)->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('Open public page')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => route('site.host', ['slug' => $this->data['slug'] ?? '']), shouldOpenInNewTab: true)
                ->visible(fn (): bool => filled($this->data['slug'] ?? null) && (bool) ($this->data['is_public'] ?? false)),
            Action::make('save')
                ->label('Save profile')
                ->icon('heroicon-m-check')
                ->action('save'),
        ];
    }
}
