<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

/**
 * Every business rule an admin may change without a deploy: booking holds, waitlist offers,
 * creation limits, match proximity, the XP economy, OTP policy, messaging switches, AI switches,
 * fees, commission, tax — and the emergency operational switches.
 *
 * Edited in /control → Platform → Platform rules (and → Operations for the `ops`/`app` keys).
 * Stored in app_settings under the `platform_rules` group, which AppSetting caches as a single
 * snapshot, busts on write, and broadcasts as a `config` change so clients refetch /api/config.
 *
 * A key nobody has saved reads its DEFAULT, which is always what the code did before the rule
 * existed — deploying this changes no behaviour until an admin changes a value.
 *
 * NOT for secrets. Payment keys, API tokens, database and mail credentials stay in .env and are
 * never read from or written to this table. {@see self::assertNotSecret()} refuses any key that
 * looks like one, so a future rule can't smuggle a credential into /control.
 */
final class PlatformRules
{
    public const GROUP = 'platform_rules';

    public const TYPE_INT = 'int';

    public const TYPE_FLOAT = 'float';

    public const TYPE_BOOL = 'bool';

    public const TYPE_TEXT = 'text';

    public const TYPE_SELECT = 'select';

    /**
     * Sections, in display order. `workspace` is the User::canManage() key allowed to edit it;
     * `admin` means super-admin only.
     *
     * @var array<string, array{label: string, description: string, workspace: string}>
     */
    public const SECTIONS = [
        'ops' => ['label' => 'Emergency switches', 'description' => 'Stop parts of the platform at once. Every switch is enforced on the server, for the app and the website.', 'workspace' => 'admin'],
        'app' => ['label' => 'App version', 'description' => 'Make people update the Android app.', 'workspace' => 'admin'],
        'fees' => ['label' => 'Fees, commission & tax', 'description' => 'Platform defaults. An event set to “Platform default” uses these; an event with its own value keeps it.', 'workspace' => 'finance'],
        'bookings' => ['label' => 'Bookings & holds', 'description' => 'How long unpaid orders hold stock, and how much one order may buy.', 'workspace' => 'admin'],
        'waitlist' => ['label' => 'Waitlist', 'description' => 'What happens when a waitlisted slot frees up.', 'workspace' => 'admin'],
        'creation' => ['label' => 'Tournaments & matches', 'description' => 'Creation limits and how “near you” is measured.', 'workspace' => 'admin'],
        'xp' => ['label' => 'XP & trust', 'description' => 'The ActionBoard ranking economy. A change applies the next time a match’s XP is awarded; XP already on the ledger is not rewritten.', 'workspace' => 'admin'],
        'otp' => ['label' => 'Sign-in codes', 'description' => 'One-time codes sent by SMS, WhatsApp and email.', 'workspace' => 'admin'],
        'messaging' => ['label' => 'Automated messaging', 'description' => 'Journeys (reminders, review requests) sent by WhatsApp.', 'workspace' => 'marketing'],
        'ai' => ['label' => 'AI features', 'description' => 'Turn individual AI features off, or cap how many AI calls the platform makes a day.', 'workspace' => 'admin'],
        'rewards' => ['label' => 'Post-match rewards', 'description' => 'Limits, unlock rules, rewarded ads and notifications for ActionBoard rewards. Programs and rules are in /control → Rewards. Rewards never change competitive XP or leaderboards.', 'workspace' => 'marketing'],
    ];

    private const FEE_TYPES = ['none' => 'No fee', 'flat' => 'Flat ₹ amount', 'percent' => 'Percentage of ticket subtotal'];

    private const TAX_TYPES = ['none' => 'No tax', 'flat' => 'Flat ₹ amount', 'percent' => 'Percentage of ticket subtotal (after discount)'];

    private const VENUE_TAX_TYPES = ['none' => 'No tax', 'flat' => 'Flat ₹ amount per order', 'percent' => 'Percentage of slot subtotal (after discount)'];

    private const PAYERS = ['customer' => 'Customer pays (added to the order)', 'host' => 'Host pays (deducted from payout)'];

