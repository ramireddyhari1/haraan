<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Services\AccountEraser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The in-app half of account deletion (Account → Privacy → Delete account).
 *
 * Google Play requires BOTH an in-app path and a public web URL; this is the
 * in-app one. The web twin lives in Web\AccountDeletionController and is the
 * door for people who have already uninstalled.
 *
 * There is no `id` in the route on purpose — a user can only ever delete the
 * account their own token belongs to.
 *
 * @see \App\Services\AccountEraser  what "deleted" means, and why the row survives
 */
final class AccountController extends Controller
{
    public function __construct(private readonly AccountEraser $eraser)
    {
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // An explicit flag rather than a bare DELETE, so a mis-routed or replayed
        // request can never erase an account by accident. The app sends it only
        // from behind a type-to-confirm dialog.
        $request->validate([
            'confirm' => ['required', 'accepted'],
        ], [
            'confirm.accepted' => 'Deletion must be explicitly confirmed.',
        ]);

        // Guests hold a throwaway identity with nothing to erase, and letting the
        // eraser rewrite one would hand out a "deleted-" email for a row the app
        // still has a live token for.
        if ((bool) ($user->is_guest ?? false)) {
            return response()->json([
                'error' => 'A guest session has no account to delete. Sign in first.',
            ], 422);
        }

        $record = AccountDeletionRequest::create([
            'user_id' => $user->getKey(),
            'email' => (string) $user->email,
            'source' => 'in_app',
            'status' => 'pending',
            'reason' => $request->string('reason')->limit(500)->value() ?: null,
            'ip_address' => $request->ip(),
            'verified_at' => now(),
        ]);

        $result = $this->eraser->erase($user);

        $record->update(['status' => 'completed', 'completed_at' => now()]);

        // The client must drop its token on receipt — every session row is gone,
        // so the token is already dead server-side.
        return response()->json([
            'message' => 'Your account has been deleted.',
            'deleted' => true,
            'user_id' => $result['user_id'],
        ]);
    }
}
