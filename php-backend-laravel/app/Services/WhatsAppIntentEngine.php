<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StandingContract;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use App\Models\VenueCourt;
use App\Support\BusinessClock;
use Illuminate\Support\Carbon;

/**
 * Reads a customer's WhatsApp message for a booking: what they want (book, price,
 * cancel…), and — when they said it — the sport, day, time and court.
 *
 * It only reports what the message actually contains. A detail the customer didn't
 * give comes back null, never a guess: a suggestion card that says "today, 6 PM" for
 * a message that said "hi" is a card staff learn to ignore, or worse, hold from.
 * The desk shows what's missing and staff fill it in.
 *
 * Prices and availability come from the same places the booking engine uses
 * ({@see VenueCourt::rateFor()} and {@see BookingService::isCourtHourFree()}), so the
 * rate on the card is the rate the hold will charge.
 */
final class WhatsAppIntentEngine
{
    public function __construct(
        private readonly BookingService $bookings,
    ) {}

    /**
     * @return array{
     *   intent_type: string,
     *   confidence: float,
     *   detected_sport: ?string,
     *   detected_date: ?string,
     *   detected_start_time: ?string,
     *   detected_end_time: ?string,
     *   detected_duration_minutes: int,
     *   detected_court_name: ?string,
     *   resolved_court_id: ?int,
     *   resolved_court_name: ?string,
     *   resolved_slot_id: ?int,
     *   is_available: bool,
     *   calculated_rate: ?float,
     *   suggested_message: string
     * }
     */
    public function analyze(Venue $venue, string $text, ?string $customerName = null): array
    {
        $normalized = mb_strtolower(trim($text));

        $intentType = $this->detectIntentType($normalized);
        $sport = $this->extractSport($normalized);
        $date = $this->extractDate($normalized);
        $times = $this->extractTimeRange($normalized);
        $courtName = $this->extractCourtName($normalized);

        $startTime = $times['start'];
        $endTime = $times['end'];
        $durationMinutes = $times['duration'];

        $court = $this->resolveCourt($venue, $sport, $courtName);

        $isAvailable = false;
        $rate = null;

        if ($court !== null && $date !== null && $startTime !== null && $endTime !== null) {
            $day = Carbon::parse($date);
            $isAvailable = $this->checkCourtAvailability($venue, $court, $day, $startTime, $endTime);
            $perHour = $court->rateFor($day, substr($startTime, 0, 5), (int) ($venue->price ?? 0));
            $rate = round($perHour * $durationMinutes / 60, 2);
        }

        $confidence = match ($intentType) {
            'booking_enquiry' => 0.4 + ($date !== null ? 0.2 : 0) + ($startTime !== null ? 0.25 : 0) + ($court !== null ? 0.15 : 0),
            'pricing_query'   => 0.85,
            default           => 0.6,
        };

        return [
            'intent_type'               => $intentType,
            'confidence'                => min(1.0, $confidence),
            'detected_sport'            => $sport,
            'detected_date'             => $date,
            'detected_start_time'       => $startTime,
            'detected_end_time'         => $endTime,
            'detected_duration_minutes' => $durationMinutes,
            'detected_court_name'       => $courtName,
            'resolved_court_id'         => $court?->id,
            'resolved_court_name'       => $court?->name,
            // Desk holds book a court and a time, not a slot template.
            'resolved_slot_id'          => null,
            'is_available'              => $isAvailable,
            'calculated_rate'           => $rate,
            'suggested_message'         => $this->suggestedReply($venue, $customerName, $court, $date, $startTime, $endTime, $isAvailable, $rate),
        ];
    }

