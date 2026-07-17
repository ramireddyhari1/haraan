@props(['event'])

<article class="event-card">
    <a href="/events/{{ $event->id }}" class="event-card__link">
        <div class="event-card__thumb">
            @php $img = $event->heroImageUrl() ?? '/bv-white.png'; @endphp
            <img src="{{ $img }}" alt="{{ $event->title }}" />
            <div class="event-card__thumb-overlay">
                <div class="event-card__thumb-text">
                    <div class="event-card__thumb-venue">{{ $event->venue }}</div>
                    <h3 class="event-card__thumb-title">{{ $event->title }}</h3>
                </div>
            </div>
        </div>
        <div class="event-card__body">
            <div class="event-card__meta">
                <span class="event-card__category">{{ $event->category ?? 'Event' }}</span>
                <div class="event-card__actions">
                    <span class="event-card__price">{{ $event->price ? '₹'.number_format($event->price) : 'Free' }}</span>
                    <button class="btn btn--solid event-card__btn">Reserve Now</button>
                </div>
            </div>
            <div class="event-card__details">
                <time class="event-card__date">{{ optional($event->date)->format('D, M j, Y \@ g:i A') }}</time>
            </div>
        </div>
    </a>
</article>
