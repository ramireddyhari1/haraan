<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\LiveMatch;
use App\Models\Notification;
use App\Models\RewardGrant;
use App\Models\User;
use App\Support\PlatformRules;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells players about their rewards — every registered player in the match, not only the
 * scorer. Each message is an inbox notification addressed to one user (source = rewards, so it
 * stays out of the admin composer list); saving it as sent fans the push out to that user's
 * devices through the existing SendNotificationPush job.
 *
 * One message per player per event: a match that gives a badge, a streak and two rewards sends
 * one notification, not four. Each kind has its own on/off switch and title in /control.
 */
final class RewardNotifier
{
    public static function matchLink(int $matchId): string
    {
        return 'haraan://rewards/match/'.$matchId;
    }

    public function afterCompletion(User $user, LiveMatch $match, RunResult $run): void
    {
        $parts = [];
        $rewards = count($run->granted);
        $locked = count(array_filter($run->granted, fn (RewardGrant $g) => $g->status === RewardGrant::LOCKED));

        if ($rewards > 0 && PlatformRules::bool('rewards.notify_match')) {
            $parts[] = $rewards === 1 ? '1 reward' : "{$rewards} rewards";
            if ($locked > 0) {
                $parts[] = $locked === 1 ? '1 unlocks when the result is confirmed' : "{$locked} unlock when the result is confirmed";
            }
        }

        $badgeNames = $this->badgeNames($run);
        if ($badgeNames !== [] && PlatformRules::bool('rewards.notify_badges')) {
            $parts[] = 'new badge: '.implode(', ', $badgeNames);
        }

        if ($run->streakGrant !== null && PlatformRules::bool('rewards.notify_streak')) {
            $parts[] = ($run->streak['current'] ?? 0).'-week streak';
        }

        if ($parts === []) {
            return;
        }

        $title = match (true) {
            $rewards > 0 && PlatformRules::bool('rewards.notify_match') => PlatformRules::string('rewards.copy_match_title'),
            $badgeNames !== [] => PlatformRules::string('rewards.copy_badge_title'),
            default => PlatformRules::string('rewards.copy_streak_title'),
        };

        $this->send($user, $title, $this->matchName($match).': '.implode(' · ', $parts), self::matchLink((int) $match->id));
    }

    public function afterSettlement(User $user, LiveMatch $match, RunResult $run): void
    {
        $parts = [];
        $unlocked = count($run->unlocked) + count(array_filter($run->granted, fn (RewardGrant $g) => $g->status !== RewardGrant::LOCKED));
        if ($unlocked > 0 && PlatformRules::bool('rewards.notify_unlocked')) {
            $parts[] = $unlocked === 1 ? '1 reward is ready to claim' : "{$unlocked} rewards are ready to claim";
        }
        if ($run->waitingForAd !== [] && PlatformRules::bool('rewards.notify_unlocked')) {
            $parts[] = count($run->waitingForAd).' more with a short video';
        }
        $badgeNames = $this->badgeNames($run);
        if ($badgeNames !== [] && PlatformRules::bool('rewards.notify_badges')) {
            $parts[] = 'new badge: '.implode(', ', $badgeNames);
        }
        if ($parts === []) {
            return;
        }

        $title = $unlocked > 0 || $run->waitingForAd !== []
            ? PlatformRules::string('rewards.copy_unlocked_title')
            : PlatformRules::string('rewards.copy_badge_title');

        $this->send($user, $title, $this->matchName($match).': '.implode(' · ', $parts), self::matchLink((int) $match->id));
    }

    /** @param list<RewardGrant> $grants all the same user's, expiring soon */
    public function expiring(User $user, array $grants): void
    {
        if ($grants === [] || ! PlatformRules::bool('rewards.notify_expiring')) {
            return;
        }
        $first = $grants[0];
        $body = count($grants) === 1
            ? $first->title.' expires '.$first->expires_at?->diffForHumans()
            : count($grants).' rewards expire soon, including '.$first->title;

        $this->send($user, PlatformRules::string('rewards.copy_expiring_title'), $body,
            $first->match_id !== null ? self::matchLink((int) $first->match_id) : 'haraan://rewards');
    }

    public function adUnlocked(User $user, RewardGrant $grant): void
    {
        if (! PlatformRules::bool('rewards.notify_unlocked')) {
            return;
        }
        $this->send($user, PlatformRules::string('rewards.copy_unlocked_title'), $grant->title.' is ready to claim.',
            $grant->match_id !== null ? self::matchLink((int) $grant->match_id) : 'haraan://rewards');
    }

    /** @return list<string> */
    private function badgeNames(RunResult $run): array
    {
        return array_values(array_map(fn ($b) => (string) ($b->definition?->name ?? $b->badge_key), $run->badges));
    }

    private function matchName(LiveMatch $match): string
    {
        $home = trim((string) ($match->home_full ?: $match->home));
        $away = trim((string) ($match->away_full ?: $match->away));

        return $home !== '' && $away !== '' ? "{$home} vs {$away}" : 'Your match';
    }

    private function send(User $user, string $title, string $body, string $link): void
    {
        try {
            Notification::query()->create([
                'title' => mb_substr($title, 0, 120),
                'body' => mb_substr($body, 0, 300),
                'deep_link' => $link,
                'audience_type' => 'user',
                'audience_value' => (string) $user->id,
                'status' => 'sent',
                'source' => 'rewards',
            ]);
        } catch (Throwable $e) {
            // A notification problem is never a reward problem.
            Log::warning('reward notification failed: '.$e->getMessage(), ['user_id' => $user->id]);
        }
    }
}
