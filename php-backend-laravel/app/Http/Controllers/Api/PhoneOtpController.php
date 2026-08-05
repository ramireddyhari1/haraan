<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TemplateResolver;
use App\Services\WhatsAppService;
use App\Support\JwtService;
use App\Support\MessageContext;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Continue with phone" for the Android app — WhatsApp (MSG91) first, Firebase SMS
 * beneath it.
 *
 * Token-returning twin of {@see \App\Http\Controllers\Auth\WhatsAppOtpController}
 * (the website's session-based version). The app previously had ONLY the Firebase
 * path ({@see FirebasePhoneAuthController}), which is gated on Firebase Console
 * config (Phone provider + SHA registration) — so phone sign-in did not work in the
 * app at all while the same number signed in fine on the website. This gives the app
 * the channel the website already uses.
 *
 * IDENTITY IS THE POINT. This deliberately does NOT reuse the older
 * {@see WhatsAppAuthController} (`/api/auth/whatsapp/*`), which stores the number as
 * bare digits with an `@whatsapp.local` placeholder. The website's WhatsApp and
 * Firebase flows both key on E.164 + `@phone.haraan.local`, so routing the app
 * through the old controller would mint a SECOND account for anyone who had ever
 * signed in on the web. Every rule below matches the website's controller exactly:
 * E.164 via {@see PhoneNumber::e164()}, find-or-create on `phone`, same placeholder
 * email suffix. Change one surface and you must change the other.
 *
 * {@see start()} never returns an error — "we couldn't use WhatsApp" is a routing
 * answer, not a failure. Every reason (unapproved template, missing credentials, a
 * number not on WhatsApp, a provider outage) answers `channel: "sms"` and the app
 * silently runs the Firebase flow it always ran. A login is the one message with
 * nothing behind it: no email copy, so non-delivery means the person cannot get in.
 *
 * Verification is local — the code is generated here, HMAC'd into the cache and
 * compared here — so a WhatsApp outage can never strand a half-finished login.
 *
 * Members only. Partners sign in through the partner console's own door, which
 * deliberately stays on its existing path.
 */
final class PhoneOtpController extends Controller
{
    private const TTL_SECONDS = 300;

    /** Wrong-code attempts allowed before the session is burned. */
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly TemplateResolver $templates,
    ) {
    }

    /**
     * POST /api/auth/phone-otp/start  { phone: string }
     *
     * → { channel: "whatsapp", token, expires_in } when the code went out
     * → { channel: "sms" } for every reason it could not. Never an error.
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $phone = PhoneNumber::e164(
            $validated['phone'],
            (string) config('services.whatsapp.default_country', '91'),
        );

        if (! PhoneNumber::isRoutable($phone)) {
            return $this->useSms();
        }

        // A first-time login is always outside the 24-hour service window — that
        // window is opened by the customer messaging us, and they never have. So an
        // approved AUTHENTICATION template is the only send WhatsApp will deliver,
        // and anything else means: use SMS.
        $route = $this->templates->resolve('auth.login_otp', 'whatsapp', $phone);

        if ($route['mode'] !== TemplateResolver::MODE_TEMPLATE) {
            return $this->useSms();
        }

        $otp = (string) random_int(100000, 999999);

        $sent = $this->whatsapp->sendOtpTemplate(
            $phone,
            (string) $route['name'],
            $otp,
            // Platform-owned: a login is Haraan's own traffic and must never be
            // billed to whichever partner they last booked with.
            MessageContext::platform(MessageContext::AUTHENTICATION, 'auth.login_otp'),
            (string) $route['language'],
        );

        if (! $sent) {
            return $this->useSms();
        }

        $token = Str::random(48);

        Cache::put($this->key($token), [
            'phone' => $phone,
            // Keyed HMAC, not bcrypt: a short-lived 6-digit code in server-side
            // cache, so bcrypt's deliberate slowness buys nothing and costs ~300ms
            // on every login.
            'otp' => $this->hash($otp),
            'attempts' => 0,
        ], self::TTL_SECONDS);

        return response()->json([
            'channel' => 'whatsapp',
            'token' => $token,
            'expires_in' => self::TTL_SECONDS,
        ]);
    }

    /**
     * POST /api/auth/phone-otp/verify  { token: string, code: string, name?: string }
     *
     * Returns the same envelope as the Google / Firebase-phone / email flows so the
     * app can treat every sign-in identically.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'code'  => ['required', 'string'],
            'name'  => ['nullable', 'string', 'max:60'],
        ]);

        $key = $this->key($validated['token']);
        $payload = Cache::get($key);

        if (! is_array($payload) || empty($payload['phone']) || empty($payload['otp'])) {
            return response()->json(['error' => 'That code has expired. Please request a new one.'], 410);
        }

        if (! hash_equals((string) $payload['otp'], $this->hash($validated['code']))) {
            $payload['attempts'] = (int) ($payload['attempts'] ?? 0) + 1;

            // Burn the session rather than let a 6-digit code be walked. The
            // throttle limits rate; this limits total guesses against one code.
            if ($payload['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($key);

                return response()->json(['error' => 'Too many incorrect codes. Please request a new one.'], 429);
            }

            Cache::put($key, $payload, self::TTL_SECONDS);

            return response()->json(['error' => 'That code is not right. Please check and try again.'], 422);
        }

        // Single use: forget before issuing, so a replayed request can't be treated
        // as a second successful verification.
        Cache::forget($key);

        $phone = (string) $payload['phone'];

        $isNew = ! User::query()->where('phone', $phone)->exists();

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => trim((string) ($validated['name'] ?? '')) ?: 'Member',
                // users.email is NOT NULL and a phone sign-up has no address, so
                // synthesize a unique placeholder from the (unique) E.164 number.
                // Same convention and suffix as the Firebase flow AND the website,
                // so no channel can ever mint two accounts for one person.
                'email' => $phone.'@phone.haraan.local',
                'password' => bcrypt(Str::random(32)),
                'role' => 'user',
                'status' => 'active',
            ],
        );

        return response()->json([
            'message' => $isNew ? 'Welcome to Haraan!' : 'Welcome back!',
            'newUser' => $isNew,
            'token' => JwtService::issue([
                'sub' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ], (string) config('app.jwt_secret')),
            'user' => new UserResource($user),
        ]);
    }

    /** "Use the SMS path" — the app reads this and runs Firebase. */
    private function useSms(): JsonResponse
    {
        return response()->json(['channel' => 'sms']);
    }

    private function key(string $token): string
    {
        // Namespaced away from the website's `wa-login-otp:` so an app token and a
        // web token can never be redeemed against the wrong surface.
        return 'app-login-otp:'.$token;
    }

    /** Fast, keyed one-way hash. Compare with hash_equals (constant time). */
    private function hash(string $otp): string
    {
        return hash_hmac('sha256', trim($otp), (string) config('app.key'));
    }
}
