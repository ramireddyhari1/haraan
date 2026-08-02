<?php

namespace App\Filament\Resources\Partners;

use App\Filament\Resources\Partners\Pages\CreatePartner;
use App\Filament\Resources\Partners\Pages\EditPartner;
use App\Filament\Resources\Partners\Pages\ListPartners;
use App\Filament\Resources\Partners\Pages\ViewPartner;
use App\Filament\Resources\Partners\RelationManagers\EventsRelationManager;
use App\Filament\Resources\Partners\RelationManagers\VenuesRelationManager;
use App\Filament\Support\AvatarColumn;
use App\Models\User;
use App\Support\ContactPrefill;
use App\Support\PartnerAccountResolver;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartnerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'partner';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * This resource is entirely super-admin-only. Gate every ability on isSuperAdmin so
     * edit/create/delete don't fall through to the granular UserPolicy (Shield's
     * Update:User etc.), which a legacy-role super-admin doesn't hold → 403.
     */
    private static function allowed(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canAccess(): bool
    {
        return static::allowed();
    }

    public static function canViewAny(): bool
    {
        return static::allowed();
    }

    public static function canCreate(): bool
    {
        return static::allowed();
    }

    public static function canView(mixed $record): bool
    {
        return static::allowed();
    }

    public static function canEdit(mixed $record): bool
    {
        return static::allowed();
    }

    public static function canDelete(mixed $record): bool
    {
        return static::allowed();
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed();
    }

    /** Partners are users with the PARTNER role. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'PARTNER');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('avatar')
                ->label('Profile picture')
                ->avatar()
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(5120)
                ->imageEditor()
                ->disk('public')
                ->directory('avatars/partners')
                ->helperText('Optional. Square photo works best.')
                ->columnSpanFull(),
            TextInput::make('name')->required(),
            // An email that already exists is usually NOT a mistake here — venue owners
            // and event hosts are typically Haraan members long before they list anything.
            // Only block what is genuinely unresolvable (an existing partner, an internal
            // staff login, or re-pointing an edit at somebody else's row); everything else
            // upgrades that member in place. See PartnerAccountResolver + CreatePartner.
            TextInput::make('email')
                ->email()
                ->required()
                ->live(onBlur: true)
                ->rule(static fn (?User $record): \Closure => static function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                    $existing = PartnerAccountResolver::findByEmail(is_string($value) ? $value : null);

                    if (! $existing || ($record && $existing->is($record))) {
                        return;
                    }

                    if ($record) {
                        // Editing: there is nothing to merge into, so this is a real clash.
                        $fail('That email already belongs to another Haraan account.');

                        return;
                    }

                    if ($reason = PartnerAccountResolver::blockReason($existing)) {
                        $fail($reason);
                    }
                })
                ->helperText(static function (?string $state, string $operation): ?string {
                    if ($operation !== 'create') {
                        return null;
                    }

                    $existing = PartnerAccountResolver::findByEmail($state);

                    if (! $existing || PartnerAccountResolver::blockReason($existing)) {
                        return null;
                    }

                    return 'Already a Haraan member (' . PartnerAccountResolver::describe($existing)
                        . '). Saving upgrades that account to a partner — their bookings and history carry over, '
                        . 'and from then on they sign in at /partner/login instead of /login.';
                }),
            TextInput::make('phone'),
            TextInput::make('password')
                ->password()
                ->revealable()
                // Only a brand-new account needs a password typed here. Upgrading an
                // existing member must default to keeping the password they already
                // chose — silently replacing it would lock them out of their own account.
                ->required(static fn (string $operation, Get $get): bool => $operation === 'create'
                    && ! PartnerAccountResolver::isUpgrade((string) $get('email')))
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(static function (string $operation, Get $get): string {
                    if ($operation === 'create' && PartnerAccountResolver::isUpgrade((string) $get('email'))) {
                        return 'Leave blank to keep the password this member already signs in with. '
                            . 'Typing one here replaces it.';
                    }

                    return 'Password for the partner to sign in. When editing, leave blank to keep the current one.';
                }),
            Select::make('partner_type')
                ->options(['venue' => 'Venue owner', 'event' => 'Event organiser'])
                ->native(false),
            Select::make('status')
                ->options(['active' => 'Active', 'suspended' => 'Suspended'])
                ->default('active')
                ->native(false),
        ]);
    }

    /**
     * The identity card on the overview page. Sits below the KPI header widgets
     * (revenue / listings) and the revenue chart, both rendered by ViewPartner.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    ImageEntry::make('avatar')
                        ->label('')
                        ->circular()
                        ->disk('public')
                        ->defaultImageUrl(fn (User $record): string => 'https://ui-avatars.com/api/?background=EEF2FF&color=4F46E5&name='
                            . urlencode((string) ($record->name ?: 'Partner'))),
                    TextEntry::make('name')
                        ->label('Name')
                        ->weight('bold')
                        ->size(TextSize::Large),
                    TextEntry::make('partner_type')
                        ->label('Type')
                        ->badge()
                        ->color('info')
                        ->formatStateUsing(fn (?string $state): string => match (strtolower((string) $state)) {
                            'venue' => 'Venue owner',
                            'event' => 'Event organiser',
                            default => '—',
                        }),
                    TextEntry::make('email')
                        ->label('Email')
                        ->icon('heroicon-m-envelope')
                        ->copyable()
                        ->placeholder('—'),
                    TextEntry::make('phone')
                        ->label('Phone')
                        ->icon('heroicon-m-phone')
                        ->placeholder('—'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                        ->color(fn (?string $state): string => strtolower((string) $state) === 'active' ? 'success' : 'danger'),
                    TextEntry::make('created_at')
                        ->label('Partner since')
                        ->dateTime('d M Y')
                        ->icon('heroicon-m-calendar')
                        ->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                AvatarColumn::make(
                    'avatar',
                    nameFor: fn (User $r): string => (string) ($r->name ?: 'Partner'),
                    avatarFor: fn (User $r): ?string => $r->avatar,
                ),
                TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn (User $r): ?string => ContactPrefill::isRealEmail($r->email)
                        ? $r->email
                        : (trim((string) $r->phone) ?: null))
                    ->searchable(),
                TextColumn::make('phone')->placeholder('—')->toggleable(),
                TextColumn::make('partner_type')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('partner_type')
                    ->options(['venue' => 'Venue owner', 'event' => 'Event organiser']),
                SelectFilter::make('status')
                    ->options(['active' => 'Active', 'suspended' => 'Suspended']),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            EventsRelationManager::class,
            VenuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartners::route('/'),
            'create' => CreatePartner::route('/create'),
            'view' => ViewPartner::route('/{record}'),
            'edit' => EditPartner::route('/{record}/edit'),
        ];
    }
}
