<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PartnerApplication;
use App\Support\MessageContext;
use App\Support\PlatformRules;
use Illuminate\Support\Facades\Log;

/**
 * Tells an applicant on WhatsApp that their application arrived, with the reference.
 *
 * Goes through TemplateResolver like every other send: until the
 * `partner.application_received` template is approved it is "blocked" and nothing
 * leaves — a first message to a new number can't be free text. It never throws:
 * the application is already saved, and a messaging hiccup must not look like a
 * failed submission.
 */
final class PartnerApplicationNotifier
{
    public const TEMPLATE_KEY = 'partner.application_received';

    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly TemplateResolver $templates,
    ) {}

    public function received(PartnerApplication $application): bool
    {
        $phone = $application->whatsapp_phone ?: $application->phone;

        if (! PlatformRules::bool('partner_onboarding.whatsapp_confirm') || blank($phone) || $application->whatsapp_confirmed_at !== null) {
            return false;
        }

        try {
            $ctx = MessageContext::platform(MessageContext::UTILITY, self::TEMPLATE_KEY);
            $route = $this->templates->resolve(self::TEMPLATE_KEY, 'whatsapp', $phone);
            $hours = (string) PlatformRules::int('partner_onboarding.callback_hours');

            $sent = match ($route['mode']) {
                TemplateResolver::MODE_TEMPLATE => $this->whatsapp->sendTemplate(
                    $phone,
                    (string) $route['name'],
                    [$application->contact_name, $application->business_name, $application->reference, $hours],
                    $ctx,
                    (string) $route['language'],
                ),
                TemplateResolver::MODE_FREE_TEXT => $this->whatsapp->sendMessage(
                    $phone,
                    "Hi {$application->contact_name}, we have received your application to list *{$application->business_name}* on Haraan.\n\n"
                        . "Reference: {$application->reference}\n\n"
                        . "Someone from the Haraan partner team will call you within {$hours} hours.\n\n— Team Haraan",
                    $ctx,
                ),
                default => false,
            };
        } catch (\Throwable $e) {
            Log::warning('partner application WhatsApp confirmation failed', ['reference' => $application->reference, 'error' => $e->getMessage()]);

            return false;
        }

        if ($sent) {
            $application->forceFill(['whatsapp_confirmed_at' => now()])->saveQuietly();
        }

        return $sent;
    }
}