    /**
     * The registry. `default` is either a literal or ['config' => path, 'fallback' => literal].
     *
     * @var array<string, array<string, mixed>>
     */
    private const RULES = [
        // ── Emergency ───────────────────────────────────────────────────────────────
        'ops.maintenance_mode' => ['section' => 'ops', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Maintenance mode', 'help' => 'The app and website show the message below and refuse requests. /control, /partner and payment webhooks keep working.', 'danger' => true],
        'ops.maintenance_message' => ['section' => 'ops', 'type' => self::TYPE_TEXT, 'default' => 'Haraan is down for scheduled maintenance. We’ll be back shortly.', 'max' => 200, 'label' => 'Maintenance message', 'help' => ''],
        'ops.pause_all_bookings' => ['section' => 'ops', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Pause all bookings', 'help' => 'No new event tickets or venue slots, on the app or the website. Orders already paying can still finish.', 'danger' => true],
        'ops.pause_event_bookings' => ['section' => 'ops', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Pause event ticket sales', 'help' => 'Events only.', 'danger' => true],
        'ops.pause_venue_bookings' => ['section' => 'ops', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Pause Pulse (venue) bookings', 'help' => 'Online slot bookings only. The partner desk can still record walk-ins.', 'danger' => true],
        'ops.pause_message' => ['section' => 'ops', 'type' => self::TYPE_TEXT, 'default' => 'Bookings are paused for a short while. Please try again soon.', 'max' => 200, 'label' => 'Message when bookings are paused', 'help' => ''],
        'ops.pause_match_creation' => ['section' => 'ops', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Pause new matches', 'help' => 'Nobody can create a match. Matches in progress keep scoring.', 'danger' => true],
        'ops.pause_tournament_creation' => ['section' => 'ops', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Pause new tournaments', 'help' => 'Nobody can create a tournament. Running tournaments are unaffected.', 'danger' => true],
        'ops.payments_disabled' => ['section' => 'ops', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Stop taking payments', 'help' => 'No new Razorpay payment starts (tickets, slots, memberships). Payments already started still confirm. Free orders still go through.', 'danger' => true],
        'ops.ai_disabled' => ['section' => 'ops', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Turn off all AI', 'help' => 'Every AI feature stops calling its provider at once and falls back to its non-AI behaviour.', 'danger' => true],
        'ops.rewards_disabled' => ['section' => 'ops', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Stop all post-match rewards', 'help' => 'No new rewards are granted, unlocked or claimed. Rewards already claimed stay with their owners.', 'danger' => true],
        'ops.rewarded_ads_disabled' => ['section' => 'ops', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Stop rewarded ads', 'help' => 'No new “watch to unlock” videos start. Verifications Google already sent are still honoured.', 'danger' => true],

        // ── App version ─────────────────────────────────────────────────────────────
        'app.min_version' => ['section' => 'app', 'type' => self::TYPE_TEXT, 'default' => '', 'max' => 20, 'pattern' => '/^(\d+(\.\d+){0,3})?$/', 'label' => 'Minimum Android version', 'help' => 'e.g. 1.0.38. Older apps are blocked with an update screen. Leave empty to allow every version. Only builds that report their version can be blocked.'],
        'app.latest_version' => ['section' => 'app', 'type' => self::TYPE_TEXT, 'default' => '', 'max' => 20, 'pattern' => '/^(\d+(\.\d+){0,3})?$/', 'label' => 'Latest Android version', 'help' => 'Apps older than this are asked (not forced) to update.'],
        'app.force_update' => ['section' => 'app', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Force everyone below the latest version to update', 'help' => 'Treats the latest version as the minimum.', 'danger' => true],
        'app.update_message' => ['section' => 'app', 'type' => self::TYPE_TEXT, 'default' => 'A new version of Haraan is available. Update to keep booking and scoring.', 'max' => 200, 'label' => 'Update message', 'help' => ''],
        'app.update_url' => ['section' => 'app', 'type' => self::TYPE_TEXT, 'default' => 'https://play.google.com/store/apps/details?id=com.haraan.app', 'max' => 255, 'pattern' => '#^https://#', 'label' => 'Update link', 'help' => 'Must be https.'],

        // ── Fees, commission & tax ──────────────────────────────────────────────────
        'fees.event_platform_fee_type' => ['section' => 'fees', 'type' => self::TYPE_SELECT, 'options' => self::FEE_TYPES, 'default' => 'none', 'label' => 'Event platform fee', 'help' => ''],
        'fees.event_platform_fee_value' => ['section' => 'fees', 'type' => self::TYPE_FLOAT, 'default' => 0.0, 'min' => 0, 'max' => 100000, 'label' => 'Event platform fee: amount or %', 'help' => ''],
        'fees.event_platform_fee_payer' => ['section' => 'fees', 'type' => self::TYPE_SELECT, 'options' => self::PAYERS, 'default' => 'customer', 'label' => 'Event platform fee: who pays', 'help' => ''],
        'fees.event_gateway_fee_type' => ['section' => 'fees', 'type' => self::TYPE_SELECT, 'options' => self::FEE_TYPES, 'default' => 'none', 'label' => 'Event payment gateway fee', 'help' => ''],
        'fees.event_gateway_fee_value' => ['section' => 'fees', 'type' => self::TYPE_FLOAT, 'default' => 0.0, 'min' => 0, 'max' => 100000, 'label' => 'Event gateway fee: amount or %', 'help' => ''],
        'fees.event_gateway_fee_payer' => ['section' => 'fees', 'type' => self::TYPE_SELECT, 'options' => self::PAYERS, 'default' => 'customer', 'label' => 'Event gateway fee: who pays', 'help' => ''],
        'fees.event_tax_type' => ['section' => 'fees', 'type' => self::TYPE_SELECT, 'options' => self::TAX_TYPES, 'default' => 'none', 'label' => 'Event tax', 'help' => 'Always paid by the customer.'],
        'fees.event_tax_value' => ['section' => 'fees', 'type' => self::TYPE_FLOAT, 'default' => 0.0, 'min' => 0, 'max' => 100000, 'label' => 'Event tax: amount or %', 'help' => ''],
        'fees.event_tax_label' => ['section' => 'fees', 'type' => self::TYPE_TEXT, 'default' => 'GST', 'max' => 30, 'label' => 'Tax name on the bill', 'help' => 'Used for every event, including ones with their own tax rate.'],
        'fees.venue_commission_percent' => ['section' => 'fees', 'type' => self::TYPE_FLOAT, 'default' => 0.0, 'min' => 0, 'max' => 50, 'label' => 'Pulse commission (%)', 'help' => 'Haraan’s share of each venue booking paid online, deducted from the venue’s payout. Walk-ins at the desk are never charged.'],
        'fees.venue_tax_type' => ['section' => 'fees', 'type' => self::TYPE_SELECT, 'options' => self::VENUE_TAX_TYPES, 'default' => 'none', 'label' => 'Pulse (venue) tax', 'help' => 'Added to court bookings paid online, on the app and the website. Haraan collects it and it is NOT paid out to the venue. Walk-ins at the desk are never taxed here. Apps older than the release that shows it will still charge it, but won’t list it before payment.'],
        'fees.venue_tax_value' => ['section' => 'fees', 'type' => self::TYPE_FLOAT, 'default' => 0.0, 'min' => 0, 'max' => 100000, 'label' => 'Pulse tax: amount or %', 'help' => 'e.g. 18 for 18% GST.'],
        'fees.venue_tax_label' => ['section' => 'fees', 'type' => self::TYPE_TEXT, 'default' => 'GST', 'max' => 30, 'label' => 'Pulse tax name on the bill', 'help' => ''],

        // ── Bookings & holds ────────────────────────────────────────────────────────
        'bookings.event_hold_minutes' => ['section' => 'bookings', 'type' => self::TYPE_INT, 'default' => 15, 'min' => 10, 'max' => 60, 'label' => 'Unpaid ticket hold (minutes)', 'help' => 'Seats in an unpaid order are held this long, then go back on sale. Keep it longer than a UPI payment takes.'],
        'bookings.whatsapp_hold_minutes' => ['section' => 'bookings', 'type' => self::TYPE_INT, 'default' => 5, 'min' => 1, 'max' => 60, 'label' => 'WhatsApp reservation hold (minutes)', 'help' => 'A slot offered in a WhatsApp chat is held this long for the customer to pay.'],
        'bookings.business_timezone' => ['section' => 'bookings', 'type' => self::TYPE_SELECT, 'options' => ['Asia/Kolkata' => 'India (IST, UTC+5:30)', 'Asia/Dubai' => 'Gulf (GST, UTC+4)', 'Asia/Singapore' => 'Singapore (SGT, UTC+8)', 'Europe/London' => 'UK (GMT/BST)', 'UTC' => 'UTC'], 'default' => 'Asia/Kolkata', 'label' => 'Business day time zone', 'help' => 'Decides when “today” starts for venue desks, partner Home and day totals, and when a booking counts as on court. Stored times are unaffected.'],
        'bookings.default_max_per_tier' => ['section' => 'bookings', 'type' => self::TYPE_INT, 'default' => 25, 'min' => 1, 'max' => 500, 'label' => 'Most tickets of one type per order', 'help' => 'For ticket types without bulk-booking limits of their own.'],
        'bookings.max_tickets_per_order' => ['section' => 'bookings', 'type' => self::TYPE_INT, 'default' => 0, 'min' => 0, 'max' => 5000, 'label' => 'Most tickets in one order', 'help' => 'Across every ticket type. 0 = no platform limit.'],

        // ── Waitlist ────────────────────────────────────────────────────────────────
        'waitlist.offer_window_minutes' => ['section' => 'waitlist', 'type' => self::TYPE_INT, 'default' => 90, 'min' => 5, 'max' => 1440, 'label' => 'Time to claim an offered slot (minutes)', 'help' => ''],
        'waitlist.offers_per_slot' => ['section' => 'waitlist', 'type' => self::TYPE_INT, 'default' => 3, 'min' => 1, 'max' => 50, 'label' => 'People offered each freed slot', 'help' => 'The first to book it gets it.'],

        // ── Tournaments & matches ───────────────────────────────────────────────────
        'creation.tournaments_per_day' => ['section' => 'creation', 'type' => self::TYPE_INT, 'default' => 10, 'min' => 1, 'max' => 500, 'label' => 'Tournaments one player may create per day', 'help' => 'Anti-spam. Member plans separately limit how many run at once.'],
        'creation.proximity_near_km' => ['section' => 'creation', 'type' => self::TYPE_FLOAT, 'default' => 5.0, 'min' => 0.5, 'max' => 100, 'label' => '“Near you” radius (km)', 'help' => 'Matches this close rank first.'],
        'creation.proximity_district_km' => ['section' => 'creation', 'type' => self::TYPE_FLOAT, 'default' => 40.0, 'min' => 1, 'max' => 500, 'label' => '“Your district” radius (km)', 'help' => ''],
        'creation.proximity_region_km' => ['section' => 'creation', 'type' => self::TYPE_FLOAT, 'default' => 150.0, 'min' => 5, 'max' => 2000, 'label' => '“Your region” radius (km)', 'help' => 'Beyond this a match is “far”.'],

        // ── XP & trust ──────────────────────────────────────────────────────────────
        'xp.base_casual' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 25, 'min' => 0, 'max' => 1000, 'label' => 'Base XP: casual match', 'help' => ''],
        'xp.base_league' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 60, 'min' => 0, 'max' => 1000, 'label' => 'Base XP: league match', 'help' => ''],
        'xp.base_tournament' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 100, 'min' => 0, 'max' => 1000, 'label' => 'Base XP: tournament match', 'help' => ''],
        'xp.trust_low' => ['section' => 'xp', 'type' => self::TYPE_FLOAT, 'default' => 0.25, 'min' => 0, 'max' => 3, 'label' => 'XP multiplier: low-trust match', 'help' => ''],
        'xp.trust_medium' => ['section' => 'xp', 'type' => self::TYPE_FLOAT, 'default' => 0.75, 'min' => 0, 'max' => 3, 'label' => 'XP multiplier: medium-trust match', 'help' => ''],
        'xp.trust_high' => ['section' => 'xp', 'type' => self::TYPE_FLOAT, 'default' => 1.0, 'min' => 0, 'max' => 3, 'label' => 'XP multiplier: high-trust match', 'help' => ''],
        'xp.trust_verified' => ['section' => 'xp', 'type' => self::TYPE_FLOAT, 'default' => 1.25, 'min' => 0, 'max' => 3, 'label' => 'XP multiplier: verified match', 'help' => ''],
        'xp.repeat_2' => ['section' => 'xp', 'type' => self::TYPE_FLOAT, 'default' => 0.8, 'min' => 0, 'max' => 1, 'label' => 'Same opponents, 2nd match this month', 'help' => 'Share of XP kept. Stops farming one friend.'],
        'xp.repeat_3' => ['section' => 'xp', 'type' => self::TYPE_FLOAT, 'default' => 0.5, 'min' => 0, 'max' => 1, 'label' => 'Same opponents, 3rd match', 'help' => ''],
        'xp.repeat_4' => ['section' => 'xp', 'type' => self::TYPE_FLOAT, 'default' => 0.25, 'min' => 0, 'max' => 1, 'label' => 'Same opponents, 4th match and later', 'help' => ''],
        'xp.win_bonus_percent' => ['section' => 'xp', 'type' => self::TYPE_FLOAT, 'default' => 15.0, 'min' => 0, 'max' => 200, 'label' => 'Win bonus (% of base)', 'help' => 'Only on medium trust or better.'],
        'xp.mom_bonus_percent' => ['section' => 'xp', 'type' => self::TYPE_FLOAT, 'default' => 10.0, 'min' => 0, 'max' => 200, 'label' => 'Player of the match bonus (% of base)', 'help' => ''],
        'xp.ranked_min_players_per_side' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 2, 'min' => 1, 'max' => 11, 'label' => 'Registered players per side for a ranked match', 'help' => ''],
        'xp.verification_window_hours' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 72, 'min' => 1, 'max' => 720, 'label' => 'Hours to confirm a finished match', 'help' => 'Unconfirmed matches then drop to low trust.'],
        'xp.trust_recovery_per_match' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 1, 'min' => 0, 'max' => 50, 'label' => 'Trust regained per clean ranked match', 'help' => ''],
        'xp.min_trust_ranked_tournament' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 60, 'min' => 0, 'max' => 100, 'label' => 'Trust needed to create ranked tournaments', 'help' => 'Trust scores run 0–100.'],
        'xp.min_trust_organize' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 70, 'min' => 0, 'max' => 100, 'label' => 'Trust needed to organise', 'help' => ''],
        'xp.min_trust_verify' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 70, 'min' => 0, 'max' => 100, 'label' => 'Trust needed to verify matches', 'help' => ''],
        'xp.penalty_match_dispute' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 10, 'min' => 0, 'max' => 100, 'label' => 'Trust penalty: match dispute', 'help' => ''],
        'xp.penalty_verification_rejection' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 15, 'min' => 0, 'max' => 100, 'label' => 'Trust penalty: verification rejected', 'help' => ''],
        'xp.penalty_fake_tournament' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 25, 'min' => 0, 'max' => 100, 'label' => 'Trust penalty: fake tournament', 'help' => ''],
        'xp.penalty_repeated_abuse' => ['section' => 'xp', 'type' => self::TYPE_INT, 'default' => 50, 'min' => 0, 'max' => 100, 'label' => 'Trust penalty: repeated abuse', 'help' => ''],

