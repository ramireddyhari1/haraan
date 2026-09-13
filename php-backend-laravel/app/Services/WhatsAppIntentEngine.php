<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\StandingContract;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class WhatsAppIntentEngine
{
    public function __construct(
        private readonly PricingMatrixService $pricingService,
    ) {}

    /**
     * Parse inbound message and resolve booking intent, availability and dynamic price.
     *
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
     *   calculated_rate: float,
     *   pricing_rule_applied: ?string,
     *   suggested_message: string
     * }
     */
    public function analyze(Venue $venue, string $text, ?string $customerName = null): array
    {
        $normalized = mb_strtolower(trim($text));

        // 1. Detect Intent Type
        $intentType = $this->detectIntentType($normalized);

        // 2. Extract Entities
        $sport = $this->extractSport($normalized);
        $date = $this->extractDate($normalized);
        $times = $this->extractTimeRange($normalized);
        $courtName = $this->extractCourtName($normalized);

        $startTime = $times['start'];
        $endTime = $times['end'];
        $durationMinutes = $times['duration'];

        // 3. Resolve Court
        $resolvedCourt = $this->resolveCourt($venue, $sport, $courtName);

        // 4. Resolve Slot & Availability
        $isAvailable = false;
        $resolvedSlotId = null;
        $calculatedRate = 0.0;
        $pricingRuleName = null;

        if ($resolvedCourt !== null && $date !== null && $startTime !== null) {
            $dateCarbon = Carbon::parse($date);
            $isAvailable = $this->checkCourtAvailability($venue, $resolvedCourt, $dateCarbon, $startTime, $endTime);

            // Find matching slot if exists
            $slot = VenueSlot::where('venue_id', $venue->id)
                ->where('venue_court_id', $resolvedCourt->id)
                ->where('start_time', '<=', $startTime)
                ->where('end_time', '>=', $endTime)
                ->first();
            $resolvedSlotId = $slot?->id;

            // Compute dynamic pricing
            $hours = max(1.0, $durationMinutes / 60.0);
            $pricing = $this->pricingService->resolveRate(
                $resolvedCourt,
                $dateCarbon,
                $startTime,
                (int) ($venue->price_per_hour ?? 800)
            );
            $calculatedRate = (float) round($pricing['amount'] * $hours);
            $pricingRuleName = $pricing['rule_name'];
        }

        // Calculate confidence score
        $confidence = 0.0;
        if ($intentType === 'booking_enquiry') {
            $confidence = 0.4;
            if ($date !== null) $confidence += 0.25;
            if ($startTime !== null) $confidence += 0.25;
            if ($resolvedCourt !== null) $confidence += 0.1;
        } elseif ($intentType === 'pricing_query') {
            $confidence = 0.85;
        } else {
            $confidence = 0.6;
        }
        $confidence = min(1.0, $confidence);

        // Generate suggested reply
        $nameGreeting = $customerName ? "Hi {$customerName}!" : "Hello!";
        $courtLabel = $resolvedCourt ? $resolvedCourt->name : ($venue->name . " court");
        $dateFormatted = $date ? Carbon::parse($date)->format('D, d M') : 'your requested date';
        $timeFormatted = $startTime ? Carbon::parse($startTime)->format('h:i A') . ' - ' . Carbon::parse($endTime)->format('h:i A') : 'the requested time';

        if ($isAvailable) {
            $suggestedMessage = "{$nameGreeting} {$courtLabel} is available on {$dateFormatted} ({$timeFormatted}) for ₹" . number_format($calculatedRate) . ". Would you like me to hold this slot for 2 minutes and send the payment link?";
        } elseif ($resolvedCourt !== null && $date !== null && $startTime !== null) {
            $suggestedMessage = "{$nameGreeting} Unfortunately {$courtLabel} is booked on {$dateFormatted} at {$timeFormatted}. Would you like me to check the next available slot?";
        } else {
            $suggestedMessage = "{$nameGreeting} Thanks for reaching out to {$venue->name}! Could you please share your preferred date and time so we can check court availability?";
        }

        return [
            'intent_type'               => $intentType,
            'confidence'                => $confidence,
            'detected_sport'            => $sport,
            'detected_date'             => $date,
            'detected_start_time'       => $startTime,
            'detected_end_time'         => $endTime,
            'detected_duration_minutes' => $durationMinutes,
            'detected_court_name'       => $courtName,
            'resolved_court_id'         => $resolvedCourt?->id,
            'resolved_court_name'       => $resolvedCourt?->name,
            'resolved_slot_id'          => $resolvedSlotId,
            'is_available'              => $isAvailable,
            'calculated_rate'           => $calculatedRate,
            'pricing_rule_applied'      => $pricingRuleName,
            'suggested_message'         => $suggestedMessage,
        ];
    }

    private function detectIntentType(string $text): string
    {
        if (preg_match('/\b(book|slot|court|turf|available|play|reserve|pitch|ground)\b/i', $text)) {
            return 'booking_enquiry';
        }
        if (preg_match('/\b(price|rate|cost|charges|fee|pricing|how much)\b/i', $text)) {
            return 'pricing_query';
        }
        if (preg_match('/\b(cancel|refund|drop)\b/i', $text)) {
            return 'cancellation';
        }
        if (preg_match('/\b(reschedule|postpone|change time|shift slot)\b/i', $text)) {
            return 'reschedule';
        }
        return 'general_faq';
    }

    private function extractSport(string $text): ?string
    {
        $sports = [
            'cricket'    => ['cricket', 'box cricket', 'turf cricket', 'nets'],
            'football'   => ['football', 'futsal', 'soccer'],
            'badminton'  => ['badminton', 'shuttle'],
            'tennis'     => ['tennis', 'lawn tennis'],
            'basketball' => ['basketball', 'hoop'],
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

    private function extractDate(string $text): ?string
    {
        $today = Carbon::today();

        if (str_contains($text, 'today') || str_contains($text, 'tonight')) {
            return $today->toDateString();
        }
        if (str_contains($text, 'tomorrow')) {
            return $today->copy()->addDay()->toDateString();
        }
        if (str_contains($text, 'day after tomorrow')) {
            return $today->copy()->addDays(2)->toDateString();
        }

        // Check for days of week: monday, tuesday, etc.
        $days = [
            'monday'    => Carbon::MONDAY,
            'tuesday'   => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday'  => Carbon::THURSDAY,
            'friday'    => Carbon::FRIDAY,
            'saturday'  => Carbon::SATURDAY,
            'sunday'    => Carbon::SUNDAY,
        ];

        foreach ($days as $dayName => $carbonDay) {
            if (preg_match('/\b(this|next)?\s*' . $dayName . '\b/i', $text)) {
                $target = $today->copy()->next($carbonDay);
                return $target->toDateString();
            }
        }

        // Check for date formats like "15th sep", "15 sep", "15 September", "2026-09-15"
        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b/i', $text, $m)) {
            $parsed = Carbon::parse("{$m[1]} {$m[2]} {$today->year}");
            if ($parsed->isPast() && $parsed->diffInDays($today) > 30) {
                $parsed->addYear();
            }
            return $parsed->toDateString();
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m)) {
            return $m[1];
        }

        return $today->toDateString(); // Default to today if booking enquiry without date
    }

    /**
     * @return array{start: ?string, end: ?string, duration: int}
     */
    private function extractTimeRange(string $text): array
    {
        $duration = 60; // default 1 hour in minutes
        if (preg_match('/\b(\d+)\s*(?:hrs?|hours?)\b/i', $text, $dm)) {
            $duration = (int) $dm[1] * 60;
        } elseif (preg_match('/\b90\s*(?:mins?|minutes?)\b/i', $text)) {
            $duration = 90;
        }

        // Match time patterns like: "6pm to 7pm", "6-7 pm", "6:00 pm", "18:00 - 19:00", "7 pm", "at 6"
        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s*(?:to|-)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $text, $m)) {
            $startH = (int) $m[1];
            $startM = (int) ($m[2] ?? 0);
            $startMeridian = $m[3] ? strtolower($m[3]) : strtolower($m[6]);
            if ($startMeridian === 'pm' && $startH < 12) $startH += 12;
            if ($startMeridian === 'am' && $startH === 12) $startH = 0;

            $endH = (int) $m[4];
            $endM = (int) ($m[5] ?? 0);
            $endMeridian = strtolower($m[6]);
            if ($endMeridian === 'pm' && $endH < 12) $endH += 12;
            if ($endMeridian === 'am' && $endH === 12) $endH = 0;

            $startFormatted = sprintf('%02d:%02d:00', $startH, $startM);
            $endFormatted = sprintf('%02d:%02d:00', $endH, $endM);
            $diffMins = ($endH * 60 + $endM) - ($startH * 60 + $startM);
            if ($diffMins > 0) $duration = $diffMins;

            return ['start' => $startFormatted, 'end' => $endFormatted, 'duration' => $duration];
        }

        // Single time match like "6pm", "6:30 pm", "18:00"
        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $text, $m)) {
            $h = (int) $m[1];
            $min = (int) ($m[2] ?? 0);
            $meridian = strtolower($m[3]);
            if ($meridian === 'pm' && $h < 12) $h += 12;
            if ($meridian === 'am' && $h === 12) $h = 0;

            $startFormatted = sprintf('%02d:%02d:00', $h, $min);
            $endTimeObj = Carbon::createFromTime($h, $min, 0)->addMinutes($duration);
            $endFormatted = $endTimeObj->format('H:i:s');

            return ['start' => $startFormatted, 'end' => $endFormatted, 'duration' => $duration];
        }

        // Check for 24-hour pattern "18:00"
        if (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $text, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            $startFormatted = sprintf('%02d:%02d:00', $h, $min);
            $endTimeObj = Carbon::createFromTime($h, $min, 0)->addMinutes($duration);
            $endFormatted = $endTimeObj->format('H:i:s');

            return ['start' => $startFormatted, 'end' => $endFormatted, 'duration' => $duration];
        }

        return ['start' => '18:00:00', 'end' => '19:00:00', 'duration' => 60]; // Sensible default evening slot
    }

    private function extractCourtName(string $text): ?string
    {
        if (preg_match('/\b(court|turf|pitch)\s*([a-z0-9]+)\b/i', $text, $m)) {
            return ucfirst($m[1]) . ' ' . strtoupper($m[2]);
        }
        return null;
    }

    private function resolveCourt(Venue $venue, ?string $sport, ?string $courtName): ?VenueCourt
    {
        $query = VenueCourt::where('venue_id', $venue->id)->where('is_active', true);

        if ($courtName !== null) {
            $byName = (clone $query)->where('name', 'LIKE', '%' . $courtName . '%')->first();
            if ($byName) return $byName;
        }

        if ($sport !== null) {
            $courts = (clone $query)->get();
            foreach ($courts as $court) {
                if (in_array($sport, $court->sportsList(), true)) {
                    return $court;
                }
            }
        }

        return $query->orderBy('sort_order')->orderBy('id')->first();
    }

    public function checkCourtAvailability(
        Venue $venue,
        VenueCourt $court,
        Carbon $date,
        string $startTime,
        string $endTime
    ): bool {
        // 1. Check existing confirmed bookings OR active holds (reserved_until in future)
        $conflicts = Booking::where('venue_id', $venue->id)
            ->where(function ($q) use ($court) {
                $q->where('venue_court_id', $court->id);
                // Also check parent/child court relationships
                if ($court->parent_court_id !== null) {
                    $q->orWhere('venue_court_id', $court->parent_court_id);
                } else {
                    $childIds = VenueCourt::where('parent_court_id', $court->id)->pluck('id')->all();
                    if (! empty($childIds)) {
                        $q->orWhereIn('venue_court_id', $childIds);
                    }
                }
            })
            ->whereDate('slot_date', $date->toDateString())
            ->where(function ($q) {
                // Confirmed or Checked In
                $q->whereIn('status', ['confirmed', 'checked_in'])
                  // OR Active temporary hold (reserved_until is still in the future)
                  ->orWhere(function ($holdQ) {
                      $holdQ->where('status', 'hold')
                            ->where('reserved_until', '>', now());
                  });
            })
            ->where(function ($timeQ) use ($startTime, $endTime) {
                $timeQ->where(function ($overlap) use ($startTime, $endTime) {
                    $overlap->where('start_time', '<', $endTime)
                            ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($conflicts) {
            return false;
        }

        // 2. Check Standing Slot recurring contracts
        $dayOfWeek = $date->dayOfWeekIso; // 1 (Mon) to 7 (Sun)
        $contractConflict = StandingContract::where('venue_id', $venue->id)
            ->where('venue_court_id', $court->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'active')
            ->where('start_date', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date->toDateString());
            })
            ->where(function ($timeQ) use ($startTime, $endTime) {
                $timeQ->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })
            ->exists();

        return ! $contractConflict;
    }
}