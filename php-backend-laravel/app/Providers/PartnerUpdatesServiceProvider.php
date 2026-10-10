<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PartnerManager;
use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;
use App\Models\PayoutBatch;
use App\Models\PartnerPayoutAccount;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\PartnerUpdates;
use Illuminate\Support\ServiceProvider;

/**
 * Everything Haraan staff change on a partner's account in /control, turned into a
 * partner update (live in the app, push, WhatsApp — see PartnerUpdates). One place,
 * so a new admin screen editing these models is covered without remembering to call
 * anything. PartnerUpdates itself drops changes the partner made, and console work.
 *
 * The settlement ACCOUNT is told from PayoutAccountEditor (it knows verify from edit);
 * everything else is watched here.
 */
final class PartnerUpdatesServiceProvider extends ServiceProvider
{
    /** Venue columns a partner would care about, in their words. */
    private const VENUE_FIELDS = [
        'name' => 'name',
        'address' => 'address', 'location' => 'address', 'city' => 'address',
        'latitude' => 'map pin', 'longitude' => 'map pin', 'map_link' => 'map pin', 'place_id' => 'map pin',
        'price' => 'prices', 'price_chart' => 'prices', 'price_note' => 'prices',
        'fees' => 'fees', 'convenience_fee_type' => 'fees', 'convenience_fee_value' => 'fees',
        'hours' => 'opening hours', 'hours_json' => 'opening hours', 'slot_minutes' => 'slot length',
        'images' => 'photos',
        'about' => 'details', 'rules' => 'rules', 'amenities' => 'amenities', 'tagline' => 'details', 'sports' => 'sports',
        'booking_window_days' => 'booking window',
        'cancel_free_hours' => 'cancellation policy', 'cancel_refund_percent' => 'cancellation policy',
    ];

