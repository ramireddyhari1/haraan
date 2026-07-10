<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The user's own privacy controls (Account → Privacy in the app).
 *
 * Reading and writing are both scoped to the authenticated user; there is no
 * `id` in the route, so one user can never toggle another's visibility.
 */
class PrivacyController extends Controller
{
    /** API field => users column. */
    private const FIELDS = [
        'publicProfile' => 'privacy_public_profile',
        'showStats' => 'privacy_show_stats',
        'showDistrict' => 'privacy_show_district',
        'discoverable' => 'privacy_discoverable',
    ];

    public function show(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json(['settings' => $this->present($user)]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'publicProfile' => ['sometimes', 'boolean'],
            'showStats' => ['sometimes', 'boolean'],
            'showDistrict' => ['sometimes', 'boolean'],
            'discoverable' => ['sometimes', 'boolean'],
        ]);

        // A partial update: the app sends only the toggle that changed, so an
        // absent key must leave its column alone rather than reset it.
        foreach (self::FIELDS as $field => $column) {
            if (array_key_exists($field, $data)) {
                $user->{$column} = (bool) $data[$field];
            }
        }

        $user->save();

        return response()->json(['settings' => $this->present($user)]);
    }

    /** @return array<string, bool> */
    private function present(User $user): array
    {
        $out = [];
        foreach (self::FIELDS as $field => $column) {
            $out[$field] = (bool) ($user->{$column} ?? true);
        }

        return $out;
    }
}
