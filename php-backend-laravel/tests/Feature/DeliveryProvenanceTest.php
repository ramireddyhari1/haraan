<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DeliveryReview;
use ReflectionClass;
use Tests\TestCase;

/**
 * Provenance, calibration and evidence grading.
 *
 * The delivery structure now carries where each value came from, because a hybrid engine
 * mixes measured quantities with inferred ones and the difference has to survive all the
 * way to the screen. These tests defend three rules:
 *
 *  1. Source is assigned by us, never accepted from the model.
 *  2. Speed and calibration stay absent until something real produces them.
 *  3. Wicket projection can never be graded high, whatever confidence is claimed.
 */
class DeliveryProvenanceTest extends TestCase
{
    private function sanitise(array $raw): ?array
    {
        $service = app(DeliveryReview::class);
        $method = (new ReflectionClass($service))->getMethod('sanitise');
        $method->setAccessible(true);

        return $method->invoke($service, $raw, 'lbw');
    }

    private function delivery(array $delivery): array
    {
        return $this->sanitise([
            'factors' => ['pitching' => ['reading' => 'in_line', 'certain' => true]],
            'visibility' => 'good',
            'delivery' => $delivery,
        ])['delivery'];
    }

    private function track(int $n): array
    {
        return array_map(
            fn (int $i) => ['timestampMs' => 100 * ($i + 1), 'x' => 0.3 + $i * 0.05, 'y' => 0.5],
            range(0, $n - 1),
        );
    }

    // ── Provenance ──────────────────────────────────────────────────────────────

    /**
     * The rule the whole hybrid design rests on: a model cannot label its own guess as a
     * measurement. It says computer_vision; we overwrite it with vertex.
     */
    public function test_the_model_cannot_claim_its_output_was_measured(): void
    {
        $d = $this->delivery([
            'ball' => [
                'detected' => true,
                'points' => $this->track(4),
                'source' => 'computer_vision',
            ],
            'pitching' => ['x' => 0.4, 'y' => 0.8, 'source' => 'computer_vision'],
        ]);

        $this->assertSame(DeliveryReview::SOURCE_VERTEX, $d['ball']['source']);
        $this->assertSame(DeliveryReview::SOURCE_VERTEX, $d['pitching']['source']);
        $this->assertSame(DeliveryReview::SOURCE_VERTEX, $d['trajectory']['source']);
    }

    public function test_absent_evidence_is_sourced_unknown_not_vertex(): void
    {
        $d = $this->delivery(['ball' => ['detected' => false, 'points' => []]]);

        $this->assertSame(DeliveryReview::SOURCE_UNKNOWN, $d['ball']['source']);
        $this->assertSame(DeliveryReview::SOURCE_UNKNOWN, $d['trajectory']['source']);
        $this->assertSame(DeliveryReview::SOURCE_UNKNOWN, $d['timing']['source']);
    }

    public function test_a_projection_is_always_sourced_and_marked_projected(): void
    {
        $d = $this->delivery([
            'wicketProjection' => ['predicted' => true, 'stumpsHit' => 'would_hit', 'x' => 0.5, 'y' => 0.3],
        ]);

        $this->assertSame(DeliveryReview::SOURCE_VERTEX, $d['wicketProjection']['source']);
        $this->assertSame('projected', $d['wicketProjection']['kind']);
    }

    // ── Calibration and speed ───────────────────────────────────────────────────

    /**
     * Nothing has ever located a crease or a stump, so there is no pixels-to-metres
     * mapping and every real-world measurement is off the table.
     */
    public function test_calibration_is_unavailable_and_says_so(): void
    {
        $d = $this->delivery(['ball' => ['detected' => true, 'points' => $this->track(5)]]);

        $this->assertSame('unavailable', $d['calibration']['status']);
        $this->assertSame(DeliveryReview::SOURCE_UNKNOWN, $d['calibration']['source']);
    }

    /**
     * The most dangerous number this feature could print. Everybody believes a speed gun,
     * and a pixel displacement per millisecond is not km/h no matter how confident the
     * tracking was.
     */
    public function test_speed_is_never_produced_without_calibration(): void
    {
        $d = $this->delivery([
            'ball' => ['detected' => true, 'points' => $this->track(8), 'confidence' => 0.97],
            'pitching' => ['x' => 0.4, 'y' => 0.8, 'confidence' => 0.95],
            'speed' => ['valueKmh' => 138, 'confidence' => 0.9],
        ]);

        $this->assertNull($d['speed'], 'speed must stay null while calibration is unavailable');
    }

