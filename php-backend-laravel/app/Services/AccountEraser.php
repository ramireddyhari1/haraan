<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Erases a person from Haraan without erasing the money.
 *
 * The one rule that shapes this whole class: `bookings.user_id` is declared
 * cascadeOnDelete (see create_bookings_table), so `$user->delete()` would take
 * every booking and payment row with it. Those are financial records we are
 * required to keep for tax purposes, so the account is ANONYMISED IN PLACE —
 * the row and its id survive, everything that identifies a human does not.
 *
 * The result is a booking history that still reconciles against Razorpay and
 * still totals correctly in reports, but no longer points at a person.
 *
 * @see \App\Http\Controllers\Web\AccountDeletionController
 * @see \App\Http\Controllers\Api\AccountController
 */
final class AccountEraser
{
    /**
     * Columns wiped to null outright. Anything here is either personal data or
     * a re-identification handle; none of it is referenced by financial reporting.
     */
    private const NULLED = [
        'phone', 'avatar', 'date_of_birth', 'gender', 'nationality', 'birth_place',
        'height', 'sport_attributes', 'batting_style', 'bowling_style', 'playing_style',
        'player_role', 'primary_sport', 'district', 'state', 'player_id',
        'email_verified_at', 'last_seen_at', 'featured_at',
        'app_authentication_secret', 'app_authentication_recovery_codes',
    ];

    /**
     * Rows deleted wholesale, keyed by table => the column holding the user id.
     * Deliberately excludes `bookings` and `booking_payments`.
     */
    private const PURGED = [
        'device_tokens' => 'user_id',           // stops all push immediately
        'support_threads' => 'user_id',         // cascades support_messages
        'notification_recipients' => 'user_id',
        'event_views' => 'user_id',
        'user_activity_days' => 'user_id',
        'slot_waitlist' => 'user_id',
        'sessions' => 'user_id',                // signs them out everywhere
    ];

    /**
     * @return array{user_id:int, purged:array<string,int>}
     */
    public function erase(User $user): array
    {
        $userId = (int) $user->getKey();
        $purged = [];

        DB::transaction(function () use ($user, $userId, &$purged): void {
            foreach (self::PURGED as $table => $column) {
                // Guarded because this list outlives any single migration state —
                // a fresh deploy that hasn't run every migration must not 500 here.
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $purged[$table] = DB::table($table)->where($column, $userId)->delete();
            }

            $this->deleteAvatarFile($user);

            $updates = array_fill_keys(self::NULLED, null);

            // The email must stay unique and must stay non-null (it is the table's
            // unique key), so it becomes an unroutable per-id placeholder rather
            // than being cleared. Nothing can be sent to it and nobody can claim it.
            $updates['email'] = 'deleted-' . $userId . '@deleted.haraan.app';
            $updates['name'] = 'Deleted user';

            // A random unusable password, so no credential path can reach the row.
            $updates['password'] = bcrypt(Str::random(64));

            // Kill every live API token. Purging `sessions` above only signs out the
            // WEBSITE — the app authenticates with a JWT, which keeps validating against
            // this row (it still exists; it is anonymised, not deleted) until it expires
            // days later. Moving token_version is the only thing that revokes those; see
            // JwtService::versionMatches. Without it "delete my account" left the app that
            // asked for it still signed in and still able to call the API.
            $updates['token_version'] = (int) ($user->token_version ?? 0) + 1;

            $updates['status'] = 'deleted';

            // Take them out of every public surface: leaderboards, search, profiles.
            $updates['is_ranked'] = false;
            $updates['visibility'] = 'private';
            $updates['trust_score'] = 0;
            $updates['trust_level'] = 0;

            // Career stats are personal performance data, not accounting data.
            foreach (['career_matches', 'career_runs', 'career_balls', 'career_wickets',
                      'career_overs_bowled', 'career_runs_conceded'] as $col) {
                $updates[$col] = 0;
            }

            // Privacy toggles default closed so nothing can re-expose the row.
            foreach (['privacy_public_profile', 'privacy_show_stats',
                      'privacy_show_district', 'privacy_discoverable'] as $col) {
                $updates[$col] = false;
            }

            // Only write columns this schema actually has — the users table has
            // grown in stages and not every deploy is at the same migration.
            $existing = array_filter(
                $updates,
                fn (string $col): bool => Schema::hasColumn('users', $col),
                ARRAY_FILTER_USE_KEY
            );

            DB::table('users')->where('id', $userId)->update($existing);
        });

        return ['user_id' => $userId, 'purged' => $purged];
    }

    /**
     * Uploads live on the `public` disk (see the uploads convention), so the file
     * is publicly reachable until it is actually removed — clearing the column
     * alone would leave the image served forever.
     */
    private function deleteAvatarFile(User $user): void
    {
        $avatar = (string) ($user->avatar ?? '');
        if ($avatar === '' || Str::startsWith($avatar, ['http://', 'https://'])) {
            return;
        }

        $path = Str::of($avatar)->after('/storage/')->ltrim('/')->value();
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
