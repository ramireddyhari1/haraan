<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LegalDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The website's account screen — the web twin of the app's AccountProfileScreen.
 *
 * Deliberately mirrors the app's structure and rules rather than reinventing them:
 * one identity hero, two lanes (Tickets / Play), a standing strip that only appears
 * once there is something earned to report, then Account / Legal / Sign out.
 *
 * The bucket rules are copied from the app on purpose (see AccountProfileScreen.kt):
 * upcoming-vs-past is a question about TIME, never payment status.
 */
final class AccountController extends Controller
{
    /** Statuses that take a booking out of the count entirely. Mirrors the app. */
    private const CANCELLED_STATUSES = ['cancelled', 'refunded', 'failed'];

    /** The only documents we serve; mirrors Api\LegalController::SLUGS. */
    private const LEGAL_SLUGS = ['terms', 'privacy'];

    public function profile(Request $request): View
    {
        $user = $request->user();

        // The lane needs two numbers, not the rows: count in SQL rather than hydrating
        // every booking (and its event/venue) just to call ->count() on the result.
        $today = now()->toDateString();
        $total = Booking::query()->where('user_id', $user->id)->count();
        $upcoming = Booking::query()
            ->where('user_id', $user->id)
            ->whereNotIn(DB::raw('lower(status)'), self::CANCELLED_STATUSES)
            // Time, never payment status — and a booking with no date counts as
            // upcoming (better to over-show a live ticket than bury it).
            ->where(function ($q) use ($today): void {
                $q->whereDate('slot_date', '>=', $today)
                    ->orWhere(function ($q2) use ($today): void {
                        $q2->whereNull('slot_date')
                            ->whereHas('event', fn ($e) => $e->whereDate('date', '>=', $today));
                    })
                    ->orWhere(function ($q3): void {
                        $q3->whereNull('slot_date')->whereDoesntHave('event');
                    });
            })
            ->count();

        return view('site.profile', [
            'title' => 'Account',
            'user' => $user,
            // A lane never buries what the user has: with nothing upcoming we still
            // count what they've booked, so the number can't vanish on them.
            'ticketsValue' => $upcoming ?: ($total ?: null),
            'ticketsCaption' => match (true) {
                $upcoming > 0 => 'upcoming',
                $total > 0 => 'past bookings',
                default => 'Book your first event',
            },
            'matchesValue' => ((int) $user->career_matches) ?: null,
        ]);
    }

    /** The Tickets lane's destination: every booking, newest first. */
    public function bookings(Request $request): View
    {
        $bookings = $this->bookingsFor((int) $request->user()->id);
        $today = now()->toDateString();

        return view('site.bookings', [
            'title' => 'My bookings',
            'upcoming' => $bookings->filter(fn (Booking $b) => ! $this->isCancelled($b) && ! $this->isPast($b, $today))->values(),
            'past' => $bookings->filter(fn (Booking $b) => ! $this->isCancelled($b) && $this->isPast($b, $today))->values(),
            'cancelled' => $bookings->filter(fn (Booking $b) => $this->isCancelled($b))->values(),
        ]);
    }

    public function privacy(Request $request): View
    {
        return view('site.account-privacy', [
            'title' => 'Privacy',
            'user' => $request->user(),
        ]);
    }

    public function updatePrivacy(Request $request): RedirectResponse
    {
        // Unchecked checkboxes are simply absent from the POST body, so every
        // toggle is read as a boolean presence check rather than validated input.
        $request->user()->update([
            'privacy_public_profile' => $request->boolean('privacy_public_profile'),
            'privacy_show_stats' => $request->boolean('privacy_show_stats'),
            'privacy_show_district' => $request->boolean('privacy_show_district'),
            'privacy_discoverable' => $request->boolean('privacy_discoverable'),
        ]);

        return back()->with('success', 'Privacy settings saved.');
    }

    /**
     * Save age + gender from the one-time "tell us a bit about you" prompt (Google
     * can't supply these, so we ask). Only fills blanks — never overwrites what the
     * user already set. `skip=1` just dismisses the prompt for the session without
     * saving. Feeds the per-event audience analytics.
     */
    public function saveDemographics(Request $request): RedirectResponse
    {
        if (! $request->boolean('skip')) {
            $data = $request->validate([
                'age' => ['nullable', 'integer', 'min:5', 'max:120'],
                'gender' => ['nullable', 'in:Male,Female,Other'],
            ]);

            $user = $request->user();
            $fill = [];
            if (blank($user->age) && filled($data['age'] ?? null)) {
                $fill['age'] = $data['age'];
            }
            if (blank($user->gender) && filled($data['gender'] ?? null)) {
                $fill['gender'] = $data['gender'];
            }
            if ($fill !== []) {
                $user->fill($fill)->save();
            }
        }

        // Don't ask again this session either way.
        $request->session()->put('demographics_prompt_dismissed', true);

        return back();
    }

    /**
     * The hero avatar's one action, matching the app. Storage shape is identical to
     * Api\PlayersController::uploadAvatar (public disk, '/storage/…' URL, previous
     * upload deleted) so a photo set here and one set in the app are the same thing.
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();

        $previous = $user->avatar;
        if (is_string($previous) && str_starts_with($previous, '/storage/')) {
            Storage::disk('public')->delete(substr($previous, strlen('/storage/')));
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => '/storage/'.$path]);

        return back()->with('success', 'Photo updated.');
    }

    /** Terms & Conditions / Privacy Policy — the same rows the app's Legal group opens. */
    public function legal(string $slug): View
    {
        abort_unless(in_array($slug, self::LEGAL_SLUGS, true), 404);

        $doc = LegalDocument::query()->where('slug', $slug)->first();
        abort_if($doc === null, 404);

        return view('site.legal', [
            'title' => $doc->title,
            'doc' => $doc,
            // The app splits on blank lines rather than shipping a markdown renderer;
            // the web does the same so both render the same admin-authored copy.
            'paragraphs' => collect(preg_split('/\n\s*\n/', (string) $doc->body))
                ->map(fn ($p) => trim($p))
                ->filter()
                ->values(),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Signed out.');
    }

    /** @return \Illuminate\Support\Collection<int, Booking> */
    private function bookingsFor(int $userId): \Illuminate\Support\Collection
    {
        return Booking::query()
            ->with(['event', 'venue'])
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();
    }

    /** Status casing is unreliable across the table — always compare lowercased. */
    private function isCancelled(Booking $b): bool
    {
        return in_array(mb_strtolower((string) $b->status), self::CANCELLED_STATUSES, true);
    }

    /**
     * A booking with no date counts as upcoming: better to over-show a live ticket
     * than to bury it. Mirrors BookingLite.isPast() in the app.
     */
    private function isPast(Booking $b, string $today): bool
    {
        $day = $this->dateOf($b);

        return $day !== null && $day < $today;
    }

    /** Both columns are cast to dates, so normalise either to a plain yyyy-mm-dd. */
    private function dateOf(Booking $b): ?string
    {
        $date = $b->slot_date ?? $b->event?->date;

        if ($date === null) {
            return null;
        }

        return $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : substr((string) $date, 0, 10);
    }
}
