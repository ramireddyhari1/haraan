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
}
