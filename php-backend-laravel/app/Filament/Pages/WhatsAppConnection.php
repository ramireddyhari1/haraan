<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AdminAction;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * WhatsApp / OTP console — now backed by Twilio (the self-hosted whatsapp-web.js bridge and its
 * QR-scan flow are gone). Shows whether Twilio is configured and lets a super-admin fire a test
 * message to confirm the sender + credentials work end to end. The test input lives in the view
 * (wire:model) so we don't depend on the Filament action-form API.
 */
class WhatsAppConnection extends Page
{
    protected string $view = 'filament.pages.whatsapp-connection';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $title = 'WhatsApp (Twilio)';

    protected static ?string $navigationLabel = 'WhatsApp (Twilio)';

    public ?string $testNumber = null;

    public bool $configured = false;

    public bool $enabled = false;

    public ?string $from = null;

    public bool $smsConfigured = false;

    public bool $smsEnabled = false;

    public ?string $smsFrom = null;

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
        $this->from = ((string) config('services.whatsapp.from')) ?: null;

        $this->smsConfigured = app(SmsService::class)->isConfigured();
        $this->smsEnabled = filter_var(config('services.whatsapp.sms_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $this->smsFrom = ((string) config('services.whatsapp.sms_from')) ?: null;
    }

    /** Send a one-off WhatsApp message to the entered number to verify Twilio end to end. */
    public function sendTest(): void
    {
        $number = trim((string) $this->testNumber);
        if ($number === '') {
            Notification::make()->title('Enter a phone number first')->warning()->send();

            return;
        }

        $ok = app(WhatsAppService::class)->sendMessage(
            $number,
            'Haraan test ✅ — your Twilio WhatsApp integration is working.'
        );

        AdminAction::log('whatsapp.test', ['to' => $number, 'ok' => $ok]);

        $ok
            ? Notification::make()->title('Test message sent')
                ->body("Twilio accepted the message to {$number}. Check that WhatsApp.")->success()->send()
            : Notification::make()->title('Test send failed')
                ->body('Twilio rejected or could not deliver it. Check logs — usually the sender isn\'t a WhatsApp-approved number, or the recipient must opt in / needs an approved template.')
                ->danger()->send();
    }

    /** Send a one-off SMS to the entered number to verify the SMS fallback channel. */
    public function sendTestSms(): void
    {
        $number = trim((string) $this->testNumber);
        if ($number === '') {
            Notification::make()->title('Enter a phone number first')->warning()->send();

            return;
        }

        $ok = app(SmsService::class)->sendSms(
            $number,
            'Haraan test SMS — your Twilio SMS fallback is working. Reply STOP to opt out.'
        );

        AdminAction::log('sms.test', ['to' => $number, 'ok' => $ok]);

        $ok
            ? Notification::make()->title('Test SMS sent')
                ->body("Twilio accepted the SMS to {$number}.")->success()->send()
            : Notification::make()->title('Test SMS failed')
                ->body('Twilio rejected or could not deliver it. Check logs — SMS to Indian numbers needs a registered Sender ID + DLT-approved template.')
                ->danger()->send();
    }
}
