<?php

namespace Tests\Feature;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Services\StaleMatchCloser;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaleMatchCloserTest extends TestCase
{
    use RefreshDatabase;

    private function match(string $status, int $idleHours, ?string $completedAt = null): LiveMatch
    {
        $m = LiveMatch::create([
            'title' => 'A v B', 'home' => 'ANT', 'away' => 'BEE', 'home_full' => 'Ants', 'away_full' => 'Bees',
            'home_score' => 0, 'away_score' => 0, 'status' => $status, 'sport' => 'football',
            'competition' => 'Friendly',
        ]);
        // Backdate without the saving hooks touching anything.
        DB::table('live_matches')->where('id', $m->id)->update([
            'updated_at' => now()->subHours($idleHours),
            'completed_at' => $completedAt,
        ]);

        return $m->fresh();
    }

    public function test_idle_live_match_is_abandoned_but_never_finished(): void
    {
        $stale = $this->match('Live', 30);

        $closed = app(StaleMatchCloser::class)->close();

        $this->assertSame([$stale->id], $closed);
        $fresh = $stale->fresh();
        $this->assertSame('Abandoned', $fresh->status);
        // Not finished: no stats, careers, rankings or rewards follow an abandoned game.
        $this->assertNull($fresh->completed_at);
        $this->assertFalse($fresh->isFinished());
    }

    public function test_recent_activity_keeps_a_match_live(): void
    {
        $recentSave = $this->match('Live', 3);
        $oldSaveRecentEvent = $this->match('Live', 40);
        MatchEvent::create([
            'live_match_id' => $oldSaveRecentEvent->id, 'sport' => 'football', 'sequence' => 1,
            'minute' => 10, 'side' => 'home', 'kind' => 'goal',
        ]);

        $this->assertSame([], app(StaleMatchCloser::class)->close());
        $this->assertSame('Live', $recentSave->fresh()->status);
        $this->assertSame('Live', $oldSaveRecentEvent->fresh()->status);
    }

    public function test_finished_scheduled_and_dry_runs_are_left_alone(): void
    {
        $finished = $this->match('Completed', 100, now()->subDays(4)->toDateTimeString());
        $scheduled = $this->match('Scheduled', 100);
        $stale = $this->match('Live', 100);

        $this->assertSame([$stale->id], app(StaleMatchCloser::class)->close(dryRun: true));
        $this->assertSame('Live', $stale->fresh()->status);
        $this->assertSame('Completed', $finished->fresh()->status);
        $this->assertSame('Scheduled', $scheduled->fresh()->status);
    }

    public function test_admin_controls_switch_and_hours(): void
    {
        $match = $this->match('Live', 30);

        PlatformRules::save(['creation.stale_live_close' => false]);
        $this->assertSame([], app(StaleMatchCloser::class)->close());
        $this->assertSame('Live', $match->fresh()->status);

        PlatformRules::save(['creation.stale_live_close' => true, 'creation.stale_live_hours' => 48]);
        $this->assertSame([], app(StaleMatchCloser::class)->close());

        PlatformRules::save(['creation.stale_live_hours' => 24]);
        $this->assertSame([$match->id], app(StaleMatchCloser::class)->close());
    }
}
