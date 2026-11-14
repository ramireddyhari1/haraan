<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminAction;
use App\Models\FeedRestriction;
use App\Models\PlayerPost;
use App\Models\PostComment;
use App\Models\PostReport;
use App\Models\User;
use App\Support\PlatformRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The ActionBoard Home feed — one place that decides what the feed shows and who may add to it.
 *
 * The app's GET /api/posts/feed and the website's /feed both read from here, so a moderator's
 * decision in /control (hide, remove, pin, feature, restrict) and every rule in
 * /control → Platform rules → ActionBoard Home feed land on both surfaces at once. Before this
 * the two controllers each carried their own copy of the query.
 *
 * Moderation actions also live here, so the /control buttons, the auto-hide on reports, and
 * the tests all go through the same code and write the same audit entries.
 */
final class HomeFeed
{
    /** Account statuses that never appear in the feed (mirrors User::BLOCKED_STATUSES). */
    private const BLOCKED_ACCOUNT_STATUSES = ['SUSPENDED', 'BANNED', 'BLOCKED', 'DELETED'];

    // ── Reading ──────────────────────────────────────────────────────────────────────

    public static function enabled(): bool
    {
        return PlatformRules::bool('home_feed.enabled');
    }

    /**
     * The flags a client needs to draw the feed's chrome (compose button, comment box, stories
     * strip, the "feed is off" state). Sent alongside the posts.
     *
     * @return array<string, mixed>
     */
    public static function clientRules(): array
    {
        return [
            'enabled' => self::enabled(),
            'message' => self::enabled() ? null : PlatformRules::string('home_feed.off_message'),
            'posting_enabled' => PlatformRules::bool('home_feed.posting_enabled'),
            'comments_enabled' => PlatformRules::bool('home_feed.comments_enabled'),
            'stories_enabled' => PlatformRules::bool('home_feed.stories_enabled'),
            'max_images' => PlatformRules::int('home_feed.max_images'),
        ];
    }

    /**
     * Posts for one feed load, in display order: active pins first (newest pin first, capped),
     * then the rest by the configured ordering, with the per-author cap applied.
     * Each post carries `likes_count` and `comments_count` (visible comments only).
     *
     * @return Collection<int, PlayerPost>
     */
    public static function posts(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $pageSize = PlatformRules::int('home_feed.page_size');
        $maxPinned = PlatformRules::int('home_feed.max_pinned');

        $pinned = $maxPinned > 0
            ? self::baseQuery()
                ->whereNotNull('pinned_at')
                ->where(fn (Builder $w) => $w->whereNull('pinned_until')->orWhere('pinned_until', '>', now()))
                ->orderByDesc('pinned_at')
                ->limit($maxPinned)
                ->get()
            : collect();

        $rest = self::baseQuery()->whereNotIn('id', $pinned->pluck('id')->all());
        $windowDays = PlatformRules::int('home_feed.window_days');
        if ($windowDays > 0) {
            $rest->where('created_at', '>=', now()->subDays($windowDays));
        }

        $popular = PlatformRules::string('home_feed.ordering') === 'popular';
        // Popular ranks a wider pool of recent posts, then keeps the best page of them.
        $rest = $rest->orderByDesc('id')->limit($popular ? $pageSize * 3 : $pageSize * 2)->get();
        if ($popular) {
            $now = now();
            $rest = $rest->sortByDesc(fn (PlayerPost $p): float => self::popularity($p, $now))->values();
        }

        $rest = self::capPerAuthor($rest, PlatformRules::int('home_feed.max_per_author'));

        return $pinned->concat($rest)->take(max($pageSize, $pinned->count()))->values();
    }

    /**
     * One story bubble per poster (their newest post in this load), newest first. Empty when
     * the stories strip is switched off.
     *
     * @param  Collection<int, PlayerPost>  $posts
     * @return Collection<int, PlayerPost>
     */
    public static function storyPosts(Collection $posts): Collection
    {
        if (! PlatformRules::bool('home_feed.stories_enabled')) {
            return collect();
        }

        return $posts->sortByDesc('id')->unique('user_id')->take(20)->values();
    }

    /** May this viewer open this post (like it, read or add comments)? */
    public static function viewable(PlayerPost $post, ?User $viewer): bool
    {
        if ($post->isVisible()) {
            return true;
        }

        // The author keeps seeing their own hidden post (so they know why it's gone from the
        // feed); a removed post is gone for everyone.
        return $post->status === PlayerPost::STATUS_HIDDEN
            && $viewer !== null && (int) $viewer->id === (int) $post->user_id;
    }

    // ── Who may add ──────────────────────────────────────────────────────────────────

