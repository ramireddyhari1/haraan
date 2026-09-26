<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use App\Models\VenueCourt;
use App\Models\WhatsAppBotSession;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\BusinessClock;
use App\Support\MessageContext;
use App\Support\PartnerLookup;
use App\Support\PlatformRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Book a court over WhatsApp, start to finish, without anyone at the venue:
 *
 *   share location → nearest venues → (sport) → day → how long → start time → (court)
 *   → confirm → Razorpay link → paid → QR ticket in the chat.
 *
 * Every step is a WhatsApp list or button. If the provider refuses interactive
 * messages, the same step goes out as a numbered text menu and a typed number works
 * just as well — so a provider quirk degrades the chat, never breaks it.
 *
 * The booking itself is an ordinary online booking ({@see BookingService::createOnlineHoldAt()}):
 * app pricing, commission and tax, under the customer's own Haraan account (found by
 * phone, or created phone-only the way WhatsApp sign-in does). Once a venue is chosen
 * the chat also lands in that venue's WhatsApp Desk, so staff can see it and step in —
 * and a staff reply there pauses the bot for that customer.
 */
final class WhatsAppBookingBot
{
    private const RESTART_WORDS = ['menu', 'restart', 'start over', 'book', 'book a court', 'booking', 'hi', 'hello', 'hey', 'hii'];

    private const HUMAN_WORDS = ['agent', 'human', 'staff', 'talk to venue', 'call me', 'help me', 'person'];

    private const LENGTHS = [60 => '1 hour', 90 => '1.5 hours', 120 => '2 hours'];

