<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TemplateResolver;
use App\Services\WhatsAppService;
use App\Support\PartnerLookup;
use App\Support\MessageContext;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Phone sign-in over WhatsApp, with Firebase SMS as the fallback beneath it.
 *
 * WhatsApp first because it costs a fraction of an SMS and lands where these users
 * already are. Firebase second because it is the only one that reaches a phone with
 * no WhatsApp — and a login is the one message where non-delivery means the person
 * simply cannot get in. There is no email fallback behind it, unlike a ticket.
 *
 * The fallback is therefore the important half, and it is deliberately *silent*:
 * {@see start()} answers `channel: "sms"` for every reason WhatsApp might not work
 * — unapproved template, missing credentials, a number not on WhatsApp, a provider
 * outage — and the browser quietly runs the Firebase flow it always ran. The user
 * sees a code arrive; they never see a failure.
 *
 * That also means this class can be deployed before WhatsApp works at all. Until
 * the login_otp template is approved, every request answers "sms" and the login
 * behaves exactly as it does today.
 *
 * Verification is local: the code is generated here, HMAC'd into the cache, and
 * compared here. That keeps the round trip short and means a WhatsApp outage can
 * never strand a half-finished login.
 */
class WhatsAppOtpController extends Controller
{
    private const TTL_SECONDS = 300;

    /** Wrong-code attempts allowed before the session is burned. */
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly TemplateResolver $templates,
    ) {}

    /**
     * Try to send the code over WhatsApp.
     *
     * Never an error response: the browser only needs to know which channel to
     * drive, and "we couldn't" is a routing answer, not a failure to report.
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'surface' => ['nullable', 'string', 'in:member,partner'],
        ]);

        $partnerSurface = ($validated['surface'] ?? 'member') === 'partner';

        $phone = PhoneNumber::e164(
            $validated['phone'],
            (string) config('services.whatsapp.default_country', '91'),
        );

        if (! PhoneNumber::isRoutable($phone)) {
            return $this->useSms();
        }

        // On the partner console, only an existing PARTNER may be sent a code.
        // Checked server-side even though the page runs its own pre-check first:
        // the surface flag comes from the browser, and a gate that trusts the
        // client is not a gate. Falls through to SMS rather than erroring, so a
        // non-partner sees the same "no partner account" message the Firebase path
        // already gives them, and this endpoint reveals nothing extra.
        if ($partnerSurface && ! PartnerLookup::isPartner($phone)) {
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
            // Keyed HMAC, not bcrypt: this is a short-lived 6-digit code in
            // server-side cache, so bcrypt's deliberate slowness buys nothing and
            // costs ~300ms on every login.
            'otp' => $this->hash($otp),
            'attempts' => 0,
            // Bound to the session at SEND time, not read from the verify request:
            // otherwise a code issued on the member page could be redeemed against
            // the partner console by flipping one field.
            'surface' => $partnerSurface ? 'partner' : 'member',
        ], self::TTL_SECONDS);

        return response()->json([
            'channel' => 'whatsapp',
            'token' => $token,
            'expires_in' => self::TTL_SECONDS,
        ]);
    }

    /** Check the code and sign them in. */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'code' => ['required', 'string'],
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

        // Single use: forget before logging in, so a replayed request can't be
        // treated as a second successful verification.
        Cache::forget($key);

        $phone = (string) $payload['phone'];

        // Partner console: resolve an EXISTING partner and never create anything.
        // Partner numbers are stored in every shape by admins, so the fuzzy lookup
        // is required — and firstOrCreate here would mint a stray member account
        // for a partner whose number simply wasn't stored as E.164.
        if (($payload['surface'] ?? 'member') === 'partner') {
            $partner = PartnerLookup::byPhone($phone);

            if ($partner === null || ! $partner->hasRoleEither(['PARTNER'])) {
                return response()->json([
                    'error' => "We couldn't find a partner account for this number. Ask your admin to add it, or use email.",
                ], 422);
            }

            Auth::login($partner, true);
            $request->session()->regenerate();

            return response()->json(['redirect' => route('filament.partner.pages.dashboard')]);
        }

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Member',
                // users.email is NOT NULL and a phone sign-up has no address, so
                // synthesize a unique placeholder from the (unique) E.164 number.
                // Same convention and same suffix as the Firebase flow, so the two
                // channels can never mint two accounts for one person.
                'email' => $phone . '@phone.haraan.local',
                'password' => bcrypt(Str::random(32)),
                'role' => 'user',
                'status' => 'active',
            ],
        );

        // Partners sign in on the console's own page, not the public site.
        if ($user->hasRoleEither(['PARTNER'])) {
            return response()->json([
                'redirect' => route('filament.partner.auth.login'),
                'message' => 'Partner accounts sign in at /partner/login.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'redirect' => $request->session()->pull('url.intended', '/'),
        ]);
    }

    /** "Use the SMS path" — the browser reads this and runs Firebase. */
    private function useSms(): JsonResponse
    {
        return response()->json(['channel' => 'sms']);
    }

    private function key(string $token): string
    {
        return 'wa-login-otp:' . $token;
    }

    /** Fast, keyed one-way hash. Compare with hash_equals (constant time). */
    private function hash(string $otp): string
    {
        return hash_hmac('sha256', trim($otp), (string) config('app.key'));
    }
}
