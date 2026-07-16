<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every member their real ID.
 *
 * The old generator minted a 6-digit random placeholder at signup and only upgraded it
 * to HRN+account-number once state AND district happened to be filled — which most
 * accounts never do. Production was left with 22 members showing a meaningless number,
 * one with a blank ID, and 4 on the retired HRN-AP-VSK-00037 form: three formats for
 * one concept. The ID is now derived from the account number at creation (see
 * User::memberId), so this backfills everything that predates that.
 *
 * player_id is a shareable lookup handle (PlayersController looks accounts up by it), so
 * the whole table is recomputed uniformly rather than left half-migrated — a mix of
 * formats is what made the lookup incoherent in the first place.
 *
 * Guests (HRN-GST-####) are deliberately untouched: they're placeholder players on a
 * squad, not accounts, and they get a member ID when they're claimed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // NB: no ->change() on the column. It's declared string('player_id', 6) — sized for
        // the old 6-digit random and already too small for the HRN00002 values it holds.
        // SQLite (production) doesn't enforce varchar length, and widening it here would
        // rebuild the whole users table, which many tables reference by FK. Left as-is
        // deliberately; widen it as part of any move to MySQL/Postgres, where the length
        // is enforced and this data would not fit.

        User::query()
            ->where(function ($q): void {
                $q->where('is_guest', false)->orWhereNull('is_guest');
            })
            ->orderBy('id')
            ->get(['id', 'player_id', 'is_guest'])
            ->each(function (User $user): void {
                if (! User::isPlaceholderPlayerId($user->player_id)) {
                    return; // already HRN + account number
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['player_id' => User::memberId((int) $user->id)]);
            });
    }

    public function down(): void
    {
        // Irreversible by design: the placeholders were random, so there is nothing to
        // restore them to. Members keep their (correct, derivable) IDs.
    }
};
