<?php

declare(strict_types=1);

use App\Models\MessageTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The number an applicant wants Haraan's WhatsApp updates on (often not the number
 * they answer calls on), plus the confirmation template those updates start with.
 *
 * The template row is registered as a DRAFT, like every other template
 * (MessageTemplateSeeder): it sends nothing until WhatsApp approves
 * `partner_application_received` and an admin marks it approved in
 * /control → Platform → Templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('partner_applications', 'whatsapp_phone')) {
            Schema::table('partner_applications', function (Blueprint $table): void {
                $table->string('whatsapp_phone', 20)->nullable();
                $table->timestamp('whatsapp_confirmed_at')->nullable();
            });
        }

        if (Schema::hasTable('message_templates')) {
            MessageTemplate::query()->firstOrCreate(
                ['key' => 'partner.application_received'],
                [
                    'name' => 'Partner application received',
                    'channel' => 'whatsapp',
                    'category' => 'utility',
                    'locale' => 'en',
                    'provider_template_id' => 'partner_application_received',
                    'body' => "Hi {{1}}, we have received your application to list *{{2}}* on Haraan.\n\n"
                        . "Your reference number is {{3}}.\n\n"
                        . "Someone from the Haraan partner team will call you within {{4}} hours to set up your listing, prices and payouts. "
                        . "Please keep this reference handy for the call.\n\n— Team Haraan",
                    'variables' => [
                        '1' => 'contact name',
                        '2' => 'business name',
                        '3' => 'application reference (HP-000123)',
                        '4' => 'call-back hours from Platform rules',
                    ],
                    'status' => 'draft',
                    'is_active' => true,
                ],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('partner_applications', 'whatsapp_phone')) {
            Schema::table('partner_applications', function (Blueprint $table): void {
                $table->dropColumn(['whatsapp_phone', 'whatsapp_confirmed_at']);
            });
        }
    }
};
