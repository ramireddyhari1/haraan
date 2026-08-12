<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AdminAction;
use App\Services\WhatsAppService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * WhatsApp console — backed by Meta's WhatsApp Cloud API (Twilio and, before it, the
 * self-hosted whatsapp-web.js bridge are both gone). Shows whether the Cloud API is
 * configured and lets a super-admin fire a test message to confirm the number + token
 * work end to end. The test input lives in the view (wire:model) so we don't depend on
 * the Filament action-form API.
 *
 * There is no SMS half any more: Meta does WhatsApp, not SMS.
 */
class WhatsAppConnection extends Page
{
    protected string $view = 'filament.pages.whatsapp-connection';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $title = 'WhatsApp (Meta)';

    protected static ?string $navigationLabel = 'WhatsApp (Meta)';

    public ?string $testNumber = null;

    public bool $configured = false;

    public bool $enabled = false;

    public ?string $from = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $this->configured = app(WhatsAppService::class)->isConfigured();
        $this->enabled = filter_var(config('services.whatsapp.enabled', false), FILTER_VALIDATE_BOOLEAN);
        // The phone number id, not a number — that's what the Cloud API sends from.
        $this->from = ((string) config('services.whatsapp.phone_number_id')) ?: null;
    }

    /** Send a one-off WhatsApp message to verify the Cloud API end to end. */
    public function sendTest(): void
    {
        $number = trim((string) $this->testNumber);
        if ($number === '') {
            Notification::make()->title('Enter a phone number first')->warning()->send();

            return;
        }

        $ok = app(WhatsAppService::class)->sendMessage(
            $number,
            'Haraan test ✅ — your Meta WhatsApp Cloud API integration is working.'
        );

        AdminAction::log('whatsapp.test', ['to' => $number, 'ok' => $ok]);

        $ok
            ? Notification::make()->title('Test message sent')
                ->body("Meta accepted the message to {$number}. Check that WhatsApp.")->success()->send()
            : Notification::make()->title('Test send failed')
                ->body('Meta rejected it. Check logs — a plain text message only works inside a 24h '
                    . 'window the recipient opened; outside that you need an approved template.')
                ->danger()->send();
    }

}
