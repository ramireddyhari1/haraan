<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PaymentsPaused;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over Razorpay's REST API (no SDK — packagist is unreachable in this
 * environment). Creates orders with HTTP basic auth and verifies payment signatures with a
 * constant-time HMAC check. The KEY_SECRET is read from config and never leaves the server.
 */
final class RazorpayGateway
{
    public const MIN_AMOUNT_PAISE = 100;

    private const ORDERS_ENDPOINT = 'https://api.razorpay.com/v1/orders';

    private const PAYMENT_LINKS_ENDPOINT = 'https://api.razorpay.com/v1/payment_links';

    private const QR_CODES_ENDPOINT = 'https://api.razorpay.com/v1/payments/qr_codes';

    /** Whether both keys are configured — callers gate the whole payment path on this. */
    public function isConfigured(): bool
    {
        return $this->keyId() !== null && $this->keySecret() !== null;
    }

    /** The public key id, safe to hand to the browser/app so it can open checkout. */
    public function publicKey(): ?string
    {
        return $this->keyId();
    }

    /**
     * Create a Razorpay order for the given amount (in paise). Returns the decoded order.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException  On misconfiguration, auth failure, or an unreachable/again-failing API.
     */
    public function createOrder(int $amountPaise, string $receipt, string $currency = 'INR', array $notes = []): array
    {
        // /control → Operations → Stop taking payments.
        PaymentsPaused::guard();

        if (! $this->isConfigured()) {
            throw new RuntimeException('Payments are not configured.', 500);
        }

        if ($amountPaise < self::MIN_AMOUNT_PAISE) {
            throw new RuntimeException('Amount is below the minimum.', 422);
        }

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->timeout(20)
                ->post(self::ORDERS_ENDPOINT, [
                    'amount'          => $amountPaise,
                    'currency'        => strtoupper($currency),
                    'receipt'         => $receipt,
                    'payment_capture' => 1,
                ] + ($notes !== [] ? ['notes' => $notes] : []));
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Payment authentication failed.', 401);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Could not create the payment order.', 500);
        }

        return $response->json();
    }

    /**
     * Create a Razorpay **payment link** the desk can send to a walk-in customer
     * (WhatsApp/SMS), so "pay online" works without the customer being in our app.
     *
     * Razorpay owns the payment page; we only keep the returned `short_url` and `id`.
     * The booking is marked paid by the existing payment webhook, never by this call —
     * a link that was created is not money that arrived.
     *
     * @param  array<string, string>  $notes  Echoed back on the webhook (booking id, etc.).
     * @return array{id: string, short_url: string|null}
     *
     * @throws RuntimeException  On misconfiguration, auth failure, or an unreachable API.
     */
    public function createPaymentLink(
        int $amountPaise,
        string $description,
        ?string $customerName = null,
        ?string $customerPhone = null,
        array $notes = [],
        bool $notifySms = true,
    ): array {
        PaymentsPaused::guard();

        if (! $this->isConfigured()) {
            throw new RuntimeException('Payments are not configured.', 500);
        }

        if ($amountPaise < self::MIN_AMOUNT_PAISE) {
            throw new RuntimeException('Amount is below the minimum.', 422);
        }

        $payload = [
            'amount'      => $amountPaise,
            'currency'    => 'INR',
            // Razorpay caps the description; keep it short and human.
            'description' => mb_substr($description, 0, 120),
            'notes'       => $notes,
            // Let Razorpay text the link too when we know the number.
            // Off when the customer pays on the desk's own screen — a text of the same
            // link would only be a second way to pay one booking.
            'notify'      => ['sms' => $notifySms && $customerPhone !== null, 'email' => false],
            'reminder_enable' => true,
        ];

        $customer = array_filter([
            'name'    => $customerName,
            'contact' => $customerPhone,
        ]);

        if ($customer !== []) {
            $payload['customer'] = $customer;
        }

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->timeout(20)
                ->post(self::PAYMENT_LINKS_ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Payment authentication failed.', 401);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Could not create the payment link.', 500);
        }

        $link = $response->json();

        return [
            'id'        => (string) ($link['id'] ?? ''),
            'short_url' => $link['short_url'] ?? null,
        ];
    }

    /**
     * Ask Razorpay whether a payment link has been paid. This is the authority, and it
     * needs no webhook — the desk can watch a link go green while the customer is still
     * standing there, and a venue whose webhook was never configured still collects.
     *
     * @return array{status: string, paid: bool, payment_id: string|null, amount_paid: float}
     *
     * @throws RuntimeException  When Razorpay can't be reached or errors — callers must
     *                           treat that as "don't know yet", never as "not paid".
     */
    public function paymentLinkStatus(string $linkId): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Payments are not configured.', 500);
        }

        $linkId = trim($linkId);

        if ($linkId === '') {
            throw new RuntimeException('Missing payment link id.', 422);
        }

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->timeout(15)
                ->get(self::PAYMENT_LINKS_ENDPOINT.'/'.$linkId);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Could not read the payment link.', 500);
        }

        $link = $response->json();
        $status = (string) ($link['status'] ?? 'created');

        // Only `paid` is money. `created`/`partially_paid`/`expired`/`cancelled` are not,
        // and treating a partial as settled would hand over a ticket for part of the fee.
        $paid = $status === 'paid';

        $paymentId = null;
        foreach ((array) ($link['payments'] ?? []) as $p) {
            if (($p['status'] ?? '') === 'captured') {
                $paymentId = (string) ($p['payment_id'] ?? $p['id'] ?? '');
                break;
            }
        }

        return [
            'status'      => $status,
            'paid'        => $paid,
            'payment_id'  => $paymentId ?: null,
            'amount_paid' => ((int) ($link['amount_paid'] ?? 0)) / 100,
            // The booking this link was minted for. Callers must match it against the
            // booking they are settling, or one paid link could settle any booking.
            'booking_id'  => isset($link['notes']['booking_id']) ? (string) $link['notes']['booking_id'] : null,
        ];
    }

    /**
     * Has this order been paid? For a desk walk-in paying in Razorpay's own checkout on
     * the desk phone: the same shape as {@see paymentLinkStatus()}, so the desk settles
     * it the same way. Only a captured payment counts.
     *
     * @return array{status: string, paid: bool, payment_id: string|null, amount_paid: float, booking_id: string|null}
     *
     * @throws RuntimeException  When Razorpay can't be reached — "don't know", never "unpaid".
     */
    public function orderStatus(string $orderId): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Payments are not configured.', 500);
        }

        $orderId = trim($orderId);

        if ($orderId === '') {
            throw new RuntimeException('Missing order id.', 422);
        }

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->timeout(15)
                ->get(self::ORDERS_ENDPOINT.'/'.$orderId);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Could not read the payment order.', 500);
        }

        $order = $response->json();
        $paymentId = $this->capturedPaymentFor($orderId);

        return [
            'status'      => $paymentId !== null ? 'paid' : (string) ($order['status'] ?? 'created'),
            'paid'        => $paymentId !== null,
            'payment_id'  => $paymentId,
            'amount_paid' => ((int) ($order['amount_paid'] ?? 0)) / 100,
            'booking_id'  => isset($order['notes']['booking_id']) ? (string) $order['notes']['booking_id'] : null,
        ];
    }

    /**
     * Stop a payment link taking money. Used when the desk replaces or abandons a link,
     * so a customer can't pay the old one and the new one both. Best-effort.
     */
    public function cancelPaymentLink(string $linkId): void
    {
        if (! $this->isConfigured() || trim($linkId) === '') {
            return;
        }

        try {
            Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()->timeout(15)
                ->post(self::PAYMENT_LINKS_ENDPOINT.'/'.trim($linkId).'/cancel');
        } catch (\Throwable) {
            // A paid or expired link refuses cancel; either way it takes no new money.
        }
    }

    /**
     * Mint a single-use, fixed-amount **UPI QR** for the desk to show the customer.
     *
     * Any UPI app scans it and pays exactly this amount. Like a payment link, creating
     * one is not money: the booking is settled only once {@see self::upiQrStatus()}
     * reports a captured payment.
     *
     * `close_by` is where Razorpay stops accepting payments. The desk's own countdown is
     * usually shorter and closes the QR itself ({@see self::closeUpiQr()}); this is only
     * the backstop, kept past Razorpay's minimum window.
     *
     * @param  array<string, string>  $notes
     * @return array{id: string, upi: string|null, image_url: string|null, close_by: int}
     *
     * @throws RuntimeException  On misconfiguration, a QR feature not enabled on the
     *                           account, auth failure, or an unreachable API.
     */
    public function createUpiQr(int $amountPaise, string $name, string $description, int $minutes, array $notes = []): array
    {
        PaymentsPaused::guard();

        if (! $this->isConfigured()) {
            throw new RuntimeException('Payments are not configured.', 500);
        }

        if ($amountPaise < self::MIN_AMOUNT_PAISE) {
            throw new RuntimeException('Amount is below the minimum.', 422);
        }

        // Razorpay refuses a close_by too close to now; 16 minutes clears its floor.
        $closeBy = time() + max($minutes + 2, 16) * 60;

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->timeout(20)
                ->post(self::QR_CODES_ENDPOINT, [
                    'type'           => 'upi_qr',
                    'name'           => mb_substr($name, 0, 40),
                    'usage'          => 'single_use',
                    'fixed_amount'   => true,
                    'payment_amount' => $amountPaise,
                    'description'    => mb_substr($description, 0, 120),
                    'close_by'       => $closeBy,
                    'notes'          => $notes,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Payment authentication failed.', 401);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Could not create the UPI QR: '.mb_substr($response->body(), 0, 200), 500);
        }

        $qr = $response->json();
        $upi = (string) ($qr['image_content'] ?? '');

        return [
            'id'        => (string) ($qr['id'] ?? ''),
            // The raw upi://pay string, so the app draws a crisp QR itself.
            'upi'       => str_starts_with($upi, 'upi://') ? $upi : null,
            'image_url' => $qr['image_url'] ?? null,
            'close_by'  => (int) ($qr['close_by'] ?? $closeBy),
        ];
    }

    /**
     * Has this UPI QR been paid? Same contract as {@see self::paymentLinkStatus()}: only a
     * captured payment of the full fixed amount counts, and an unreachable gateway throws
     * (callers treat that as "don't know", never "unpaid").
     *
     * @return array{status: string, paid: bool, payment_id: string|null, amount_paid: float, booking_id: string|null}
     */
    public function upiQrStatus(string $qrId): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Payments are not configured.', 500);
        }

        $qrId = trim($qrId);

        if ($qrId === '') {
            throw new RuntimeException('Missing QR id.', 422);
        }

        try {
            $qrResponse = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()->timeout(15)
                ->get(self::QR_CODES_ENDPOINT.'/'.$qrId);
            $paymentsResponse = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()->timeout(15)
                ->get(self::QR_CODES_ENDPOINT.'/'.$qrId.'/payments');
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if (! $qrResponse->successful() || ! $paymentsResponse->successful()) {
            throw new RuntimeException('Could not read the UPI QR.', 500);
        }

        $qr = $qrResponse->json();
        $expected = (int) ($qr['payment_amount'] ?? 0);

        $paymentId = null;
        $amountPaid = 0;
        foreach ((array) ($paymentsResponse->json('items') ?? []) as $p) {
            if (is_array($p) && ($p['status'] ?? '') === 'captured') {
                $paymentId = (string) ($p['id'] ?? '');
                $amountPaid = (int) ($p['amount'] ?? 0);
                break;
            }
        }

        return [
            'status'      => (string) ($qr['status'] ?? 'active'),
            // A partial payment is not the fee; only the full fixed amount settles.
            'paid'        => $paymentId !== null && $paymentId !== '' && $amountPaid >= $expected && $expected > 0,
            'payment_id'  => $paymentId ?: null,
            'amount_paid' => $amountPaid / 100,
            'booking_id'  => isset($qr['notes']['booking_id']) ? (string) $qr['notes']['booking_id'] : null,
        ];
    }

    /**
     * Stop a UPI QR taking payments — the desk's countdown ran out or the booking was
     * dropped. Best-effort: an already-closed QR is the outcome we wanted anyway.
     */
    public function closeUpiQr(string $qrId): void
    {
        if (! $this->isConfigured() || trim($qrId) === '') {
            return;
        }

        try {
            Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()->timeout(15)
                ->post(self::QR_CODES_ENDPOINT.'/'.trim($qrId).'/close');
        } catch (\Throwable) {
            // Razorpay's own close_by closes it regardless.
        }
    }

    /**
     * The id of a CAPTURED payment against a Razorpay order, or null when the order has not
     * been paid. Asks Razorpay directly, so it is the authority when our own record of an
     * order is in doubt — a buyer whose browser died after paying leaves us a reservation
     * that looks abandoned and a payment that very much happened.
     *
     * Only `captured` counts. An `authorized` payment is money held, not money taken, and it
     * can still fail; orders here are created with `payment_capture: 1`, so authorisation
     * turns into capture within seconds and the webhook picks up anything that lands late.
     *
     * @throws RuntimeException  When Razorpay can't be reached or answers with an error — the
     *                           caller must treat that as "don't know", never as "not paid".
     */
    public function capturedPaymentFor(string $orderId): ?string
    {
        if (! $this->isConfigured() || trim($orderId) === '') {
            return null;
        }

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->timeout(15)
                ->get(self::ORDERS_ENDPOINT . '/' . trim($orderId) . '/payments');
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Could not read the payment order.', 502);
        }

        foreach ((array) ($response->json('items') ?? []) as $payment) {
            if (! is_array($payment) || ($payment['status'] ?? null) !== 'captured') {
                continue;
            }

            $id = trim((string) ($payment['id'] ?? ''));

            if ($id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * Issue a refund against a captured Razorpay payment.
     *
     * @param int|null $amountPaise Amount in paise. If null, a full refund is processed.
     * @return array<string, mixed>
     *
     * @throws RuntimeException On API failure or connection error.
     */
    public function refund(string $paymentId, ?int $amountPaise = null, array $notes = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Payments are not configured.', 500);
        }

        $payload = [];
        if ($amountPaise !== null && $amountPaise > 0) {
            $payload['amount'] = $amountPaise;
        }
        if (! empty($notes)) {
            $payload['notes'] = $notes;
        }

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->timeout(15)
                ->post('https://api.razorpay.com/v1/payments/' . trim($paymentId) . '/refund', $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the payment provider for refund.', 502);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Refund request failed: ' . $response->body(), $response->status());
        }

        return (array) $response->json();
    }

    /**
     * Verify a Razorpay checkout signature: HMAC-SHA256(order_id|payment_id, secret) must equal
     * the returned signature (constant-time). Returns false on any mismatch or missing secret.
     */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = $this->keySecret();

        if ($secret === null) {
            return false;
        }

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);

        return hash_equals($expected, $signature);
    }

    private function keyId(): ?string
    {
        $key = config('services.razorpay.key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function keySecret(): ?string
    {
        $secret = config('services.razorpay.secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
