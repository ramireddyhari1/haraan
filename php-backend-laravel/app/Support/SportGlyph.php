<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Line drawings for the racquet sports, as inner SVG markup for a 24×24 stroked icon
 * (fill none, stroke currentColor, round caps — the site's icon convention).
 *
 * One object per sport. The single "racket" glyph these replace (an oval on a stick)
 * read as a magnifying glass, so badminton, tennis and pickleball each show the thing
 * a player recognises: a shuttlecock, a strung racket with a ball, a paddle with a
 * holed ball.
 */
final class SportGlyph
{
    /** Shuttlecock, cork down, tilted as if in flight. */
    public const BADMINTON = '<g transform="rotate(-25 12 12)">'
        . '<path d="M9.4 17.6h5.2v1.1a2.6 2.6 0 0 1-5.2 0z"></path>'
        . '<path d="M9.4 17.6 6.2 4.8M14.6 17.6l3.2-12.8M10.9 17.6 10 4.3M13.1 17.6l.9-13.3"></path>'
        . '<path d="M6.2 4.8c3.8-1.1 7.8-1.1 11.6 0M7.5 10.6h9"></path>'
        . '</g>';

    /** Strung racket (head, throat, grip) and a ball with its seam. */
    public const TENNIS = '<g transform="rotate(30 10 10)">'
        . '<ellipse cx="10" cy="7.5" rx="5" ry="6"></ellipse>'
        . '<path d="M7.6 12.8 10 15.6l2.4-2.8M10 15.6v6"></path>'
        . '<path d="M7.5 3.5v8M10 1.5v12M12.5 3.5v8M5.2 6h9.6M5 9h10"></path>'
        . '</g>'
        . '<circle cx="18.6" cy="18.6" r="2.9"></circle>'
        . '<path d="M16.1 17.2c1.4.5 2.3 1.6 2.5 3.4M21.1 20c-1.4-.5-2.3-1.6-2.5-3.4"></path>';

    /** Solid-face paddle and a perforated ball. */
    public const PICKLEBALL = '<g transform="rotate(30 10 12)">'
        . '<rect x="4.5" y="2.5" width="11" height="11.5" rx="5"></rect>'
        . '<path d="M8.6 14v6.5h2.8V14"></path>'
        . '</g>'
        . '<circle cx="18.6" cy="18.4" r="3"></circle>'
        . '<path d="M17.5 17.4h.01M19.7 17.4h.01M18.6 19.4h.01" stroke-width="2.2"></path>';

    /** A bat (blade, shoulders, taped handle) leaning on a ball with its seam. The old
     *  glyph was a single slanted stroke with a circle and read as a pen. */
    public const CRICKET = '<path d="M3.3 17.9 12.4 8.8l2.8 2.8-9.1 9.1a2 2 0 0 1-2.8 0 2 2 0 0 1 0-2.8z"></path>'
        . '<path d="m13.8 10.2 6.4-6.4M17.4 5.2l1.4 1.4M18.8 3.8l1.4 1.4"></path>'
        . '<circle cx="6.6" cy="6.6" r="2.9"></circle>'
        . '<path d="M4.4 4.9c1.5.5 2.6 1.7 3 3.4"></path>';

    /** Panelled ball: a centre pentagon and the seams running out to the edge. */
    public const FOOTBALL = '<circle cx="12" cy="12" r="9"></circle>'
        . '<path d="M12 8.2 8.6 10.6l1.3 4h4.2l1.3-4z"></path>'
        . '<path d="M12 3v5.2M3.6 9.6l5 1M20.4 9.6l-5 1M7.2 19.6l2.7-5M16.8 19.6l-2.7-5"></path>';

    public const BASKETBALL = '<circle cx="12" cy="12" r="9"></circle>'
        . '<path d="M12 3v18M3 12h18M5.6 5.6a12 12 0 0 1 0 12.8M18.4 5.6a12 12 0 0 0 0 12.8"></path>';

    public const VOLLEYBALL = '<circle cx="12" cy="12" r="9"></circle>'
        . '<path d="M12 12V3M12 12l7.8 4.5M12 12l-7.8 4.5"></path>'
        . '<path d="M7.5 4.2A9 9 0 0 0 12 12M20.8 13.6A9 9 0 0 0 12 12M5.8 18.6A9 9 0 0 0 12 12"></path>';

    /** The court-drawing key (filament/venue/court-pitch) for a sport name, or '' for none. */
    public static function courtKind(string $sport): string
    {
        $s = strtolower($sport);

        return match (true) {
            str_contains($s, 'cricket') => 'cricket',
            str_contains($s, 'football'), str_contains($s, 'soccer'), str_contains($s, 'futsal') => 'football',
            str_contains($s, 'basket') => 'basketball',
            str_contains($s, 'volley') => 'volleyball',
            str_contains($s, 'pickle') => 'pickleball',
            str_contains($s, 'tennis') && ! str_contains($s, 'table') => 'tennis',
            str_contains($s, 'badminton'), str_contains($s, 'shuttle') => 'badminton',
            default => '',
        };
    }

    /** The glyph for a sport name as venues store it ("Cricket", "cricket", "Box cricket"). */
    public static function for(string $sport): string
    {
        $s = strtolower($sport);

        return match (true) {
            str_contains($s, 'cricket') => self::CRICKET,
            str_contains($s, 'football'), str_contains($s, 'soccer'), str_contains($s, 'futsal') => self::FOOTBALL,
            str_contains($s, 'basket') => self::BASKETBALL,
            str_contains($s, 'volley') => self::VOLLEYBALL,
            str_contains($s, 'pickle') => self::PICKLEBALL,
            str_contains($s, 'tennis') => self::TENNIS,
            default => self::BADMINTON,
        };
    }
}
