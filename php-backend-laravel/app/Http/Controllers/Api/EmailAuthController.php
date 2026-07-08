<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Support\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Passwordless email login. Mirrors {@see WhatsAppAuthController} but delivers the OTP by
 * email through {@see \App\Services\EmailOtpService} (Laravel-native SMTP, rotating through the
 * admin-managed EmailSender pool). New users are created on first request with the name + age
 * supplied by the sign-up form.
 */
final class EmailAuthController extends Controller
{
    private const OTP_TTL_SECONDS = 300;

    public function __construct(private readonly EmailOtpService $emailService)
    {
    }

    public function requestOtp(Request $request): JsonResponse
    {
        // Step 1 is email-only: we identify the account by email, send a code, and only
        // ask a brand-new user for name + date of birth AFTER they verify (see completeProfile).
        // name/age are still accepted (ignored) so older app builds don't 422.
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'age' => ['nullable', 'integer', 'min:5', 'max:120'],
        ]);

        $email = mb_strtolower(trim($validated['email']));

        // Is this a first-time email? Decides whether verify logs straight in or asks for details.
        // No account is created here — a new user's row is only written in completeProfile().
        $isNew = ! User::query()->where('email', $email)->exists();

        $verificationToken = Str::random(48);
        $otp = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($verificationToken), [
            'email' => $email,
            // Keyed HMAC (see WhatsAppAuthController) — instant and safe for a short-lived code.
            'otp' => $this->hashOtp($otp),
            'is_new' => $isNew,
            'otp_verified' => false,
        ], self::OTP_TTL_SECONDS);

        $subject = 'Your Haraan login code';
        $text = "Your Haraan login code is: {$otp}\n\nThis code will expire in 5 minutes.\n\nIf you didn't request this, you can ignore this email.";
        $html = $this->otpHtml($otp);
        $sent = $this->emailService->send($email, $subject, $text, $html);

        // Local dev has no bridge, so a send failure must not block sign-up.
        if (! $sent && ! app()->environment('local')) {
            Cache::forget($this->cacheKey($verificationToken));

            return response()->json([
                'error' => 'Failed to send the code. Please check the email address and try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'A login code has been sent to your email.',
            'verificationToken' => $verificationToken,
            'expiresIn' => self::OTP_TTL_SECONDS,
            'email' => $email,
            'newUser' => $isNew,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_token' => ['required', 'string'],
            'otp' => ['required', 'string'],
        ]);

        $payload = Cache::get($this->cacheKey($validated['verification_token']));

        if (! is_array($payload) || empty($payload['email']) || empty($payload['otp'])) {
            return response()->json(['error' => 'Session expired. Please request a new code.'], 410);
        }

        if (! hash_equals((string) $payload['otp'], $this->hashOtp($validated['otp']))) {
            return response()->json(['error' => 'Invalid code. Please try again.'], 422);
        }

        $email = (string) $payload['email'];
        $user = User::query()->where('email', $email)->first();

        // Existing account → the code is all we needed; log them straight in.
        if ($user instanceof User) {
            Cache::forget($this->cacheKey($validated['verification_token']));

            return response()->json([
                'message' => 'Login successful.',
                'newUser' => false,
                'token' => $this->issueToken($user),
                'user' => new UserResource($user),
            ]);
        }

        // Brand-new email → the code is verified, but we still need name + date of birth.
        // Keep the session alive (now marked verified) so completeProfile() can finish sign-up.
        $payload['otp_verified'] = true;
        Cache::put($this->cacheKey($validated['verification_token']), $payload, self::OTP_TTL_SECONDS);

        return response()->json([
            'message' => 'Email verified — tell us a bit about you.',
            'newUser' => true,
            'verificationToken' => $validated['verification_token'],
            'email' => $email,
        ]);
    }

    /**
     * Finish sign-up for a brand-new user: after their email is verified, collect name +
     * date of birth, create the account, and issue a token. Age is derived from the DOB.
     */
    public function completeProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
        ]);

        $key = $this->cacheKey($validated['verification_token']);
        $payload = Cache::get($key);

        if (! is_array($payload) || empty($payload['email']) || empty($payload['otp_verified'])) {
            return response()->json(['error' => 'Session expired. Please request a new code.'], 410);
        }

        $email = (string) $payload['email'];
        $dob = \Carbon\Carbon::parse($validated['date_of_birth'])->startOfDay();

        // firstOrCreate guards the rare race where the email got registered meanwhile.
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $validated['name'],
                'date_of_birth' => $dob->toDateString(),
                'age' => $dob->age,
                'password' => Hash::make(Str::random(32)),
                'role' => 'user',
                'status' => 'active',
            ],
        );

        // If it already existed (race), still apply the details they just entered.
        if (! $user->wasRecentlyCreated) {
            $user->fill([
                'name' => $validated['name'],
                'date_of_birth' => $dob->toDateString(),
                'age' => $dob->age,
            ])->save();
        }

        Cache::forget($key);

        return response()->json([
            'message' => 'Welcome to Haraan!',
            'newUser' => true,
            'token' => $this->issueToken($user),
            'user' => new UserResource($user),
        ]);
    }

    private function issueToken(User $user): string
    {
        return JwtService::issue([
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
        ], (string) config('app.jwt_secret'));
    }

    private function cacheKey(string $verificationToken): string
    {
        return 'email-otp:' . $verificationToken;
    }

    /** Fast, keyed one-way hash for the cached OTP. Compare with hash_equals (constant time). */
    private function hashOtp(string $otp): string
    {
        return hash_hmac('sha256', $otp, (string) config('app.key'));
    }

    /**
     * Branded, email-client-safe HTML body for the OTP email.
     *
     * Table-based layout with fully inlined styles (required by Gmail/Outlook), and the Haraan
     * brand rendered as real HTML text — wordmark + tagline in brand blue/green — rather than a
     * heavy banner image. Keeping it text-first (high text-to-image ratio, no external images,
     * no links) is deliberate: it's the design that least antagonises spam filters while still
     * looking on-brand. The hidden preheader controls the grey preview line in the inbox list.
     */
    private function otpHtml(string $otp): string
    {
        $brandBlue = '#1E63FF';
        $brandGreen = '#22B24C';
        $ink = '#0F172A';
        $muted = '#64748B';

        // Space the digits so the code is easy to read and copy: "4 2 8 5 8 8".
        $spacedOtp = trim(chunk_split($otp, 1, ' '));

        return <<<HTML
        <!-- preheader: shown as the grey preview text, then hidden -->
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">Your Haraan login code is {$otp} — expires in 5 minutes.</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1F5F9;margin:0;padding:0">
          <tr>
            <td align="center" style="padding:32px 16px">
              <table role="presentation" width="440" cellpadding="0" cellspacing="0" style="width:440px;max-width:100%;background:#FFFFFF;border-radius:18px;overflow:hidden;box-shadow:0 6px 24px rgba(15,23,42,0.08)">
                <!-- brand accent bar: blue → green -->
                <tr><td style="height:6px;line-height:6px;font-size:6px;background:{$brandBlue};background-image:linear-gradient(90deg,{$brandBlue},{$brandGreen})">&nbsp;</td></tr>
                <!-- header: wordmark + tagline -->
                <tr>
                  <td align="center" style="padding:32px 32px 8px">
                    <div style="font-family:'Segoe UI',Arial,Helvetica,sans-serif;font-size:40px;font-weight:800;letter-spacing:-1px;color:{$brandBlue};line-height:1">Haraan</div>
                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:600;letter-spacing:3px;color:{$ink};margin-top:8px">CONNECT &nbsp;&middot;&nbsp; DISCOVER &nbsp;&middot;&nbsp; EXPERIENCE</div>
                  </td>
                </tr>
                <!-- body -->
                <tr>
                  <td style="padding:24px 32px 8px;font-family:Arial,Helvetica,sans-serif">
                    <h1 style="margin:0 0 6px;font-size:20px;font-weight:700;color:{$ink}">Your login code</h1>
                    <p style="margin:0;font-size:14px;line-height:22px;color:{$muted}">Use this code to sign in to Haraan. It expires in 5 minutes.</p>
                  </td>
                </tr>
                <!-- code chip -->
                <tr>
                  <td style="padding:20px 32px 4px">
                    <div style="font-family:'Courier New',Courier,monospace;font-size:34px;font-weight:800;letter-spacing:6px;color:{$ink};background:#EEF3FF;border:1px solid #DCE6FF;border-radius:14px;padding:18px;text-align:center">{$spacedOtp}</div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:16px 32px 28px;font-family:Arial,Helvetica,sans-serif">
                    <p style="margin:0;font-size:12px;line-height:18px;color:#94A3B8">If you didn't request this, you can safely ignore this email — no changes will be made to your account.</p>
                  </td>
                </tr>
                <!-- footer -->
                <tr><td style="border-top:1px solid #EEF2F7">&nbsp;</td></tr>
                <tr>
                  <td align="center" style="padding:18px 32px 26px;font-family:Arial,Helvetica,sans-serif">
                    <div style="font-size:12px;color:{$muted}">info.haraan@gmail.com &nbsp;&nbsp;|&nbsp;&nbsp; @haraan_official</div>
                    <div style="font-size:11px;color:#B4BECC;margin-top:6px">&copy; Haraan · Connect · Discover · Experience</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
        HTML;
    }
}
