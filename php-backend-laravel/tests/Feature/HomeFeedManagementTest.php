<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\HomeFeedRulesPage;
use App\Filament\Resources\FeedRestrictions\FeedRestrictionResource;
use App\Filament\Resources\HomeFeedComments\HomeFeedCommentResource;
use App\Filament\Resources\HomeFeedPosts\HomeFeedPostResource;
use App\Filament\Resources\HomeFeedPosts\Pages\ListHomeFeedPosts;
use App\Filament\Resources\PostReports\Pages\ListPostReports;
use App\Filament\Resources\PostReports\PostReportResource;
use App\Models\AdminAction;
use App\Models\FeedRestriction;
use App\Models\PlayerPost;
use App\Models\PostComment;
use App\Models\PostReport;
use App\Models\User;
use App\Support\JwtService;
use App\Support\PlatformRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Managing the ActionBoard Home feed: what /control decides (hide, remove, pin, feature,
 * restrict, rules) is what the app's API and the website both show, and reports from players
 * reach the queue and can take a post down by themselves.
 */
class HomeFeedManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function player(string $username, array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst($username),
            'email' => $username.'@haraan.test',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => 'active',
            'username' => $username,
            'player_id' => 'HRN'.strtoupper(substr(md5($username), 0, 7)),
            'is_guest' => false,
        ], $overrides));
    }

    private function admin(string $role = 'ADMIN'): User
    {
        return User::create([
            'name' => $role, 'email' => strtolower($role).random_int(1, 999999).'@haraan.test',
            'password' => bcrypt('secret123'), 'role' => $role, 'status' => 'active',
        ]);
    }

    private function token(User $u): string
    {
        return JwtService::issueForUser($u, (string) (config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me')));
    }

    private function makePost(User $author, string $caption = 'Net session', array $overrides = []): PlayerPost
    {
        return PlayerPost::create(array_merge([
            'user_id' => $author->id,
            'image_path' => '/storage/posts/'.uniqid().'.jpg',
            'caption' => $caption,
        ], $overrides));
    }

    /** @return list<int> */
    private function feedIds(?User $viewer = null): array
    {
        $req = $viewer ? $this->withHeader('Authorization', 'Bearer '.$this->token($viewer)) : $this;

        return array_column($req->getJson('/api/posts/feed')->assertOk()->json('posts'), 'id');
    }

    public function test_existing_posts_stay_on_the_feed_after_deploy(): void
    {
        $a = $this->player('rohit');
        $p = $this->makePost($a);

        $this->assertSame('visible', $p->fresh()->status);
        $json = $this->getJson('/api/posts/feed')->assertOk()->json();
        $this->assertSame([$p->id], array_column($json['posts'], 'id'));
        $this->assertTrue($json['rules']['enabled']);
        $this->assertTrue($json['rules']['posting_enabled']);
    }

    public function test_hidden_and_removed_posts_leave_the_feed_and_profile(): void
    {
        $a = $this->player('rohit');
        $keep = $this->makePost($a, 'keep');
        $hidden = $this->makePost($a, 'hidden', ['status' => PlayerPost::STATUS_HIDDEN]);
        $removed = $this->makePost($a, 'removed', ['status' => PlayerPost::STATUS_REMOVED]);

        $this->assertSame([$keep->id], $this->feedIds());

        // Public profile grid: visible only.
        $public = array_column($this->getJson("/api/players/{$a->player_id}/posts")->json('results'), 'id');
        $this->assertSame([$keep->id], $public);

        // The author still sees the hidden one (marked), never the removed one.
        $own = collect($this->withHeader('Authorization', 'Bearer '.$this->token($a))
            ->getJson("/api/players/{$a->player_id}/posts")->json('results'));
        $this->assertEqualsCanonicalizing([$keep->id, $hidden->id], $own->pluck('id')->all());
        $this->assertSame('hidden', $own->firstWhere('id', $hidden->id)['status']);

        // Nobody can like a removed post; the author can't erase the evidence.
        $b = $this->player('virat');
        $this->withHeader('Authorization', 'Bearer '.$this->token($b))
            ->postJson("/api/players/posts/{$removed->id}/like")->assertNotFound();
        $this->withHeader('Authorization', 'Bearer '.$this->token($a))
            ->postJson("/api/players/posts/{$removed->id}/delete")->assertNotFound();
        $this->assertNotNull($removed->fresh());
    }

    public function test_pinned_posts_lead_and_expired_pins_do_not(): void
    {
        $a = $this->player('rohit');
        $old = $this->makePost($a, 'old', ['pinned_at' => now()]);
        $expired = $this->makePost($a, 'expired', ['pinned_at' => now(), 'pinned_until' => now()->subHour()]);
        $new = $this->makePost($a, 'new');

        $ids = $this->feedIds();
        $this->assertSame($old->id, $ids[0]);
        $this->assertSame([$old->id, $new->id, $expired->id], $ids);

        $row = collect($this->getJson('/api/posts/feed')->json('posts'))->firstWhere('id', $old->id);
        $this->assertTrue($row['pinned']);
    }

    public function test_restricted_author_is_off_the_feed_and_cannot_post_or_comment(): void
    {
        $a = $this->player('rohit');
        $b = $this->player('virat');
        $p = $this->makePost($a);
        $this->makePost($b);

        FeedRestriction::create(['user_id' => $a->id, 'type' => FeedRestriction::HIDDEN_FROM_FEED]);
        $this->assertNotContains($p->id, $this->feedIds());

        FeedRestriction::create(['user_id' => $b->id, 'type' => FeedRestriction::NO_POSTING]);
        FeedRestriction::create(['user_id' => $b->id, 'type' => FeedRestriction::NO_COMMENTS, 'until' => now()->addDay()]);
        Storage::fake('public');
        $this->withHeader('Authorization', 'Bearer '.$this->token($b))
            ->post('/api/players/posts', ['image' => UploadedFile::fake()->create('a.jpg', 20, 'image/jpeg')], ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$this->token($b))
            ->postJson("/api/players/posts/{$p->id}/comments", ['body' => 'hi'])
            ->assertForbidden();

        // An expired restriction no longer applies.
        FeedRestriction::query()->where('user_id', $b->id)->where('type', FeedRestriction::NO_COMMENTS)->update(['until' => now()->subMinute()]);
        $this->withHeader('Authorization', 'Bearer '.$this->token($b))
            ->postJson("/api/players/posts/{$p->id}/comments", ['body' => 'hi'])
            ->assertCreated();
    }

    public function test_rules_switch_the_feed_off_and_block_words(): void
    {
        $a = $this->player('rohit');
        $p = $this->makePost($a);

        PlatformRules::save(['home_feed.blocked_words' => "scam, fake tickets"]);
        $this->withHeader('Authorization', 'Bearer '.$this->token($a))
            ->postJson("/api/players/posts/{$p->id}/comments", ['body' => 'Buy FAKE TICKETS here'])
            ->assertStatus(422);

        PlatformRules::save(['home_feed.comments_enabled' => false]);
        $this->withHeader('Authorization', 'Bearer '.$this->token($a))
            ->postJson("/api/players/posts/{$p->id}/comments", ['body' => 'nice'])
            ->assertForbidden();

        PlatformRules::save(['home_feed.enabled' => false]);
        $json = $this->getJson('/api/posts/feed')->assertOk()->json();
        $this->assertSame([], $json['posts']);
        $this->assertFalse($json['rules']['enabled']);
        $this->assertNotEmpty($json['rules']['message']);
    }

    public function test_per_author_cap_and_stories_switch(): void
    {
        $a = $this->player('rohit');
        $b = $this->player('virat');
        foreach (range(1, 4) as $i) {
            $this->makePost($a, "a{$i}");
        }
        $bp = $this->makePost($b);

        PlatformRules::save(['home_feed.max_per_author' => 2, 'home_feed.stories_enabled' => false]);
        $json = $this->getJson('/api/posts/feed')->json();
        $this->assertCount(3, $json['posts']);
        $this->assertContains($bp->id, array_column($json['posts'], 'id'));
        $this->assertSame([], $json['stories']);
    }

    public function test_reports_reach_the_queue_and_auto_hide_at_the_threshold(): void
    {
        PlatformRules::save(['home_feed.auto_hide_reports' => 2]);
        $author = $this->player('rohit');
        $p = $this->makePost($author);
        $r1 = $this->player('virat');
        $r2 = $this->player('shubman');

        // Own post can't be reported; a bad reason is refused.
        $this->withHeader('Authorization', 'Bearer '.$this->token($author))
            ->postJson("/api/players/posts/{$p->id}/report", ['reason' => 'spam'])->assertStatus(422);
        $this->withHeader('Authorization', 'Bearer '.$this->token($r1))
            ->postJson("/api/players/posts/{$p->id}/report", ['reason' => 'nonsense'])->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$this->token($r1))
            ->postJson("/api/players/posts/{$p->id}/report", ['reason' => 'spam'])->assertCreated()->assertJson(['hidden' => false]);
        // The same person twice is still one person.
        $this->withHeader('Authorization', 'Bearer '.$this->token($r1))
            ->postJson("/api/players/posts/{$p->id}/report", ['reason' => 'hate'])->assertCreated()->assertJson(['hidden' => false]);
        $this->assertSame(1, PostReport::query()->count());

        $this->withHeader('Authorization', 'Bearer '.$this->token($r2))
            ->postJson("/api/players/posts/{$p->id}/report", ['reason' => 'spam'])->assertCreated()->assertJson(['hidden' => true]);

        $this->assertSame(PlayerPost::STATUS_HIDDEN, $p->fresh()->status);
        $this->assertSame(2, $p->fresh()->open_reports);
        $this->assertNotContains($p->id, $this->feedIds());
        $this->assertTrue(AdminAction::query()->where('action', 'home_feed.post_auto_hidden')->exists());
    }

    public function test_hidden_comments_leave_the_thread_and_the_count(): void
    {
        $a = $this->player('rohit');
        $p = $this->makePost($a);
        $c1 = PostComment::create(['post_id' => $p->id, 'user_id' => $a->id, 'body' => 'ok']);
        PostComment::create(['post_id' => $p->id, 'user_id' => $a->id, 'body' => 'rude', 'status' => PostComment::STATUS_HIDDEN]);

        $this->assertSame([$c1->id], array_column($this->getJson("/api/posts/{$p->id}/comments")->json('results'), 'id'));
        $row = collect($this->getJson('/api/posts/feed')->json('posts'))->firstWhere('id', $p->id);
        $this->assertSame(1, $row['comment_count']);
    }

    public function test_website_feed_follows_the_same_moderation(): void
    {
        $a = $this->player('rohit');
        $this->makePost($a, 'Visible on web');
        $this->makePost($a, 'Hidden on web', ['status' => PlayerPost::STATUS_HIDDEN]);

        $this->get('/feed')->assertOk()->assertSee('Visible on web')->assertDontSee('Hidden on web');
    }

    public function test_website_signed_in_can_report_and_sees_paused_comments(): void
    {
        $a = $this->player('rohit');
        $b = $this->player('virat');
        $p = $this->makePost($a, 'Pinned on web', ['pinned_at' => now()]);

        PlatformRules::save(['home_feed.comments_enabled' => false]);
        $this->actingAs($b)->get('/feed')->assertOk()
            ->assertSee('Pinned')
            ->assertSee('Report this post')
            ->assertSee('Comments are paused for now.');

        $this->actingAs($b)->postJson("/feed/posts/{$p->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->assertSame(1, $p->fresh()->open_reports);

        PlatformRules::save(['home_feed.enabled' => false]);
        $this->actingAs($b)->get('/feed')->assertOk()->assertSee('Home is taking a break')->assertDontSee('Pinned on web');
    }

    public function test_control_pages_render_for_ops_and_not_for_marketing(): void
    {
        $this->makePost($this->player('rohit'));

        $this->actingAs($this->admin('MARKETING'));
        $this->assertFalse(HomeFeedPostResource::canAccess());
        $this->assertFalse(HomeFeedRulesPage::canAccess());

        $this->actingAs($this->admin('OPS'));
        $this->get(HomeFeedPostResource::getUrl('index'))->assertOk();
        $this->get(HomeFeedPostResource::getUrl('create'))->assertOk();
        $this->get(HomeFeedCommentResource::getUrl('index'))->assertOk();
        $this->get(PostReportResource::getUrl('index'))->assertOk();
        $this->get(FeedRestrictionResource::getUrl('index'))->assertOk();
        $this->get(HomeFeedRulesPage::getUrl())->assertOk();
    }

    public function test_moderator_actions_change_the_feed_and_are_audited(): void
    {
        $this->actingAs($ops = $this->admin('OPS'));
        $a = $this->player('rohit');
        $p = $this->makePost($a);
        $other = $this->makePost($a, 'second');
        PostReport::create(['post_id' => $p->id, 'reporter_id' => $this->player('virat')->id, 'reason' => 'spam', 'status' => 'open']);

        Livewire::test(ListHomeFeedPosts::class)
            ->callTableAction('pin', $other, data: ['until' => null])
            ->assertHasNoTableActionErrors()
            ->callTableAction('feature', $other)
            ->callTableAction('hide', $p, data: ['reason' => 'Off topic'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(PlayerPost::STATUS_HIDDEN, $p->fresh()->status);
        $this->assertSame($ops->id, $p->fresh()->moderated_by);
        $this->assertSame('actioned', PostReport::query()->first()->status);
        $this->assertTrue($other->fresh()->isPinned());
        $this->assertTrue($other->fresh()->featured);
        $this->assertSame([$other->id], $this->feedIds());

        Livewire::test(ListHomeFeedPosts::class)
            ->callTableAction('restore', $p)
            ->callTableAction('restrict', $p, data: ['type' => FeedRestriction::NO_POSTING, 'until' => null, 'reason' => 'spam'])
            ->assertHasNoTableActionErrors();
        $this->assertTrue($p->fresh()->isVisible());
        $this->assertTrue(FeedRestriction::has($a->id, FeedRestriction::NO_POSTING));

        foreach (['home_feed.post_pinned', 'home_feed.post_featured', 'home_feed.post_hidden', 'home_feed.post_restored', 'home_feed.restriction_added'] as $action) {
            $this->assertTrue(AdminAction::query()->where('action', $action)->exists(), $action);
        }
    }

    public function test_report_queue_dismiss_restores_an_auto_hidden_post(): void
    {
        $this->actingAs($this->admin('OPS'));
        $a = $this->player('rohit');
        $p = $this->makePost($a, 'fine', ['status' => PlayerPost::STATUS_HIDDEN, 'open_reports' => 1]);
        $report = PostReport::create(['post_id' => $p->id, 'reporter_id' => $this->player('virat')->id, 'reason' => 'spam', 'status' => 'open']);

        Livewire::test(ListPostReports::class)
            ->callTableAction('dismiss', $report)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($p->fresh()->isVisible());
        $this->assertSame('dismissed', $report->fresh()->status);
        $this->assertSame(0, $p->fresh()->open_reports);
    }

    public function test_rules_page_saves_for_ops(): void
    {
        $this->actingAs($this->admin('OPS'));

        Livewire::test(HomeFeedRulesPage::class)
            ->set('data.home_feed__daily_post_limit', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, PlatformRules::int('home_feed.daily_post_limit'));

        $a = $this->player('rohit');
        $this->makePost($a);
        Storage::fake('public');
        $this->withHeader('Authorization', 'Bearer '.$this->token($a))
            ->post('/api/players/posts', ['image' => UploadedFile::fake()->create('a.jpg', 20, 'image/jpeg')], ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
