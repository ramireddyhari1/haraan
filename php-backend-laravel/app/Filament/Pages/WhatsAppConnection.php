<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AdminAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp / OTP connection console. Talks to the self-hosted whatsapp-web.js bridge
 * (localhost:8090) so the team can see whether OTP sending is live and, when the session
 * drops, re-scan the QR straight from /control — no SSH. The page polls the bridge every
 * few seconds via wire:poll. Super-admins only.
 */
class WhatsAppConnection extends Page
{
    protected string $view = 'filament.pages.whatsapp-connection';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $title = 'WhatsApp / OTP';

    protected static ?string $navigationLabel = 'WhatsApp / OTP';

    /** Live state pulled from the bridge. */
    public bool $reachable = true;

    public bool $ready = false;

    public ?string $qr = null;

    public ?string $event = null;

    public ?string $at = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->refreshStatus();
    }

    /** Derive the bridge base URL from the configured send-message endpoint. */
    protected function bridgeBase(): string
    {
        $url = config('services.whatsapp.bridge_url', 'http://localhost:8090/api/send-message');

        return preg_replace('#/api/send-message/?$#', '', (string) $url) ?: 'http://localhost:8090';
    }

    /** Polled by wire:poll — refreshes health + QR from the bridge. */
    public function refreshStatus(): void
    {
        try {
            $res = Http::timeout(6)->get($this->bridgeBase() . '/status');

            if ($res->successful()) {
                $this->reachable = true;
                $this->ready = (bool) $res->json('ready');
                $this->qr = $res->json('qr');
                $this->event = $res->json('event');
                $this->at = $res->json('at');

                return;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        $this->reachable = false;
    }

    /** Logs the current WhatsApp session out so a fresh QR is generated. */
    public function forceRelink(): void
    {
        try {
            Http::timeout(15)->post($this->bridgeBase() . '/logout');
            AdminAction::log('whatsapp.relink', []);
            Notification::make()->title('Re-link requested')->body('A fresh QR will appear in a few seconds — scan it from WhatsApp ▸ Linked devices.')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Could not reach the WhatsApp bridge')->danger()->send();
        }

        $this->refreshStatus();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('relink')
                ->label('Force re-link')
                ->icon('heroicon-m-arrow-path')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Re-link WhatsApp')
                ->modalDescription('This logs the current WhatsApp session out and shows a fresh QR to scan. OTP sending pauses until you re-scan.')
                ->action('forceRelink'),
        ];
    }
}