    /** Venue rows shown at once (WhatsApp lists hold 10). */
    private const MAX_VENUES = 10;

    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly BookingService $bookings,
        private readonly WhatsAppReservationService $reservations,
    ) {}

    public static function enabled(): bool
    {
        return PlatformRules::bool('whatsapp_bot.enabled');
    }

    /** Partway through a booking right now — the bot owns this customer's next message. */
    public static function isMidFlow(string $phone): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $session = WhatsAppBotSession::query()->where('phone', $phone)->first();

        return $session !== null
            && ! $session->isPaused()
            && ! $session->isStale()
            && ! in_array($session->state, ['start', 'done'], true);
    }

    /**
     * Handle one inbound message. Returns false when the bot stays out of it — turned
     * off, or paused because venue staff are talking to this customer.
     *
     * @param  array{reply_id?: ?string, location?: ?array{lat: float, lng: float}}  $extra
     */
    public function handle(string $phone, string $text, array $extra = []): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $session = WhatsAppBotSession::for($phone);
        $words = mb_strtolower(trim($text));
        $restart = in_array($words, self::RESTART_WORDS, true);

        if ($session->isPaused() && ! $restart) {
            return false;
        }

        if ($restart || $session->isStale() || $session->state === 'done') {
            // A live hold survives a restart request only if they're mid-payment and
            // just said hi again — "menu" always starts over.
            if (! ($session->state === 'await_payment' && ! $session->isStale() && $words !== 'menu')) {
                $this->reset($session);
            }
        }

        $session->last_activity_at = now();

        if (in_array($words, self::HUMAN_WORDS, true)) {
            $this->handOff($session);

            return true;
        }

        try {
            $this->step($session, $text, $extra['reply_id'] ?? null, $extra['location'] ?? null);
        } catch (\Throwable $e) {
            Log::error("WhatsApp bot failed for {$phone}: ".$e->getMessage());
            $this->say($session, 'Sorry — something went wrong on our side. Type “menu” to start again.');
        }

        $session->save();

        return true;
    }

    // ------------------------------------------------------------------ the flow

    /** @param array{lat: float, lng: float}|null $location */
    private function step(WhatsAppBotSession $s, string $text, ?string $replyId, ?array $location): void
    {
        switch ($s->state) {
            case 'start':
                $this->askLocation($s);

                return;

            case 'await_location':
                if ($location === null) {
                    $this->askLocation($s, 'Tap “Send location” below (or 📎 → Location) so we can find courts near you.');

                    return;
                }
                $s->put(['lat' => $location['lat'], 'lng' => $location['lng']]);
                $this->offerVenues($s);

                return;

            case 'await_venue':
                if ($location !== null) {
                    $s->put(['lat' => $location['lat'], 'lng' => $location['lng']]);
                    $this->offerVenues($s);

                    return;
                }
                $id = $this->choice($s, $text, $replyId);
                $venue = $id !== null ? Venue::query()->published()->find((int) Str::after($id, 'v:')) : null;
                if ($venue === null) {
                    $this->again($s);

                    return;
                }
                $s->venue_id = $venue->id;
                $s->put(['sport' => null]);
                $this->openDeskChat($s, $venue);
                $this->offerSports($s, $venue);

                return;

            case 'await_sport':
                $sport = $this->choice($s, $text, $replyId);
                if ($sport === null) {
                    $this->again($s);

                    return;
                }
                $s->put(['sport' => Str::after($sport, 's:')]);
                $this->offerDays($s);

                return;

            case 'await_date':
                $date = $this->choice($s, $text, $replyId);
                if ($date === null) {
                    $this->again($s);

                    return;
                }
                $s->put(['date' => Str::after($date, 'd:')]);
                $this->offerLengths($s);

                return;

            case 'await_length':
                $length = $this->choice($s, $text, $replyId);
                if ($length === null) {
                    $this->again($s);

                    return;
                }
                $s->put(['length' => (int) Str::after($length, 'l:'), 'page' => 0]);
                $this->offerTimes($s);

                return;

            case 'await_time':
                $pick = $this->choice($s, $text, $replyId);
                if ($pick === 'more') {
                    $s->put(['page' => (int) $s->get('page', 0) + 1]);
                    $this->offerTimes($s);

                    return;
                }
                if ($pick === 'days') {
                    $this->offerDays($s);

                    return;
                }
                if ($pick === null) {
                    $this->again($s);

                    return;
                }
                $s->put(['start' => (int) Str::after($pick, 't:')]);
                $this->offerCourts($s);

                return;

            case 'await_court':
                $court = $this->choice($s, $text, $replyId);
                if ($court === null) {
                    $this->again($s);

                    return;
                }
                $s->put(['court_id' => (int) Str::after($court, 'c:')]);
                $this->confirm($s);

                return;

            case 'await_confirm':
                $answer = $this->choice($s, $text, $replyId);
                match ($answer) {
                    'pay'  => $this->book($s),
                    'time' => $this->offerTimes($s),
                    'over' => $this->restartAtLocation($s),
                    default => $this->again($s),
                };

                return;

            case 'await_payment':
                $this->paymentReminder($s);

                return;

            default:
                $this->reset($s);
                $this->askLocation($s);
        }
    }

    private function askLocation(WhatsAppBotSession $s, ?string $body = null): void
    {
        $s->state = 'await_location';
        $body ??= PlatformRules::string('whatsapp_bot.greeting');

        if (! $this->whatsapp->sendLocationRequest($s->phone, $body, $this->context($s))) {
            $this->say($s, $body."\n\nTap 📎 → Location → Send your current location.");
        }
    }

    private function offerVenues(WhatsAppBotSession $s): void
    {
        $lat = (float) $s->get('lat');
        $lng = (float) $s->get('lng');
        $radius = PlatformRules::int('whatsapp_bot.radius_km');

        $venues = Venue::query()
            ->published()
            ->where('is_bookable', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['courts' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->map(fn (Venue $v) => ['venue' => $v, 'km' => self::km($lat, $lng, (float) $v->latitude, (float) $v->longitude)])
            ->filter(fn (array $r) => $r['km'] <= $radius)
            ->sortBy('km')
            ->take(self::MAX_VENUES)
            ->values();

        if ($venues->isEmpty()) {
            $s->state = 'await_location';
            $this->say($s, PlatformRules::string('whatsapp_bot.no_venues'));

            return;
        }

        $rows = $venues->map(function (array $r): array {
            /** @var Venue $v */
            $v = $r['venue'];
            $sports = collect($v->courts)->flatMap(fn (VenueCourt $c) => $c->sportsList())->unique()->take(3)
                ->map(fn ($x) => ucfirst((string) $x))->implode(', ');
            $from = collect($v->courts)->map(fn (VenueCourt $c) => (int) ($c->price ?? $v->price ?? 0))->filter()->min();

            return [
                'id'          => 'v:'.$v->id,
                'title'       => $v->name,
                'description' => implode(' · ', array_filter([
                    number_format($r['km'], 1).' km',
                    $sports ?: null,
                    $from ? 'from ₹'.number_format($from).'/hr' : null,
                ])),
            ];
        })->all();

        $s->state = 'await_venue';
        $this->ask($s, $venues->count() === 1 ? 'The nearest venue to you:' : 'Venues near you — pick one:', 'Choose venue', $rows);
    }

    private function offerSports(WhatsAppBotSession $s, Venue $venue): void
    {
        $sports = $this->courts($venue)->flatMap(fn (VenueCourt $c) => $c->sportsList())->unique()->values();

        if ($sports->count() <= 1) {
            $s->put(['sport' => $sports->first()]);
            $this->offerDays($s);

            return;
        }

        $s->state = 'await_sport';
        $this->ask($s, "What would you like to play at {$venue->name}?", 'Choose sport',
            $sports->map(fn ($x) => ['id' => 's:'.$x, 'title' => ucfirst((string) $x)])->all());
    }

    private function offerDays(WhatsAppBotSession $s): void
    {
        $venue = $this->venue($s);
        $today = BusinessClock::todayDate();
        $count = min(7, $venue->bookingWindowDays());

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $day = $today->copy()->addDays($i);
            if (! $venue->isOpenOn($day)
                || VenueBlockedDate::query()->where('venue_id', $venue->id)->whereDate('date', $day->toDateString())->exists()) {
                continue;
            }
            $rows[] = [
                'id'    => 'd:'.$day->toDateString(),
                'title' => match ($i) { 0 => 'Today', 1 => 'Tomorrow', default => $day->format('l') },
                'description' => $day->format('D, j M'),
            ];
        }

        if ($rows === []) {
            $this->say($s, "{$venue->name} isn’t taking bookings in the next few days. Type “menu” to pick another venue.");
            $s->state = 'done';

            return;
        }

        $s->state = 'await_date';
        $this->ask($s, 'Which day?', 'Choose day', $rows);
    }

    private function offerLengths(WhatsAppBotSession $s): void
    {
        $s->state = 'await_length';
        $this->ask($s, 'How long do you want to play?', 'Choose',
            collect(self::LENGTHS)->map(fn ($label, $min) => ['id' => 'l:'.$min, 'title' => $label])->values()->all());
    }

    private function offerTimes(WhatsAppBotSession $s): void
    {
        $venue = $this->venue($s);
        $starts = $this->freeStarts($s, $venue);

        if ($starts === []) {
            $this->say($s, 'Nothing free for that long on '.Carbon::parse($s->get('date'))->format('D, j M').'. Pick another day:');
            $this->offerDays($s);

            return;
        }

        $page = max(0, (int) $s->get('page', 0));
        $perPage = 9;
        $slice = array_slice($starts, $page * $perPage, $perPage);
        if ($slice === []) {
            $page = 0;
            $s->put(['page' => 0]);
            $slice = array_slice($starts, 0, $perPage);
        }

        $rows = array_map(fn (array $t) => [
            'id'          => 't:'.$t['start'],
            'title'       => self::clock($t['start']).' – '.self::clock($t['start'] + (int) $s->get('length')),
            'description' => $t['courts'] === 1 ? '1 court free · ₹'.number_format($t['from']) : "{$t['courts']} courts free · from ₹".number_format($t['from']),
        ], $slice);

        $rows[] = count($starts) > ($page + 1) * $perPage
            ? ['id' => 'more', 'title' => 'Later times', 'description' => 'See more start times']
            : ['id' => 'days', 'title' => 'Another day', 'description' => 'Pick a different day'];

        $s->state = 'await_time';
        $this->ask($s, 'Free start times on '.Carbon::parse($s->get('date'))->format('D, j M').':', 'Choose time', $rows);
    }

    private function offerCourts(WhatsAppBotSession $s): void
    {
        $venue = $this->venue($s);
        $free = $this->freeCourts($s, $venue, (int) $s->get('start'));

        if ($free->isEmpty()) {
            $this->say($s, 'That time was just taken. Here’s what’s still free:');
            $this->offerTimes($s);

            return;
        }

        if ($free->count() === 1) {
            $s->put(['court_id' => $free->first()['court']->id]);
            $this->confirm($s);

            return;
        }

        $s->state = 'await_court';
        $this->ask($s, 'Which court?', 'Choose court', $free->map(fn (array $r) => [
            'id'          => 'c:'.$r['court']->id,
            'title'       => $r['court']->name,
            'description' => '₹'.number_format($r['price']),
        ])->values()->all());
    }

    private function confirm(WhatsAppBotSession $s): void
    {
        $venue = $this->venue($s);
        $court = VenueCourt::query()->where('venue_id', $venue->id)->find((int) $s->get('court_id'));
        if ($court === null) {
            $this->offerCourts($s);

            return;
        }

        $start = (int) $s->get('start');
        $length = (int) $s->get('length');
        $date = Carbon::parse((string) $s->get('date'));
        // The same arithmetic the booking engine charges: slot + venue fee + Pulse tax.
        $subtotal = $this->price($venue, $court, $date, $start, $length);
        $fee = $venue->convenienceFeeFor($subtotal);
        $tax = Venue::taxFor($subtotal, 0.0);
        $money = fn (float $v): string => '₹'.number_format($v, $v == floor($v) ? 0 : 2);

        $lines = [
            "*{$venue->name}* · {$court->name}",
            $date->format('D, j M').', '.self::clock($start).' – '.self::clock($start + $length),
            'Court '.$money($subtotal),
        ];
        if ($fee > 0) {
            $lines[] = 'Booking fee '.$money($fee);
        }
        if ($tax > 0) {
            $lines[] = PlatformRules::string('fees.venue_tax_label').' '.$money($tax);
        }
        $lines[] = '*Total '.$money($subtotal + $fee + $tax).'*';

        $s->state = 'await_confirm';
        $this->ask($s, implode("\n", $lines), 'Choose', [
            ['id' => 'pay', 'title' => 'Confirm & pay'],
            ['id' => 'time', 'title' => 'Change time'],
            ['id' => 'over', 'title' => 'Start over'],
        ]);
    }

    /** Hold the court under the customer's account and send the payment link. */
    private function book(WhatsAppBotSession $s): void
    {
        $venue = $this->venue($s);
        $start = (int) $s->get('start');
        $date = (string) $s->get('date');

        $startsAt = BusinessClock::at($date, self::hm($start));
        if ($startsAt === null || $startsAt->lte(BusinessClock::now())) {
            $this->say($s, 'That time has just passed. Pick another:');
            $this->offerTimes($s);

            return;
        }

        $holdMinutes = PlatformRules::int('whatsapp_bot.hold_minutes');

        try {
            $booking = $this->bookings->createOnlineHoldAt(
                $this->customer($s),
                (int) $venue->id,
                (int) $s->get('court_id'),
                $date,
                $start,
                (int) $s->get('length'),
                $holdMinutes,
            );
        } catch (ConflictHttpException $e) {
            $this->say($s, 'Sorry, '.lcfirst(rtrim($e->getMessage(), '.')).'. Here’s what’s free now:');
            $this->offerTimes($s);

            return;
        } catch (HttpException $e) {
            $this->say($s, $e->getMessage() ?: 'Bookings are paused right now. Please try again soon.');
            $s->state = 'done';

            return;
        }

        $conversation = $this->openDeskChat($s, $venue);
        $conversation->update(['active_booking_id' => $booking->id, 'status' => 'hold_active']);

        try {
            $this->reservations->sendPaymentLink($venue, $conversation, $booking->load('venueCourt'), null, $holdMinutes);
        } catch (\Throwable $e) {
            // No link, no hold: let the court go rather than keep it for a payment that can't happen.
            $this->bookings->releaseReservation([(int) $booking->id]);
            $conversation->update(['active_booking_id' => null, 'status' => 'needs_action']);
            Log::warning("WhatsApp bot: payment link for booking {$booking->id} failed: ".$e->getMessage());
            $this->say($s, 'We couldn’t create a payment link just now, so the court wasn’t held. The venue team can see this chat and will reply here.');
            $s->state = 'done';

            return;
        }

        $s->booking_id = $booking->id;
        $s->state = 'await_payment';
    }

    private function paymentReminder(WhatsAppBotSession $s): void
    {
        $booking = $s->booking_id !== null ? Booking::query()->find($s->booking_id) : null;
        $status = strtoupper((string) $booking?->status);

        if ($booking === null || in_array($status, ['EXPIRED', 'CANCELLED', 'FAILED_OVERBOOKED'], true)) {
            $this->say($s, 'That hold has ended and the court was released. Let’s find you another time:');
            $s->booking_id = null;
            $this->offerTimes($s);

            return;
        }

        if (in_array($status, ['CONFIRMED', 'CHECKED_IN', 'COMPLETED'], true)) {
            $this->say($s, 'You’re booked — your code is in the message above. Type “menu” to book another court.');
            $s->state = 'done';

            return;
        }

        $link = \App\Models\WhatsAppPaymentLink::query()->where('booking_id', $booking->id)->latest('id')->value('short_url');
        $left = $booking->reserved_until ? max(0, (int) ceil(now()->diffInSeconds($booking->reserved_until, false) / 60)) : 0;

        $this->say($s, "Your court is held for {$left} more min. Pay here to confirm:\n{$link}\n\nType “menu” to start over.");
    }

    private function handOff(WhatsAppBotSession $s): void
    {
        $this->say($s, PlatformRules::string('whatsapp_bot.handoff'));
        $s->paused_until = now()->addHours(max(1, PlatformRules::int('whatsapp_bot.pause_hours')));

        if ($s->venue_id !== null) {
            WhatsAppConversation::query()
                ->where('venue_id', $s->venue_id)
                ->where('phone_number', $s->phone)
                ->update(['status' => 'needs_action']);
        }

        $s->save();
    }

    private function restartAtLocation(WhatsAppBotSession $s): void
    {
        $this->reset($s);
        $this->askLocation($s);
    }

    private function reset(WhatsAppBotSession $s): void
    {
        $s->state = 'start';
        $s->data = [];
        $s->venue_id = null;
        $s->booking_id = null;
        $s->paused_until = null;
    }

    // ---------------------------------------------------------------- availability

    /** @var array<int, Collection<int, VenueCourt>> loaded once per message, not once per time */
    private array $courtCache = [];

    /** @return Collection<int, VenueCourt> */
    private function courts(Venue $venue): Collection
    {
        return $this->courtCache[$venue->id] ??= VenueCourt::query()
            ->where('venue_id', $venue->id)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Start times on the chosen day where at least one court for the chosen sport is
     * free for the whole length. Steps follow the venue's own slot length and hours.
     *
     * @return list<array{start: int, courts: int, from: float}>
     */
    private function freeStarts(WhatsAppBotSession $s, Venue $venue): array
    {
        $date = Carbon::parse((string) $s->get('date'));
        $length = (int) $s->get('length', 60);
        $step = max(30, (int) ($venue->slot_minutes ?: 60));

        $windows = $venue->windowsForWeekday($date->format('D'));
        if ($windows === [] && (! is_array($venue->hours_json) || $venue->hours_json === [])) {
            // No structured hours: the venue's slot templates are its times.
            $windows = $venue->slotsOn($date)
                ->map(fn ($slot) => BookingService::timeToMinutes($slot->time))
                ->filter(fn ($m) => $m !== null)
                ->map(fn ($m) => [$m, min(24 * 60, $m + max($step, $length))])
                ->values()->all();
        }

        $now = BusinessClock::now();
        $isToday = $date->toDateString() === $now->toDateString();
        $nowMin = $now->hour * 60 + $now->minute;

        $starts = [];
        foreach ($windows as [$open, $close]) {
            for ($m = $open; $m + $length <= $close; $m += $step) {
                if ($isToday && $m <= $nowMin) {
                    continue;
                }
                $free = $this->freeCourts($s, $venue, $m);
                if ($free->isNotEmpty()) {
                    $starts[$m] = ['start' => $m, 'courts' => $free->count(), 'from' => (float) $free->min('price')];
                }
            }
        }

        ksort($starts);

        return array_values($starts);
    }

    /** @return Collection<int, array{court: VenueCourt, price: float}> */
    private function freeCourts(WhatsAppBotSession $s, Venue $venue, int $start): Collection
    {
        $date = Carbon::parse((string) $s->get('date'));
        $length = (int) $s->get('length', 60);
        $sport = $s->get('sport');

        return $this->courts($venue)
            ->filter(fn (VenueCourt $c) => $sport === null || $c->sportsList() === [] || in_array($sport, $c->sportsList(), true))
            ->filter(fn (VenueCourt $c) => $this->bookings->isCourtHourFree((int) $venue->id, (int) $c->id, $date->toDateString(), $start, $start + $length))
            ->map(fn (VenueCourt $c) => ['court' => $c, 'price' => $this->price($venue, $c, $date, $start, $length)])
            ->values();
    }

    /** What the booking engine will charge before tax: the court's rate at that time × length. */
    private function price(Venue $venue, VenueCourt $court, Carbon $date, int $start, int $length): float
    {
        return round($court->rateFor($date, self::hm($start), (int) ($venue->price ?? 0)) * $length / 60, 2);
    }

    // ---------------------------------------------------------------- plumbing

    /**
     * Ask a question with options: buttons for up to three, a list otherwise, and a
     * numbered text menu if the provider won't take either.
     *
     * @param  list<array{id: string, title: string, description?: string}>  $options
     */
    private function ask(WhatsAppBotSession $s, string $body, string $button, array $options): void
    {
        $s->put(['options' => array_map(fn (array $o) => ['id' => $o['id'], 'title' => $o['title']], $options)]);

        $sent = count($options) <= 3 && collect($options)->every(fn ($o) => mb_strlen($o['title']) <= 20)
            ? $this->whatsapp->sendButtons($s->phone, $body, $options, $this->context($s))
            : $this->whatsapp->sendList($s->phone, $body, $button, $options, $this->context($s));

        $menu = collect($options)->values()->map(fn ($o, $i) => ($i + 1).'. '.$o['title']
            .(! empty($o['description']) ? ' — '.$o['description'] : ''))->implode("\n");

        if (! $sent) {
            $sent = $this->whatsapp->sendMessage($s->phone, $body."\n\n".$menu."\n\nReply with a number.", $this->context($s));
        }

        $this->record($s, $body."\n".$menu, $sent);
    }

    private function say(WhatsAppBotSession $s, string $text): void
    {
        $sent = $this->whatsapp->sendMessage($s->phone, $text, $this->context($s));
        $this->record($s, $text, $sent);
    }

    /** They answered something we can't match: repeat the same question. */
    private function again(WhatsAppBotSession $s): void
    {
        $options = (array) $s->get('options', []);
        $menu = collect($options)->values()->map(fn ($o, $i) => ($i + 1).'. '.$o['title'])->implode("\n");

        $this->say($s, $menu !== ''
            ? "Please pick one of these (tap the list, or reply with its number):\n\n{$menu}\n\nType “menu” to start over."
            : 'Type “menu” to start a booking.');
    }

    /** The option id they picked: a tapped row, its number, or its exact title. */
    private function choice(WhatsAppBotSession $s, string $text, ?string $replyId): ?string
    {
        $options = (array) $s->get('options', []);
        $ids = array_column($options, 'id');

        if ($replyId !== null && in_array($replyId, $ids, true)) {
            return $replyId;
        }

        $t = trim($text);
        if (ctype_digit($t) && isset($options[(int) $t - 1])) {
            return $options[(int) $t - 1]['id'];
        }

        foreach ($options as $o) {
            if (mb_strtolower($o['title']) === mb_strtolower($t)) {
                return $o['id'];
            }
        }

        return null;
    }

    private function venue(WhatsAppBotSession $s): Venue
    {
        return Venue::query()->findOrFail($s->venue_id);
    }

    /**
     * The chat in the venue's WhatsApp Desk, so staff see the customer from the moment
     * they pick the venue.
     */
    private function openDeskChat(WhatsAppBotSession $s, Venue $venue): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::query()->firstOrCreate(
            ['venue_id' => $venue->id, 'phone_number' => $s->phone],
            ['partner_id' => $venue->partner_id, 'status' => 'active', 'unread_count' => 0],
        );

        $conversation->update([
            'window_expires_at' => now()->addHours(24),
            'last_message_at'   => now(),
        ]);

        return $conversation;
    }

    /** The customer's Haraan account by phone — or a phone-only one, as WhatsApp sign-in makes. */
    private function customer(WhatsAppBotSession $s): User
    {
        $existing = PartnerLookup::byPhone($s->phone);
        if ($existing !== null) {
            return $existing;
        }

        $name = WhatsAppConversation::query()->where('phone_number', $s->phone)->whereNotNull('customer_name')->value('customer_name');

        return User::query()->create([
            'phone'    => $s->phone,
            'name'     => $name ?: 'Member',
            'email'    => $s->phone.'@phone.haraan.local',
            'password' => bcrypt(Str::random(32)),
            'role'     => 'user',
            'status'   => 'active',
        ]);
    }

    /** Once there's a venue, the bot's side of the chat is visible in its desk too. */
    private function record(WhatsAppBotSession $s, string $body, bool $sent): void
    {
        if ($s->venue_id === null) {
            return;
        }

        $conversation = WhatsAppConversation::query()->where('venue_id', $s->venue_id)->where('phone_number', $s->phone)->first();
        if ($conversation === null) {
            return;
        }

        WhatsAppMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'sender_type'     => 'bot',
            'message_type'    => 'text',
            'body'            => mb_substr($body, 0, 4000),
            'delivery_status' => $sent ? 'sent' : 'failed',
        ]);
    }

    private function context(WhatsAppBotSession $s): MessageContext
    {
        $partnerId = $s->venue_id !== null ? Venue::query()->whereKey($s->venue_id)->value('partner_id') : null;

        return new MessageContext($partnerId !== null ? (int) $partnerId : null, MessageContext::SERVICE, 'bot.booking');
    }

    private static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private static function hm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** 1110 → "6:30 PM"; 1440 → "12 AM". */
    private static function clock(int $minutes): string
    {
        $h = intdiv($minutes, 60) % 24;
        $m = $minutes % 60;
        $h12 = $h % 12 === 0 ? 12 : $h % 12;

        return $h12.($m ? ':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '').' '.($h < 12 ? 'AM' : 'PM');
    }
}
