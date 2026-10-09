<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Venue;
use Illuminate\Support\HtmlString;

/**
 * The venue form as a sequence of steps, each with a drawn icon and a done/missing state
 * read from the saved venue. "Required" mirrors {@see Venue::readinessErrors()} exactly, so
 * the journey can never say "ready" while publishing would refuse.
 */
final class VenueSetupSteps
{
    /** key => [label, anchor id, required?] in the order the form shows them. */
    public const STEPS = [
        'basics' => ['Basics', 'vf-basics', true],
        'location' => ['Location', 'vf-location', true],
        'hours' => ['Hours', 'vf-hours', true],
        'courts' => ['Courts', null, true],          // lives in the Courts & slots tab below
        'pricing' => ['Pricing', 'vf-pricing', true],
        'photos' => ['Photos', 'vf-photos', true],
        'extras' => ['Amenities & rules', 'vf-extras', false],
        'live' => ['Go live', 'vf-live', false],
    ];

    /**
     * @return list<array{key:string,n:int,label:string,anchor:?string,required:bool,done:bool,hint:string}>
     */
    public static function forVenue(?Venue $v): array
    {
        $images = $v && is_array($v->images) ? array_filter($v->images, fn ($i) => trim((string) $i) !== '') : [];
        $hours = $v && is_array($v->hours_json) ? array_filter($v->hours_json) : [];
        $activeCourts = $v ? $v->courts()->where('is_active', true)->count() : 0;
        $slots = $v ? $v->slots()->count() : 0;
        $courtPriced = $v ? $v->courts()->where('is_active', true)->where('price', '>', 0)->exists() : false;
        $amenities = $v && is_array($v->amenities) ? array_filter($v->amenities) : [];
        $rules = $v && is_array($v->rules ?? null) ? array_filter($v->rules) : [];

        $state = [
            'basics' => [$v && trim((string) $v->name) !== '', 'Add the venue name'],
            'location' => [$v && trim((string) $v->location) !== '' && trim((string) $v->city) !== '', 'Add the area and city'],
            'hours' => [$slots > 0, $hours === [] ? 'Add opening hours' : 'Save to create slots'],
            'courts' => [$activeCourts > 0, 'Add a court in the tab below'],
            'pricing' => [($v && (int) $v->price > 0) || $courtPriced, 'Set a price above ₹0'],
            'photos' => [$images !== [], 'Upload at least one photo'],
            'extras' => [$amenities !== [] || $rules !== [], 'Optional — helps players choose'],
        ];
        $ready = $state['basics'][0] && $state['location'][0] && $state['hours'][0] && $state['courts'][0] && $state['pricing'][0] && $state['photos'][0];
        $state['live'] = [self::isLive($v), $ready ? self::goLiveHint() : 'Finish the steps above first'];

        $out = [];
        $n = 1;
        foreach (self::STEPS as $key => [$label, $anchor, $required]) {
            [$done, $hint] = $state[$key];
            $out[] = [
                'key' => $key,
                'n' => $n++,
                'label' => $label,
                'anchor' => $anchor,
                'required' => $required,
                'done' => (bool) $done,
                'hint' => $done ? self::doneNote($key, $v, $activeCourts, $slots, count($images)) : $hint,
            ];
        }

        return $out;
    }

    /**
     * Live = visible and taking bookings. Newer trees have one Status (Venue::visibilityState);
     * older ones only the two switches, so this reads whichever the running code has.
     */
    public static function isLive(?Venue $v): bool
    {
        if ($v === null) {
            return false;
        }
        if (method_exists($v, 'visibilityState')) {
            return $v->visibilityState() === 'live';
        }

        return (bool) $v->is_active && (bool) $v->is_bookable;
    }

    /** Visible but not taking new bookings. */
    public static function isPaused(?Venue $v): bool
    {
        if ($v === null) {
            return false;
        }
        if (method_exists($v, 'visibilityState')) {
            return $v->visibilityState() === 'paused';
        }

        return (bool) $v->is_active && ! (bool) $v->is_bookable;
    }

    /** What the last step asks for, in the words of the form that is actually running. */
    public static function goLiveHint(): string
    {
        return method_exists(Venue::class, 'visibilityState') ? 'Set Status to Live' : 'Turn on Active and Open for booking';
    }

    private static function doneNote(string $key, ?Venue $v, int $courts, int $slots, int $photos): string
    {
        return match ($key) {
            'location' => (string) $v?->city,
            'hours' => $slots.' '.($slots === 1 ? 'slot' : 'slots'),
            'courts' => $courts.' active',
            'pricing' => (int) $v?->price > 0 ? 'from ₹'.number_format((int) $v->price).'/hr' : 'court rates',
            'photos' => $photos.' '.($photos === 1 ? 'photo' : 'photos'),
            'live' => 'Taking bookings',
            default => 'Done',
        };
    }

    /** Section heading with its step number, e.g. "① Basics". */
    public static function heading(string $key, ?string $label = null): HtmlString
    {
        $keys = array_keys(self::STEPS);
        $n = array_search($key, $keys, true) + 1;

        return new HtmlString('<span class="vf-num">'.$n.'</span>'.e($label ?? self::STEPS[$key][0]));
    }

