<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Registers the templates Haraan actually sends, as DRAFTS.
 *
 * Nothing here is approved and nothing carries a Meta template name, because both
 * come from Meta: create the template in WhatsApp Manager, and once it's approved
 * put its NAME into /control → Platform → Templates and mark it approved. Until then TemplateResolver routes these as "blocked — template not
 * approved", which is the honest state.
 *
 * `body` is the submitted copy with {{n}} placeholders; `variables` documents
 * what each position means and MUST stay in step with
 * JourneyTemplates::variables().
 */
class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'event.reminder_24h',
                'name' => 'Reminder — day before',
                'category' => 'utility',
                'body' => "Tomorrow: {{1}}\n{{2}}\n\nYour ticket & QR: {{3}}\n\n— Haraan\nReply STOP to opt out.",
                'variables' => ['1' => 'event or venue name', '2' => 'date and time', '3' => 'ticket pass URL'],
            ],
            [
                'key' => 'event.reminder_2h',
                'name' => 'Reminder — starting soon',
                'category' => 'utility',
                'body' => "Starting soon: {{1}}\n{{2}}\n\nHave your QR ready: {{3}}\n\n— Haraan\nReply STOP to opt out.",
                'variables' => ['1' => 'event or venue name', '2' => 'date and time', '3' => 'ticket pass URL'],
            ],
            [
                'key' => 'review.request',
                'name' => 'Post-event review request',
                // Marketing, not utility: it asks for something rather than serving
                // the transaction, and Meta prices and polices the two differently.
                'category' => 'marketing',
                'body' => "Hope you enjoyed {{1}}!\n\nHow was it? Reply with a rating from 1 to 5 — "
                    . "it helps the organiser and everyone booking next.\n\n— Haraan\nReply STOP to opt out.",
                'variables' => ['1' => 'event or venue name'],
            ],
            [
                'key' => 'booking.ticket',
                'name' => 'Ticket delivery',
                'category' => 'utility',
                'body' => "You're confirmed for {{1}}\n{{2}}\n\nYour ticket & QR: {{3}}",
                'variables' => ['1' => 'event or venue name', '2' => 'date and time', '3' => 'ticket pass URL'],
            ],
        ];

        foreach ($templates as $template) {
            MessageTemplate::query()->firstOrCreate(
                ['key' => $template['key']],
                array_merge($template, [
                    'channel' => 'whatsapp',
                    'locale' => 'en',
                    'status' => 'draft',
                    'is_active' => true,
                ]),
            );
        }
    }
}
