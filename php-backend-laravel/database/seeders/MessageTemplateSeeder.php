<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Registers the templates Haraan actually sends, as DRAFTS.
 *
 * Nothing here is approved, because approval doesn't come from us: submit the
 * template (in MSG91's panel if MSG91 is the BSP, in WhatsApp Manager if we're on
 * Meta direct — either way it lands in the same WABA), and once WhatsApp approves
 * it, mark the row approved in /control → Platform → Templates. Until then
 * TemplateResolver routes these as "blocked — template not approved", which is
 * the honest state and stops a rejected send from reading like an outage.
 *
 * `provider_template_id` is pre-filled with the registered NAME so the two sides
 * agree on spelling; it is what both drivers put on the wire. If a template gets
 * approved under a different name, fix it here in /control rather than in code.
 *
 * `body` is the submitted copy with {{n}} placeholders; `variables` documents
 * what each position means and MUST stay in step with the caller that fills them
 * ({@see \App\Services\JourneyTemplates::variables()} for the journey steps).
 */
class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'booking.ticket',
                'name' => 'Booking confirmation',
                'category' => 'utility',
                'provider_template_id' => 'booking_confirmation',
                'body' => "Your booking is confirmed.\n\n*{{1}}*\n{{2}}\n{{3}}\n\nBooking ID: {{4}}\n"
                    . "Show the QR code at entry: {{5}}\n\nThank you for booking with Haraan.",
                'variables' => [
                    '1' => 'event or venue name',
                    '2' => 'date and time',
                    '3' => 'venue and city',
                    '4' => 'booking / ticket code',
                    '5' => 'link to the ticket QR',
                ],
            ],
            [
                // To the PARTNER OWNER, whenever Haraan (admin, finance, their manager)
                // changes something on their account: settlement account, settlements,
                // venue, courts, slots, plan. See App\Support\PartnerUpdates.
                'key' => 'partner.account_update',
                'name' => 'Partner account update',
                'category' => 'utility',
                'provider_template_id' => 'partner_account_update',
                'body' => "Hi {{1}}, there is an update on your Haraan partner account.\n\n{{2}}\n\nChanged by: {{3}}\n\n"
                    . "You can see the details in the Haraan Partner app. If you did not expect this change, "
                    . "please reply to this message or call your Haraan manager.",
                'variables' => [
                    '1' => 'partner first name',
                    '2' => 'what changed, e.g. "₹2,400 settled to your account. UTR 123456 · sent to 63••••@ibl."',
                    '3' => 'who changed it: the Haraan manager by name, Haraan finance, or Haraan',
                ],
            ],
            [
                // To the VENUE OWNER, not the customer. Owners almost never have a
                // 24-hour window open with us, so without this approved template the
                // alert is free text WhatsApp refuses to deliver.
                'key' => 'booking.partner_alert',
                'name' => 'New venue booking (owner)',
                'category' => 'utility',
                'provider_template_id' => 'venue_booking_alert',
                // Longer than the free-text fallback on purpose: WhatsApp rejects a
                // template with "too many variables for its length", and seven slots
                // need this much fixed copy around them (submitted 2026-09-25).
                'body' => "You have a new court booking on Haraan.\n\nVenue: *{{1}}*\nDate: {{2}}\nSlots: {{3}}\n\n"
                    . "Customer name: {{4}}\nCustomer phone: {{5}}\nAmount: {{6}}\nBooking ID: {{7}}\n\n"
                    . "The booking is already on your day grid in the Haraan partner app. "
                    . "Please keep the court ready for the customer at the booked time.",
                'variables' => [
                    '1' => 'venue name',
                    '2' => 'date',
                    '3' => 'court and time of each slot',
                    '4' => 'customer name',
                    '5' => 'customer phone',
                    '6' => 'amount, and whether it is paid or due at the desk',
                    '7' => 'booking code',
                ],
            ],
            [
                // To the CUSTOMER. Promises no refund by itself — whether one is due
                // is the cancellation policy's call, not this message's.
                'key' => 'booking.cancelled',
                'name' => 'Booking cancelled (customer)',
                'category' => 'utility',
                'provider_template_id' => 'booking_cancelled',
                'body' => "Your booking has been cancelled.\n\n*{{1}}*\n{{2}}\nBooking ID: {{3}}\nReason: {{4}}\n\n"
                    . "Refunds, where applicable, go back to your original payment method as per the "
                    . "cancellation policy. Thank you for using Haraan.",
                'variables' => [
                    '1' => 'event or venue name',
                    '2' => 'date and time',
                    '3' => 'booking / ticket code',
                    '4' => 'cancellation reason',
                ],
            ],
            [
                'key' => 'booking.partner_cancellation',
                'name' => 'Venue booking cancelled (owner)',
                'category' => 'utility',
                'provider_template_id' => 'venue_booking_cancelled',
                'body' => "Booking cancelled at *{{1}}*\n\n{{2}}\nCustomer: {{3}}\nReason: {{4}}\n\n"
                    . "The slot is open again on your grid.",
                'variables' => [
                    '1' => 'venue name',
                    '2' => 'date, court and time',
                    '3' => 'customer name',
                    '4' => 'cancellation reason',
                ],
            ],
            [
                'key' => 'payment.success',
                'name' => 'Payment received',
                'category' => 'utility',
                'provider_template_id' => 'payment_success',
                // A receipt, not a second ticket. It deliberately doesn't repeat the
                // QR — the booking confirmation already carries that, and two QRs in
                // one thread is how someone shows the wrong one at the gate. It also
                // carries no outstanding balance: Haraan takes payment in full at
                // checkout, so that line would read "Rs.0" on every receipt.
                'body' => "We've received your payment for *{{1}}*.\n{{2}}\n\nAmount: Rs.{{3}}\n"
                    . "Booking ID: {{4}}\n\nThank you — Haraan.",
                'variables' => [
                    '1' => 'event or venue name',
                    '2' => 'date and time',
                    '3' => 'amount paid',
                    '4' => 'ticket / booking code',
                ],
            ],
            [
                'key' => 'event.reminder_24h',
                'name' => 'Reminder — day before',
                'category' => 'utility',
                // Both reminder steps point at ONE approved template. The copy differs
                // only in its lead-in, and the customer reads the timing from when it
                // arrives — not worth a second approval queue to chase.
                'provider_template_id' => 'event_reminder',
                'body' => "Reminder: {{1}} is coming up.\n{{2}}\n\nYour ticket & QR: {{3}}\n\n— Haraan\nReply STOP to opt out.",
                'variables' => ['1' => 'event or venue name', '2' => 'date and time', '3' => 'ticket pass URL'],
            ],
            [
                'key' => 'event.reminder_2h',
                'name' => 'Reminder — starting soon',
                'category' => 'utility',
                'provider_template_id' => 'event_reminder',
                'body' => "Reminder: {{1}} is coming up.\n{{2}}\n\nYour ticket & QR: {{3}}\n\n— Haraan\nReply STOP to opt out.",
                'variables' => ['1' => 'event or venue name', '2' => 'date and time', '3' => 'ticket pass URL'],
            ],
            [
                'key' => 'auth.login_otp',
                'name' => 'Login OTP',
                // AUTHENTICATION is a distinct template category with its own pricing
                // and its own rules — WhatsApp rejects an OTP submitted as utility,
                // and an authentication template may not carry marketing copy.
                'category' => 'authentication',
                'provider_template_id' => 'login_otp',
                'body' => "{{1}} is your Haraan verification code. It expires in 5 minutes.",
                'variables' => ['1' => 'the 6-digit code'],
            ],
            [
                'key' => 'review.request',
                'name' => 'Post-event review request',
                // Marketing, not utility: it asks for something rather than serving
                // the transaction, and WhatsApp prices and polices the two differently.
                'category' => 'marketing',
                'provider_template_id' => 'review_request',
                'body' => "Hope you enjoyed *{{1}}*.\n\nHow was it? Leave a quick rating here: {{2}}\n\n"
                    . "Your feedback helps the organiser and everyone booking next.\n\n"
                    . "— Haraan\nReply STOP to opt out.",
                'variables' => [
                    '1' => 'event or venue name',
                    // /r/{ticket_code} — public and sessionless, so a gift recipient
                    // can rate what they attended without an account.
                    '2' => 'review page URL',
                ],
            ],
        ];

        foreach ($templates as $template) {
            // firstOrCreate, not updateOrCreate: once a row exists an admin owns it,
            // and re-seeding must never quietly overwrite a name they corrected or
            // flip an approved template back to draft.
            $row = MessageTemplate::query()->firstOrCreate(
                ['key' => $template['key']],
                array_merge($template, [
                    'channel' => 'whatsapp',
                    'locale' => 'en',
                    'status' => 'draft',
                    'is_active' => true,
                ]),
            );

            // The one exception: fill in a name that was never set. Rows seeded
            // before the templates were registered carry an empty
            // provider_template_id, which isn't a choice anyone made — it's the
            // reason TemplateResolver would keep reporting them as unsendable.
            // Status is untouched, so this still can't send anything by itself.
            if (! $row->wasRecentlyCreated && blank($row->provider_template_id)) {
                $row->update(['provider_template_id' => $template['provider_template_id']]);
            }
        }
    }
}
