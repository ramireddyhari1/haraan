<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * On-the-fly UI translation via Google Cloud Translation. The app posts the strings
 * currently on screen + a target language and gets them back translated. The Google
 * key stays server-side (never shipped in the APK), and every string is cached
 * forever per (target, text) so a phrase is only ever paid for once.
 */
final class TranslationController extends Controller
{
    public function translate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'        => ['required', 'array', 'max:200'],
            'q.*'      => ['nullable', 'string', 'max:600'],
            'target'   => ['required', 'string', 'max:8'],
        ]);

        $texts = array_values($data['q']);
        $target = strtolower($data['target']);

        // English / blank target = passthrough (nothing to translate).
        if ($target === '' || $target === 'en') {
            return response()->json(['translations' => $texts]);
        }

        $key = config('services.google_translate.key');
        if (empty($key)) {
            // Not configured → return originals so the UI simply stays in English.
            return response()->json(['translations' => $texts, 'note' => 'translation_unconfigured']);
        }

        // Split cache hits from misses (blank/numeric strings never need Google).
        $result = [];
        $miss = [];
        foreach ($texts as $t) {
            $t = (string) $t;
            if (trim($t) === '' || is_numeric(trim($t))) {
                $result[$t] = $t;
                continue;
            }
            $cached = Cache::get(self::cacheKey($target, $t));
            if ($cached !== null) {
                $result[$t] = $cached;
            } else {
                $miss[$t] = true; // de-dupe repeated strings
            }
        }

        if ($miss !== []) {
            $batch = array_keys($miss);
            try {
                $resp = Http::asJson()->timeout(12)->post(
                    'https://translation.googleapis.com/language/translate/v2?key=' . $key,
                    ['q' => $batch, 'target' => $target, 'format' => 'text'],
                );
                $translations = $resp->ok() ? ($resp->json('data.translations') ?? []) : [];
                foreach ($batch as $i => $t) {
                    $tx = $translations[$i]['translatedText'] ?? $t;
                    $result[$t] = $tx;
                    Cache::forever(self::cacheKey($target, $t), $tx);
                }
            } catch (\Throwable $e) {
                // Network/API failure → fall back to originals (UI stays readable).
                foreach ($batch as $t) {
                    $result[$t] = $t;
                }
            }
        }

        // Return aligned to the input order (with duplicates resolved).
        return response()->json([
            'translations' => array_map(static fn ($t): string => $result[(string) $t] ?? (string) $t, $texts),
        ]);
    }

    private static function cacheKey(string $target, string $text): string
    {
        return 'tr:' . $target . ':' . md5($text);
    }
}
