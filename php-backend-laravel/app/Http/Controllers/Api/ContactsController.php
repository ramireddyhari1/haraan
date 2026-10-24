<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FriendsPlaying;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contacts on Haraan — the opt-in half of "your people are playing".
 *
 * The app sends SHA-256 hashes of its contacts' phone numbers (last 10 digits) and gets back
 * which of them are players. Nothing is stored: no address book, no hash list, not even the
 * fact that a lookup happened. The names the user saved stay on their phone.
 */
class ContactsController extends Controller
{
    /** One phone book's worth; bigger books are sent in pages by the app. */
    private const MAX_HASHES = 3000;

    public function match(Request $request, FriendsPlaying $friends): JsonResponse
    {
        $viewer = $request->attributes->get('auth_user');
        if (! $viewer instanceof User || $viewer->is_guest) {
            return response()->json(['message' => 'Sign in to find your contacts.'], 401);
        }

        $data = $request->validate([
            'hashes'   => ['required', 'array', 'max:'.self::MAX_HASHES],
            'hashes.*' => ['string', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        return response()->json(['data' => $friends->matchContacts($viewer, $data['hashes'])]);
    }
}
