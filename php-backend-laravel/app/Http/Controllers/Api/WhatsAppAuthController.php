<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Support\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class WhatsAppAuthController extends Controller
{
    private const OTP_TTL_SECONDS = 300;

    public function __construct(private readonly WhatsAppService $whatsappService)
    {
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $phone = preg_replace('/[^0-9]/', '', $validated['phone']);
        if ($phone === '') {
            return response()->json(['error' => 'Please enter a valid phone number.'], 422);
        }

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'User ' . substr($phone, -4),
                'email' => $phone . '@whatsapp.local',
                'password' => Hash::make(Str::random(32)),
                'role' => 'user',
                'status' => 'active',
            ],
        );

        $verificationToken = Str::random(48);
        $otp = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($verificationToken), [
            'phone' => $phone,
            'otp' => Hash::make($otp),
            'user_id' => $user->id,
        ], self::OTP_TTL_SECONDS);

        $message = "Your Haraan login code is: *{$otp}*\n\nThis code will expire in 5 minutes.";
        $sent = $this->whatsappService->sendMessage($phone, $message);

        if (! $sent) {
            Cache::forget($this->cacheKey($verificationToken));

            return response()->json([
                'error' => 'Failed to send OTP. Please check if the number is registered on WhatsApp.',
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent to WhatsApp.',
            'verificationToken' => $verificationToken,
            'expiresIn' => self::OTP_TTL_SECONDS,
            'phone' => $phone,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_token' => ['required', 'string'],
            'otp' => ['required', 'string'],
        ]);

        $payload = Cache::get($this->cacheKey($validated['verification_token']));

        if (! is_array($payload) || empty($payload['phone']) || empty($payload['otp'])) {
            return response()->json(['error' => 'Session expired. Please request a new OTP.'], 410);
        }

        if (! Hash::check($validated['otp'], (string) $payload['otp'])) {
            return response()->json(['error' => 'Invalid OTP. Please try again.'], 422);
        }

        $user = User::query()->where('phone', (string) $payload['phone'])->first();
        if (! $user instanceof User) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        Cache::forget($this->cacheKey($validated['verification_token']));

        return response()->json([
            'message' => 'Login successful via WhatsApp.',
            'token' => JwtService::issue([
                'sub' => $user->id,
                'phone' => $user->phone,
                'role' => $user->role,
            ], (string) config('app.jwt_secret')),
            'user' => new UserResource($user),
        ]);
    }

    private function cacheKey(string $verificationToken): string
    {
        return 'whatsapp-otp:' . $verificationToken;
    }
}