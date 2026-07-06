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
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'age' => ['nullable', 'integer', 'min:5', 'max:120'],
        ]);

        $email = mb_strtolower(trim($validated['email']));

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $validated['name'] ?? ('User ' . Str::before($email, '@')),
                'age' => $validated['age'] ?? null,
                'password' => Hash::make(Str::random(32)),
                'role' => 'user',
                'status' => 'active',
            ],
        );

        // Existing user re-entering name/age on the form → keep their record fresh.
        $fill = [];
        if (! empty($validated['name']) && $user->name !== $validated['name']) {
            $fill['name'] = $validated['name'];
        }
        if (! empty($validated['age']) && (int) $user->age !== (int) $validated['age']) {
            $fill['age'] = $validated['age'];
        }
        if ($fill !== []) {
            $user->fill($fill)->save();
        }

        $verificationToken = Str::random(48);
        $otp = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($verificationToken), [
            'email' => $email,
            // Keyed HMAC (see WhatsAppAuthController) — instant and safe for a short-lived code.
            'otp' => $this->hashOtp($otp),
            'user_id' => $user->id,
        ], self::OTP_TTL_SECONDS);

        $subject = 'Your Haraan login code';
        $text = "Your Haraan login code is: {$otp}\n\nThis code will expire in 5 minutes.\n\nIf you didn't request this, you can ignore this email.";
        $html = $this->otpHtml($otp);
        $sent = $this->emailService->send($email, $subject, $text, $html);

        // Local dev has no bridge, so a send failure must not block sign-up — the master code
        // 000000 (see verifyOtp) still works.
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

        $user = User::query()->where('email', (string) $payload['email'])->first();
        if (! $user instanceof User) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        Cache::forget($this->cacheKey($validated['verification_token']));

        return response()->json([
            'message' => 'Login successful.',
            'token' => JwtService::issue([
                'sub' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ], (string) config('app.jwt_secret')),
            'user' => new UserResource($user),
        ]);
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
