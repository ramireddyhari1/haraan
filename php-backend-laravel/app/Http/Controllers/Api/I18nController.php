<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;

/**
 * Localization bundles for the app. Public — copy/translation fixes ship without
 * a release: the client pulls a locale bundle and overlays it on its built-in
 * strings. A bundle hash lets the client skip re-applying unchanged sets.
 */
final class I18nController extends Controller
{
    /** GET /api/i18n — supported locales + the source/fallback. */
    public function index(): JsonResponse
    {
        return response()->json([
            'locales' => Translation::LOCALES,
            'fallback' => Translation::FALLBACK,
        ]);
    }

    /** GET /api/i18n/{locale} — full key=>value bundle (English-filled). */
    public function show(string $locale): JsonResponse
    {
        $bundle = Translation::bundle($locale);

        $resolved = in_array($locale, Translation::LOCALES, true) ? $locale : Translation::FALLBACK;

        return response()->json([
            'locale' => $resolved,
            'version' => md5(json_encode($bundle)),
            'translations' => (object) $bundle,
        ]);
    }
}
