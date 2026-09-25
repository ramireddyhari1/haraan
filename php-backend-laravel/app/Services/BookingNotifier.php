<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\MessageTemplate;
use App\Services\Fcm\FcmClient;
use App\Support\ContactPrefill;
use App\Support\MessageContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a confirmed booking's ticket — QR, venue details, and a short note — to the booker
 * over email, push and WhatsApp.
 *
 * The channels are PARALLEL, not a ladder: whichever one the customer happens to look at is the
 * one that has to work, and we can't know in advance which that is. Email carries the full
 * ticket with the QR inline, push lands instantly on the phone of anyone with the app, and
 * WhatsApp carries the QR as a scannable image.
 *
 * There is no SMS rung. It was the one channel that reached a customer with no WhatsApp and no
 * data, but it was never enabled — India's DLT registry means every transactional SMS needs a
 * template approved before the send, and carrying a whole second transport for a channel nobody
 * had switched on cost more in complexity than it bought in reach. Email is now the guaranteed
 * fallback: it is the only one of the three that needs no approval and no app.
 *
 * What's live is a deployment decision, not a code one: email and push need no third-party
 * approval and are on; WhatsApp waits on template approval, so it sits behind its config toggle
 * and logs to the ledger meanwhile.
 *
 * Every send is best-effort and independently guarded: a dead aggregator, a bounced address or
 * a missing WhatsApp number must never bubble up into the booking flow, and must never stop the
 * others. Call it AFTER the response (see the deferred dispatch in the booking controllers)
 * so SMTP/HTTP latency never blocks a booking.
 */
