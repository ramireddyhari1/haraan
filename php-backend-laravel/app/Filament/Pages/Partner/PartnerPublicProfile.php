<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\HostProfile;
use App\Support\MediaUrl;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

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

    protected static ?int $navigationSort = 9;

    public ?array $data = [];

    public ?int $profileId = null;

    /** View/follower analytics for the insights panel; empty until a profile exists. */
    public array $insights = [];

    /** Any partner owner (event organiser or venue owner), in the partner console only. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'partner'
            && $user !== null
            && ! $user->isDeskStaff();
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
                            ->live(onBlur: true)
                            ->maxLength(120),
                        TextInput::make('slug')
                            ->label('Page address')
                            ->required()
                            ->live(onBlur: true)
                            ->maxLength(80)
                            ->rule('alpha_dash')
                            ->prefix(rtrim(config('app.url'), '/') . '/host/')
                            ->rule(Rule::unique('host_profiles', 'slug')->ignore($this->profileId))
                            ->helperText('Lowercase letters, numbers and dashes.'),
                        TextInput::make('tagline')
                            ->label('Tagline')
                            ->live(onBlur: true)
                            ->maxLength(160)
                            ->placeholder('e.g. Live music nights across Hyderabad'),
                        TextInput::make('city')
                            ->label('City')
                            ->live(onBlur: true)
                            ->maxLength(80),
                    ])
                    ->columns(2),

                Section::make('Branding')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->live()
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('host-profiles/logos'),
                        FileUpload::make('cover_path')
                            ->label('Cover image')
                            ->live()
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
                            ->live(onBlur: true)
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
                            ->live()
                            ->helperText('Goes live at your page address once on — needs a public name and an about.'),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Everything the preview card at the top of the page needs — a live mirror of
     * what attendees will see at /host/{slug}.
     *
     * It reads the *form* state, not the saved row, so the card moves as the
     * partner types (the identity fields are live-on-blur, the uploads live). The
     * publish rules here are HostProfile::isLive()'s, restated on unsaved state.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $state = $this->data ?? [];
        $user = auth()->user();

        $name = trim((string) ($state['display_name'] ?? '')) ?: ($user->name ?: 'Your brand');
        $about = trim((string) ($state['about'] ?? ''));
        $isPublic = (bool) ($state['is_public'] ?? false);
        $slug = Str::slug((string) ($state['slug'] ?? '')) ?: (Str::slug($user->name) . '-' . $user->id);

        // Same three conditions as HostProfile::isLive(), so the pill never
        // promises a page the public route would 404 on.
        $missing = [];
        if (! $isPublic) {
            $missing[] = 'turn on “Make my page public”';
        }
        if ($name === '') {
            $missing[] = 'add a public name';
        }
        if ($about === '') {
            $missing[] = 'write an about';
        }

        $socials = collect([
            ['label' => 'Instagram', 'url' => $state['socials']['instagram'] ?? null],
            ['label' => 'X', 'url' => $state['socials']['x'] ?? null],
            ['label' => 'YouTube', 'url' => $state['socials']['youtube'] ?? null],
            ['label' => 'Facebook', 'url' => $state['socials']['facebook'] ?? null],
            ['label' => 'Website', 'url' => $state['website'] ?? null],
        ])->filter(fn (array $s): bool => filled($s['url']))->values()->all();

        $profile = $this->profileId !== null ? HostProfile::find($this->profileId) : null;

        return [
            'name' => $name,
            'initial' => strtoupper(mb_substr($name, 0, 1)) ?: 'H',
            'tagline' => $state['tagline'] ?? null,
            'city' => $state['city'] ?? null,
            'about' => $about,
            'logo' => $this->previewImageUrl($state['logo_path'] ?? null),
            'cover' => $this->previewImageUrl($state['cover_path'] ?? null),
            'socials' => $socials,
            'slug' => $slug,
            'url' => url('/host/' . $slug),
            'isLive' => $missing === [],
            'missing' => $missing,
            'isVenue' => $user->partnerLane() === 'gamehub',
            'verified' => (bool) $profile?->isVerified(),
            'followers' => (int) ($this->insights['followers']['total'] ?? 0),
        ];
    }

    /**
     * A displayable URL for an upload field, whether it holds a saved path or a
     * file the partner just dropped in (still in Livewire's temp store).
     */
    private function previewImageUrl(mixed $state): ?string
    {
        if (is_array($state)) {
            $state = Arr::first($state);
        }

        if ($state instanceof TemporaryUploadedFile) {
            try {
                return $state->temporaryUrl();
            } catch (Throwable) {
                return null; // non-image or a disk that can't sign temp URLs
            }
        }

        return filled($state) ? MediaUrl::resolve((string) $state) : null;
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