    /** The step's drawn icon as inline SVG (24×24, one stroke weight, currentColor). */
    public static function icon(string $key, int $size = 20): HtmlString
    {
        $paths = match ($key) {
            // A signboard on a post
            'basics' => '<rect x="3.5" y="4" width="17" height="10" rx="2"/><path d="M7 8h7M7 11h4.5"/><path d="M12 14v6.5M9 20.5h6"/>',
            // Pin on a folded map
            'location' => '<path d="M3 7l5.5-2.5 7 2.5L21 4.5v12.5L15.5 19.5l-7-2.5L3 19.5z"/><path d="M8.5 4.5v12.5M15.5 7v12.5" opacity=".45"/><path d="M12 13.2s3-2.6 3-5a3 3 0 0 0-6 0c0 2.4 3 5 3 5z" fill="currentColor" fill-opacity=".12"/>',
            // Clock
            'hours' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.2V12l3.2 2"/><path d="M12 3.5v1.2M20.5 12h-1.2M12 20.5v-1.2M3.5 12h1.2" opacity=".5"/>',
            // A court seen from above
            'courts' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M12 5v14"/><circle cx="12" cy="12" r="2.6"/><path d="M3 9h3v6H3M21 9h-3v6h3"/>',
            // Price tag with ₹
            'pricing' => '<path d="M3.5 12.2V4.5a1 1 0 0 1 1-1h7.7l8.3 8.3a1.5 1.5 0 0 1 0 2.1l-6.2 6.2a1.5 1.5 0 0 1-2.1 0z"/><circle cx="8" cy="8" r="1.4"/><path d="M11.5 11.5h4M11.5 13.3h4M13 11.5c1.6 0 1.6 3.6 0 3.6h-1.5l3.2 3"/>',
            // Photo with a hill and sun
            'photos' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M3.5 17l5-4.5 3.5 3 3-2.5 5.5 4.5"/>',
            // Clipboard with ticks
            'extras' => '<rect x="5" y="4.5" width="14" height="16" rx="2"/><path d="M9 3.5h6v2.5H9z"/><path d="M8 11l1.4 1.4L12 9.8M8 16l1.4 1.4L12 14.8M14 11h2.5M14 16h2.5"/>',
            // Flag on a pole
            'live' => '<path d="M5 21V4"/><path d="M5 4.5c4-2 6 2 10 0s4 0 4 0v8.5s-1-2-4 0-6-2-10 0"/>',
            default => '<circle cx="12" cy="12" r="8"/>',
        };

        return new HtmlString('<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>');
    }

    /** Amenity tile label: a drawn icon and the name (the stored value stays the plain name). */
    public static function amenityLabel(string $name): string
    {
        $paths = match ($name) {
            'Parking' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M10 16V8h3a2.5 2.5 0 0 1 0 5h-3"/>',
            'Washroom' => '<circle cx="8" cy="6" r="1.6"/><circle cx="16" cy="6" r="1.6"/><path d="M8 9v11M6 10h4l-1 5M16 9l-2.5 6h5L16 9zM15 15v5M17 15v5"/><path d="M12 4v16" opacity=".4"/>',
            'Shower' => '<path d="M5 20V8a4 4 0 0 1 8 0"/><path d="M10 8h6"/><path d="M11 12v1M13 12v1M15 12v1M11 15v1M13 15v1M15 15v1M12 18v1M14 18v1"/>',
            'Changing room' => '<path d="M12 6.5a1.6 1.6 0 1 1 1.6 1.6c-.9 0-1.6.7-1.6 1.6v.3"/><path d="M12 10l8 5.5a1 1 0 0 1-.6 1.8H4.6a1 1 0 0 1-.6-1.8z"/>',
            'Café' => '<path d="M5 9h11v5a5 5 0 0 1-5 5h-1a5 5 0 0 1-5-5z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M9 4.5c0 1 1 1 1 2M12.5 4.5c0 1 1 1 1 2"/>',
            'Restaurant' => '<path d="M7 3v7a2 2 0 0 0 2 2v9M11 3v7a2 2 0 0 1-2 2M9 3v6"/><path d="M17 21V3c-2 1.5-3 4-3 7h3"/>',
            'Drinking water' => '<path d="M12 3.5s-5.5 6-5.5 10a5.5 5.5 0 0 0 11 0c0-4-5.5-10-5.5-10z"/><path d="M9.5 14a2.5 2.5 0 0 0 2.5 2.5"/>',
            'Floodlights' => '<path d="M12 21V11"/><rect x="7" y="4" width="10" height="7" rx="1.5"/><path d="M9 7h1M12 7h0M14 7h1"/><path d="M4 13l2-1M20 13l-2-1M9 21h6"/>',
            'AC' => '<rect x="3" y="5" width="18" height="7" rx="2"/><path d="M6 9.5h12"/><path d="M8 15c0 1.5-1 2-1 3M12 15c0 1.5-1 2-1 3M16 15c0 1.5-1 2-1 3"/>',
            'WiFi' => '<path d="M3.5 9.5a12 12 0 0 1 17 0M6.5 12.5a8 8 0 0 1 11 0M9.5 15.5a4 4 0 0 1 5 0"/><circle cx="12" cy="18.5" r="1"/>',
            'CCTV / Security' => '<path d="M3 8l13-3 1.5 5L4.5 13z"/><path d="M15 10.5l1 3.5h3M19 11v6"/><path d="M6.5 12.5L8 17"/>',
            'Seating' => '<path d="M6 11V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v5"/><rect x="4" y="11" width="16" height="4" rx="1.5"/><path d="M6 15v5M18 15v5"/>',
            'Equipment rental' => '<circle cx="8" cy="8" r="4.5"/><path d="M11.3 11.3L20 20M18 20h2v-2"/><path d="M5 6l6 4M4.5 9l5-4" opacity=".5"/>',
            default => '<circle cx="12" cy="12" r="7"/>',
        };

        return '<span class="vf-am"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg><span>'.e($name).'</span></span>';
    }
}
