<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The WhatsApp Desk moves onto the real booking engine. Data only — no column changes
 * (on SQLite a column change rebuilds the table and cascades deletes; see the venues
 * incident).
 *
 *  1. Desk holds were their own booking_type ('venue_slot') with lowercase statuses
 *     ('hold', 'confirmed', 'cancelled') that nothing else in the system recognised: the
 *     app and web checkout never saw a desk hold, and the desk never saw an app booking.
 *     They become ordinary venue bookings with the statuses everything else reads.
 *  2. Payment links were invented (a made-up `haraan.app/pay/…` URL and `plink_…` id);
 *     those rows are voided so nothing treats them as money owed.
 *  3. Quick replies were auto-seeded per venue with made-up rates and an address that
 *     isn't any venue's. Those rows go; platform defaults, filled from each venue's real
 *     details and editable in /control, replace them.
 */
return new class extends Migration {
    /** The bodies the old controller seeded into every venue that opened the desk. */
    private const INVENTED_REPLY_MARKERS = [
        '124 Main Sports Hub',
        'Mon to Thu: ₹800/hr',
        'Non-marking turf shoes only (no metal studs)',
        'your 2-minute slot hold for {{court_name}}',
    ];

    public function up(): void
    {
        if (Schema::hasTable('bookings')) {
            $desk = fn () => DB::table('bookings')->where('channel', 'whatsapp');

            $desk()->where('booking_type', 'venue_slot')->update(['booking_type' => 'venue']);

            $desk()->whereRaw('lower(status) = ?', ['hold'])
                ->where(fn ($q) => $q->whereNull('reserved_until')->orWhere('reserved_until', '<=', now()))
                ->update(['status' => 'EXPIRED', 'reserved_until' => null]);
            $desk()->whereRaw('lower(status) = ?', ['hold'])->update(['status' => 'PENDING']);
            $desk()->whereRaw('status = ?', ['confirmed'])->update(['status' => 'CONFIRMED']);
            $desk()->whereRaw('status = ?', ['cancelled'])->update(['status' => 'CANCELLED']);
        }

        if (Schema::hasTable('whatsapp_payment_links')) {
            DB::table('whatsapp_payment_links')
                ->where('short_url', 'like', 'https://haraan.app/pay/%')
                ->update(['status' => 'cancelled']);
        }

        if (Schema::hasTable('whatsapp_conversations')) {
            // A chat pointing at a hold that is no longer live has no hold.
            DB::table('whatsapp_conversations')
                ->where('status', 'hold_active')
                ->where(fn ($q) => $q->whereNull('active_booking_id')
                    ->orWhereNotIn('active_booking_id', DB::table('bookings')->whereRaw('upper(status) = ?', ['PENDING'])->select('id')))
                ->update(['status' => 'active', 'active_booking_id' => null]);
        }

        if (! Schema::hasTable('whatsapp_quick_replies')) {
            return;
        }

        foreach (self::INVENTED_REPLY_MARKERS as $marker) {
            DB::table('whatsapp_quick_replies')
                ->whereNotNull('venue_id')
                ->where('body', 'like', '%'.$marker.'%')
                ->delete();
        }

        if (DB::table('whatsapp_quick_replies')->whereNull('venue_id')->exists()) {
            return;
        }

        $now = now();
        DB::table('whatsapp_quick_replies')->insert(array_map(fn (array $r) => $r + [
            'venue_id'   => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            ['shortcut' => '/rates', 'category' => 'Pricing', 'title' => 'Rate card',
                'body' => "Hi {{customer_name}}! Our rates at {{venue_name}}:\n{{rate_card}}"],
            ['shortcut' => '/location', 'category' => 'Directions', 'title' => 'How to find us',
                'body' => "{{venue_name}}\n{{venue_address}}\n{{maps_url}}"],
            ['shortcut' => '/hours', 'category' => 'General', 'title' => 'Opening hours',
                'body' => '{{venue_name}} is open {{venue_hours}}.'],
            ['shortcut' => '/rules', 'category' => 'Rules', 'title' => 'Venue rules',
                'body' => "Before you play at {{venue_name}}:\n{{venue_rules}}"],
            ['shortcut' => '/pay', 'category' => 'Booking', 'title' => 'Payment reminder',
                'body' => 'Hi {{customer_name}}, your slot is held for {{hold_minutes}} minutes. Tap the payment link above to confirm it.'],
        ]));
    }

    public function down(): void
    {
        // Data normalisation; the old values were the bug.
    }
};