    /** Null when the user may post; otherwise the message to show them. */
    public static function postingBlockedReason(User $user): ?string
    {
        if (! PlatformRules::bool('home_feed.posting_enabled')) {
            return 'Posting is paused for now.';
        }
        if (FeedRestriction::has((int) $user->id, FeedRestriction::NO_POSTING)) {
            return 'Your account can’t post right now.';
        }
        if ($reason = self::newAccountReason($user)) {
            return $reason;
        }

        $daily = PlatformRules::int('home_feed.daily_post_limit');
        if ($daily > 0) {
            $today = PlayerPost::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDay())
                ->count();
            if ($today >= $daily) {
                return "You can post {$daily} times a day. Try again tomorrow.";
            }
        }

        return null;
    }

    /** Null when the user may comment; otherwise the message to show them. */
    public static function commentingBlockedReason(User $user): ?string
    {
        if (! PlatformRules::bool('home_feed.comments_enabled')) {
            return 'Comments are paused for now.';
        }
        if (FeedRestriction::has((int) $user->id, FeedRestriction::NO_COMMENTS)) {
            return 'Your account can’t comment right now.';
        }

        return self::newAccountReason($user);
    }

    /** The first blocked word or phrase the text contains, or null. */
    public static function blockedWordIn(?string $text): ?string
    {
        $text = mb_strtolower(trim((string) $text));
        if ($text === '') {
            return null;
        }

        foreach (self::blockedWords() as $word) {
            if (str_contains($text, $word)) {
                return $word;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function blockedWords(): array
    {
        $raw = PlatformRules::string('home_feed.blocked_words');

        return array_values(array_unique(array_filter(array_map(
            fn (string $w): string => mb_strtolower(trim($w)),
            preg_split('/[\r\n,]+/u', $raw) ?: [],
        ), fn (string $w): bool => $w !== '')));
    }

    // ── Reports ──────────────────────────────────────────────────────────────────────

    /**
     * File a report. One open report per reporter per post/comment — a second tap updates the
     * reason instead of stacking duplicates, so the count that triggers auto-hide is a count
     * of people. Returns true when this report took the post down.
     */
    public static function report(User $reporter, PlayerPost $post, ?PostComment $comment, string $reason, ?string $details): bool
    {
        return DB::transaction(function () use ($reporter, $post, $comment, $reason, $details): bool {
            $existing = PostReport::query()
                ->where('post_id', $post->id)
                ->where('reporter_id', $reporter->id)
                ->where('status', 'open')
                ->when($comment, fn ($q) => $q->where('comment_id', $comment->id), fn ($q) => $q->whereNull('comment_id'))
                ->first();

            if ($existing !== null) {
                $existing->update(['reason' => $reason, 'details' => $details]);
            } else {
                PostReport::create([
                    'post_id' => $post->id,
                    'comment_id' => $comment?->id,
                    'reporter_id' => $reporter->id,
                    'reason' => $reason,
                    'details' => $details,
                    'status' => 'open',
                ]);
            }

            self::refreshOpenReports($post);

            if ($comment !== null) {
                return false;
            }

            $threshold = PlatformRules::int('home_feed.auto_hide_reports');
            $people = PostReport::query()
                ->where('post_id', $post->id)->whereNull('comment_id')->where('status', 'open')
                ->distinct()->count('reporter_id');

            if ($threshold > 0 && $people >= $threshold && $post->isVisible() && ! $post->featured && ! $post->isPinned()) {
                $post->update([
                    'status' => PlayerPost::STATUS_HIDDEN,
                    'moderated_by' => null,
                    'moderated_at' => now(),
                    'moderation_reason' => "Hidden automatically after {$people} reports",
                ]);
                AdminAction::log('home_feed.post_auto_hidden', ['post_id' => $post->id, 'reports' => $people], $post);

                return true;
            }

            return false;
        });
    }

    public static function refreshOpenReports(PlayerPost $post): void
    {
        $post->forceFill([
            'open_reports' => PostReport::query()->where('post_id', $post->id)->where('status', 'open')->count(),
        ])->save();
    }

    // ── Moderation (used by /control) ───────────────────────────────────────────────

    /** Hide or remove a post, closing its open reports as actioned. */
    public static function takeDown(PlayerPost $post, string $status, ?string $reason): void
    {
        $from = $post->status;
        $post->update([
            'status' => $status,
            'moderated_by' => auth()->id(),
            'moderated_at' => now(),
            'moderation_reason' => $reason,
            // A taken-down post can't stay pinned or featured.
            'pinned_at' => null,
            'pinned_until' => null,
            'featured' => false,
        ]);
        self::closeReports($post, 'actioned');
        AdminAction::log('home_feed.post_'.$status, ['post_id' => $post->id, 'from' => $from, 'reason' => $reason], $post);
    }

    public static function restore(PlayerPost $post): void
    {
        $from = $post->status;
        $post->update([
            'status' => PlayerPost::STATUS_VISIBLE,
            'moderated_by' => auth()->id(),
            'moderated_at' => now(),
            'moderation_reason' => null,
        ]);
        // Restoring is a judgment that the reports were wrong.
        self::closeReports($post, 'dismissed');
        AdminAction::log('home_feed.post_restored', ['post_id' => $post->id, 'from' => $from], $post);
    }

    public static function pin(PlayerPost $post, ?Carbon $until): void
    {
        $post->update(['pinned_at' => now(), 'pinned_until' => $until, 'status' => PlayerPost::STATUS_VISIBLE]);
        AdminAction::log('home_feed.post_pinned', ['post_id' => $post->id, 'until' => $until?->toIso8601String()], $post);
    }

    public static function unpin(PlayerPost $post): void
    {
        $post->update(['pinned_at' => null, 'pinned_until' => null]);
        AdminAction::log('home_feed.post_unpinned', ['post_id' => $post->id], $post);
    }

    public static function setFeatured(PlayerPost $post, bool $featured): void
    {
        $post->update(['featured' => $featured]);
        AdminAction::log($featured ? 'home_feed.post_featured' : 'home_feed.post_unfeatured', ['post_id' => $post->id], $post);
    }

    public static function setCommentStatus(PostComment $comment, string $status): void
    {
        $comment->update(['status' => $status, 'moderated_by' => auth()->id(), 'moderated_at' => now()]);
        PostReport::query()->where('comment_id', $comment->id)->where('status', 'open')->update([
            'status' => $status === PostComment::STATUS_HIDDEN ? 'actioned' : 'dismissed',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        if ($comment->post !== null) {
            self::refreshOpenReports($comment->post);
        }
        AdminAction::log('home_feed.comment_'.$status, ['comment_id' => $comment->id, 'post_id' => $comment->post_id], $comment);
    }

    public static function closeReports(PlayerPost $post, string $outcome): void
    {
        PostReport::query()->where('post_id', $post->id)->whereNull('comment_id')->where('status', 'open')->update([
            'status' => $outcome,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        self::refreshOpenReports($post);
    }

    // ── Internals ───────────────────────────────────────────────────────────────────

    /** Visible posts by public, active, unrestricted accounts. */
    private static function baseQuery(): Builder
    {
        $hiddenAuthors = FeedRestriction::query()->active()
            ->where('type', FeedRestriction::HIDDEN_FROM_FEED)
            ->select('user_id');

        return PlayerPost::query()
            ->visible()
            ->with(['user', 'images'])
            ->withCount(['likes', 'comments' => fn (Builder $q) => $q->where('status', PostComment::STATUS_VISIBLE)])
            ->whereNotIn('user_id', $hiddenAuthors)
            ->whereHas('user', function (Builder $q): void {
                $q->where('is_guest', false)
                    // Null = pre-privacy accounts, treated as public (same rule the player
                    // search uses for privacy_discoverable).
                    ->where(fn (Builder $sub) => $sub->whereNull('privacy_public_profile')->orWhere('privacy_public_profile', true))
                    ->where(fn (Builder $sub) => $sub->whereNull('status')
                        ->orWhereRaw('UPPER(status) NOT IN ('.implode(',', array_fill(0, count(self::BLOCKED_ACCOUNT_STATUSES), '?')).')', self::BLOCKED_ACCOUNT_STATUSES));
            });
    }

    /** Engagement that fades with age (hours), Hacker-News style. Featured counts double. */
    private static function popularity(PlayerPost $p, Carbon $now): float
    {
        $hours = $p->created_at !== null ? max(0.0, $p->created_at->diffInMinutes($now, true) / 60) : 0.0;
        $engagement = 1 + 2 * (int) $p->likes_count + 3 * (int) $p->comments_count;
        $score = $engagement / (($hours + 2) ** 1.5);

        return $p->featured ? $score * 2 : $score;
    }

    /**
     * @param  Collection<int, PlayerPost>  $posts
     * @return Collection<int, PlayerPost>
     */
    private static function capPerAuthor(Collection $posts, int $cap): Collection
    {
        if ($cap <= 0) {
            return $posts->values();
        }

        $seen = [];

        return $posts->filter(function (PlayerPost $p) use (&$seen, $cap): bool {
            $seen[$p->user_id] = ($seen[$p->user_id] ?? 0) + 1;

            return $seen[$p->user_id] <= $cap;
        })->values();
    }

    private static function newAccountReason(User $user): ?string
    {
        $hours = PlatformRules::int('home_feed.min_account_age_hours');
        if ($hours > 0 && $user->created_at !== null && $user->created_at->gt(now()->subHours($hours))) {
            return 'New accounts can post and comment a little later. Try again soon.';
        }

        return null;
    }
}
