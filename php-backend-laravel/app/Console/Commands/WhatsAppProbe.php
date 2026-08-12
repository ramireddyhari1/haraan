<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MessageLog;
use App\Services\TemplateResolver;
use App\Services\WhatsAppService;
use App\Support\MessageContext;
use Illuminate\Console\Command;

/**
 * Send one real WhatsApp message and say exactly what happened.
 *
 * This exists because the two things that break a BSP integration — a wrong
 * payload shape and an unapproved template — both surface as "the customer didn't
 * get it", hours later, inside a booking flow that swallowed the error on purpose.
 * Here nothing is swallowed: the config is printed, the send is attempted, and the
 * provider's own verdict is read straight back out of the messaging ledger.
 *
 *   php artisan whatsapp:probe 9876543210
 *   php artisan whatsapp:probe 9876543210 --key=booking.ticket --var="Test Event" --var="Sat, 9 Aug" --var=https://haraan.app/t/ABC
 *   php artisan whatsapp:probe 9876543210 --otp=123456
 */
final class WhatsAppProbe extends Command
{
    protected $signature = 'whatsapp:probe
        {phone : Recipient, with or without the country code}
        {--key= : Template key to send (e.g. booking.ticket). Omit for a plain text message.}
        {--var=* : Template variables, in the order the template was approved with}
        {--otp= : Send this code as the login OTP, using the authentication template}';

    protected $description = 'Send one WhatsApp message through the active driver and print the provider verdict';

    public function handle(WhatsAppService $whatsapp, TemplateResolver $templates): int
    {
        $phone = (string) $this->argument('phone');
        $driver = $whatsapp->driver();

        $this->line('Driver:     ' . $driver);
        $this->line('Enabled:    ' . (config('services.whatsapp.enabled') ? 'yes' : 'NO — nothing will be sent'));
        $this->line('Configured: ' . ($whatsapp->isConfigured() ? 'yes' : 'NO — credentials missing'));

        if ($driver === 'msg91') {
            $this->line('Sender:     ' . (config('services.whatsapp.msg91.integrated_number') ?: '(none set)'));
        }

        $this->newLine();

        // Note the high-water mark first: reading "the newest log row" after the
        // send would happily report an unrelated message if this one never got as
        // far as writing one.
        $before = (int) MessageLog::query()->max('id');

        $ok = $this->send($whatsapp, $templates, $phone);

        $log = MessageLog::query()->where('id', '>', $before)->latest('id')->first();

        if ($log === null) {
            $this->error('No ledger row was written — the send never started. Check the log for an exception.');

            return self::FAILURE;
        }

        $this->line('Status:     ' . $log->status);
        $this->line('Provider:   ' . $log->provider . ($log->provider_message_id ? ' (' . $log->provider_message_id . ')' : ''));

        if ($log->error !== null) {
            $this->newLine();
            $this->error('Provider said: ' . $log->error);
        }

        if ($ok) {
            $this->newLine();
            $this->info('Sent. If it does not arrive, the provider accepted it and the problem is downstream (template approval, opt-out, or the number not being on WhatsApp).');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function send(WhatsAppService $whatsapp, TemplateResolver $templates, string $phone): bool
    {
        $context = MessageContext::platform(MessageContext::UTILITY, 'probe');

        if (($otp = (string) $this->option('otp')) !== '') {
            $route = $templates->resolve('auth.login_otp', 'whatsapp', $phone);
            $this->reportRoute('auth.login_otp', $route);

            if ($route['mode'] !== TemplateResolver::MODE_TEMPLATE) {
                $this->warn('No approved authentication template — sending as free text instead, which only works inside an open window.');

                return $whatsapp->sendMessage($phone, "Your Haraan login code is: *{$otp}*", $context);
            }

            return $whatsapp->sendOtpTemplate($phone, (string) $route['name'], $otp, $context, (string) $route['language']);
        }

        if (($key = (string) $this->option('key')) !== '') {
            $route = $templates->resolve($key, 'whatsapp', $phone);
            $this->reportRoute($key, $route);

            if ($route['mode'] !== TemplateResolver::MODE_TEMPLATE) {
                $this->error('Not sending: "' . $key . '" has no approved template to send, and free text needs an open window.');

                return false;
            }

            return $whatsapp->sendTemplate(
                $phone,
                (string) $route['name'],
                (array) $this->option('var'),
                $context,
                (string) $route['language'],
            );
        }

        $this->warn('No --key given: sending plain text, which WhatsApp only delivers inside an open 24h window.');

        return $whatsapp->sendMessage($phone, 'Haraan test message — ' . now()->toDateTimeString(), $context);
    }

    /** @param array{mode: string, name: string|null, language: string, reason: string|null} $route */
    private function reportRoute(string $key, array $route): void
    {
        $this->line('Template:   ' . $key . ' → ' . ($route['name'] ?? '(none)')
            . ' [' . $route['mode'] . ($route['reason'] !== null ? ': ' . $route['reason'] : '') . ']');
        $this->newLine();
    }
}
