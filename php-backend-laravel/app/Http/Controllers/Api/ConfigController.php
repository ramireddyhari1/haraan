<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Services\Rewards\RewardEngine;
use App\Support\Membership\MemberFeature;
use App\Support\Operations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Remote config for the app. Returns feature flags resolved for the current
 * viewer (anonymous-safe via auth.jwt.optional) so the client can toggle
 * capabilities without an app release. App version may be passed as the
 * `X-App-Version` header or `?app_version=` for version-gated flags.
 */
final class ConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $user = $user instanceof User ? $user : null;

        $appVersion = $request->header('X-App-Version') ?? $request->query('app_version');

        $features = FeatureFlag::query()->get()
            ->mapWithKeys(fn (FeatureFlag $flag): array => [
                $flag->key => $flag->isEnabledFor($user, $appVersion),
            ]);

        return response()->json([
            'features' => $features,
            'theme' => $this->theme(),
            'realtime' => $this->realtime(),
            // /control → Operations: maintenance, forced/optional update, paused bookings,
            // payments, match creation, AI. The server enforces all of these regardless; this
            // lets the app explain them instead of failing request by request. A change is
            // broadcast as a `config` content event, so clients refetch within seconds.
            'operations' => Operations::clientState(is_string($appVersion) ? $appVersion : null),
            // Post-match rewards. `rewarded_ads` is false for a member whose plan hides ads, so the
            // app never even initialises the ad SDK for them.
            'rewards' => [
                'enabled' => RewardEngine::enabled(),
                'rewarded_ads' => RewardEngine::enabled()
                    && RewardEngine::rewardedAdsAvailable()
                    && ! app(MemberEntitlements::class)->allows($user, MemberFeature::ADS_HIDDEN),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Where/whether the client should open a realtime (Reverb) connection. */
    private function realtime(): array
    {
        $enabled = config('broadcasting.default') === 'reverb';
        $reverb = config('broadcasting.connections.reverb');

        return [
            'enabled' => $enabled,
            'key' => $enabled ? ($reverb['key'] ?? null) : null,
            'host' => $reverb['options']['host'] ?? null,
            'port' => (int) ($reverb['options']['port'] ?? 443),
            'scheme' => $reverb['options']['scheme'] ?? 'https',
            'channel' => 'content',
        ];
    }

    /** Branding settings, with the logo path resolved to a public URL. */
    private function theme(): array
    {
        $theme = AppSetting::group('branding');

        if (! empty($theme['logo'])) {
            $theme['logo'] = Storage::disk('public')->url($theme['logo']);
        }

        return $theme;
    }
}

