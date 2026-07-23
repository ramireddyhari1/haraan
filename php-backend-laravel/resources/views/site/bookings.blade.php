@extends('site.layout')

@section('content')
{{-- My bookings — the Tickets lane's destination, the web twin of the app's
     MyBookingsScreen. Buckets are time-based (and cancellation), NEVER payment
     status: a confirmed gig that happened last month is Past, not Active. --}}
@php
    $sections = [
        ['title' => 'Upcoming', 'rows' => $upcoming, 'passable' => true],
        ['title' => 'Past', 'rows' => $past, 'passable' => true],
        // A cancelled or refunded booking must never open an entry pass: the pass
        // renders a scannable QR, and handing one to a refunded customer reads as a
        // valid ticket. Cancelled rows are shown, but they're not doors.
        ['title' => 'Cancelled', 'rows' => $cancelled, 'passable' => false],
    ];
    $total = $upcoming->count() + $past->count() + $cancelled->count();
@endphp

<div class="aprof">
    <h1 class="aprof-doc__title">My bookings</h1>

    @if($total === 0)
        <div class="aprof-card aprof-empty">
            <p><b>No bookings yet</b></p>
            <p>Tickets you book turn up here — event passes and turf slots alike.</p>
            <a class="btn btn--solid" href="{{ url('/events') }}">Browse events</a>
        </div>
    @else
        @foreach($sections as $section)
            @continue($section['rows']->isEmpty())
            <h2 class="aprof-heading">{{ $section['title'] }}</h2>
            <div class="aprof-card">
                @foreach($section['rows'] as $i => $b)
                    @if($i > 0)<i class="aprof-hr" style="margin-left:16px"></i>@endif
                    @php
                        $isVenue = filled($b->venue_id);
                        $heading = $isVenue
                            ? ($b->venue?->name ?? 'Venue booking')
                            : ($b->event?->title ?? 'Event booking');
                        $when = $b->slot_date?->format('D, j M Y') ?? $b->event?->date?->format('D, j M Y');
                        $sub = trim(implode(' · ', array_filter([$when, $b->slot_label])));
                        // Only an event booking with a live event has a pass to open —
                        // the pass page is event-only and 404s on anything else. Rows
                        // without one stay plain <div>s rather than links to a dead end.
                        $hasPass = $section['passable'] && ! $isVenue && $b->event !== null;
                    @endphp
                    <{{ $hasPass ? 'a' : 'div' }} class="aprof-booking"
                        @if($hasPass) href="{{ route('site.booking.pass', ['id' => $b->id]) }}" @endif>
                        <span class="aprof-booking__icon {{ $isVenue ? 'is-venue' : 'is-event' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5v2m0 4v2m0 4v2M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7a2 2 0 0 1 2-2z"></path></svg>
                        </span>
                        <span class="aprof-booking__text">
                            <b>{{ $heading }}</b>
                            <small>{{ $sub ?: 'Date to be announced' }}</small>
                        </span>
                        <span class="aprof-booking__status">{{ ucfirst(mb_strtolower((string) $b->status)) }}</span>
                    </{{ $hasPass ? 'a' : 'div' }}>
                @endforeach
            </div>
        @endforeach
    @endif

    <p class="aprof-doc__back"><a href="{{ route('site.profile') }}">← Account</a></p>
</div>
@endsection
