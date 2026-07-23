<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Venue;

/**
 * Builds a partner's booking report (their events + venues) for a date range.
 * Shared by the partner API (JWT) and the Filament "Reports" page (session) so
 * both produce identical CSVs.
 */
class BookingReport
{
    /** @return array<int, array<string, string>> */
    public static function rows(int $partnerId, string $from, string $to): array
    {
        $eventIds = Event::query()->where('partner_id', $partnerId)->pluck('id');
        $venueIds = Venue::query()->where('partner_id', $partnerId)->pluck('id');

        $bookings = Booking::query()
            ->where(function ($q) use ($eventIds, $venueIds): void {
                $q->whereIn('event_id', $eventIds)->orWhereIn('venue_id', $venueIds);
            })
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->with(['event:id,title', 'venue:id,name', 'user:id,name'])
            ->orderBy('created_at')
            ->get();

        return $bookings->map(fn (Booking $b): array => [
            'id'         => (string) $b->id,
            'booked_at'  => optional($b->created_at)->toDateTimeString() ?? '',
            'type'       => $b->event_id !== null ? 'Event' : 'Venue',
            'item'       => $b->event?->title ?? $b->venue?->name ?? '',
            'slot'       => (string) ($b->slot_label ?? ''),
            'slot_date'  => optional($b->slot_date)->toDateString() ?? '',
            'customer'   => (string) ($b->guest_name ?: ($b->user?->name ?? 'Guest')),
            'phone'      => (string) ($b->guest_phone ?? ''),
            'channel'    => (string) ($b->channel ?? 'online'),
            'quantity'   => (string) (int) $b->quantity,
            'amount'     => number_format((float) $b->total_amount, 2, '.', ''),
            'status'     => (string) $b->status,
            'checked_in' => (string) (int) $b->checked_in_count,
            'ticket'     => (string) ($b->ticket_code ?? ''),
        ])->all();
    }

    /** @return array<int, string> */
    public static function headers(): array
    {
        return [
            'Booking ID', 'Booked At', 'Type', 'Item', 'Slot', 'Slot Date',
            'Customer', 'Phone', 'Channel', 'Qty', 'Amount', 'Status', 'Checked In', 'Ticket',
        ];
    }

    /** Render the report as a CSV string. */
    public static function csv(int $partnerId, string $from, string $to): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::headers());

        foreach (self::rows($partnerId, $from, $to) as $row) {
            fputcsv($out, array_values($row));
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return (string) $csv;
    }
}
