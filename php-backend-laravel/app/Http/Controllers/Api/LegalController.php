<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Http\JsonResponse;

/**
 * Public legal copy for the app's Terms & Conditions / Privacy Policy screens.
 * Deliberately unauthenticated: a user must be able to read the terms before
 * they have an account.
 */
class LegalController extends Controller
{
    /** Slugs the app is allowed to ask for. */
    private const SLUGS = ['terms', 'privacy'];

    public function show(string $slug): JsonResponse
    {
        if (! in_array($slug, self::SLUGS, true)) {
            return response()->json(['error' => 'Unknown document.'], 404);
        }

        $doc = LegalDocument::query()->where('slug', $slug)->first();

        if ($doc === null) {
            return response()->json(['error' => 'Unknown document.'], 404);
        }

        return response()->json([
            'slug' => $doc->slug,
            'title' => $doc->title,
            'body' => $doc->body,
            'updatedAt' => $doc->updated_at?->toIso8601String(),
        ]);
    }
}