    // ── Timing ──────────────────────────────────────────────────────────────────

    public function test_timing_is_taken_from_points_that_exist(): void
    {
        $d = $this->delivery([
            'ball' => ['detected' => true, 'points' => $this->track(3)],
            'pitching' => ['x' => 0.4, 'y' => 0.8, 'timestampMs' => 2400],
            'impact' => ['x' => 0.6, 'y' => 0.7, 'timestampMs' => 3100],
        ]);

        $this->assertSame(100, $d['timing']['releaseMs']);
        $this->assertSame(2400, $d['timing']['bounceMs']);
        $this->assertSame(3100, $d['timing']['impactMs']);
    }

    /** A timestamp with no position behind it is a guess nothing can check. */
    public function test_timing_is_null_where_the_event_was_not_located(): void
    {
        $d = $this->delivery(['pitching' => ['x' => 9.9, 'y' => 0.8, 'timestampMs' => 2400]]);

        $this->assertNull($d['timing']['bounceMs']);
        $this->assertNull($d['timing']['releaseMs']);
        // And a block of nothing is not sourced to the model.
        $this->assertSame(DeliveryReview::SOURCE_UNKNOWN, $d['timing']['source']);
    }

    // ── Trajectory ──────────────────────────────────────────────────────────────

    /** Observed points only. Gap-filling is a drawing decision, not evidence. */
    public function test_trajectory_carries_only_observed_points(): void
    {
        $d = $this->delivery(['ball' => ['detected' => true, 'points' => $this->track(4)]]);

        $this->assertCount(4, $d['trajectory']['points']);
        $this->assertSame(
            array_column($d['ball']['points'], 'timestampMs'),
            array_column($d['trajectory']['points'], 'timestampMs'),
        );
    }

    // ── Evidence grading ────────────────────────────────────────────────────────

    public function test_a_single_sighting_is_not_a_track(): void
    {
        $one = $this->delivery(['ball' => ['detected' => true, 'points' => $this->track(1), 'confidence' => 0.99]]);
        $many = $this->delivery(['ball' => ['detected' => true, 'points' => $this->track(6), 'confidence' => 0.99]]);

        $this->assertSame('medium', $one['evidence']['ballTracking']);
        $this->assertSame('high', $many['evidence']['ballTracking']);
    }

    public function test_nothing_detected_grades_low(): void
    {
        $d = $this->delivery([]);

        $this->assertSame('low', $d['evidence']['ballTracking']);
        $this->assertSame('low', $d['evidence']['pitching']);
        $this->assertSame('low', $d['evidence']['impact']);
        $this->assertSame('low', $d['evidence']['wicketProjection']);
    }

    /**
     * Capped by construction, not by a threshold. One uncalibrated camera cannot produce
     * high-confidence evidence that a ball would have hit the stumps, and no confidence
     * value the model returns may promote it.
     */
    public function test_wicket_projection_can_never_be_graded_high(): void
    {
        $d = $this->delivery([
            'wicketProjection' => [
                'predicted' => true,
                'stumpsHit' => 'would_hit',
                'x' => 0.5,
                'y' => 0.3,
                'confidence' => 1.0,
            ],
        ]);

        $this->assertSame('medium', $d['evidence']['wicketProjection']);
    }

    public function test_calibration_evidence_stays_low_while_uncalibrated(): void
    {
        $d = $this->delivery(['ball' => ['detected' => true, 'points' => $this->track(9), 'confidence' => 1.0]]);

        $this->assertSame('low', $d['evidence']['calibration']);
    }

    public function test_confidence_moves_the_grade(): void
    {
        foreach ([[0.95, 'high'], [0.6, 'medium'], [0.2, 'low']] as [$confidence, $expected]) {
            $d = $this->delivery([
                'pitching' => ['x' => 0.4, 'y' => 0.8, 'confidence' => $confidence],
            ]);
            $this->assertSame($expected, $d['evidence']['pitching'], "for $confidence");
        }
    }
}