final class BookingNotifier
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly EmailOtpService $mailer,
        private readonly FcmClient $fcm,
        private readonly TemplateResolver $templates,
    ) {}

    /**
     * Queue the ticket delivery to run AFTER the HTTP response is flushed (Laravel's deferred
     * callbacks — no queue worker needed), so SMTP/bridge latency never slows a booking. Pass
     * the primary booking of an order; the email/WhatsApp link to the pass showing every QR.
     */
    public static function dispatch(?Booking $booking): void
    {
        if ($booking === null) {
            return;
        }

        $id = (int) $booking->id;

        \Illuminate\Support\defer(function () use ($id): void {
            $fresh = Booking::query()->find($id);
            if ($fresh !== null && strtoupper((string) $fresh->status) === 'CONFIRMED') {
                app(self::class)->notify($fresh);
            }
        });
    }

    public function notify(Booking $booking): void
    {
        $booking->loadMissing(['user', 'event', 'venue', 'ticketType']);

        $email = $this->recipientEmail($booking);
        $phone = $this->recipientPhone($booking);

        $code     = (string) $booking->ticket_code;
        $title    = $this->title($booking);
        $when     = $this->when($booking);
        $where    = $this->where($booking);
        $address  = $this->address($booking);
        $mapsUrl  = $this->mapsUrl($booking);
        $tier     = $booking->ticketType?->name;
        $qty      = max(1, (int) $booking->quantity);
        // The public, code-addressed pass — NOT /bookings/{id}/pass, which needs the
        // buyer's session and so is unopenable by a gift recipient or a desk walk-in.
        // Venue/turf bookings have no event pass page, so they get no link at all
        // rather than a link to a 404; their code is shown at the desk.
        $passUrl  = $booking->event_id !== null ? url('/t/' . $code) : null;
        $qrUrl    = url('/t/' . $code . '/qr.png');
        $note     = $this->note($booking);

        // Push first — it's the only free channel and the only instant one, and it
        // deliberately does NOT sit behind the email/phone check below: someone who
        // signed in with Google and left the phone field blank still has a phone in
        // their pocket with the app on it.
        $this->push($booking, $title, $when, $where, $code, $passUrl);

        // The venue owner hears about it whether or not the CUSTOMER left a way to
        // be reached — a booking with no contact on it is still a court taken.
        if ($booking->booking_type === 'venue') {
            try {
                $this->notifyPartnerOnNewBooking($booking);
            } catch (Throwable $e) {
                Log::warning("Booking {$booking->id}: partner alert failed: " . $e->getMessage());
            }
        }

        if ($email === null && $phone === null) {
            return;
        }

        if ($email !== null) {
            try {
                [$subject, $text, $html] = $this->email($booking, $title, $when, $where, $address, $mapsUrl, $tier, $qty, $code, $qrUrl, $passUrl, $note);
                $this->mailer->send($email, $subject, $text, $html);
            } catch (Throwable $e) {
                Log::warning('Booking email failed: ' . $e->getMessage());
            }
        }

        if ($phone !== null) {
            // Attribute the ticket to whoever owns the event/venue, so the messaging
            // ledger can answer "what did this partner send, and what did it cost?".
            // Utility category: it's a transaction the customer asked for, not marketing.
            $ctx = MessageContext::forBooking($booking, MessageContext::UTILITY, 'booking.ticket');

            $caption = $this->caption($title, $when, $where, $address, $tier, $qty, $code, $note);

            // WhatsApp is the only channel that puts the actual QR in the customer's
            // hand without them opening anything. The compact date is for the
            // template: the email can afford "Sat, 15 Aug 2026", a template parameter
            // reads better short.
            $whatsappOk = $this->whatsappTicket(
                $phone,
                $ctx,
                $caption,
                $qrUrl,
                $passUrl,
                $title,
                $this->compactWhen($booking),
                $where,
                $code,
            );
        }
    }

    /**
     * Dispatch cancellation notice to customer and partner after response flushes.
     */
    public static function dispatchCancellation(?Booking $booking, string $reason = '', bool $byPartner = false): void
    {
        if ($booking === null) {
            return;
        }

        $id = (int) $booking->id;

        \Illuminate\Support\defer(function () use ($id, $reason, $byPartner): void {
            $fresh = Booking::query()->find($id);
            if ($fresh !== null) {
                app(self::class)->notifyCancellation($fresh, $reason, $byPartner);
            }
        });
    }

    /**
     * @param  bool  $byPartner  the venue's own desk cancelled it — telling them what they
     *                           just did is noise, so only the customer hears about it
     */
    public function notifyCancellation(Booking $booking, string $reason = '', bool $byPartner = false): void
    {
        $booking->loadMissing(['user', 'venue.partner', 'venueCourt', 'event']);

        $title = $this->title($booking);
        $when = $this->when($booking);
        $where = $this->where($booking);

        // Notify customer
        $phone = $this->recipientPhone($booking);
        if ($phone !== null) {
            try {
                $this->whatsappCustomerCancellation($booking, $phone, $title, $when, $reason);
            } catch (Throwable $e) {
                Log::warning("Booking {$booking->id}: cancellation notice failed: " . $e->getMessage());
            }
        }

        // Notify venue partner
        if ($booking->booking_type === 'venue' && ! $byPartner && ! $this->isDeskWalkIn($booking)
            && $booking->venue?->partner !== null) {
            $whatsappOn = $this->partnerAlertEnabled('booking.partner_cancellation');

            foreach ($this->venueTeam($booking->venue->partner, (int) $booking->venue_id) as [$member, $phone]) {
                $this->pushCancellationToPartner($booking, (int) $member->id, $reason);

                if ($phone !== null && $whatsappOn) {
                    $this->whatsappPartnerCancellation($booking, $phone, $reason);
                }
            }
        }
    }

    /**
     * The customer's cancellation notice. Nobody who just had a booking cancelled has a
     * 24-hour window open with us, so the approved template is the only send that lands;
     * free text is the fallback for before it's approved.
     *
     * The copy promises no refund of its own: whether one is due is the venue's/event's
     * cancellation policy, not something this message can know.
     *
     * Approved variable order: 1 event/venue  2 when  3 booking code  4 reason
     */
    private function whatsappCustomerCancellation(Booking $booking, string $phone, string $title, string $when, string $reason): void
    {
        $ctx = MessageContext::forBooking($booking, MessageContext::UTILITY, 'booking.cancelled');
        $code = (string) $booking->ticket_code;
        // A template parameter may not be empty — WhatsApp rejects the whole message.
        $reasonText = trim($reason) !== '' ? trim($reason) : 'Not given';

        $route = $this->templates->resolve('booking.cancelled', 'whatsapp', $phone);

        if ($route['mode'] === TemplateResolver::MODE_TEMPLATE) {
            $this->whatsapp->sendTemplate(
                $phone,
                (string) $route['name'],
                [$title, $when, $code, $reasonText],
                $ctx,
                (string) $route['language'],
            );

            return;
        }

        $this->whatsapp->sendMessage(
            $phone,
            "Your booking has been cancelled.\n\n*{$title}*\n{$when}\nBooking ID: {$code}\nReason: {$reasonText}\n\n"
                . 'Refunds, where applicable, go back to your original payment method as per the cancellation policy.',
            $ctx,
        );
    }

    /**
     * Tell the venue owner and their desk team a court was just booked — WhatsApp + push.
     *
     * Skips desk walk-ins: the desk made that booking, so the owner already knows. A
     * multi-slot checkout is ONE message listing every slot, not one per row, because
     * the rows share a Razorpay order and arrive together.
     */
    public function notifyPartnerOnNewBooking(Booking $booking): void
    {
        if ($this->isDeskWalkIn($booking)) {
            return;
        }

        $booking->loadMissing(['venue.partner', 'venueCourt', 'user']);
        $partner = $booking->venue?->partner;

        if ($partner === null) {
            return;
        }

        $rows = $this->venueOrderRows($booking);
        $whatsappOn = $this->partnerAlertEnabled('booking.partner_alert');

        foreach ($this->venueTeam($partner, (int) $booking->venue_id) as [$member, $phone]) {
            $this->pushToPartner($booking, (int) $member->id, $rows);

            if ($phone !== null && $whatsappOn) {
                $this->whatsappPartnerNewBooking($booking, $rows, $phone);
            }
        }
    }

    /**
     * Who at the venue hears about a booking: the owner, plus every desk staff member who
     * works bookings or check-in AT THIS VENUE — the same access the owner already set in
     * Staff & team, so there's no second list to keep in step. Finance-only staff (reports)
     * and suspended accounts are left out, and a staff member assigned to other branches
     * doesn't hear about this one.
     *
     * Phones are de-duplicated: a small turf often puts the owner's own number on the desk
     * login too, and one booking must not buzz the same phone twice.
     *
     * @return list<array{0: \App\Models\User, 1: string|null}> [member, whatsapp phone or null]
     */
    private function venueTeam(\App\Models\User $owner, int $venueId): array
    {
        $staff = \App\Models\User::query()
            ->where('parent_partner_id', $owner->id)
            ->whereRaw('UPPER(COALESCE(status, ?)) <> ?', ['ACTIVE', 'SUSPENDED'])
            ->get()
            ->filter(fn (\App\Models\User $u): bool => ($u->hasPartnerPermission('bookings') || $u->hasPartnerPermission('checkin'))
                && (($scope = $u->scopedVenueIds()) === null || in_array($venueId, $scope, true)));

        $team = [];
        $seenPhones = [];

        foreach ([$owner, ...$staff->all()] as $member) {
            $phone = $this->partnerPhone($member);
            // Compare on the last ten digits so "+91 98…" and "98…" are one phone.
            $key = $phone !== null ? substr($phone, -10) : null;

            if ($key !== null && isset($seenPhones[$key])) {
                $phone = null;
            } elseif ($key !== null) {
                $seenPhones[$key] = true;
            }

            $team[] = [$member, $phone];
        }

        return $team;
    }

    /**
     * Every confirmed row of the same checkout at the same venue, primary first.
     *
     * @return list<Booking>
     */
    private function venueOrderRows(Booking $booking): array
    {
        $orderId = trim((string) $booking->razorpay_order_id);

        if ($orderId === '') {
            return [$booking];
        }

        $rows = Booking::query()
            ->with('venueCourt')
            ->where('razorpay_order_id', $orderId)
            ->where('venue_id', $booking->venue_id)
            ->whereRaw('UPPER(status) = ?', ['CONFIRMED'])
            ->orderBy('slot_date')
            ->orderBy('start_time')
            ->get()
            ->all();

        return $rows !== [] ? $rows : [$booking];
    }

    /** The owner's phone as bare digits, or null when there's nothing routable on file. */
    private function partnerPhone(\App\Models\User $partner): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $partner->phone);

        return $digits !== null && strlen($digits) >= 10 ? $digits : null;
    }

    /**
     * The admin's off switch. Deactivating the template row in /control → Platform →
     * Templates stops the owner WhatsApp outright — without this, an inactive row just
     * looks unregistered to the resolver and the free-text fallback still fires.
     */
    private function partnerAlertEnabled(string $key): bool
    {
        return ! MessageTemplate::query()
            ->where('key', $key)
            ->where('channel', 'whatsapp')
            ->where('is_active', false)
            ->exists();
    }

    /** "Court 1 · 06:00 – 07:00" — one slot, as the owner's day grid names it. */
    private function slotLine(Booking $row): string
    {
        $window = trim(($row->start_time ? substr((string) $row->start_time, 0, 5) : '')
            . ($row->end_time ? ' – ' . substr((string) $row->end_time, 0, 5) : ''), ' –');

        return implode(' · ', array_filter([$row->venueCourt?->name ?? 'Court', $window !== '' ? $window : null]));
    }

    /**
     * The slots of an order for one template parameter. A parameter can't hold a line
     * break, so slots are comma-joined; different dates carry their own date.
     *
     * @param  list<Booking>  $rows
     */
    private function slotsSummary(array $rows): string
    {
        $dates = array_unique(array_map(fn (Booking $r): string => (string) $r->slot_date?->format('Y-m-d'), $rows));
        $mixedDates = count($dates) > 1;

        return implode(', ', array_map(
            fn (Booking $r): string => ($mixedDates && $r->slot_date !== null ? $r->slot_date->format('d M') . ' ' : '') . $this->slotLine($r),
            $rows,
        ));
    }

    /** "Rs.1,200 paid online" or "Rs.1,200 · Rs.800 due at the desk". @param list<Booking> $rows */
    private function amountSummary(array $rows): string
    {
        $charged = array_sum(array_map(fn (Booking $r): float => $r->amountCharged(), $rows));
        $due = array_sum(array_map(fn (Booking $r): float => $r->balanceDue(), $rows));

        $money = fn (float $v): string => 'Rs.' . number_format($v, fmod($v, 1.0) === 0.0 ? 0 : 2);

        if ($charged <= 0) {
            return 'Free booking';
        }

        return $due > 0
            ? $money($charged) . ' · ' . $money($due) . ' due at the desk'
            : $money($charged) . ' paid online';
    }

    private function customerName(Booking $booking): string
    {
        return trim((string) ($booking->attendee_name ?: ($booking->guest_name ?: ($booking->user?->name ?: '')))) ?: 'Guest';
    }

    /**
     * The ticket over WhatsApp, obeying the rule that decides whether it arrives at all.
     *
     * WhatsApp only permits free text and media inside the 24-hour service window, and that
     * window is opened by the CUSTOMER messaging us — buying a ticket does not open it. So for
     * most buyers the media message is illegal on arrival, and the approved template is the only
     * send that gets delivered. It carries the link rather than the image (templates can't attach
     * a media body we generate per booking), which is why the QR-as-image path is still tried
     * first whenever it's actually allowed.
     *
     * When nothing is registered yet the old behaviour stands — attempt it and let the ledger
     * record the rejection. That keeps a self-hosted bridge or a freshly-approved number working
     * without a code change, and an honest failure row is more useful than a silent skip.
     */
    private function whatsappTicket(
        string $phone,
        MessageContext $ctx,
        string $caption,
        string $qrUrl,
        ?string $passUrl,
        string $title,
        string $when,
        string $where,
        string $code,
    ): bool {
        $route = $this->templates->resolve('booking.ticket', 'whatsapp', $phone);

        if ($route['mode'] === TemplateResolver::MODE_TEMPLATE) {
            // Variable order is the approved one, and changing it here without
            // re-approving the template silently reorders the customer's ticket:
            //   1 event  2 when  3 venue  4 booking code  5 link to the QR
            //
            // Venue bookings have no public pass page, so slot 5 falls back to the QR
            // image itself rather than a link to a 404 — the slot means "where to see
            // your QR", and both forms answer that.
            return $this->whatsapp->sendTemplate(
                $phone,
                (string) $route['name'],
                [$title, $when, $where, $code, $passUrl ?? $qrUrl],
                $ctx,
                (string) $route['language'],
            );
        }

        return $this->whatsapp->sendMedia($phone, $caption, $qrUrl, $ctx)
            || $this->whatsapp->sendMessage($phone, $caption . ($passUrl !== null ? "\n\nYour ticket & QR: " . $passUrl : ''), $ctx);
    }

    /**
     * Push the ticket to the buyer's devices via FCM.
     *
     * Two guards do the real work here:
     *
     *  1. **Desk walk-ins are skipped.** An offline booking carries the PARTNER's
     *     user_id (the desk created it, so the FK has somewhere to point) — pushing
     *     it would fire the customer's ticket at the partner's phone, and the
     *     customer, who has no account, would get nothing either way.
     *  2. **Dead tokens are pruned as we go**, the same as {@see \App\Jobs\SendNotificationPush}.
     *     Uninstalls are the normal case, not an error; leaving them accumulates a
     *     tail of guaranteed-failing sends on every future booking.
     *
     * No `notifications` row is written. A booking's home is the account Tickets
     * lane, and duplicating it into the bell inbox would make one purchase look like
     * two things that happened.
     */
    private function push(Booking $booking, string $title, string $when, string $where, string $code, ?string $passUrl): void
    {
        if (strtolower((string) $booking->channel) === 'offline') {
            return;
        }

        $userId = (int) $booking->user_id;

        if ($userId <= 0 || ! $this->fcm->isConfigured()) {
            return;
        }

        // The deep link is the public pass URL, which MainActivity opens directly —
        // the app's DeepLinks parser only models tabs, so an entity route would
        // silently resolve to null and drop the user on the Events tab instead.
        // booking_id/ticket_code ride along so in-app routing can use them later
        // without a server change.
        $data = array_filter([
            'deep_link' => $passUrl,
            'booking_id' => (string) $booking->id,
            'ticket_code' => $code,
        ]);

        $body = trim(implode(' · ', array_filter([$when, $where]))) . ' · Ticket ' . $code;

        try {
            DeviceToken::query()
                ->where('user_id', $userId)
                ->chunkById(200, function ($tokens) use ($title, $body, $data): void {
                    foreach ($tokens as $device) {
                        if ($this->fcm->send($device->token, "You're in — {$title}", $body, $data) === FcmClient::INVALID) {
                            $device->delete();
                        }
                    }
                });
        } catch (Throwable $e) {
            // Same contract as every other channel: a push problem is never a booking problem.
            Log::warning("Booking {$booking->id}: ticket push failed: " . $e->getMessage());
        }
    }

    /**
     * The owner's WhatsApp. Template first — the owner almost never has a 24-hour window
     * open with us, so free text is only tried when the template isn't approved yet (it
     * lands if they messaged us recently, and otherwise leaves an honest failure row in
     * the ledger instead of a silent skip).
     *
     * Approved variable order — changing it without re-approving the template reorders
     * what the owner reads:
     *   1 venue  2 date  3 slot(s)  4 customer  5 customer phone  6 amount  7 booking code
     *
     * @param  list<Booking>  $rows
     */
    private function whatsappPartnerNewBooking(Booking $booking, array $rows, string $partnerPhone): void
    {
        $venue = $booking->venue;
        if ($venue === null) {
            return;
        }

        $date = $booking->slot_date?->format('D, d M Y') ?? 'Date on your grid';
        $slots = $this->slotsSummary($rows);
        $customer = $this->customerName($booking);
        $customerPhone = trim((string) ($booking->attendee_phone ?: ($booking->guest_phone ?: ($booking->user?->phone ?: '')))) ?: 'Not given';
        $amount = $this->amountSummary($rows);
        $code = (string) $booking->ticket_code;

        $ctx = MessageContext::forBooking($booking, MessageContext::UTILITY, 'booking.partner_alert');

        $route = $this->templates->resolve('booking.partner_alert', 'whatsapp', $partnerPhone);

        if ($route['mode'] === TemplateResolver::MODE_TEMPLATE) {
            $this->whatsapp->sendTemplate(
                $partnerPhone,
                (string) $route['name'],
                [(string) $venue->name, $date, $slots, $customer, $customerPhone, $amount, $code],
                $ctx,
                (string) $route['language'],
            );

            return;
        }

        $text = "New booking at *{$venue->name}*\n\n"
            . "{$date}\n"
            . implode("\n", array_map(fn (Booking $r): string => $this->slotLine($r), $rows)) . "\n\n"
            . "Customer: {$customer} ({$customerPhone})\n"
            . "Amount: {$amount}\n"
            . "Booking ID: {$code}\n\n"
            . 'It is on your Haraan partner app.';

        $this->whatsapp->sendMessage($partnerPhone, $text, $ctx);
    }

    /**
     * Approved variable order: 1 venue  2 slot (date · court · time)  3 customer  4 reason
     */
    private function whatsappPartnerCancellation(Booking $booking, string $partnerPhone, string $reason = ''): void
    {
        $venue = $booking->venue;
        if ($venue === null) {
            return;
        }

        $slot = implode(' · ', array_filter([$booking->slot_date?->format('D, d M'), $this->slotLine($booking)]));
        $customer = $this->customerName($booking);
        // A template parameter may not be empty — WhatsApp rejects the whole message.
        $reasonText = trim($reason) !== '' ? trim($reason) : 'Not given';

        $ctx = MessageContext::forBooking($booking, MessageContext::UTILITY, 'booking.partner_cancellation');

        $route = $this->templates->resolve('booking.partner_cancellation', 'whatsapp', $partnerPhone);

        if ($route['mode'] === TemplateResolver::MODE_TEMPLATE) {
            $this->whatsapp->sendTemplate(
                $partnerPhone,
                (string) $route['name'],
                [(string) $venue->name, $slot, $customer, $reasonText],
                $ctx,
                (string) $route['language'],
            );

            return;
        }

        $text = "Booking cancelled at *{$venue->name}*\n\n"
            . "{$slot}\n"
            . "Customer: {$customer}\n"
            . "Reason: {$reasonText}\n\n"
            . 'The slot is open again on your grid.';

        $this->whatsapp->sendMessage($partnerPhone, $text, $ctx);
    }

    /** @param list<Booking> $rows */
    private function pushToPartner(Booking $booking, int $partnerId, array $rows): void
    {
        if ($partnerId <= 0 || ! $this->fcm->isConfigured()) {
            return;
        }

        $title = 'New booking · ' . ($booking->venue?->name ?? 'your venue');
        $body = $this->customerName($booking) . ' · '
            . implode(' · ', array_filter([$booking->slot_date?->format('d M'), $this->slotsSummary($rows)]))
            . ' · ' . $this->amountSummary($rows);

        $data = [
            'type' => 'partner_booking_received',
            'venue_id' => (string) $booking->venue_id,
            'booking_id' => (string) $booking->id,
            'ticket_code' => (string) $booking->ticket_code,
        ];

        try {
            DeviceToken::query()
                ->where('user_id', $partnerId)
                ->chunkById(100, function ($tokens) use ($title, $body, $data): void {
                    foreach ($tokens as $device) {
                        if ($this->fcm->send($device->token, $title, $body, $data) === FcmClient::INVALID) {
                            $device->delete();
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::warning("Partner push notification failed for booking {$booking->id}: " . $e->getMessage());
        }
    }

    private function pushCancellationToPartner(Booking $booking, int $partnerId, string $reason = ''): void
    {
        if ($partnerId <= 0 || ! $this->fcm->isConfigured()) {
            return;
        }

        $court = $booking->venueCourt?->name ?? 'Court';
        $when = $this->when($booking);
        $title = "Booking Cancelled: {$court}";
        $body = "Slot {$when} has been cancelled" . ($reason !== '' ? " ({$reason})" : '') . ". Court is now open.";

        $data = [
            'type' => 'partner_booking_cancelled',
            'venue_id' => (string) $booking->venue_id,
            'booking_id' => (string) $booking->id,
        ];

        try {
            DeviceToken::query()
                ->where('user_id', $partnerId)
                ->chunkById(100, function ($tokens) use ($title, $body, $data): void {
                    foreach ($tokens as $device) {
                        if ($this->fcm->send($device->token, $title, $body, $data) === FcmClient::INVALID) {
                            $device->delete();
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::warning("Partner cancellation push failed for booking {$booking->id}: " . $e->getMessage());
        }
    }

    /**
     * True for a desk walk-in. Such a booking carries the PARTNER's user_id (the desk
     * created it), so the account contact on it belongs to the venue, not the customer.
     * Only the guest fields may be used, or the customer's ticket is delivered to the
     * venue's own phone/inbox — see the same guard in {@see push()}.
     */
    private function isDeskWalkIn(Booking $booking): bool
    {
        return strtolower((string) $booking->channel) === 'offline';
    }

    private function recipientEmail(Booking $booking): ?string
    {
        $attendee = trim((string) $booking->attendee_email);
        if ($attendee !== '' && ContactPrefill::isRealEmail($attendee)) {
            return $attendee;
        }

        if ($this->isDeskWalkIn($booking)) {
            return null;
        }

        $userEmail = trim((string) ($booking->user->email ?? ''));

        return ($userEmail !== '' && ContactPrefill::isRealEmail($userEmail)) ? $userEmail : null;
    }

    private function recipientPhone(Booking $booking): ?string
    {
        $candidates = $this->isDeskWalkIn($booking)
            ? [$booking->attendee_phone, $booking->guest_phone]
            : [$booking->attendee_phone, $booking->user->phone ?? null, $booking->guest_phone];

        foreach ($candidates as $p) {
            $digits = preg_replace('/[^0-9]/', '', (string) $p);
            if ($digits !== null && strlen($digits) >= 10) {
                return $digits;
            }
        }

        return null;
    }

    private function title(Booking $booking): string
    {
        if ($booking->event !== null) {
            return (string) $booking->event->title;
        }

        return (string) ($booking->venue->name ?? 'Your booking');
    }

    private function when(Booking $booking): string
    {
        if ($booking->event !== null && $booking->event->date !== null) {
            // Time is stored in the event's `time` string column; `date` is date-only,
            // so formatting the time out of it would always read 12:00 AM on the ticket.
            $time = trim((string) $booking->event->time);

            return $booking->event->date->format('D, d M Y') . ($time !== '' ? ' · ' . $time : '');
        }

        // Venue booking: date + slot window (start–end).
        $parts = [];
        if ($booking->slot_date !== null) {
            $parts[] = $booking->slot_date->format('D, d M Y');
        }
        $window = trim(($booking->start_time ? substr((string) $booking->start_time, 0, 5) : '')
            . ($booking->end_time ? ' – ' . substr((string) $booking->end_time, 0, 5) : ''), ' –');
        if ($window !== '') {
            $parts[] = $window;
        }

        return implode(' · ', $parts) ?: 'See your ticket';
    }

    private function where(Booking $booking): string
    {
        if ($booking->event !== null) {
            return trim(implode(', ', array_filter([$booking->event->venue, $booking->event->city]))) ?: 'See your ticket';
        }

        $v = $booking->venue;

        return trim(implode(', ', array_filter([$v?->name, $v?->address ?: $v?->city]))) ?: 'See your ticket';
    }

    /**
     * The full street address, when it says more than {@see where()} already did.
     *
     * Events keep the human-readable venue name in `venue` and the postal address in
     * `location`, and the two are often the same string on a quickly-created event —
     * printing both would just look like a bug on the ticket.
     */
    private function address(Booking $booking): ?string
    {
        $address = $booking->event !== null
            ? trim((string) $booking->event->location)
            : trim((string) ($booking->venue->address ?? ''));

        if ($address === '') {
            return null;
        }

        $shown = $this->where($booking);

        // Same place said twice (case/spacing aside) — or the address is merely a
        // shorter echo of what's already on the line above it.
        return str_contains(mb_strtolower($shown), mb_strtolower($address)) ? null : $address;
    }

    /**
     * A directions link. Prefers the host's own map link (they may have pinned an
     * exact gate); otherwise a Maps search on the venue, matching what the pass page
     * and the app both do.
     */
    private function mapsUrl(Booking $booking): ?string
    {
        $pinned = trim((string) ($booking->event->map_link ?? ''));

        if ($pinned !== '' && str_starts_with($pinned, 'http')) {
            return $pinned;
        }

        $query = $booking->event !== null
            ? trim(implode(', ', array_filter([$booking->event->venue, $booking->event->location, $booking->event->city])))
            : trim(implode(', ', array_filter([$booking->venue->name ?? null, $booking->venue->address ?? null, $booking->venue->city ?? null])));

        return $query !== ''
            ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query)
            : null;
    }

    private function note(Booking $booking): string
    {
        return $booking->event !== null
            ? 'Show this QR at the entry gate. Please arrive a little early. This ticket is non-transferable.'
            : 'Show this QR at the venue desk to check in. Please arrive a little early.';
    }

    /**
     * The confirmation email.
     *
     * Rebuilt 2026-08-02: the old one led with a blue→green gradient banner, an
     * emoji, a five-row label/value table and a slogan footer — the visual
     * shorthand for "generated", and the first thing a buyer sees from us. This
     * version leads with the event's own poster and answers the three questions
     * a ticket has to answer (what, when, where) in plain lines, then hands over
     * the QR. Table-based with inline styles only, because Gmail and Outlook
     * discard stylesheets, flex and grid.
     *
     * @return array{0: string, 1: string, 2: string} [subject, text, html]
     */
    private function email(Booking $booking, string $title, string $when, string $where, ?string $address, ?string $mapsUrl, ?string $tier, int $qty, string $code, string $qrUrl, ?string $passUrl, string $note): array
    {
        $event = $booking->event;

        // Split date from time so the two can sit side by side. Falls back to the
        // combined `when` string for venue bookings, which have no event row.
        $dateLabel = $event?->date?->format('D, d M Y')
            ?? $booking->slot_date?->format('D, d M Y');
        $timeLabel = $event?->timeRangeLabel()
            ?? trim(($booking->start_time ? substr((string) $booking->start_time, 0, 5) : '')
                . ($booking->end_time ? ' – ' . substr((string) $booking->end_time, 0, 5) : ''), ' –');

        $venueName = $event !== null
            ? trim((string) $event->venue)
            : trim((string) ($booking->venue->name ?? ''));
        // The locality, not the full postal address — "Koramangala, Bengaluru"
        // tells you where you're going; the address is there for the map link.
        $venueArea = trim(implode(', ', array_filter([
            $event?->venueArea(),
            $event !== null ? $event->city : ($booking->venue->city ?? null),
        ])));

        $poster   = $event?->heroImageUrl();
        $guests   = $qty . ' ' . ($qty === 1 ? 'guest' : 'guests');
        $ticketLn = trim(($tier !== null ? $tier . ' · ' : '') . $guests);
        $subject  = 'Your ticket — ' . $title;

        // ---- plain text ------------------------------------------------------
        $text = "You're confirmed for {$title}.\n\n"
            . implode("\n", array_filter([
                $dateLabel !== null ? "When:    {$dateLabel}" . ($timeLabel !== '' ? ', ' . $timeLabel : '') : "When:    {$when}",
                "Where:   " . ($venueName !== '' ? $venueName : $where) . ($venueArea !== '' ? ', ' . $venueArea : ''),
                $address !== null ? "Address: {$address}" : null,
                "Ticket:  {$ticketLn}",
                "Code:    {$code}",
            ]))
            . "\n\n{$note}\n"
            . ($passUrl !== null ? "\nYour ticket & QR: {$passUrl}" : '')
            . ($mapsUrl !== null ? "\nDirections: {$mapsUrl}" : '');

        // ---- html ------------------------------------------------------------
        $titleE  = e($title);
        $noteE   = e($note);
        $qrUrlE  = e($qrUrl);
        $codeE   = e($code);
        $font    = "-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

        // Shown in the inbox preview line, then hidden in the body.
        $preheader = e(trim($title . ' · ' . ($dateLabel ?? $when) . ($venueName !== '' ? ' · ' . $venueName : '')));

        // A thumbnail beside the title, NOT a full-bleed hero: event posters here
        // are portrait, so at 600px wide one opens the email with ~750px of image
        // before a single fact. Fixed width, auto height — no object-fit, which
        // most mail clients ignore.
        $posterCell = '';
        if ($poster !== null && $poster !== '') {
            $posterE = e($poster);
            $posterCell = <<<HTML
        <td width="92" valign="top" style="padding:0 14px 0 0;">
          <img src="{$posterE}" alt="" width="92" style="width:92px;height:auto;display:block;border:1px solid #E2E8F0;border-radius:10px;" />
        </td>
HTML;
        }

        // Date and time as two columns; one stacked cell when only one is known.
        $factCells = '';
        if ($dateLabel !== null) {
            $dateE = e($dateLabel);
            $factCells .= <<<HTML
          <td width="50%" valign="top" style="padding:0 12px 0 0;">
            <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;font-weight:700;">Date</div>
            <div style="font-size:15px;color:#0F172A;font-weight:600;padding-top:3px;">{$dateE}</div>
          </td>
HTML;
        }
        if ($timeLabel !== '') {
            $timeE = e($timeLabel);
            $factCells .= <<<HTML
          <td width="50%" valign="top" style="padding:0;">
            <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;font-weight:700;">Time</div>
            <div style="font-size:15px;color:#0F172A;font-weight:600;padding-top:3px;">{$timeE}</div>
          </td>
HTML;
        }

        $venueHtml = '';
        if ($venueName !== '' || $where !== '') {
            $vNameE = e($venueName !== '' ? $venueName : $where);
            $vAreaE = $venueArea !== '' ? e($venueArea) : null;
            $dirHtml = '';
            if ($mapsUrl !== null) {
                $mapsE = e($mapsUrl);
                $dirHtml = "<a href=\"{$mapsE}\" style=\"color:#2563EB;text-decoration:none;font-weight:600;font-size:13px;\">Get directions</a>";
            }
            $areaLine = $vAreaE !== null
                ? "<div style=\"font-size:13px;color:#64748B;padding-top:2px;\">{$vAreaE}</div>"
                : '';
            $venueHtml = <<<HTML
      <tr><td style="padding:18px 24px 0;">
        <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;font-weight:700;">Where</div>
        <div style="font-size:15px;color:#0F172A;font-weight:600;padding-top:3px;">{$vNameE}</div>
        {$areaLine}
        <div style="padding-top:6px;">{$dirHtml}</div>
      </td></tr>
HTML;
        }

        $ticketE = e($ticketLn);

        $ctaHtml = '';
        if ($passUrl !== null) {
            $passE = e($passUrl);
            // Inline-block, not a full-bleed bar: it reads as a considered control
            // rather than a template's default CTA slab.
            $ctaHtml = <<<HTML
      <tr><td align="center" style="padding:22px 24px 4px;">
        <a href="{$passE}" style="display:inline-block;background:#2563EB;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 34px;border-radius:10px;">Open your ticket</a>
      </td></tr>
HTML;
        }

        $html = <<<HTML
<div style="background:#EEF2F7;padding:28px 12px;font-family:{$font};">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{$preheader}</div>
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:100%;max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #E2E8F0;border-radius:16px;overflow:hidden;">
    <tr><td style="padding:18px 24px;border-bottom:1px solid #EEF2F7;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr>
        <td align="left" style="font-size:17px;font-weight:800;color:#121620;letter-spacing:-.01em;">Haraan</td>
        <td align="right" style="font-size:11px;font-weight:700;letter-spacing:.1em;color:#94A3B8;">E-TICKET</td>
      </tr></table>
    </td></tr>
    <tr><td style="padding:22px 24px 0;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr>
{$posterCell}
        <td valign="top">
          <div style="font-size:12px;color:#0E9F6E;font-weight:700;letter-spacing:.04em;padding-bottom:5px;">CONFIRMED</div>
          <div style="font-size:20px;line-height:1.3;font-weight:800;color:#0F172A;letter-spacing:-.02em;">{$titleE}</div>
        </td>
      </tr></table>
    </td></tr>
    <tr><td style="padding:18px 24px 0;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr>{$factCells}</tr></table>
    </td></tr>
{$venueHtml}
    <tr><td style="padding:22px 24px 0;">
      <div style="border-top:1px dashed #D7DEE8;line-height:0;font-size:0;">&nbsp;</div>
    </td></tr>
    <tr><td align="center" style="padding:20px 24px 0;">
      <div style="font-size:13px;color:#0F172A;font-weight:600;padding-bottom:12px;">{$ticketE}</div>
      <img src="{$qrUrlE}" alt="Ticket QR code" width="176" height="176" style="width:176px;height:176px;display:block;margin:0 auto;border:1px solid #E2E8F0;border-radius:12px;" />
      <div style="font-family:'SFMono-Regular',Menlo,Consolas,monospace;font-size:13px;font-weight:700;letter-spacing:.14em;color:#121620;padding-top:12px;word-break:break-all;">{$codeE}</div>
    </td></tr>
{$ctaHtml}
    <tr><td style="padding:16px 24px 24px;">
      <p style="margin:0;font-size:13px;line-height:1.6;color:#64748B;text-align:center;">{$noteE}</p>
    </td></tr>
    <tr><td style="padding:14px 24px;background:#F8FAFC;border-top:1px solid #EEF2F7;font-size:11px;color:#94A3B8;text-align:center;">
      Booked on Haraan · Questions? Just reply to this email.
    </td></tr>
  </table>
</div>
HTML;

        return [$subject, $text, $html];
    }

    private function caption(string $title, string $when, string $where, ?string $address, ?string $tier, int $qty, string $code, string $note): string
    {
        $lines = array_filter([
            "🎟️ *Your Haraan ticket*",
            "",
            "*{$title}*",
            "🗓️ {$when}",
            "📍 {$where}",
            $address !== null ? "   {$address}" : null,
            "🎫 " . ($tier !== null ? $tier . ' · ' : '') . $qty . ' ' . ($qty === 1 ? 'guest' : 'guests'),
            "🔑 Code: {$code}",
            "",
            $note,
        ], fn (?string $line): bool => $line !== null);

        return implode("\n", $lines);
    }

    /** Date + time with the year and weekday dropped — "15 Aug, 6:00 PM". */
    private function compactWhen(Booking $booking): string
    {
        if ($booking->event !== null && $booking->event->date !== null) {
            $time = trim((string) $booking->event->time);

            return $booking->event->date->format('d M') . ($time !== '' ? ', ' . $time : '');
        }

        $parts = array_filter([
            $booking->slot_date?->format('d M'),
            $booking->start_time ? substr((string) $booking->start_time, 0, 5) : null,
        ]);

        return implode(', ', $parts) ?: 'See your ticket';
    }
}
