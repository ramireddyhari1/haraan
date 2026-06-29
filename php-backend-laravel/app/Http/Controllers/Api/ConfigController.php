<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\FeatureFlag;
use App\Models\User;
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
            'server_time' => now()->toIso8601String(),
        ]);
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

