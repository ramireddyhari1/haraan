<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PlayerPost;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostReport;
use App\Models\PostSave;
use App\Models\User;
use App\Services\HomeFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The website's Home feed — the twin of the app's HomeFeedScreen, which is what the
 * ActionBoard's Home button opens there. The web's Home button used to jump to Pulse
 * (venue discovery), so the same label opened two unrelated products.
 *
 * Reads exactly what Api\PlayersController::feed serves the app, off the same tables,
 * with the same public-only rule — the difference is only that a browser arrives with a
 * session instead of a JWT. Kept as its own controller rather than bolted onto
 * PublicWebController, which is already the site's junk drawer.
 */
final class SocialFeedController extends Controller
{
    /** GET /feed — stories strip + photo posts, newest first. Guests may read. */
    public function index(): View
    {
        $me = auth()->user();
        $meId = $me instanceof User ? (int) $me->id : null;

        // Moderation, pins, ordering and every /control feed rule — shared with the app's API.
        $posts = HomeFeed::posts();

        // Which of these the viewer has already liked / saved, in one query each rather
        // than a lookup per card.
        $liked = [];
        $saved = [];
        if ($meId !== null && $posts->isNotEmpty()) {
            $ids = $posts->pluck('id');
            $liked = PostLike::query()->where('user_id', $meId)->whereIn('post_id', $ids)->pluck('post_id')->flip()->all();
            $saved = PostSave::query()->where('user_id', $meId)->whereIn('post_id', $ids)->pluck('post_id')->flip()->all();
        }

        $items = $posts->map(function (PlayerPost $p) use ($liked, $saved, $meId): array {
            $images = $p->imagePaths();
            $author = $p->user;

            return [
                'id' => (int) $p->id,
                'images' => array_map([$this, 'mediaUrl'], $images),
                'caption' => (string) ($p->caption ?? ''),
                'createdAt' => $p->created_at,
                'likeCount' => (int) $p->likes_count,
                'commentCount' => (int) $p->comments_count,
                'liked' => isset($liked[$p->id]),
                'saved' => isset($saved[$p->id]),
                'mine' => $meId !== null && (int) $p->user_id === $meId,
                'pinned' => $p->isPinned(),
                'featured' => (bool) $p->featured,
                'authorId' => (string) ($author?->player_id ?? ''),
                'authorName' => (string) ($author?->name ?? 'Player'),
                'authorUsername' => (string) ($author?->username ?? ''),
                'authorAvatar' => $this->mediaUrl($author?->avatar),
            ];
        })->values();

        // One story bubble per recent poster (their newest post), newest first — same
        // derivation as the app, so both surfaces show the same ring of people.
        $stories = HomeFeed::storyPosts($posts)->map(fn (PlayerPost $p): array => [
            'playerId' => (string) ($p->user?->player_id ?? ''),
            'name' => (string) ($p->user?->name ?? 'Player'),
            'username' => (string) ($p->user?->username ?? ''),
            'avatar' => $this->mediaUrl($p->user?->avatar),
        ])->values();

        return view('site.feed', [
            'title' => 'Home',
            'feedPosts' => $items,
            'feedStories' => $stories,
            'feedRules' => HomeFeed::clientRules(),
            'reportReasons' => PostReport::REASONS,
        ]);
    }

    /** POST /feed/posts/{id}/like — toggle the viewer's like. Answers the new truth. */
    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $post = PlayerPost::query()->find($id);
        if ($post === null || ! HomeFeed::viewable($post, $user)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $existing = PostLike::query()
            ->where('post_id', (int) $post->id)
            ->where('user_id', (int) $user->id);

        if ($existing->exists()) {
            $existing->delete();
            $liked = false;
        } else {
            // firstOrCreate, not create: a double-tap that races itself must not throw on
            // the unique index.
            PostLike::query()->firstOrCreate([
                'post_id' => (int) $post->id,
                'user_id' => (int) $user->id,
            ]);
            $liked = true;
        }

        // The authoritative count, counted — never the client's optimistic guess echoed back.
        return response()->json(['liked' => $liked, 'like_count' => (int) $post->likes()->count()]);
    }

    /** GET /feed/posts/{id}/comments — the thread, oldest first. Guests may read. */
    public function comments(int $id): JsonResponse
    {
        $post = PlayerPost::query()->find($id);
        $viewer = auth()->user();
        if ($post === null || ! HomeFeed::viewable($post, $viewer instanceof User ? $viewer : null)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $rows = PostComment::query()
            ->with('user')
            ->where('post_id', $post->id)
            ->visible()
            ->orderBy('id')
            ->limit(300)
            ->get();

        return response()->json([
            'results' => $rows->map(fn (PostComment $c): array => [
                'id' => (int) $c->id,
                'body' => (string) $c->body,
                'ago' => $c->created_at?->diffForHumans(null, true) ?? '',
                'name' => (string) ($c->user?->name ?? 'Player'),
                'username' => (string) ($c->user?->username ?? ''),
                'avatar' => $this->mediaUrl($c->user?->avatar),
            ])->values(),
        ]);
    }

    /** POST /feed/posts/{id}/comments — add one. */
    public function addComment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($blocked = HomeFeed::commentingBlockedReason($user)) {
            return response()->json(['error' => $blocked], 403);
        }
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);
        $body = trim($data['body']);
        if ($body === '') {
            return response()->json(['error' => 'Empty comment'], 422);
        }
        if (HomeFeed::blockedWordIn($body) !== null) {
            return response()->json(['error' => 'Your comment has words that aren’t allowed on Haraan.'], 422);
        }

        $post = PlayerPost::query()->find($id);
        if ($post === null || ! HomeFeed::viewable($post, $user)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $comment = PostComment::create([
            'post_id' => (int) $post->id,
            'user_id' => (int) $user->id,
            'body' => $body,
        ]);

        return response()->json([
            'id' => (int) $comment->id,
            'body' => $comment->body,
            'ago' => 'just now',
            'name' => (string) $user->name,
            'username' => (string) ($user->username ?? ''),
            'avatar' => $this->mediaUrl($user->avatar),
            'comment_count' => (int) $post->visibleComments()->count(),
        ], 201);
    }

    /** POST /feed/posts/{id}/report — report a post, or with comment_id one comment under it. */
    public function report(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', array_keys(PostReport::REASONS))],
            'details' => ['nullable', 'string', 'max:500'],
            'comment_id' => ['nullable', 'integer'],
        ]);

        $post = PlayerPost::query()->find($id);
        if ($post === null || ! HomeFeed::viewable($post, $user)) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ((int) $post->user_id === (int) $user->id && empty($data['comment_id'])) {
            return response()->json(['error' => 'You can’t report your own post'], 422);
        }

        $comment = null;
        if (! empty($data['comment_id'])) {
            $comment = PostComment::query()->where('post_id', $post->id)->find((int) $data['comment_id']);
            if ($comment === null) {
                return response()->json(['error' => 'Comment not found'], 404);
            }
        }

        $details = trim((string) ($data['details'] ?? ''));
        $hidden = HomeFeed::report($user, $post, $comment, $data['reason'], $details === '' ? null : $details);

        return response()->json(['reported' => true, 'hidden' => $hidden], 201);
    }

    /**
     * Stored media comes in three shapes in this database — an absolute URL, a
     * web-rooted "/storage/..." path, and a bare disk path — and all three are real,
     * so all three have to resolve rather than one being declared correct.
     */
    private function mediaUrl(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
