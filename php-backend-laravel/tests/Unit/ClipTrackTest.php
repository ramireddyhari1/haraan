<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ClipTrack;
use PHPUnit\Framework\TestCase;

/**
 * The camera's track arrives from a phone with no account, so the sanitiser is the only
 * thing between a request body and a path drawn on the scorer's screen.
 */
class ClipTrackTest extends TestCase
{
    private function body(array $points, float $aspect = 1.7778, mixed $bounce = null): string
    {
        return json_encode(['v' => 1, 'aspect' => $aspect, 'bounce' => $bounce, 'points' => $points]);
    }

    public function test_a_clean_track_is_kept_and_rounded(): void
    {
        $out = json_decode(ClipTrack::sanitise($this->body([
            [0, 0.1, 0.5, 0.8], [33, 0.2, 0.55, 0.7], [66, 0.312345, 0.6, 0.9],
        ], bounce: 1)), true);

        $this->assertSame(1, $out['bounce']);
        $this->assertCount(3, $out['points']);
        $this->assertSame(0.3123, $out['points'][2][1]);
    }

    public function test_points_outside_the_frame_are_dropped(): void
    {
        $out = json_decode(ClipTrack::sanitise($this->body([
            [0, 0.1, 0.5], [10, 1.4, 0.5], [20, 0.2, 0.5], [30, 0.3, -0.1], [40, 0.4, 0.5],
        ])), true);

        $this->assertCount(3, $out['points']);
    }

    public function test_too_few_points_is_no_track(): void
    {
        $this->assertNull(ClipTrack::sanitise($this->body([[0, 0.1, 0.5], [10, 0.2, 0.5]])));
    }

    public function test_garbage_is_no_track(): void
    {
        $this->assertNull(ClipTrack::sanitise('not json'));
        $this->assertNull(ClipTrack::sanitise(null));
        $this->assertNull(ClipTrack::sanitise($this->body([[0, 0.1, 0.5], [1, 0.2, 0.5], [2, 0.3, 0.5]], aspect: 0)));
    }

    public function test_a_bounce_index_past_the_points_is_dropped(): void
    {
        $out = json_decode(ClipTrack::sanitise($this->body(
            [[0, 0.1, 0.5], [10, 0.2, 0.6], [20, 0.3, 0.5]],
            bounce: 9,
        )), true);

        $this->assertNull($out['bounce']);
    }

    public function test_the_list_is_capped(): void
    {
        $points = [];
        for ($i = 0; $i < 400; $i++) {
            $points[] = [$i, 0.5, 0.5, 0.5];
        }
        $out = json_decode(ClipTrack::sanitise($this->body($points)), true);

        $this->assertCount(ClipTrack::MAX_POINTS, $out['points']);
    }
}
