<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DeliveryReview;
use ReflectionClass;
use Tests\TestCase;

/**
 * The sanitiser is the safety boundary between a language model and a cricket screen.
 *
 * Controlled generation makes malformed output unlikely, not impossible, and this output
 * decides which components the app draws and what a player reads about a delivery they
 * are arguing over. Every one of these tests exists to prove the same thing from a
 * different angle: nothing the model invents can reach the screen wearing the clothes of
 * something the model actually saw.
 *
 * Reached by reflection because sanitise() is correctly private — it is an internal
 * detail of the service, and widening it to public just to test it would be letting the
 * test suite dictate the API.
 */
class DeliveryReviewSanitiserTest extends TestCase
{
    private function sanitise(array $raw, string $kind = 'lbw'): ?array
    {
        $service = app(DeliveryReview::class);
        $method = (new ReflectionClass($service))->getMethod('sanitise');
        $method->setAccessible(true);

        return $method->invoke($service, $raw, $kind);
    }

    /** @return array<string, array{reading: string, certain: bool}> */
    private function factors(string $reading = 'in_line', bool $certain = true): array
    {
        $out = [];
        foreach (DeliveryReview::FACTORS as $factor) {
            $out[$factor] = ['reading' => $reading, 'certain' => $certain];
        }
        // Two factors have their own vocabulary and would be dropped by a blanket value.
        $out['bat_involved'] = ['reading' => 'pad_first', 'certain' => $certain];
        $out['height'] = ['reading' => 'below_stumps', 'certain' => $certain];
        $out['line'] = ['reading' => 'cannot_tell', 'certain' => false];

        return $out;
    }

    public function test_it_keeps_a_well_formed_response(): void
    {
        $clean = $this->sanitise([
            'factors' => $this->factors(),
            'visibility' => 'good',
            'notes' => 'Filmed side-on from mid-wicket.',
        ]);

        $this->assertNotNull($clean);
        $this->assertSame('good', $clean['visibility']);
        $this->assertSame('Filmed side-on from mid-wicket.', $clean['notes']);
        $this->assertCount(5, $clean['factors']);
        $this->assertSame('in_line', $clean['factors']['pitching']['reading']);
        $this->assertTrue($clean['factors']['pitching']['certain']);
    }

    public function test_it_drops_a_factor_with_an_invented_reading(): void
    {
        $factors = $this->factors();
        // Plausible cricket, and not one of ours. This is the exact shape of the failure
        // the sanitiser exists to stop: a value that would render as a blank row.
        $factors['pitching'] = ['reading' => 'outside_leg_stump', 'certain' => true];

        $clean = $this->sanitise(['factors' => $factors, 'visibility' => 'good']);

        $this->assertNotNull($clean);
        $this->assertArrayNotHasKey('pitching', $clean['factors']);
        $this->assertCount(4, $clean['factors']);
    }

    public function test_it_rejects_a_reading_borrowed_from_another_factor(): void
    {
        $factors = $this->factors();
        // "pad_first" is real, but it belongs to bat_involved, not to height. Each factor
        // is checked against its OWN vocabulary rather than one shared list.
        $factors['height'] = ['reading' => 'pad_first', 'certain' => true];

        $clean = $this->sanitise(['factors' => $factors, 'visibility' => 'good']);

        $this->assertArrayNotHasKey('height', $clean['factors']);
    }

    public function test_it_ignores_factors_we_never_asked_for(): void
    {
        $factors = $this->factors();
        $factors['spin_direction'] = ['reading' => 'in_line', 'certain' => true];
        $factors['ball_speed_kph'] = ['reading' => '132', 'certain' => true];

        $clean = $this->sanitise(['factors' => $factors, 'visibility' => 'good']);

        $this->assertSame(DeliveryReview::FACTORS, array_keys($clean['factors']));
    }

    public function test_it_survives_missing_factors(): void
    {
        $clean = $this->sanitise([
            'factors' => ['pitching' => ['reading' => 'outside_off', 'certain' => false]],
            'visibility' => 'partial',
        ]);

        $this->assertNotNull($clean);
        $this->assertCount(1, $clean['factors']);
        $this->assertSame('outside_off', $clean['factors']['pitching']['reading']);
    }

    public function test_it_returns_null_when_no_factor_survives(): void
    {
        $this->assertNull($this->sanitise([
            'factors' => ['pitching' => ['reading' => 'probably_out', 'certain' => true]],
            'visibility' => 'good',
        ]));
        $this->assertNull($this->sanitise(['factors' => [], 'visibility' => 'good']));
        $this->assertNull($this->sanitise([]));
    }

    public function test_cannot_tell_is_kept_and_never_upgraded(): void
    {
        $factors = $this->factors();
        // The single most important behaviour in the class. An unresolved factor has to
        // survive as unresolved: silently dropping it would make a review look more
        // decisive than the footage was.
        $factors['impact'] = ['reading' => 'cannot_tell', 'certain' => false];

        $clean = $this->sanitise(['factors' => $factors, 'visibility' => 'poor']);

        $this->assertSame('cannot_tell', $clean['factors']['impact']['reading']);
        $this->assertFalse($clean['factors']['impact']['certain']);
    }

