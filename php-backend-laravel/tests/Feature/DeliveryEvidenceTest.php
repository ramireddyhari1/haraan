<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DeliveryReview;
use App\Support\Mp4Probe;
use ReflectionClass;
use Tests\TestCase;

/**
 * The coordinate evidence, and the limits that decide what ever reaches it.
 *
 * A coordinate is more dangerous than a label. A wrong word reads as a wrong opinion; a
 * wrong point renders as a confident dot on a pitch map and looks like a measurement. So
 * everything here is about what must NOT survive: coordinates outside the frame, a
 * detected flag with nothing behind it, a projection presented as an observation, and a
 * confidence that was never a number.
 */
class DeliveryEvidenceTest extends TestCase
{
    private function sanitise(array $raw, string $kind = 'lbw'): ?array
    {
        $service = app(DeliveryReview::class);
        $method = (new ReflectionClass($service))->getMethod('sanitise');
        $method->setAccessible(true);

        return $method->invoke($service, $raw, $kind);
    }

    private function withDelivery(array $delivery): ?array
    {
        return $this->sanitise([
            'factors' => [
                'pitching' => ['reading' => 'in_line', 'certain' => true],
            ],
            'visibility' => 'good',
            'delivery' => $delivery,
        ])['delivery'] ?? null;
    }

    // ── The contract ────────────────────────────────────────────────────────────

    public function test_the_review_and_vertex_limits_are_separate_numbers(): void
    {
        $this->assertSame(50 * 1024 * 1024, DeliveryReview::MAX_REVIEW_BYTES);
        $this->assertSame(10, DeliveryReview::MAX_REVIEW_SECONDS);
        // The whole point: what the product accepts is bigger than what Vertex can be
        // handed inline, and conflating them is what caused the original silent dead zone.
        $this->assertLessThan(
            DeliveryReview::MAX_REVIEW_BYTES,
            DeliveryReview::MAX_VERTEX_INLINE_BYTES,
        );
        // Base64 inflates by 4/3; the inline payload must stay under Vertex's request cap.
        $this->assertLessThan(20 * 1024 * 1024, DeliveryReview::MAX_VERTEX_INLINE_BYTES * 4 / 3);
    }

    public function test_upload_sizes(): void
    {
        $mb = 1024 * 1024;
        $cases = [
            '12MB' => [12 * $mb, true],
            '20MB' => [20 * $mb, true],
            '30MB' => [30 * $mb, true],
            '49MB' => [49 * $mb, true],
            '50MB' => [50 * $mb, true],
            '50MB + 1 byte' => [50 * $mb + 1, false],
            '64MB' => [64 * $mb, false],
        ];
        foreach ($cases as $label => [$bytes, $accepted]) {
            $this->assertSame(
                $accepted,
                $bytes <= DeliveryReview::MAX_REVIEW_BYTES,
                $label,
            );
        }
    }

    public function test_durations(): void
    {
        $cases = [
            '5s' => [5_000, true],
            '8s' => [8_000, true],
            '10s' => [10_000, true],
            '10.01s' => [10_010, false],
            '11s' => [11_000, false],
            '15s' => [15_000, false],
        ];
        foreach ($cases as $label => [$ms, $accepted]) {
            $this->assertSame(
                $accepted,
                $ms <= DeliveryReview::MAX_REVIEW_SECONDS * 1000,
                $label,
            );
        }
    }

    /**
     * An unreadable container has an unknown length, and unknown must not read as zero —
     * zero would sail straight through a "must be under ten seconds" check.
     */
    public function test_a_non_video_has_no_duration(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'notvideo');
        file_put_contents($path, 'this is not an mp4');

        $this->assertNull(Mp4Probe::durationMs($path));
        $this->assertNull(Mp4Probe::durationMs($path . '-does-not-exist'));

