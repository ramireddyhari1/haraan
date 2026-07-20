<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Services\SupportChat;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * The partner's line to the Haraan team — the console twin of the in-app support
 * chat, riding the same support_threads/messages the admin Support resource
 * answers from. Polls like the app does, so a reply lands without a refresh.
 */
class PartnerSupport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $title = 'Support';

    protected static ?string $navigationLabel = 'Support';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.partner.support';

    /** The message being composed. */
    public string $body = '';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'partner';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        // Opening the page clears the partner's unread badge.
        app(SupportChat::class)->openForUser(auth()->user());
    }

    public function getThreadProperty(): SupportThread
    {
        return app(SupportChat::class)->threadFor(auth()->user());
    }

    /** @return Collection<int, SupportMessage> */
    public function getMessagesProperty(): Collection
    {
        return $this->thread->messages()->with('sender')->get();
    }

    public function send(): void
    {
        $body = trim($this->body);

        if ($body === '') {
            return;
        }

        app(SupportChat::class)->postUserMessage(auth()->user(), mb_substr($body, 0, 4000));

        $this->body = '';

        FilamentNotification::make()
            ->title('Message sent')
            ->body('The Haraan team will get back to you here.')
            ->success()
            ->send();
    }

    /** Clear the unread badge as replies stream in while the page is open. */
    public function refreshThread(): void
    {
        app(SupportChat::class)->openForUser(auth()->user());
    }
}
