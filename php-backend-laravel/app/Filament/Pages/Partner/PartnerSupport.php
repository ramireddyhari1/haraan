<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Services\PartnerSupportAI;
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

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.partner.support';

    /** The message being composed (human chat). */
    public string $body = '';

    /** The question being composed for the AI assistant. */
    public string $aiInput = '';

    /**
     * The AI assistant transcript, this session only.
     *
     * @var array<int, array{role:string, text:string, handoff?:bool}>
     */
    public array $aiMessages = [];

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

    /** Seed the AI assistant from a quick-action card so the partner starts fast. */
    public function prefill(string $topic): void
    {
        $starters = [
            'events'      => 'How do I create or edit an event?',
            'bookings'    => 'Where do I see and manage my bookings?',
            'payments'    => 'How do payments and refunds work on Haraan?',
            'settlements' => 'When and how do I get my settlement / payout?',
        ];

        $this->aiInput = $starters[$topic] ?? '';
        $this->dispatch('focus-ai');
    }

    /** Ask the Claude-backed assistant; the reply appears in the AI panel. */
    public function askAi(): void
    {
        $question = trim($this->aiInput);
        if ($question === '') {
            return;
        }

        $this->aiMessages[] = ['role' => 'user', 'text' => $question];
        $this->aiInput = '';

        $reply = app(PartnerSupportAI::class)->answer($question, $this->aiMessages);

        $this->aiMessages[] = [
            'role' => 'assistant',
            'text' => $reply['text'],
            'handoff' => (bool) ($reply['handoff'] ?? false),
        ];

        $this->dispatch('ai-answered');
    }

    /** A topic/popular-question chip: fill it in and ask immediately. */
    public function askQuick(string $question): void
    {
        $this->aiInput = $question;
        $this->askAi();
    }

    /** Move the last AI question into the human chat composer (escalation). */
    public function escalateToHuman(): void
    {
        // Carry the partner's most recent question over to the team chat.
        foreach (array_reverse($this->aiMessages) as $m) {
            if (($m['role'] ?? null) === 'user') {
                $this->body = (string) $m['text'];
                break;
            }
        }

        $this->dispatch('focus-composer');
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
