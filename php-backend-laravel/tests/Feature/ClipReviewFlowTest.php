<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ReviewMatchClip;
use App\Models\LiveMatch;
use App\Models\User;
use App\Services\DeliveryReview;
use App\Support\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The review request flow, now that the Vertex call happens on a queue.
 *
 * These cover the parts that are easy to break silently and impossible to notice: that a
 * second tap does not buy a second Vertex call, that a finished review is served from the
 * row rather than re-run, that the size limit refuses BEFORE a job is queued, and that
 * only the scorer can ask at all.
 *
 * Vertex itself is never called here. The job is faked, because what these assert is the
 * flow around the model, and a test that spent real money to prove routing would be a
 * test nobody runs.
 */
class ClipReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A scorer who can actually reach the route.
     *
     * The clip routes sit behind actionboard.profile as well as the scorer check, so a
     * bare user gets 403 "complete your profile" long before any review logic runs — a
     * completed ActionBoard profile is part of the fixture, not an optional extra.
     */
    private function scorer(string $email = 'scorer@haraan.test'): User
    {
        $user = User::create([
            'name' => 'Scorer', 'email' => $email,
            'password' => bcrypt('secret'), 'role' => 'USER', 'status' => 'active',
        ]);
        $user->forceFill([
            'state' => 'Andhra Pradesh',
            'district' => 'YSR Kadapa',
            'primary_sport' => 'Cricket',
            'sport_attributes' => ['role' => 'Batter', 'batting' => 'Right', 'bowling' => 'Right arm medium'],
        ])->save();

        return $user;
    }

    private function match(User $owner): LiveMatch
    {
        $match = LiveMatch::create([
            'sport' => 'cricket', 'status' => 'live',
            'home' => 'KDK', 'away' => 'NLW', 'title' => 'KDK vs NLW',
        ]);
        // Set outside create(): user_id is guarded, and the scorer gate compares against
        // it — a match created without an owner is a match nobody can review.
        $match->forceFill(['user_id' => $owner->id])->save();

        return $match;
    }

    private function clip(LiveMatch $match, array $overrides = []): int
    {
        return (int) DB::table('match_device_clips')->insertGetId(array_merge([
            'match_id' => $match->id,
            'device_id' => 1,
            'role' => 'LBW_AI_CAMERA',
            'path' => 'match-clips/' . $match->id . '/test.mp4',
            'bytes' => 1_000_000,
            'duration_ms' => 8000,
            'over_ball' => '9.5',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Minted through issueForUser, not issue(), so the token carries the user's
     * token_version — a hand-rolled payload sidesteps revocation and would keep passing
     * after a logout that should have invalidated it.
     */
    private function asScorer(User $user): self
    {
        $token = JwtService::issueForUser(
            $user,
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        );

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_requesting_a_review_queues_a_job_and_returns_202(): void
    {
        Queue::fake();
        $user = $this->scorer();
        $match = $this->match($user);
        $clipId = $this->clip($match);

        $this->asScorer($user)
            ->postJson("/api/matches/{$match->id}/clips/{$clipId}/review")
            ->assertStatus(202)
            ->assertJsonPath('data.status', DeliveryReview::STATUS_PENDING)
            ->assertJsonPath('data.review', null);

        Queue::assertPushed(ReviewMatchClip::class, 1);
        $this->assertSame(
            DeliveryReview::STATUS_PENDING,
            DB::table('match_device_clips')->find($clipId)->review_status,
        );
    }

    /** A scorer tapping twice must not be charged for two readings of the same footage. */
    public function test_a_second_request_while_running_does_not_queue_again(): void
    {
        Queue::fake();
        $user = $this->scorer();
        $match = $this->match($user);
        $clipId = $this->clip($match, ['review_status' => DeliveryReview::STATUS_PROCESSING]);

        $this->asScorer($user)
            ->postJson("/api/matches/{$match->id}/clips/{$clipId}/review")
            ->assertStatus(202)
            ->assertJsonPath('data.status', DeliveryReview::STATUS_PROCESSING);

        Queue::assertNotPushed(ReviewMatchClip::class);
    }

    public function test_a_finished_review_is_served_from_the_row(): void
    {
        Queue::fake();
        $user = $this->scorer();
        $match = $this->match($user);
        $stored = ['factors' => ['pitching' => ['reading' => 'in_line', 'certain' => true]], 'visibility' => 'good'];
        $clipId = $this->clip($match, [
            'analysis' => json_encode($stored),
            'analysed_at' => now(),
            'review_status' => DeliveryReview::STATUS_COMPLETED,
        ]);

        $this->asScorer($user)
            ->postJson("/api/matches/{$match->id}/clips/{$clipId}/review")
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryReview::STATUS_COMPLETED)
            ->assertJsonPath('data.cached', true)
            ->assertJsonPath('data.review.factors.pitching.reading', 'in_line');

        Queue::assertNotPushed(ReviewMatchClip::class);
    }

    /**
     * The bug this whole phase exists to kill: upload accepted 40MB while review refused
     * anything over 12MB, so a clip in between sat on the server unreviewable with
     * nothing on screen explaining why. It is now refused with a reason, before a job.
     */
    public function test_an_oversized_clip_is_refused_with_a_reason_and_never_queued(): void
    {
        Queue::fake();
        $user = $this->scorer();
        $match = $this->match($user);
        $clipId = $this->clip($match, ['bytes' => DeliveryReview::MAX_BYTES + 1]);

        $response = $this->asScorer($user)
            ->postJson("/api/matches/{$match->id}/clips/{$clipId}/review")
            ->assertStatus(422)
            ->assertJsonPath('data.status', DeliveryReview::STATUS_FAILED);

        $this->assertStringContainsString(
            (string) DeliveryReview::MAX_MB,
            (string) $response->json('data.error'),
        );
        Queue::assertNotPushed(ReviewMatchClip::class);
    }

    public function test_only_the_scorer_may_request_a_review(): void
    {
        Queue::fake();
        $owner = $this->scorer();
        $match = $this->match($owner);
        $clipId = $this->clip($match);

        $stranger = $this->scorer('other@haraan.test');

        $this->asScorer($stranger)
            ->postJson("/api/matches/{$match->id}/clips/{$clipId}/review")
            ->assertStatus(403);

        Queue::assertNotPushed(ReviewMatchClip::class);
    }

    public function test_a_clip_from_another_match_is_not_found(): void
    {
        Queue::fake();
        $user = $this->scorer();
        $mine = $this->match($user);
        $theirs = $this->match($user);
        $clipId = $this->clip($theirs);

        $this->asScorer($user)
            ->postJson("/api/matches/{$mine->id}/clips/{$clipId}/review")
            ->assertStatus(404);
    }

    public function test_status_reports_a_failure_with_a_safe_message(): void
    {
        $user = $this->scorer();
        $match = $this->match($user);
        $clipId = $this->clip($match, [
            'review_status' => DeliveryReview::STATUS_FAILED,
            'review_error' => 'That footage could not be reviewed.',
        ]);

        $this->asScorer($user)
            ->getJson("/api/matches/{$match->id}/clips/{$clipId}/review")
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryReview::STATUS_FAILED)
            ->assertJsonPath('data.error', 'That footage could not be reviewed.')
            ->assertJsonPath('data.review', null);
    }

    /** A clip nobody has asked about reports no status, which is how the app knows to offer the button. */
    public function test_an_unrequested_clip_has_no_status(): void
    {
        $user = $this->scorer();
        $match = $this->match($user);
        $clipId = $this->clip($match);

        $this->asScorer($user)
            ->getJson("/api/matches/{$match->id}/clips/{$clipId}/review")
            ->assertOk()
            ->assertJsonPath('data.status', null);
    }
}