        // ── Sign-in codes ───────────────────────────────────────────────────────────
        'otp.ttl_seconds' => ['section' => 'otp', 'type' => self::TYPE_INT, 'default' => 300, 'min' => 60, 'max' => 1800, 'label' => 'Code valid for (seconds)', 'help' => ''],
        'otp.max_attempts' => ['section' => 'otp', 'type' => self::TYPE_INT, 'default' => 5, 'min' => 1, 'max' => 20, 'label' => 'Wrong tries before a code is burned', 'help' => ''],

        // ── Automated messaging ─────────────────────────────────────────────────────
        'messaging.journeys_enabled' => ['section' => 'messaging', 'type' => self::TYPE_BOOL, 'default' => ['config' => 'messaging.journeys.enabled', 'fallback' => false], 'label' => 'Send journey messages', 'help' => 'Master switch. Off queues messages but delivers none.', 'danger' => true],
        'messaging.quiet_start_hour' => ['section' => 'messaging', 'type' => self::TYPE_INT, 'default' => ['config' => 'messaging.journeys.quiet_hours.start', 'fallback' => 21], 'min' => 0, 'max' => 23, 'label' => 'Quiet hours start (0–23, local time)', 'help' => 'Nothing is sent between start and end; it waits until morning.'],
        'messaging.quiet_end_hour' => ['section' => 'messaging', 'type' => self::TYPE_INT, 'default' => ['config' => 'messaging.journeys.quiet_hours.end', 'fallback' => 8], 'min' => 0, 'max' => 23, 'label' => 'Quiet hours end (0–23, local time)', 'help' => ''],
        'messaging.max_attempts' => ['section' => 'messaging', 'type' => self::TYPE_INT, 'default' => ['config' => 'messaging.journeys.max_attempts', 'fallback' => 3], 'min' => 1, 'max' => 10, 'label' => 'Delivery attempts per message', 'help' => ''],
        'messaging.review_delay_hours' => ['section' => 'messaging', 'type' => self::TYPE_INT, 'default' => ['config' => 'messaging.journeys.review_delay_hours', 'fallback' => 3], 'min' => 0, 'max' => 168, 'label' => 'Review request after the booking ends (hours)', 'help' => ''],
        'messaging.horizon_days' => ['section' => 'messaging', 'type' => self::TYPE_INT, 'default' => ['config' => 'messaging.journeys.horizon_days', 'fallback' => 7], 'min' => 1, 'max' => 60, 'label' => 'Schedule messages this many days ahead', 'help' => ''],

