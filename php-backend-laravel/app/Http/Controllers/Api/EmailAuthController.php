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

    /** Minimal branded HTML body for the OTP email. */
    private function otpHtml(string $otp): string
    {
        return <<<HTML
        <div style="font-family:Arial,Helvetica,sans-serif;max-width:420px;margin:0 auto;padding:24px">
          <h2 style="color:#0F172A;margin:0 0 8px">Haraan login code</h2>
          <p style="color:#475569;margin:0 0 20px">Use this code to sign in. It expires in 5 minutes.</p>
          <div style="font-size:34px;font-weight:800;letter-spacing:8px;color:#0F172A;background:#F1F5F9;border-radius:12px;padding:16px;text-align:center">{$otp}</div>
          <p style="color:#94A3B8;font-size:12px;margin:20px 0 0">If you didn't request this, you can safely ignore this email.</p>
        </div>
        HTML;
    }
}