        @unlink($path);
    }

    // ── Coordinates ─────────────────────────────────────────────────────────────

    public function test_it_keeps_coordinates_inside_the_frame(): void
    {
        $d = $this->withDelivery([
            'pitching' => ['detected' => true, 'x' => 0.42, 'y' => 0.81, 'timestampMs' => 3200, 'confidence' => 0.9],
        ]);

        $this->assertTrue($d['pitching']['detected']);
        $this->assertSame(0.42, $d['pitching']['x']);
        $this->assertSame(3200, $d['pitching']['timestampMs']);
        $this->assertSame('detected', $d['pitching']['kind']);
        $this->assertSame('image_normalised', $d['coordinateSpace']);
    }

    /**
     * The most important test in the file. A model that says it saw the bounce but gives
     * a point outside the picture did not see the bounce, and `detected` is derived from
     * the coordinates rather than taken on trust.
     */
    public function test_a_detected_claim_cannot_survive_bad_coordinates(): void
    {
        foreach ([['x' => 1.4, 'y' => 0.5], ['x' => -0.2, 'y' => 0.5], ['x' => 'left', 'y' => 0.5], ['y' => 0.5]] as $bad) {
            $d = $this->withDelivery(['impact' => array_merge(['detected' => true], $bad)]);

            $this->assertFalse($d['impact']['detected'], json_encode($bad));
            $this->assertNull($d['impact']['x']);
            // And no confidence is carried for something that was not detected.
            $this->assertNull($d['impact']['modelConfidence']);
        }
    }

    public function test_ball_points_are_ordered_deduplicated_and_filtered(): void
    {
        $d = $this->withDelivery([
            'ball' => [
                'detected' => true,
                'confidence' => 0.77,
                'points' => [
                    ['timestampMs' => 900, 'x' => 0.5, 'y' => 0.4],
                    ['timestampMs' => 100, 'x' => 0.2, 'y' => 0.3],
                    ['timestampMs' => 900, 'x' => 0.6, 'y' => 0.4],   // duplicate time
                    ['timestampMs' => 400, 'x' => 9.9, 'y' => 0.3],   // outside the frame
                    ['timestampMs' => 99_000, 'x' => 0.5, 'y' => 0.5], // beyond the clip
                    'not even an object',
                ],
            ],
        ]);

        $this->assertTrue($d['ball']['detected']);
        $this->assertSame([100, 900], array_column($d['ball']['points'], 'timestampMs'));
        $this->assertSame(0.77, $d['ball']['modelConfidence']);
    }

    public function test_no_usable_points_means_the_ball_was_not_tracked(): void
    {
        $d = $this->withDelivery([
            'ball' => ['detected' => true, 'points' => [['timestampMs' => 100, 'x' => 5, 'y' => 5]]],
        ]);

        $this->assertFalse($d['ball']['detected']);
        $this->assertSame([], $d['ball']['points']);
    }

    // ── Detected vs projected ───────────────────────────────────────────────────

    public function test_a_projection_is_labelled_as_projected(): void
    {
        $d = $this->withDelivery([
            'wicketProjection' => ['predicted' => true, 'stumpsHit' => 'would_hit', 'x' => 0.5, 'y' => 0.3, 'confidence' => 0.6],
        ]);

        $this->assertSame('projected', $d['wicketProjection']['kind']);
        $this->assertTrue($d['wicketProjection']['predicted']);
        $this->assertSame('would_hit', $d['wicketProjection']['stumpsHit']);
        // And an observed point is never labelled the same way.
        $this->assertNotSame($d['wicketProjection']['kind'], $d['pitching']['kind'] ?? 'detected');
    }

    /** "predicted: true" carrying cannot_tell is not a prediction, whatever it claims. */
    public function test_cannot_tell_is_never_a_prediction(): void
    {
        $d = $this->withDelivery([
            'wicketProjection' => ['predicted' => true, 'stumpsHit' => 'cannot_tell'],
        ]);

        $this->assertFalse($d['wicketProjection']['predicted']);
        $this->assertSame('cannot_tell', $d['wicketProjection']['stumpsHit']);
    }

    public function test_an_invented_stumps_verdict_falls_back_to_cannot_tell(): void
    {
        $d = $this->withDelivery([
            'wicketProjection' => ['predicted' => true, 'stumpsHit' => 'clipping_leg_stump'],
        ]);

        $this->assertSame('cannot_tell', $d['wicketProjection']['stumpsHit']);
        $this->assertFalse($d['wicketProjection']['predicted']);
    }

    // ── Confidence ──────────────────────────────────────────────────────────────

    public function test_confidence_is_validated_and_percentages_are_normalised(): void
    {
        $cases = [0.86 => 0.86, 86 => 0.86, 100 => 1.0, 0 => 0.0];
        foreach ($cases as $given => $expected) {
            $d = $this->withDelivery([
                'pitching' => ['x' => 0.5, 'y' => 0.5, 'confidence' => $given],
            ]);
            $this->assertSame((float) $expected, $d['pitching']['modelConfidence'], "for $given");
        }
    }

    public function test_junk_confidence_becomes_null_not_certainty(): void
    {
        foreach (['very high', -1, 250, null, true] as $junk) {
            $d = $this->withDelivery([
                'pitching' => ['x' => 0.5, 'y' => 0.5, 'confidence' => $junk],
            ]);
            $this->assertNull($d['pitching']['modelConfidence'], var_export($junk, true));
        }
    }

    public function test_uncertainty_keeps_only_known_levels(): void
    {
        $d = $this->withDelivery([
            'uncertainty' => ['ballTracking' => 'high', 'impact' => 'medium', 'trajectory' => 'catastrophic'],
        ]);

        $this->assertSame(['ballTracking' => 'high', 'impact' => 'medium'], $d['uncertainty']);
    }

    // ── The coordinate space itself ─────────────────────────────────────────────

    /**
     * Nothing here is calibrated: no stump height, no crease reference, no camera pose.
     * The model does not get to promote image coordinates to real-world ones by returning
     * a different string for the space they live in.
     */
    public function test_the_model_cannot_claim_pitch_relative_coordinates(): void
    {
        $d = $this->withDelivery([
            'coordinateSpace' => 'pitch_relative_metres',
            'pitching' => ['x' => 0.5, 'y' => 0.5],
        ]);

        $this->assertSame('image_normalised', $d['coordinateSpace']);
    }

    public function test_a_response_with_no_delivery_block_is_still_valid(): void
    {
        $clean = $this->sanitise([
            'factors' => ['pitching' => ['reading' => 'in_line', 'certain' => true]],
            'visibility' => 'good',
        ]);

        $this->assertNotNull($clean);
        $this->assertNull($clean['delivery']);
    }
}
