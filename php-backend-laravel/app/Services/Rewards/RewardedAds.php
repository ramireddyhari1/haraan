<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\RewardedAdSession;
use App\Models\RewardGrant;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Support\Membership\MemberFeature;
use App\Support\PlatformRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Watch a short video to unlock" — opted into by the player, verified by Google, never by the app.
 *
 *   1. start()    the player taps Watch on a locked grant. We check it's theirs, still needs an
 *                 ad, their plan shows ads, and today's cap; then issue a single-use nonce.
 *   2. The app loads an AdMob rewarded ad with ServerSideVerificationOptions(userId = our
 *      opaque id, customData = nonce) and shows it.
 *   3. callback() Google calls /api/webhooks/admob/rewarded. Only a valid Google signature, a known
 *                 pending unexpired nonce, the same opaque user, the configured ad unit and a
 *                 never-seen transaction id clear the grant's ad lock.
 *   4. The app polls status() — its own "ad finished" callback unlocks nothing.
 */
final class RewardedAds
{
    public function __construct(
        private readonly MemberEntitlements $entitlements,
        private readonly AdMobVerifier $verifier,
        private readonly RewardLedger $ledger,
        private readonly RewardNotifier $notifier,
    ) {}

    /** The opaque id we give AdMob for a user — the callback must carry the same one. */
    public static function opaqueUserId(int $userId): string
    {
        return substr(hash_hmac('sha256', 'admob:'.$userId, (string) config('app.key')), 0, 32);
    }

    /** @throws RewardClaimException */
    public function start(User $user, RewardGrant $grant): RewardedAdSession
    {
        if ((int) $grant->user_id !== (int) $user->id) {
            throw new RewardClaimException('Reward not found.', 'not_found', 404);
        }
        if (! RewardEngine::enabled() || ! RewardEngine::rewardedAdsAvailable()) {
            throw new RewardClaimException('Videos aren’t available right now.', 'ads_unavailable', 503);
        }
        if ($this->entitlements->allows($user, MemberFeature::ADS_HIDDEN)) {
            // A no-ads member never sees an ad. Their rewards unlock without one, or not at all.
            throw new RewardClaimException('Your plan has no ads.', 'ads_hidden', 403);
        }
        if ($grant->status !== RewardGrant::LOCKED || ! $grant->needs_ad) {
            throw new RewardClaimException('This reward doesn’t need a video.', 'not_needed', 409);
        }

        $cap = PlatformRules::int('rewards.ad_daily_cap');
        $today = RewardedAdSession::query()->where('user_id', $user->id)
            ->where('status', RewardedAdSession::VERIFIED)->where('verified_at', '>=', now()->startOfDay())->count();
        $started = RewardedAdSession::query()->where('user_id', $user->id)->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= $cap || $started >= $cap * 4) {
            throw new RewardClaimException('You’ve watched today’s videos. Come back tomorrow.', 'daily_cap', 429);
        }

        return RewardedAdSession::query()->create([
            'nonce' => Str::random(40),
            'user_id' => $user->id,
            'grant_id' => $grant->id,
            'provider' => 'admob',
            'ad_unit' => PlatformRules::string('rewards.admob_ad_unit_id'),
            'status' => RewardedAdSession::PENDING,
            'expires_at' => now()->addMinutes(PlatformRules::int('rewards.ad_session_minutes')),
        ]);
    }

    /**
     * Handle Google's SSV callback.
     *
     * @return string 'verified' | 'duplicate' | 'ignored'
     *
     * @throws InvalidAdSignature when the signature is invalid (the controller answers 400)
     */
    public function callback(string $rawQuery): string
    {
        $params = $this->verifier->verify($rawQuery);

        $nonce = (string) ($params['custom_data'] ?? '');
        $txn = (string) ($params['transaction_id'] ?? '');
        if ($nonce === '' || $txn === '') {
            return 'ignored';
        }

        if (RewardedAdSession::query()->where('transaction_id', $txn)->exists()) {
            return 'duplicate';
        }

        $session = RewardedAdSession::query()->where('nonce', $nonce)->first();
        if ($session === null) {
            return 'ignored';
        }
        if ($session->status !== RewardedAdSession::PENDING) {
            return 'duplicate';
        }

        $reject = function (string $why) use ($session): string {
            RewardedAdSession::query()->whereKey($session->id)->where('status', RewardedAdSession::PENDING)
                ->update(['status' => RewardedAdSession::REJECTED, 'updated_at' => now()]);
            Log::warning('rewarded ad rejected: '.$why, ['session_id' => $session->id]);

            return 'ignored';
        };

        if ($session->expires_at->isPast()) {
            return $reject('session expired');
        }
        if (! hash_equals(self::opaqueUserId((int) $session->user_id), (string) ($params['user_id'] ?? ''))) {
            return $reject('user mismatch');
        }
        $unit = (string) ($params['ad_unit'] ?? '');
        $expected = (string) Str::afterLast((string) $session->ad_unit, '/');
        if ($unit !== '' && $expected !== '' && $unit !== $expected) {
            return $reject('ad unit mismatch');
        }

        try {
            $marked = RewardedAdSession::query()->whereKey($session->id)->where('status', RewardedAdSession::PENDING)
                ->update([
                    'status' => RewardedAdSession::VERIFIED,
                    'transaction_id' => $txn,
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (UniqueConstraintViolationException) {
            return 'duplicate';
        }
        if ($marked !== 1) {
            return 'duplicate';
        }

        $grant = RewardGrant::query()->find($session->grant_id);
        if ($grant !== null && $grant->status === RewardGrant::LOCKED && $grant->needs_ad) {
            $opened = $this->ledger->clearLock($grant, 'rewarded_ad');
            if ($opened && $grant->user !== null) {
                $this->notifier->adUnlocked($grant->user, $grant->fresh());
            }
        }

        return 'verified';
    }

    /** @return array{status: string, grant_status: ?string} */
    public function status(User $user, string $nonce): ?array
    {
        $session = RewardedAdSession::query()->where('nonce', $nonce)->where('user_id', $user->id)->first();
        if ($session === null) {
            return null;
        }
        if ($session->status === RewardedAdSession::PENDING && $session->expires_at->isPast()) {
            $session->update(['status' => RewardedAdSession::EXPIRED]);
        }

        return [
            'status' => $session->status,
            'grant_status' => RewardGrant::query()->whereKey($session->grant_id)->value('status'),
        ];
    }
}
