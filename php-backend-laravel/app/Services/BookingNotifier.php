<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Support\ContactPrefill;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a confirmed booking's ticket — QR, details, and a short note — to the booker over
 * both email and WhatsApp. Every send is best-effort and independently guarded: a dead bridge
 * or a missing email must never bubble up into the booking flow. Call it AFTER the response
 * (see the deferred dispatch in the booking controllers) so SMTP/HTTP latency never blocks a
 * booking.
 */
final class BookingNotifier
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly EmailOtpService $mailer,
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

        if ($email === null && $phone === null) {
            return;
        }

        $code     = (string) $booking->ticket_code;
        $title    = $this->title($booking);
        $when     = $this->when($booking);
        $where    = $this->where($booking);
        $tier     = $booking->ticketType?->name;
        $qty      = max(1, (int) $booking->quantity);
        $passUrl  = url('/bookings/' . $booking->id . '/pass');
        $qrUrl    = url('/t/' . $code . '/qr.png');
        $note     = $this->note($booking);

        if ($email !== null) {
            try {
                [$subject, $text, $html] = $this->email($title, $when, $where, $tier, $qty, $code, $qrUrl, $passUrl, $note);
                $this->mailer->send($email, $subject, $text, $html);
            } catch (Throwable $e) {
                Log::warning('Booking email failed: ' . $e->getMessage());
            }
        }

        if ($phone !== null) {
            $caption = $this->caption($title, $when, $where, $tier, $qty, $code, $passUrl, $note);
            // Prefer an image (the scannable QR, hosted at $qrUrl) with the details as caption;
            // if Twilio can't send the media, fall back to a text message + the pass link.
            if (! $this->whatsapp->sendMedia($phone, $caption, $qrUrl)) {
                $this->whatsapp->sendMessage($phone, $caption . "\n\nYour ticket & QR: " . $passUrl);
            }
        }
    }

    private function recipientEmail(Booking $booking): ?string
    {
        $attendee = trim((string) $booking->attendee_email);
        if ($attendee !== '' && ContactPrefill::isRealEmail($attendee)) {
            return $attendee;
        }

        $userEmail = trim((string) ($booking->user->email ?? ''));

        return ($userEmail !== '' && ContactPrefill::isRealEmail($userEmail)) ? $userEmail : null;
    }

    private function recipientPhone(Booking $booking): ?string
    {
        foreach ([$booking->attendee_phone, $booking->user->phone ?? null, $booking->guest_phone] as $p) {
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
            return $booking->event->date->format('D, d M Y · g:i A');
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

    private function note(Booking $booking): string
    {
        return $booking->event !== null
            ? 'Show this QR at the entry gate. Please arrive a little early. This ticket is non-transferable.'
            : 'Show this QR at the venue desk to check in. Please arrive a little early.';
    }

    /**
     * @return array{0: string, 1: string, 2: string} [subject, text, html]
     */
    private function email(string $title, string $when, string $where, ?string $tier, int $qty, string $code, string $qrUrl, string $passUrl, string $note): array
    {
        $subject = 'Your Haraan ticket — ' . $title;

        $rows = [
            'When'    => $when,
            'Where'   => $where,
            'Tickets' => ($tier !== null ? $tier . ' · ' : '') . $qty . ' ' . ($qty === 1 ? 'guest' : 'guests'),
            'Code'    => $code,
        ];

        $text = "You're confirmed for {$title}.\n\n"
            . implode("\n", array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($rows), array_values($rows)))
            . "\n\n{$note}\n\nView your ticket & QR: {$passUrl}";

        $rowHtml = '';
        foreach ($rows as $k => $v) {
            $kE = e($k);
            $vE = e($v);
            $rowHtml .= "<tr><td style=\"padding:6px 0;color:#64748B;font-size:13px;width:90px;\">{$kE}</td>"
                . "<td style=\"padding:6px 0;color:#0F172A;font-size:14px;font-weight:600;\">{$vE}</td></tr>";
        }

        $titleE = e($title);
        $noteE  = e($note);
        $qrUrlE = e($qrUrl);
        $passE  = e($passUrl);

        $html = <<<HTML
<div style="background:#F4F7FB;padding:24px 0;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #E9EDF3;">
    <div style="background:linear-gradient(120deg,#2563EB 0%,#12B76A 100%);color:#fff;padding:18px 22px;">
      <div style="font-size:18px;font-weight:800;letter-spacing:.02em;">Haraan</div>
      <div style="font-size:12px;opacity:.9;margin-top:2px;">e-Ticket — you're confirmed 🎉</div>
    </div>
    <div style="padding:22px;">
      <div style="font-size:18px;font-weight:800;color:#0F172A;margin-bottom:14px;">{$titleE}</div>
      <table style="width:100%;border-collapse:collapse;margin-bottom:18px;">{$rowHtml}</table>
      <div style="text-align:center;padding:16px;background:#F8FAFC;border-radius:14px;">
        <img src="{$qrUrlE}" alt="Ticket QR" width="200" height="200" style="width:200px;height:200px;display:block;margin:0 auto 8px;" />
        <div style="font-size:13px;font-weight:800;letter-spacing:.12em;color:#121620;">{$code}</div>
      </div>
      <p style="font-size:13px;color:#64748B;line-height:1.6;margin:16px 0;">{$noteE}</p>
      <a href="{$passE}" style="display:block;text-align:center;background:#2563EB;color:#fff;text-decoration:none;font-weight:700;padding:13px;border-radius:12px;font-size:15px;">View your ticket</a>
    </div>
    <div style="padding:14px 22px;color:#94A3B8;font-size:11px;text-align:center;border-top:1px solid #EEF2F7;">Discover. Book. Play. · © Haraan</div>
  </div>
</div>
HTML;

        return [$subject, $text, $html];
    }

    private function caption(string $title, string $when, string $where, ?string $tier, int $qty, string $code, string $passUrl, string $note): string
    {
        $lines = [
            "🎟️ *Your Haraan ticket*",
            "",
            "*{$title}*",
            "🗓️ {$when}",
            "📍 {$where}",
            "🎫 " . ($tier !== null ? $tier . ' · ' : '') . $qty . ' ' . ($qty === 1 ? 'guest' : 'guests'),
            "🔑 Code: {$code}",
            "",
            $note,
        ];

        return implode("\n", $lines);
    }
}
