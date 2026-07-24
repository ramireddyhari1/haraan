<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;

/**
 * The partner's own account page (profile menu → Profile).
 *
 * Filament's stock profile page renders on the *simple* layout — a bare white
 * card on an empty screen, with no sidebar, no topbar, no way back. Partners
 * read that as "the console dropped me somewhere else". This subclass keeps the
 * page inside the console shell and leads with who they are: avatar, member ID,
 * lane and contact details as an at-a-glance card.
 *
 * Identity is deliberately NOT self-serve here. Name, email and phone are the
 * fields payouts, tickets and support tie back to, so they are admin-only — the
 * card shows them read-only and points at Support for a change. A partner may
 * still swap their own profile photo and password.
 */
class PartnerProfile extends EditProfile
{
    protected static ?string $title = 'Your profile';

    /** Render inside the panel shell (sidebar + topbar), not the bare simple layout. */
    public static function isSimple(): bool
    {
        return false;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Your profile';
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    /** The identity card first, then the edit form. */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.pages.partner.profile-header')
                    ->viewData(['user' => $this->getUser()]),
                $this->getFormContentComponent(),
                ...Arr::wrap($this->getMultiFactorAuthenticationContentComponent()),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile photo')
                    ->description('Only you can change this. It shows on your console and to the Haraan team.')
                    ->icon('heroicon-o-camera')
                    ->schema([
                        $this->getAvatarFormComponent(),
                    ]),

                Section::make('Password')
                    ->description('Leave blank to keep your current password.')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
    }

    protected function getAvatarFormComponent(): Component
    {
        return FileUpload::make('avatar')
            ->label('Profile photo')
            ->avatar()
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(5120)
            ->imageEditor()
            ->disk('public')
            ->directory('avatars/partners');
    }

    /**
     * Stock Filament also shows this whenever the email field differs from the
     * stored one — but email isn't in this form at all, so that check would read
     * null !== email and pin the field open forever. Here it guards the password
     * change and nothing else.
     */
    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->visible(fn (Get $get): bool => filled($get('password')));
    }
}
