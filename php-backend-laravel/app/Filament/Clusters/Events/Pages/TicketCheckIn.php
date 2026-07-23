<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Events\Pages;

use App\Filament\Clusters\Events\EventsCluster;
use App\Models\Event;
use App\Services\BookingService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Host gate check-in. The host opens their device camera in the browser and
 * scans an attendee's ticket QR (payload `haraan:ticket:<code>`), or types the
 * code, to mark arrivals. Resolution + check-in are host/admin-gated in
 * {@see BookingService}, so a host can only check in their own events.
 *
 * NB: browser camera access (getUserMedia) requires HTTPS or localhost. Over
 * plain HTTP the camera is blocked by the browser — the manual code entry still
 * works, and the camera lights up once the panel is served over TLS.
 */
class TicketCheckIn extends Page
{
    protected string $view = 'filament.clusters.events.pages.ticket-check-in';

    protected static ?string $cluster = EventsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $title = 'Ticket Check-in';

    protected static ?string $navigationLabel = 'Check-in';

    protected static ?int $navigationSort = 5;

    public string $manualCode = '';

    /** Recent scan results shown on the page: [{name, event, detail, ok}]. */
    public array $recent = [];

    /** Guards against the camera firing the same code many times a second. */
    public ?string $lastCode = null;

    /**
     * Optional event lock (?event=<id>). When set to one of the host's own events,
     * the scanner only checks in tickets for that event and rejects the rest — so a
     * gate person working one door can't accidentally admit another event's ticket.
     */
    #[Url]
    public ?int $event = null;

    /** The locked event's title, once validated as the host's own. */
    public ?string $lockedTitle = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->canManage('events') ?? false) && $user->hasPartnerPermission('checkin');
    }

    public function mount(): void
    {
        if ($this->event !== null) {
            $e = Event::query()->whereKey($this->event)->first();

            // Only honour a lock on the host's own event; otherwise drop it silently.
            if ($e !== null && $this->ownsEvent($e)) {
                $this->lockedTitle = (string) $e->title;
            } else {
                $this->event = null;
            }
        }
    }

    private function ownsEvent(Event $e): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->isSuperAdmin() || (int) $e->partner_id === (int) $user->effectivePartnerId());
    }

    /** Called from JS when the camera decodes a QR. */
    public function scan(string $payload): void
    {
        $code = $this->extractCode($payload);

        // Debounce repeated frames of the same ticket.
        if ($code === $this->lastCode) {
            return;
        }
        $this->lastCode = $code;

        $this->process($code);
    }

    /** Manual code entry (works even without camera / over HTTP). */
    public function submitManual(): void
    {
        $code = trim($this->manualCode);

        if ($code === '') {
            return;
        }

        $this->process($this->extractCode($code));
        $this->manualCode = '';
        $this->lastCode = null;
    }

    /** Pull the ticket code out of `haraan:ticket:<code>` or accept it raw. */
    private function extractCode(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match('/ticket[:\/]([A-Za-z0-9]{6,})/i', $raw, $m)) {
            return $m[1];
        }

        return $raw;
    }

    private function process(string $code): void
    {
        /** @var BookingService $service */
        $service = app(BookingService::class);
        $actor = auth()->user();

        try {
            // resolveByCode now tenant-scopes the ticket (own event/venue + any
            // per-staff assignment) and throws AccessDeniedHttpException otherwise,
            // caught below — so the scanner refuses tickets outside this user's scope.
            $booking = $service->resolveByCode($actor, $code);

            // Event lock: refuse tickets that belong to a different event.
            if ($this->event !== null && (int) $booking->event_id !== $this->event) {
                $wrong = $booking->event?->title ?? 'another event';
                $this->pushResult($booking->user?->name ?? 'Ticket', $wrong, "Not for “{$this->lockedTitle}”", false);
                Notification::make()
                    ->title('Wrong event')
                    ->body("This ticket is for “{$wrong}”, not “{$this->lockedTitle}”.")
                    ->warning()->send();

                return;
            }

            $before = (int) $booking->checked_in_count;
            $updated = $service->checkIn($actor, (string) $booking->id);
            $newlyIn = (int) $updated->checked_in_count - $before;

            $name = $updated->user?->name ?? ('Booking #' . $updated->id);
            $eventTitle = $updated->event?->title ?? 'Event';

            if ($newlyIn > 0) {
                $detail = "Checked in {$newlyIn} of {$updated->quantity}";
                $this->pushResult($name, $eventTitle, $detail, true);
                Notification::make()->title("✓ {$name} checked in")->body($detail)->success()->send();
            } else {
                $detail = "Already checked in ({$updated->checked_in_count}/{$updated->quantity})";
                $this->pushResult($name, $eventTitle, $detail, false);
                Notification::make()->title('Already checked in')->body("{$name} — {$eventTitle}")->warning()->send();
            }
        } catch (HttpException $e) {
            $msg = $e->getMessage() ?: 'Check-in failed';
            $this->pushResult('Unknown ticket', '', $msg, false);
            Notification::make()->title('Check-in failed')->body($msg)->danger()->send();
        }
    }

    private function pushResult(string $name, string $event, string $detail, bool $ok): void
    {
        array_unshift($this->recent, [
            'name' => $name,
            'event' => $event,
            'detail' => $detail,
            'ok' => $ok,
            'at' => now()->format('H:i:s'),
        ]);

        $this->recent = array_slice($this->recent, 0, 15);
    }
}
