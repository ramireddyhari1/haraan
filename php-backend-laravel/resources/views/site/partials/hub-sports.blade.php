{{-- The app's VenueSportsIcons: the sports playable at a venue, as up to two bare
     glyphs then a "+N". De-duped by glyph, so a turf offering both badminton and
     tennis (one racket icon) shows one icon, not the same one twice. --}}
@php
    $hubShown = collect($sports ?? [])
        ->filter()
        ->unique(fn ($s) => $glyphKey($s))
        ->values();
    $hubExtra = max(0, $hubShown->count() - 2);
    $hubShown = $hubShown->take(2);
@endphp
@if($hubShown->count())
    <span class="mhub__sports">
        @foreach($hubShown as $sport)
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="{{ $sport }}">{!! $icons[$glyphKey($sport)] !!}</svg>
        @endforeach
        @if($hubExtra > 0)<i>+{{ $hubExtra }}</i>@endif
    </span>
@endif
