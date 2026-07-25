<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\GoogleAuthException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebasePhoneVerifier;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Sign-in for the partner console (/partner/login).
 *
 * The public-site phone/Google controllers deliberately REFUSE partners (they bounce
 * them here), because a partner is a console operator, not a member. This controller
 * is the mirror image: it authenticates ONLY existing partner accounts and opens a
 * web-guard session that the Filament partner panel accepts, then sends them to the
 * dashboard.
 *
 * Three doors, one rule — the account must already exist AND carry the PARTNER role.
 * Unlike the member flows, we NEVER create an account here: partners are provisioned
 * by an admin, so an unknown number/email is a plain "no partner account" error rather
 * than a silent sign-up that would grant console access to anyone with a phone.
 */
final class PartnerAuthController extends Controller
{
    public function __construct(
        private readonly FirebasePhoneVerifier $phoneVerifier,
        private readonly GoogleIdTokenVerifier $googleVerifier,
    ) {
    }

    /** POST /partner/auth/phone  { id_token } — Firebase phone OTP. */
    public function phone(Request $request): JsonResponse
    {
        $data = $request->validate(['id_token' => ['required', 'string']]);

        try {
            $phone = $this->phoneVerifier->phoneFromIdToken($data['id_token']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $user = $this->findByPhone($phone);

        return $this->completeOrReject(
            $request,
            $user,
            'We couldn\'t find a partner account for this number. Ask your admin to add it, or use email.',
        );
    }

    /**
     * Match a verified E.164 number against however the partner's phone happens to be
     * stored. Firebase always hands us +91XXXXXXXXXX, but admin-entered numbers live in
     * the DB in every shape — bare 10 digits, a leading 0, "91…", "+91…" — so an exact
     * `where('phone', $e164)` silently misses. Compare on the last 10 digits across the
     * common variants instead.
     */
    private function findByPhone(string $e164): ?User
    {
        $digits = preg_replace('/\D/', '', $e164) ?? '';
        $local  = substr($digits, -10);

        if ($local === '' || strlen($local) < 10) {
            return User::query()->where('phone', $e164)->first();
        }

        $candidates = array_unique([$e164, $local, '+91'.$local, '91'.$local, '0'.$local]);

        return User::query()->whereIn('phone', $candidates)->first();
    }

    /** POST /partner/auth/google  { credential } — Google Identity Services token. */
    public function google(Request $request): JsonResponse
    {
        $data = $request->validate(['credential' => ['required', 'string']]);

        try {
            $claims = $this->googleVerifier->verify($data['credential']);
        } catch (GoogleAuthException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status());
        }

        $user = User::query()->where('email', (string) $claims['email'])->first();

        return $this->completeOrReject(
            $request,
            $user,
            'No partner account is linked to this Google address. Ask your admin to add it, or use email.',
        );
    }

    /** POST /partner/auth/email  { email, password } — password fallback. */
    public function email(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => 'Those credentials do not match our records.'], 422);
        }

        return $this->completeOrReject(
            $request,
            $user,
            'That account is not a partner account.',
        );
    }

    /**
     * Shared tail: gate on the PARTNER role, open a session, and hand back the
     * dashboard URL. A missing or non-partner user yields the caller's message so we
     * never reveal which half (existence vs. role) failed for phone/Google.
     */
    private function completeOrReject(Request $request, ?User $user, string $rejectMessage): JsonResponse
    {
        if (! $user || ! $user->hasRoleEither(['PARTNER'])) {
            return response()->json(['error' => $rejectMessage], 422);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('filament.partner.pages.dashboard')]);
    }
}
