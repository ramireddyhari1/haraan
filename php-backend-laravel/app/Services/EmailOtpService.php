<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailSender;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport as SymfonyTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends OTP emails through the pool of {@see EmailSender} accounts managed in the /control
 * admin panel. Rotates round-robin across active, under-limit accounts and fails over to the
 * next account on error — so no single Gmail account hits its daily send cap or gets blocked.
 *
 * Laravel-native: each account's SMTP creds configure a Symfony mailer transport on the fly.
 * No external Node service is involved.
 */
class EmailOtpService
{
    /**
     * Send an email via the account pool, trying each sendable account until one succeeds.
     *
     * @return bool True if any account delivered it.
     */
    public function send(string $to, string $subject, string $text, ?string $html = null): bool
    {
        // Least-recently-used first so sends spread evenly across the pool.
        $accounts = EmailSender::query()
            ->sendable()
            ->orderByRaw('last_used_at IS NULL DESC')
            ->orderBy('last_used_at')
            ->get();

        if ($accounts->isEmpty()) {
            // No pool configured. In local dev this is fine — the master code 000000 still
            // logs you in (see EmailAuthController::verifyOtp).
            Log::warning("Email OTP: no sendable accounts configured (to {$to}).");

            return false;
        }

        foreach ($accounts as $account) {
            if ($this->sendVia($account, $to, $subject, $text, $html)) {
                return true;
            }
        }

        Log::error("Email OTP: all {$accounts->count()} account(s) failed for {$to}.");

        return false;
    }

    /** Attempt a single send through one account; record success/failure on the row. */
    private function sendVia(EmailSender $account, string $to, string $subject, string $text, ?string $html): bool
    {
        try {
            $fromName = $account->from_name ?: config('app.name', 'Haraan');

            // Each account gets its own isolated Symfony transport built from its SMTP creds —
            // no shared Laravel mail config, so accounts never leak into one another.
            $mailer = new Mailer($this->transportFor($account));

            $email = (new Email())
                ->from(new Address($account->username, $fromName))
                ->to($to)
                ->subject($subject)
                ->text($text);

            if ($html !== null) {
                $email->html($html);
            }

            $mailer->send($email);

            $account->markSent();
            Log::info("Email OTP sent to {$to} via {$account->username}.");

            return true;
        } catch (\Throwable $e) {
            $account->markFailed($e->getMessage());
            Log::error("Email OTP via {$account->username} failed: {$e->getMessage()} — trying next account.");

            return false;
        }
    }

    /** Build an isolated Symfony SMTP transport from one account's credentials. */
    private function transportFor(EmailSender $account): \Symfony\Component\Mailer\Transport\TransportInterface
    {
        // smtps = implicit TLS (port 465); smtp + STARTTLS = explicit TLS (port 587).
        $scheme = $account->encryption === 'tls' ? 'smtp' : 'smtps';

        // Google shows app passwords as 4 space-separated groups ("abcd efgh ijkl mnop");
        // Gmail's SMTP AUTH rejects them if the spaces are sent literally, so strip all
        // whitespace. rawurlencode then keeps any remaining symbols from corrupting the DSN.
        $password = preg_replace('/\s+/', '', (string) $account->app_password);
        $dsn = sprintf(
            '%s://%s:%s@%s:%d',
            $scheme,
            rawurlencode($account->username),
            rawurlencode((string) $password),
            $account->host,
            $account->port,
        );

        return SymfonyTransport::fromDsn($dsn);
    }

    /** Send a test email from a specific account (used by the admin "Send test" action). */
    public function sendTest(EmailSender $account, string $to): bool
    {
        $ok = $this->sendVia(
            $account,
            $to,
            'Haraan · test email',
            "This is a test from your Haraan email sender ({$account->username}). If you received it, this account can deliver OTPs.",
            '<p>This is a test from your Haraan email sender (<b>' . e($account->username) . '</b>).</p><p>If you received it, this account can deliver OTPs.</p>',
        );

        return $ok;
    }
}
