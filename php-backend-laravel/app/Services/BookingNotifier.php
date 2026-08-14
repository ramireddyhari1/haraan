<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\DeviceToken;
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

            if (! $whatsappOk) {
                Log::warning("Booking {$booking->id}: WhatsApp ticket delivery failed"
                    . ($email !== null ? '; the emailed ticket is the customer\'s copy.' : ' AND there is no email on file.'));
            }
        }
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
