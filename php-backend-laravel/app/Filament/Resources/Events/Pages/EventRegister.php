<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Booking;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\TicketType;
use App\Services\BookingNotifier;
use App\Services\BookingService;
use App\Services\RazorpayGateway;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Partner desk registration — "register an attendee and take payment" for a single
 * event, reached from the Analytics hero. The acting partner keys in who the ticket
 * is for (name / phone / optional email), picks a ticket tier + quantity (the amount
 * pre-fills from the tier price but stays editable), then either collects cash at the
 * desk or runs the payment through Razorpay checkout right here.
 *
 * The booking is attributed to the acting partner (user_id), exactly like the offline
 * venue walk-in path, while the attendee_* fields hold the customer's own details so
 * their ticket (QR + email/WhatsApp) still goes to them.
 */
class EventRegister extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Register attendee';

    protected string $view = 'filament.resources.events.pages.event-register';

    // ---- Form state (bound in the view) --------------------------------------
    public string $name = '';
    public string $phone = '';
    public string $email = '';
    // These are bound to <input>/<select> controls, which always post strings (and a number
    // field can post ""). Keep them untyped so Livewire never fails to cast into a strict
    // scalar and unset the property — that threw "Property [$quantity] not found" on change.
    public $ticketTypeId = null;
    public $eventSlotId = null;
    public $quantity = 1;
    public string $amount = '0';

    /**
     * Razorpay hand-off state. Null until "Pay online" reserves the order and opens
     * checkout; then holds the key/order details the view feeds to checkout.js.
     *
     * @var array{key:string,orderId:string,amount:int,currency:string}|null
     */
    public ?array $pay = null;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $tiers = $this->tiers();
        if ($tiers->isNotEmpty()) {
            $this->ticketTypeId = (int) $tiers->first()->id;
        }

        $slots = $this->slots();
        if ($slots->count() >= 1) {
            $this->eventSlotId = (int) $slots->first()->id;
        }

        $this->recomputeAmount();
    }

    public function getTitle(): string
    {
        return 'Register — ' . $this->getRecord()->title;
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return ($user?->canManage('events') ?? false) && $user->hasPartnerPermission('bookings');
    }

    // ---- Pricing helpers -----------------------------------------------------

    /** @return Collection<int, TicketType> */
    public function tiers(): Collection
    {
        return $this->getRecord()->ticketTypes()->orderBy('sort')->orderBy('id')->get();
    }

    /** @return Collection<int, EventSlot> */
    public function slots(): Collection
    {
        return $this->getRecord()->slots()->orderBy('sort')->orderBy('id')->get();
    }

    /** Unit price driving the auto-filled amount: the chosen tier's live price, else the event base. */
    public function unitPrice(): float
    {
        if ($this->ticketTypeId !== null && $this->ticketTypeId !== '') {
            $tier = $this->tiers()->firstWhere('id', (int) $this->ticketTypeId);
            if ($tier !== null) {
                return $tier->effectivePrice();
            }
        }

        return (float) $this->getRecord()->price;
    }

    /** Re-fill the editable amount from tier × quantity. Called when either changes. */
    private function recomputeAmount(): void
    {
        $this->amount = number_format($this->unitPrice() * max(1, (int) $this->quantity), 2, '.', '');
    }

    public function updatedTicketTypeId(): void
    {
        $this->recomputeAmount();
    }

    public function updatedQuantity(): void
    {
        $this->quantity = max(1, min(20, (int) $this->quantity));
        $this->recomputeAmount();
    }

    // ---- Actions -------------------------------------------------------------

    /** Record the registration as paid at the desk (no gateway). */
    public function collectCash(): mixed
    {
        $data = $this->validateForm(minAmount: 0);

        try {
            $order = $this->placeOrder($data, reserve: false);
        } catch (ConflictHttpException | NotFoundHttpException $e) {
            Notification::make()->title('Could not register')->body($e->getMessage())->danger()->send();

            return null;
        }

        $this->stampAttendee($order, (float) $data['amount'], paid: 'cash');
        BookingNotifier::dispatch($order->first());

        Notification::make()
            ->title('Registered')
            ->body($data['name'] . ' is booked in — cash collected. Their ticket has been sent.')
            ->success()
            ->send();

        return $this->backToAnalytics();
    }

    /** Reserve the order and open Razorpay checkout to collect the payment online. */
    public function payOnline(): void
    {
        $gateway = app(RazorpayGateway::class);

        if (! $gateway->isConfigured()) {
            Notification::make()->title('Online payments are not set up')
                ->body('No Razorpay keys are configured. Use “Collect cash” instead.')->danger()->send();

            return;
        }

        $data = $this->validateForm(minAmount: RazorpayGateway::MIN_AMOUNT_PAISE / 100);

        try {
            $order = $this->placeOrder($data, reserve: true);
        } catch (ConflictHttpException | NotFoundHttpException $e) {
            Notification::make()->title('Could not start payment')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->stampAttendee($order, (float) $data['amount'], paid: null);

        $grandPaise = (int) round((float) $data['amount'] * 100);
        $service    = app(BookingService::class);

        try {
            $rzp = $gateway->createOrder($grandPaise, 'reg_' . $this->getRecord()->id . '_' . $order->first()->id);
        } catch (RuntimeException $e) {
            $service->releaseReservation($order->pluck('id')->all());
            Notification::make()->title('Could not start payment')->body('Please try again.')->danger()->send();

            return;
        }

        $service->attachOrderId($order, (string) $rzp['id']);

        // A desk payment runs at the pace of a queue: card handed over, terminal, receipt. Hold
        // the seats for the full window so the background sweep can't reclaim them mid-checkout
        // and let the order oversell. Same length as the standard hold now
        // (BookingService::RESERVATION_HOLD_MINUTES) — restated rather than inherited, because
        // this one is about the desk's own pace, not the buyer's.
        Booking::query()->whereIn('id', $order->pluck('id')->all())
            ->update(['reserved_until' => now()->addMinutes(15)]);

        $this->pay = [
            'key'      => (string) $gateway->publicKey(),
            'orderId'  => (string) $rzp['id'],
            'amount'   => (int) $rzp['amount'],
            'currency' => (string) $rzp['currency'],
        ];
    }

    /** Called from checkout.js on a successful payment — verify + confirm the reserved order. */
    public function confirmOnline(string $razorpayOrderId, string $paymentId, string $signature): mixed
    {
        $gateway = app(RazorpayGateway::class);

        if (! $gateway->verifySignature($razorpayOrderId, $paymentId, $signature)) {
            Notification::make()->title('Payment could not be verified')->danger()->send();

            return null;
        }

        try {
            $order = app(BookingService::class)->confirmReservedOrder(auth()->user(), $razorpayOrderId, $paymentId);
        } catch (NotFoundHttpException $e) {
            Notification::make()->title('Reservation not found')->danger()->send();

            return null;
        }

        BookingNotifier::dispatch($order->first());

        Notification::make()->title('Payment received')
            ->body($this->name . ' is booked in. Their ticket has been sent.')->success()->send();

        return $this->backToAnalytics();
    }

    /** Called when the buyer dismisses the Razorpay modal — hand the held seats back. */
    public function cancelOnline(string $razorpayOrderId): void
    {
        app(BookingService::class)->releaseReservedOrder(auth()->user(), $razorpayOrderId);
        $this->pay = null;

        Notification::make()->title('Payment cancelled')->body('The held seats were released.')->warning()->send();
    }

    // ---- Internals -----------------------------------------------------------

    /**
     * Validate the desk form and return clean values.
     *
     * @return array{name:string,phone:string,email:?string,amount:float,quantity:int}
     */
    private function validateForm(float $minAmount): array
    {
        $slotCount = $this->slots()->count();
        $tierIds   = $this->tiers()->pluck('id')->all();

        $rules = [
            'name'     => ['required', 'string', 'max:120'],
            'phone'    => ['required', 'string', 'max:32', 'regex:/^[0-9 +()\-]{7,20}$/'],
            'email'    => ['nullable', 'email', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'amount'   => ['required', 'numeric', 'min:' . $minAmount],
        ];

        if ($tierIds !== []) {
            $rules['ticketTypeId'] = ['required', 'in:' . implode(',', $tierIds)];
        }
        if ($slotCount > 1) {
            $rules['eventSlotId'] = ['required', 'in:' . implode(',', $this->slots()->pluck('id')->all())];
        }

        $this->validate($rules, [
            'phone.regex'         => 'That phone number doesn’t look right.',
            'ticketTypeId.required' => 'Pick a ticket type.',
            'eventSlotId.required'  => 'Pick a session.',
        ]);

        return [
            'name'     => trim($this->name),
            'phone'    => trim($this->phone),
            'email'    => ($e = trim($this->email)) !== '' ? $e : null,
            'amount'   => round((float) $this->amount, 2),
            'quantity' => max(1, (int) $this->quantity),
        ];
    }

    /**
     * Reserve/confirm the ticket line through BookingService so inventory (event slots, the
     * tier's sold count, the session count) stays correct — the same path the app/web use.
     *
     * @param  array{name:string,phone:string,email:?string,amount:float,quantity:int}  $data
     * @return Collection<int, Booking>
     */
    private function placeOrder(array $data, bool $reserve): Collection
    {
        $lines = [[
            'ticketTypeId' => $this->tiers()->isNotEmpty() ? (int) $this->ticketTypeId : null,
            'quantity'     => $data['quantity'],
        ]];

        return app(BookingService::class)->createOrder(
            auth()->user(),
            (int) $this->getRecord()->id,
            $lines,
            couponCode: null,
            contact: ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone']],
            eventSlotId: $this->slots()->count() > 0 ? (int) $this->eventSlotId : null,
            reserve: $reserve,
        );
    }

    /**
     * Force the recorded total to the amount the partner is actually charging (which may
     * override the tier price), mark the channel as a desk registration, and make sure the
     * attendee's own contact — not the partner's account — is what's stored/notified.
     *
     * @param  Collection<int, Booking>  $order
     */
    private function stampAttendee(Collection $order, float $amount, ?string $paid): void
    {
        $first = $order->first();
        $first->total_amount    = $amount;
        $first->convenience_fee = 0;
        $first->discount        = 0;
        $first->channel         = 'offline';
        $first->attendee_email  = ($e = trim($this->email)) !== '' ? $e : null;
        $first->save();
    }

    private function backToAnalytics(): mixed
    {
        return redirect(EventAnalytics::getUrl(['record' => $this->getRecord()]));
    }
}