    /**
     * Can this court be held for this window? Venue open that day, no closed date, no
     * live booking or hold on the court (or a court sharing its ground), no block, and
     * no standing booking that hasn't been written out as a row yet.
     */
    public function checkCourtAvailability(Venue $venue, VenueCourt $court, Carbon $date, string $startTime, string $endTime): bool
    {
        $startMin = BookingService::timeToMinutes(substr($startTime, 0, 5));
        $endMin = BookingService::endMinutes(substr($endTime, 0, 5));

        if ($startMin === null || $endMin === null || $endMin <= $startMin) {
            return false;
        }

        if (! $venue->isOpenOn($date)) {
            return false;
        }

        if (VenueBlockedDate::query()->where('venue_id', $venue->id)->whereDate('date', $date->toDateString())->exists()) {
            return false;
        }

        if (! $this->bookings->isCourtHourFree((int) $venue->id, (int) $court->id, $date->toDateString(), $startMin, $endMin)) {
            return false;
        }

        return ! StandingContract::query()
            ->where('venue_id', $venue->id)
            ->where('venue_court_id', $court->id)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->where('status', 'active')
            ->where('start_date', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date->toDateString()))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
    }

    private function suggestedReply(Venue $venue, ?string $name, ?VenueCourt $court, ?string $date, ?string $start, ?string $end, bool $available, ?float $rate): string
    {
        $hello = $name ? "Hi {$name}!" : 'Hello!';

        if ($court === null || $date === null || $start === null || $end === null) {
            return "{$hello} Thanks for messaging {$venue->name}. Which day and time would you like to play?";
        }

        $when = Carbon::parse($date)->format('D, d M').', '
            .Carbon::parse($start)->format('g:i A').' – '.Carbon::parse($end)->format('g:i A');

        if (! $available) {
            return "{$hello} {$court->name} is already booked on {$when}. Shall I check another time?";
        }

        return "{$hello} {$court->name} is free on {$when} for ₹".number_format((float) $rate)
            .'. Shall I hold it for you and send the payment link?';
    }

    private function detectIntentType(string $text): string
    {
        return match (true) {
            (bool) preg_match('/\b(cancel|refund)\b/i', $text) => 'cancellation',
            (bool) preg_match('/\b(reschedule|postpone|change (the )?time|shift (my )?slot)\b/i', $text) => 'reschedule',
            (bool) preg_match('/\b(book|slot|court|turf|available|availability|play|reserve|pitch|ground|free)\b/i', $text) => 'booking_enquiry',
            (bool) preg_match('/\b(price|prices|rate|rates|cost|charges|fee|pricing|how much)\b/i', $text) => 'pricing_query',
            default => 'general_faq',
        };
    }

    private function extractSport(string $text): ?string
    {
        $sports = [
            'cricket'    => ['cricket', 'nets'],
            'football'   => ['football', 'futsal', 'soccer'],
            'badminton'  => ['badminton', 'shuttle'],
            'tennis'     => ['tennis'],
            'basketball' => ['basketball'],
            'pickleball' => ['pickleball'],
            'volleyball' => ['volleyball'],
        ];

        foreach ($sports as $sport => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($text, $alias)) {
                    return $sport;
                }
            }
        }

        return null;
    }

    /** The day the customer named, on the venue's calendar — or null if they named none. */
    private function extractDate(string $text): ?string
    {
        $today = BusinessClock::todayDate();

        // Longest phrase first: "day after tomorrow" also contains "tomorrow".
        if (str_contains($text, 'day after tomorrow')) {
            return $today->copy()->addDays(2)->toDateString();
        }
        if (str_contains($text, 'tomorrow') || str_contains($text, 'tmrw') || str_contains($text, 'tmr')) {
            return $today->copy()->addDay()->toDateString();
        }
        if (str_contains($text, 'today') || str_contains($text, 'tonight')) {
            return $today->toDateString();
        }

        $days = [
            'monday' => Carbon::MONDAY, 'tuesday' => Carbon::TUESDAY, 'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY, 'friday' => Carbon::FRIDAY, 'saturday' => Carbon::SATURDAY,
            'sunday' => Carbon::SUNDAY,
        ];

        foreach ($days as $dayName => $weekday) {
            if (preg_match('/\b(next\s+)?'.$dayName.'\b/i', $text, $m)) {
                $target = $today->dayOfWeek === $weekday && empty($m[1])
                    ? $today->copy()
                    : $today->copy()->next($weekday);

                return $target->toDateString();
            }
        }

        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b/i', $text, $m)) {
            try {
                $parsed = Carbon::parse("{$m[1]} {$m[2]} {$today->year}");
            } catch (\Throwable) {
                return null;
            }
            // "5 Jan" said in December is next year.
            if ($parsed->lt($today->copy()->subDays(30))) {
                $parsed->addYear();
            }

            return $parsed->toDateString();
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return array{start: ?string, end: ?string, duration: int}
     */
    private function extractTimeRange(string $text): array
    {
        $duration = 60;
        if (preg_match('/\b(\d+(?:\.5)?)\s*(?:hrs?|hours?)\b/i', $text, $dm)) {
            $duration = (int) round((float) $dm[1] * 60);
        } elseif (preg_match('/\b(\d{2,3})\s*(?:mins?|minutes?)\b/i', $text, $dm)) {
            $duration = (int) $dm[1];
        }
        $duration = max(30, min(12 * 60, $duration));

        // "6pm to 7pm", "6-7 pm", "6:30 - 8 pm"
        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s*(?:to|-|–)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $text, $m)) {
            $endMer = strtolower($m[6]);
            $startMer = $m[3] !== '' ? strtolower($m[3]) : $endMer;
            $startMin = $this->to24((int) $m[1], $startMer) * 60 + (int) ($m[2] ?: 0);
            $endMin = $this->to24((int) $m[4], $endMer) * 60 + (int) ($m[5] ?: 0);

            // "11-1 pm" means 11 AM to 1 PM.
            if ($m[3] === '' && $endMin <= $startMin && $startMin >= 12 * 60) {
                $startMin -= 12 * 60;
            }

            if ($endMin > $startMin) {
                return ['start' => $this->hms($startMin), 'end' => $this->hms($endMin), 'duration' => $endMin - $startMin];
            }
        }

        // "6pm", "6:30 pm"
        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $text, $m)) {
            $startMin = $this->to24((int) $m[1], strtolower($m[3])) * 60 + (int) ($m[2] ?: 0);

            return $this->window($startMin, $duration);
        }

        // "18:00"
        if (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $text, $m)) {
            return $this->window((int) $m[1] * 60 + (int) $m[2], $duration);
        }

        return ['start' => null, 'end' => null, 'duration' => $duration];
    }

    /** @return array{start: ?string, end: ?string, duration: int} */
    private function window(int $startMin, int $duration): array
    {
        // A booking can't run past midnight; don't suggest one that would.
        if ($startMin + $duration > 24 * 60) {
            return ['start' => $this->hms($startMin), 'end' => null, 'duration' => $duration];
        }

        return ['start' => $this->hms($startMin), 'end' => $this->hms($startMin + $duration), 'duration' => $duration];
    }

    private function to24(int $hour, string $meridian): int
    {
        $hour %= 12;

        return $meridian === 'pm' ? $hour + 12 : $hour;
    }

    private function hms(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    private function extractCourtName(string $text): ?string
    {
        if (preg_match('/\b(court|turf|pitch|net)\s*(?:no\.?\s*)?([a-z]|\d{1,2})\b/i', $text, $m)) {
            return ucfirst(strtolower($m[1])).' '.strtoupper($m[2]);
        }

        return null;
    }

    /**
     * The court the customer means: named outright, or the only one that runs their
     * sport, or the only court the venue has. Anything more ambiguous is left for staff.
     */
    private function resolveCourt(Venue $venue, ?string $sport, ?string $courtName): ?VenueCourt
    {
        $courts = VenueCourt::query()
            ->where('venue_id', $venue->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($courtName !== null) {
            $needle = mb_strtolower($courtName);
            $byName = $courts->first(fn (VenueCourt $c): bool => str_contains(mb_strtolower($c->name), $needle));
            if ($byName !== null) {
                return $byName;
            }
        }

        if ($sport !== null) {
            $forSport = $courts->filter(fn (VenueCourt $c): bool => in_array($sport, $c->sportsList(), true));
            if ($forSport->count() === 1) {
                return $forSport->first();
            }
        }

        return $courts->count() === 1 ? $courts->first() : null;
    }
}