    public function test_certainty_defaults_to_false_when_absent_or_junk(): void
    {
        $factors = $this->factors();
        unset($factors['pitching']['certain']);
        $factors['impact']['certain'] = 'yes, definitely';

        $clean = $this->sanitise(['factors' => $factors, 'visibility' => 'good']);

        // Missing certainty must never read as certain.
        $this->assertFalse($clean['factors']['pitching']['certain']);
        // And neither must junk. A plain (bool) cast made "yes, definitely" — and, worse,
        // "unsure" — come out TRUE, which would light the certainty dot on a factor the
        // model was hedging. Only a real boolean true survives.
        $this->assertFalse($clean['factors']['impact']['certain']);
    }

    public function test_a_hedging_string_never_reads_as_certain(): void
    {
        foreach (['unsure', 'no', 'false', '0.4', 1, 'maybe'] as $junk) {
            $factors = $this->factors();
            $factors['pitching']['certain'] = $junk;

            $clean = $this->sanitise(['factors' => $factors, 'visibility' => 'good']);

            $this->assertFalse(
                $clean['factors']['pitching']['certain'],
                'certain must be false for ' . var_export($junk, true),
            );
        }
    }

    public function test_unknown_visibility_falls_back_to_poor(): void
    {
        $clean = $this->sanitise([
            'factors' => $this->factors(),
            'visibility' => 'excellent',
        ]);

        // Fails toward "trust this less", never toward "trust this more".
        $this->assertSame('poor', $clean['visibility']);
    }

    public function test_notes_are_stripped_of_markup(): void
    {
        $clean = $this->sanitise([
            'factors' => $this->factors(),
            'visibility' => 'good',
            'notes' => '<b>Side-on</b> view <script>alert(1)</script>from square leg.',
        ]);

        $this->assertStringNotContainsString('<', $clean['notes']);
        $this->assertStringNotContainsString('script', $clean['notes']);
        $this->assertStringContainsString('Side-on', $clean['notes']);
    }

    public function test_oversized_notes_are_dropped_not_truncated(): void
    {
        $clean = $this->sanitise([
            'factors' => $this->factors(),
            'visibility' => 'good',
            'notes' => str_repeat('The camera was a long way from the stumps. ', 20),
        ]);

        // Dropped whole rather than cut mid-sentence: a review panel is not a place for
        // an essay, and half a sentence reads as a bug.
        $this->assertNull($clean['notes']);
        $this->assertNotNull($clean['factors']);
    }

    public function test_non_string_notes_do_not_crash_it(): void
    {
        foreach ([null, 42, ['a' => 'b'], true] as $junk) {
            $clean = $this->sanitise([
                'factors' => $this->factors(),
                'visibility' => 'good',
                'notes' => $junk,
            ]);
            $this->assertNull($clean['notes']);
        }
    }

    public function test_readings_are_matched_case_insensitively(): void
    {
        $factors = $this->factors();
        $factors['pitching'] = ['reading' => '  IN_LINE  ', 'certain' => true];

        $clean = $this->sanitise(['factors' => $factors, 'visibility' => 'good']);

        $this->assertSame('in_line', $clean['factors']['pitching']['reading']);
    }

    public function test_bowler_shape_is_sanitised_separately(): void
    {
        $clean = $this->sanitise([
            'armAction' => 'side_on',
            'frontFoot' => 'behind_line',
            'visibility' => 'good',
            'notes' => 'Crease visible throughout.',
        ], 'bowler');

        $this->assertSame('side_on', $clean['armAction']);
        $this->assertSame('behind_line', $clean['frontFoot']);
        // No LBW factors leak into a bowling read.
        $this->assertArrayNotHasKey('factors', $clean);
    }

    public function test_bowler_response_without_visibility_is_rejected(): void
    {
        $this->assertNull($this->sanitise([
            'armAction' => 'side_on',
            'frontFoot' => 'behind_line',
            'visibility' => 'crystal clear',
        ], 'bowler'));
    }

    public function test_no_verdict_field_can_ever_survive(): void
    {
        // The guarantee the whole feature rests on: even if a model volunteered a
        // decision, nothing carries it out of this method.
        $clean = $this->sanitise([
            'factors' => $this->factors(),
            'visibility' => 'good',
            'decision' => 'OUT',
            'out' => true,
            'confidence' => 0.97,
        ]);

        $this->assertSame(['factors', 'visibility', 'notes', 'delivery'], array_keys($clean));
        $this->assertArrayNotHasKey('decision', $clean);
        $this->assertArrayNotHasKey('out', $clean);
        $this->assertArrayNotHasKey('confidence', $clean);
    }
}
