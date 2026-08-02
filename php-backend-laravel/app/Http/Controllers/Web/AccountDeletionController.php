<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Services\AccountEraser;
use App\Services\EmailOtpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The public account-deletion door, required by Google Play.
 *
 * Play insists this page be reachable by someone who has ALREADY UNINSTALLED the
 * app, which is why every route here is public and why the signed-out path exists
 * at all — an in-app button alone does not satisfy the policy.
 *
 * Two paths, because proof of ownership differs:
 *   - signed in  -> the session IS the proof; erase on confirm, immediately.
 *   - signed out -> anyone can type any address, so nothing happens until they
 *                   click a token we emailed to that address.
 *
 * Both paths answer identically whether or not the address matches an account.
 * Telling a stranger "no such account" turns this form into a free account-existence
 * oracle, which is a worse privacy leak than the one the page exists to fix.
 */
final class AccountDeletionController extends Controller
{
    public function __construct(
        private readonly AccountEraser $eraser,
        private readonly EmailOtpService $mail,
    ) {
    }

    public function show(Request $request): View
    {
        return view('site.account-delete', [
            'title' => 'Delete your Haraan account',
            'user' => $request->user(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $signedIn = $request->user();

        $data = $request->validate([
            'email' => [$signedIn ? 'nullable' : 'required', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Please tick the box to confirm you want the account deleted.',
            'email.required' => 'Enter the email address on the account.',
        ]);

        $email = Str::lower(trim($signedIn ? (string) $signedIn->email : (string) $data['email']));

        // A signed-in request is already proven, so it completes here and now.
        if ($signedIn !== null && Str::lower((string) $signedIn->email) === $email) {
            $record = AccountDeletionRequest::create([
                'user_id' => $signedIn->getKey(),
                'email' => $email,
                'source' => 'web',
                'status' => 'pending',
                'reason' => $data['reason'] ?? null,
                'ip_address' => $request->ip(),
                'verified_at' => now(),
            ]);

            $this->eraser->erase($signedIn);

            $record->update(['status' => 'completed', 'completed_at' => now()]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('site.account.delete')
                ->with('deleted', 'Your account has been deleted. Sorry to see you go.');
        }

        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        $record = AccountDeletionRequest::create([
            'user_id' => $user?->getKey(),
            'email' => $email,
            'source' => 'web',
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
            'ip_address' => $request->ip(),
            'verify_token' => AccountDeletionRequest::freshToken(),
        ]);

        if ($user !== null) {
            $link = route('site.account.delete.confirm', ['token' => $record->verify_token]);

            $this->mail->send(
                $email,
                'Confirm deletion of your Haraan account',
                "We received a request to delete your Haraan account.\n\n"
                . "To confirm, open this link within 48 hours:\n{$link}\n\n"
                . "This permanently deletes your profile, your play history and your "
                . "personal details. Records of past ticket purchases are kept for tax "
                . "purposes but are no longer linked to you.\n\n"
                . "If you did not ask for this, ignore this email — nothing will happen.\n\n"
                . 'Haraan'
            );
        }

        // Identical answer either way. See the class docblock.
        return back()->with('success',
            'If that address has a Haraan account, we have emailed a confirmation link. '
            . 'Open it to finish deleting the account.');
    }

    public function confirm(string $token): View
    {
        $record = AccountDeletionRequest::where('verify_token', $token)
            ->where('status', 'pending')
            ->first();

        // 48h window, matching the wording in the email.
        $expired = $record !== null && $record->created_at?->lt(now()->subHours(48));

        if ($record === null || $expired) {
            return view('site.account-delete-done', [
                'title' => 'Link expired',
                'ok' => false,
                'message' => $record === null
                    ? 'This link is not valid. It may already have been used.'
                    : 'This link has expired. Please start the request again.',
            ]);
        }

        $record->update(['verified_at' => now(), 'verify_token' => null]);

        $user = $record->user_id !== null ? User::find($record->user_id) : null;

        if ($user !== null) {
            $this->eraser->erase($user);
        }

        $record->update(['status' => 'completed', 'completed_at' => now()]);

        return view('site.account-delete-done', [
            'title' => 'Account deleted',
            'ok' => true,
            'message' => 'Your Haraan account and personal data have been deleted.',
        ]);
    }
}
