<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Filament's own Authenticate middleware answers "signed in, but not for this
 * panel" with a bare abort(403) — a dead-end page with no sign-out link and no
 * explanation. That bites every time someone switches lanes: a partner who opens
 * /control (or an admin who opens /partner) just sees "403 Forbidden".
 *
 * Same decision, better exit. Still 403 — we do not widen access, and we do not
 * silently bounce them to their own console either, because that would make the
 * other panel's login unreachable without clearing cookies. Instead we render a
 * page that says who they're signed in as and offers the two ways out: open the
 * console they can use, or sign out and use another account.
 */
class AuthenticateFilamentPanel extends FilamentAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return; /** @phpstan-ignore-line */
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();

        // Signing out is always allowed. The logout route lives *inside* the panel,
        // so without this the wrong-panel page below would intercept its own
        // "sign out" button and trap the user in the console they can't use.
        if ($request->routeIs('filament.*.auth.logout')) {
            return;
        }

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($user instanceof FilamentUser) {
            if ($user->canAccessPanel($panel)) {
                return;
            }
        } elseif (config('app.env') === 'local') {
            // Matches Filament's rule: a model that doesn't implement FilamentUser
            // is only tolerated locally.
            return;
        }

        throw new HttpResponseException(
            response()->view('errors.wrong-panel', [
                'user' => $user,
                'panel' => $panel,
                'otherPanel' => self::firstAccessiblePanel($user, $panel),
            ], 403),
        );
    }

    /** The panel this user *can* open, if any — so we can offer it as a way out. */
    private static function firstAccessiblePanel(mixed $user, Panel $current): ?Panel
    {
        if (! $user instanceof FilamentUser) {
            return null;
        }

        foreach (Filament::getPanels() as $panel) {
            if ($panel->getId() === $current->getId()) {
                continue;
            }

            try {
                if ($user->canAccessPanel($panel)) {
                    return $panel;
                }
            } catch (\Throwable) {
                // A panel that can't even be evaluated is not a way out.
                continue;
            }
        }

        return null;
    }
}