        // ── AI ──────────────────────────────────────────────────────────────────────
        'ai.career_read' => ['section' => 'ai', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'AI career read', 'help' => 'Player profile analysis.'],
        'ai.delivery_review' => ['section' => 'ai', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'AI delivery review', 'help' => 'Video review of a ball (the most expensive feature).'],
        'ai.match_commentary' => ['section' => 'ai', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'AI commentary, headlines & innings reads', 'help' => 'Live match text. Falls back to plain scoring text.'],
        'ai.match_insights' => ['section' => 'ai', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'AI match insights narrative', 'help' => 'The numbers still show; only the written summary stops.'],
        'ai.event_copy' => ['section' => 'ai', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'AI event description writer', 'help' => 'In the create-event wizard.'],
        'ai.partner_support' => ['section' => 'ai', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'AI partner support assistant', 'help' => ''],
        'rewards.enabled' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'Post-match rewards on', 'help' => 'Badges, weekly streaks, Bonus XP and every live program.'],
        'rewards.min_trust_to_unlock' => ['section' => 'rewards', 'type' => self::TYPE_SELECT, 'options' => ['medium' => 'Medium — both captains confirmed', 'high' => 'High — organiser verified', 'verified' => 'Verified — Haraan venue booking'], 'default' => 'medium', 'label' => 'Result trust needed to unlock money-value rewards', 'help' => 'Coupons, sponsor codes and membership trials stay locked until the result reaches this level. A match that settles lower loses them.'],
        'rewards.private_matches' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Reward private matches', 'help' => 'Private matches never earn competitive XP. Off = no rewards either.'],
        'rewards.max_grants_per_match' => ['section' => 'rewards', 'type' => self::TYPE_INT, 'default' => 6, 'min' => 1, 'max' => 50, 'label' => 'Most rewards one player gets from one match', 'help' => 'Badges and streaks are not counted.'],
        'rewards.daily_grant_cap' => ['section' => 'rewards', 'type' => self::TYPE_INT, 'default' => 20, 'min' => 1, 'max' => 500, 'label' => 'Most rewards one player gets per day', 'help' => 'Across every match. Stops farming.'],
        'rewards.grant_expiry_days' => ['section' => 'rewards', 'type' => self::TYPE_INT, 'default' => 30, 'min' => 1, 'max' => 365, 'label' => 'Days a reward waits to be claimed', 'help' => 'For rules without their own expiry.'],
        'rewards.streak_bonus_xp' => ['section' => 'rewards', 'type' => self::TYPE_INT, 'default' => 10, 'min' => 0, 'max' => 1000, 'label' => 'Bonus XP for keeping a weekly streak', 'help' => 'Given once a week when a match extends a streak to 2+ weeks. 0 = none.'],
        'rewards.celebration_enabled' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'Play the celebration animation', 'help' => 'Off shows the rewards straight away.'],
        'rewards.celebration_animation_url' => ['section' => 'rewards', 'type' => self::TYPE_TEXT, 'default' => '', 'max' => 255, 'pattern' => '#^https://#', 'label' => 'Celebration animation (Lottie JSON URL)', 'help' => 'https only. Empty = the built-in animation.'],
        'rewards.rewarded_ads_enabled' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Offer “watch a video to unlock”', 'help' => 'Needs the AdMob ad unit below. Never shown to members whose plan has no ads.'],
        'rewards.admob_ad_unit_id' => ['section' => 'rewards', 'type' => self::TYPE_TEXT, 'default' => '', 'max' => 80, 'pattern' => '#^ca-app-pub-\\d+/\\d+$#', 'label' => 'AdMob rewarded ad unit ID', 'help' => 'e.g. ca-app-pub-1234567890123456/1234567890. Not a secret — it ships inside the app.'],
        'rewards.ad_daily_cap' => ['section' => 'rewards', 'type' => self::TYPE_INT, 'default' => 5, 'min' => 1, 'max' => 50, 'label' => 'Rewarded videos one player may watch per day', 'help' => ''],
        'rewards.ad_session_minutes' => ['section' => 'rewards', 'type' => self::TYPE_INT, 'default' => 30, 'min' => 5, 'max' => 240, 'label' => 'Minutes a started video has to be verified', 'help' => ''],
        'rewards.expiry_warning_hours' => ['section' => 'rewards', 'type' => self::TYPE_INT, 'default' => 48, 'min' => 1, 'max' => 720, 'label' => 'Warn before a reward expires (hours)', 'help' => ''],
        'rewards.notify_match' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'Notify players when a match gives them rewards', 'help' => 'Every registered player in the match, not only the scorer.'],
        'rewards.notify_unlocked' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'Notify when locked rewards unlock', 'help' => ''],
        'rewards.notify_badges' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'Notify new badges', 'help' => ''],
        'rewards.notify_streak' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'Notify weekly streak milestones', 'help' => ''],
        'rewards.notify_expiring' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'Remind before rewards expire', 'help' => ''],
        'rewards.copy_match_title' => ['section' => 'rewards', 'type' => self::TYPE_TEXT, 'default' => 'Your match rewards are in', 'max' => 80, 'label' => 'Notification title: match rewards', 'help' => ''],
        'rewards.copy_unlocked_title' => ['section' => 'rewards', 'type' => self::TYPE_TEXT, 'default' => 'Rewards unlocked', 'max' => 80, 'label' => 'Notification title: rewards unlocked', 'help' => ''],
        'rewards.copy_badge_title' => ['section' => 'rewards', 'type' => self::TYPE_TEXT, 'default' => 'New badge unlocked', 'max' => 80, 'label' => 'Notification title: new badge', 'help' => ''],
        'rewards.copy_streak_title' => ['section' => 'rewards', 'type' => self::TYPE_TEXT, 'default' => 'Weekly streak going strong', 'max' => 80, 'label' => 'Notification title: streak', 'help' => ''],
        'rewards.copy_expiring_title' => ['section' => 'rewards', 'type' => self::TYPE_TEXT, 'default' => 'A reward is about to expire', 'max' => 80, 'label' => 'Notification title: expiring reward', 'help' => ''],

        // ── Location targeting (docs/location-rewards-design.md) ────────────────────
        // Off by default: with this off, a program's zones are ignored and every program fires
        // exactly where it does today.
        'rewards.geo_enabled' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => false, 'label' => 'Target rewards by location', 'help' => 'Let a program be limited to zones drawn in /control → Rewards → Zones. Off = every program reaches every match, as today.'],
        'rewards.geo_require_coordinates' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'Only reward matches with a real location fix', 'help' => 'On, a match carrying only place names never satisfies a zone. Off lets older and private matches match an area zone by name.'],
        'rewards.geo_max_zone_radius_km' => ['section' => 'rewards', 'type' => self::TYPE_FLOAT, 'default' => 50.0, 'min' => 0.1, 'max' => 2000, 'label' => 'Largest radius a zone may use (km)', 'help' => 'Stops a “local” zone quietly being drawn around half the country.'],
        'rewards.geo_default_radius_km' => ['section' => 'rewards', 'type' => self::TYPE_FLOAT, 'default' => 5.0, 'min' => 0.1, 'max' => 2000, 'label' => 'Radius a new zone starts with (km)', 'help' => ''],
        'rewards.geo_tighter_zone_wins' => ['section' => 'rewards', 'type' => self::TYPE_BOOL, 'default' => true, 'label' => 'A tighter zone beats a wider one', 'help' => 'Within the same priority, a 3 km offer is tried before a national one. Affects order only, never who is eligible.'],
        'rewards.geo_min_trust_for_zone_money' => ['section' => 'rewards', 'type' => self::TYPE_SELECT, 'options' => ['medium' => 'Medium — both captains confirmed', 'high' => 'High — organiser verified', 'verified' => 'Verified — Haraan venue booking'], 'default' => 'high', 'label' => 'Result trust needed to unlock a location-targeted money reward', 'help' => 'Applies on top of the normal unlock level, for coupons, sponsor codes and trials won from a zone-targeted program. A spoofed location is worth nothing until the result is this trusted.'],
        'rewards.geo_ground_grants_per_day' => ['section' => 'rewards', 'type' => self::TYPE_INT, 'default' => 25, 'min' => 1, 'max' => 5000, 'label' => 'Most location-targeted rewards from one ground per day', 'help' => 'Across every player. Stops one group playing all weekend at one turf from draining a sponsor’s city budget.'],

        'ai.daily_call_budget' => ['section' => 'ai', 'type' => self::TYPE_INT, 'default' => 0, 'min' => 0, 'max' => 1000000, 'label' => 'Most AI calls per day, whole platform', 'help' => '0 = no cap. Once reached, AI features fall back until midnight (IST).'],
    ];

    /** Keys whose name suggests a credential. Rules must never hold one. */
    private const SECRET_MARKERS = ['secret', 'password', 'passwd', 'token', 'api_key', 'apikey', 'private_key', 'credential', 'dsn', 'db_'];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return self::RULES;
    }

    /** @return array<string, array<string, mixed>> */
    public static function inSection(string $section): array
    {
        return array_filter(self::RULES, fn (array $r): bool => $r['section'] === $section);
    }

    public static function exists(string $key): bool
    {
        return isset(self::RULES[$key]);
    }

    /** @return array<string, mixed> */
    public static function definition(string $key): array
    {
        return self::RULES[$key] ?? throw new \InvalidArgumentException("Unknown platform rule [{$key}].");
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function float(string $key): float
    {
        return (float) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    public static function string(string $key): string
    {
        return (string) self::get($key);
    }

    /** The effective value: the saved one when valid, else the default. Always within bounds. */
    public static function get(string $key): int|float|bool|string
    {
        $def = self::definition($key);
        $stored = AppSetting::get(self::storageKey($key));

        if ($stored !== null) {
            $parsed = self::parse($def, $stored);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return self::defaultOf($key);
    }

    public static function defaultOf(string $key): int|float|bool|string
    {
        $def = self::definition($key);
        $default = $def['default'];

        if (is_array($default)) {
            $default = config($default['config'], $default['fallback']);
        }

        return self::parse($def, is_bool($default) ? ($default ? '1' : '0') : (string) $default)
            ?? self::parse($def, is_bool($def['default']) ? '0' : '')
            ?? '';
    }

    /** True when an admin has saved a value for the key (even one equal to the default). */
    public static function isOverridden(string $key): bool
    {
        return AppSetting::get(self::storageKey($key)) !== null;
    }

    /**
     * Validate and store a set of rule values. Only keys that actually change are written, so
     * one save is one cache bust per changed key and one broadcast, and the audit diff is exact.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array{from: mixed, to: mixed}> what changed
     *
     * @throws \InvalidArgumentException on an unknown key or an out-of-range value
     */
    public static function save(array $values): array
    {
        $changes = [];

        foreach ($values as $key => $raw) {
            self::assertNotSecret((string) $key);
            $def = self::definition((string) $key);
            $string = is_bool($raw) ? ($raw ? '1' : '0') : trim((string) $raw);
            $parsed = self::parse($def, $string);

            if ($parsed === null) {
                throw new \InvalidArgumentException("“{$def['label']}” has an invalid value.");
            }

            $before = self::get((string) $key);
            if ($before === $parsed) {
                continue;
            }

            AppSetting::set(self::storageKey((string) $key), is_bool($parsed) ? ($parsed ? '1' : '0') : (string) $parsed, self::GROUP);
            $changes[(string) $key] = ['from' => $before, 'to' => $parsed];
        }

        return $changes;
    }

    public static function storageKey(string $key): string
    {
        return 'rules.'.$key;
    }

    public static function sectionOf(string $key): string
    {
        return self::definition($key)['section'];
    }

    /** Refuse a rule key that looks like it would hold a credential. */
    public static function assertNotSecret(string $key): void
    {
        $lower = strtolower($key);
        foreach (self::SECRET_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                throw new \InvalidArgumentException("[{$key}] looks like a secret. Secrets belong in .env, never in /control.");
            }
        }
    }

    /**
     * Parse a stored string into the rule's type, or null when it isn't valid for the rule.
     *
     * @param  array<string, mixed>  $def
     */
    private static function parse(array $def, string $value): int|float|bool|string|null
    {
        switch ($def['type']) {
            case self::TYPE_BOOL:
                return match (strtolower($value)) {
                    '1', 'true', 'on', 'yes' => true,
                    '0', 'false', 'off', 'no', '' => false,
                    default => null,
                };

            case self::TYPE_INT:
                if (! preg_match('/^-?\d+$/', $value)) {
                    return null;
                }
                $n = (int) $value;

                return self::inRange($def, $n) ? $n : null;

            case self::TYPE_FLOAT:
                if (! is_numeric($value)) {
                    return null;
                }
                $f = round((float) $value, 4);

                return self::inRange($def, $f) ? $f : null;

            case self::TYPE_SELECT:
                return array_key_exists($value, $def['options']) ? $value : null;

            case self::TYPE_TEXT:
                // A rule with real default copy can't be blanked — the default shows instead.
                if ($value === '' && ! is_array($def['default']) && (string) $def['default'] !== '') {
                    return null;
                }
                if (mb_strlen($value) > ($def['max'] ?? 255)) {
                    return null;
                }
                if (isset($def['pattern']) && $value !== '' && ! preg_match($def['pattern'], $value)) {
                    return null;
                }

                return $value;
        }

        return null;
    }

    /** @param  array<string, mixed>  $def */
    private static function inRange(array $def, int|float $n): bool
    {
        return (! isset($def['min']) || $n >= $def['min']) && (! isset($def['max']) || $n <= $def['max']);
    }
}