    public function boot(): void
    {
        // ── Settlements ───────────────────────────────────────────────────────
        PayoutBatch::created(function (PayoutBatch $b): void {
            if ($b->isPaid()) {
                self::batchPaid($b);

                return;
            }
            PartnerUpdates::record(
                (int) $b->partner_id, 'payout_batch.started',
                'Settlement of ' . PartnerUpdates::inr($b->amount) . ' started',
                'Haraan is sending it to ' . self::destination((int) $b->partner_id) . '. You get the bank reference (UTR) once it lands.',
                'payouts', $b,
            );
        });

        PayoutBatch::updated(function (PayoutBatch $b): void {
            if ($b->wasChanged('status')) {
                if ($b->isPaid()) {
                    self::batchPaid($b);
                } elseif (strtolower((string) $b->status) === 'failed') {
                    PartnerUpdates::record(
                        (int) $b->partner_id, 'payout_batch.failed',
                        'Settlement of ' . PartnerUpdates::inr($b->amount) . ' failed',
                        trim(($b->note ? $b->note . ' ' : '') . 'The money is back in your balance and goes out with the next settlement.'),
                        'payouts', $b,
                    );
                }

                return;
            }
            if ($b->wasChanged('amount') && ! $b->isPaid()) {
                PartnerUpdates::record(
                    (int) $b->partner_id, 'payout_batch.started',
                    'Settlement changed to ' . PartnerUpdates::inr($b->amount),
                    'Going to ' . self::destination((int) $b->partner_id) . '.',
                    'payouts', $b,
                );
            }
        });

        // ── Your Haraan manager ───────────────────────────────────────────────
        PartnerManager::saved(function (PartnerManager $m): void {
            if (! $m->wasChanged('manager_id') && ! ($m->wasChanged('is_visible') && $m->is_visible) && ! $m->wasRecentlyCreated) {
                return;
            }
            if ($m->is_visible === false || $m->manager === null) {
                return;
            }
            PartnerUpdates::record(
                (int) $m->partner_id, 'manager.assigned',
                $m->manager->name . ' is now your Haraan manager',
                'Call or message them from the Venues tab whenever you need Haraan.',
                'venues', $m,
            );
        });

        // ── Venues ────────────────────────────────────────────────────────────
        Venue::updated(function (Venue $v): void {
            if (! $v->partner_id) {
                return;
            }
            $changed = array_keys($v->getChanges());
            $status = null;
            if (in_array('status', $changed, true) || in_array('is_bookable', $changed, true) || in_array('is_active', $changed, true)) {
                $status = match (true) {
                    $v->wasChanged('is_active') && ! $v->is_active => 'is turned off on Haraan',
                    $v->wasChanged('is_bookable') && ! $v->is_bookable => 'stopped taking online bookings',
                    $v->wasChanged('is_bookable') && $v->is_bookable => 'is taking online bookings again',
                    $v->wasChanged('status') && $v->status === 'published' => 'is live on Haraan',
                    $v->wasChanged('status') => 'is now ' . str_replace('_', ' ', (string) $v->status),
                    default => null,
                };
            }
            $labels = array_values(array_unique(array_filter(array_map(fn ($c) => self::VENUE_FIELDS[$c] ?? null, $changed))));
            if ($status === null && $labels === []) {
                return;
            }
            PartnerUpdates::record(
                (int) $v->partner_id, 'venue.updated',
                $status !== null ? $v->name . ' ' . $status : 'Haraan updated ' . $v->name,
                $labels !== [] ? 'Changed: ' . implode(', ', $labels) . '.' : null,
                'venues', $v,
            );
        });

        $courtChanged = function (VenueCourt $c, string $what): void {
            $venue = Venue::query()->find($c->venue_id);
            if ($venue === null || ! $venue->partner_id) {
                return;
            }
            PartnerUpdates::record(
                (int) $venue->partner_id, 'venue.courts',
                'Haraan updated courts at ' . $venue->name,
                $c->name . ': ' . $what . '.',
                'venues', $venue,
            );
        };
        VenueCourt::created(fn (VenueCourt $c) => $courtChanged($c, 'added'));
        VenueCourt::deleted(fn (VenueCourt $c) => $courtChanged($c, 'removed'));
        VenueCourt::updated(function (VenueCourt $c) use ($courtChanged): void {
            $parts = [];
            if ($c->wasChanged('price')) {
                $parts[] = 'price ' . PartnerUpdates::inr($c->price) . '/hr';
            }
            if ($c->wasChanged(['peak_price', 'peak_days', 'peak_start', 'peak_end'])) {
                $parts[] = 'peak pricing changed';
            }
            if ($c->wasChanged('is_active')) {
                $parts[] = $c->is_active ? 'turned on' : 'turned off';
            }
            if ($c->wasChanged('name')) {
                $parts[] = 'renamed';
            }
            if ($c->wasChanged('sports')) {
                $parts[] = 'sports changed';
            }
            if ($parts !== []) {
                $courtChanged($c, implode(', ', $parts));
            }
        });

        $slotsChanged = function (VenueSlot $s): void {
            $venue = Venue::query()->find($s->venue_id);
            if ($venue === null || ! $venue->partner_id) {
                return;
            }
            PartnerUpdates::record(
                (int) $venue->partner_id, 'venue.slots',
                'Haraan updated time slots at ' . $venue->name,
                'Slot times or prices changed. Your day grid already shows the new ones.',
                'venues', $venue,
            );
        };
        VenueSlot::created($slotsChanged);
        VenueSlot::deleted($slotsChanged);
        VenueSlot::updated(function (VenueSlot $s) use ($slotsChanged): void {
            if ($s->wasChanged(['time', 'price', 'court_prices', 'is_available', 'day', 'sports'])) {
                $slotsChanged($s);
            }
        });

        // ── The partner's own account ─────────────────────────────────────────
        User::updated(function (User $u): void {
            // Users save constantly (last-seen heartbeat): leave before any role lookup.
            if (! $u->wasChanged(['status', 'name', 'phone', 'email', 'avatar'])) {
                return;
            }
            if ($u->parent_partner_id !== null || ! $u->hasRoleEither(['PARTNER'])) {
                return;
            }
            if ($u->wasChanged('status')) {
                $active = strtoupper((string) $u->status) === 'ACTIVE';
                PartnerUpdates::record(
                    (int) $u->id, 'account.status',
                    $active ? 'Your partner account is active again' : 'Your partner account was suspended',
                    $active ? 'Bookings and the partner app work as before.' : 'Contact your Haraan manager to sort it out.',
                    'account', $u,
                );
            }
            $fields = array_values(array_filter([
                $u->wasChanged('name') ? 'name' : null,
                $u->wasChanged('phone') ? 'phone number' : null,
                $u->wasChanged('email') ? 'email' : null,
                $u->wasChanged('avatar') ? 'photo' : null,
            ]));
            if ($fields !== []) {
                PartnerUpdates::record(
                    (int) $u->id, 'account.profile',
                    'Haraan updated your profile',
                    'Changed: ' . implode(', ', $fields) . '.',
                    'account', $u,
                );
            }
        });

        // ── Plan ──────────────────────────────────────────────────────────────
        PartnerSubscription::saved(function (PartnerSubscription $s): void {
            if (! $s->wasRecentlyCreated && ! $s->wasChanged(['plan_id', 'status', 'current_period_end'])) {
                return;
            }
            $plan = PartnerPlan::query()->find($s->plan_id);
            PartnerUpdates::record(
                (int) $s->partner_id, 'plan.changed',
                'Your Haraan plan: ' . ($plan?->name ?? 'updated'),
                trim('Status: ' . str_replace('_', ' ', (string) $s->status)
                    . ($s->current_period_end ? '. Renews ' . $s->current_period_end->format('d M Y') : '') . '.'),
                'account', $s,
            );
        });
    }

    private static function batchPaid(PayoutBatch $b): void
    {
        PartnerUpdates::record(
            (int) $b->partner_id, 'payout_batch.paid',
            PartnerUpdates::inr($b->amount) . ' settled to your account',
            trim(($b->reference ? 'UTR ' . $b->reference . ' · ' : '') . 'sent to ' . self::destination((int) $b->partner_id) . '.'),
            'payouts', $b,
        );
    }

    /** Masked, as the partner sees it in the app: "63••••@ibl", "HDFC •••• 4321". */
    private static function destination(int $partnerId): string
    {
        $a = PartnerPayoutAccount::query()->where('partner_id', $partnerId)->first();

        return $a?->summaryLine() ?: 'your settlement account';
    }
}
