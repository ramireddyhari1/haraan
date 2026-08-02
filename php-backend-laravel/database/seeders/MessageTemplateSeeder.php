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
