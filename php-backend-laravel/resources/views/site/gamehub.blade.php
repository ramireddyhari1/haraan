@extends('site.layout')
@section('footer_icon_secondary', '#16a34a')
@section('content')

<section class="page-shell gamehub-page theme-gamehub">
    <div class="gamehub-actions">
        <a href="/gamehub/actionboard" class="gamehub-action-card active thanna-trigger">
            <div class="action-card__icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="action-card__svg">
                    <path d="M21 7.5V16.5M21 7.5L12 3L3 7.5M21 7.5L12 12M3 7.5V16.5M3 7.5L12 12M12 12V21M12 21L21 16.5M12 21L3 16.5" />
                    <polygon points="12 4.5 8 9.5 11.5 9.5 10.5 14.5 15.5 9.5 12 9.5 13 4.5" fill="currentColor" stroke="none" />
                </svg>
            </div>
            <div class="action-card__content">
                <span class="action-card__title">ActionBoard</span>
                <span class="action-card__desc">Join active open sessions</span>
            </div>
            <span class="action-card__badge">Live Match</span>
        </a>
        <a href="#featured-venues" class="gamehub-action-card">
            <div class="action-card__icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="action-card__svg">
                    <line x1="5" y1="18" x2="5" y2="7" />
                    <line x1="9" y1="18" x2="9" y2="7" />
                    <line x1="13" y1="18" x2="13" y2="7" />
                    <line x1="4" y1="7" x2="14" y2="7" />
                    <path d="M19.5 3.5l1 1-11.5 11.5-2.5-2.5 13-10z" />
                    <path d="M6.5 13.5l-3 3c-.7.7-.7 1.8 0 2.5s1.8.7 2.5 0l3-3" />
                    <circle cx="19" cy="18" r="3" />
                    <path d="M17 17a3 3 0 0 1 4 2" />
                </svg>
            </div>
            <div class="action-card__content">
                <span class="action-card__title">Reserve Turf</span>
                <span class="action-card__desc">Book secure hourly slots</span>
            </div>
        </a>
        <a href="#all-sports" class="gamehub-action-card">
            <div class="action-card__icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="action-card__svg">
                    <ellipse cx="12" cy="10" rx="9" ry="5.5" />
                    <ellipse cx="12" cy="10" rx="6.2" ry="3.8" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <circle cx="12" cy="10" r="1.2" />
                    <path d="M3 10v6c0 3 4 5.5 9 5.5s9-2.5 9-5.5v-6" />
                    <line x1="7.5" y1="14.2" x2="7.5" y2="19.5" />
                    <line x1="12" y1="15.5" x2="12" y2="21.5" />
                    <line x1="16.5" y1="14.2" x2="16.5" y2="19.5" />
                </svg>
            </div>
            <div class="action-card__content">
                <span class="action-card__title">Explore Arenas</span>
                <span class="action-card__desc">Find premier sports grounds</span>
            </div>
        </a>
    </div>

    <section class="gamehub-hero">
        <div class="gamehub-hero__copy">
            <h1>Venues in {{ $selectedCity ?? 'All India' }}</h1>
            <p>Discover and book premium sports facilities curated for champions.</p>

            <div class="search-strip search-strip--gamehub">
                <label class="search-field">
                    <span>⌕</span>
                    <input type="text" id="venueSearchInput" value="" placeholder="Search by name or sport...">
                </label>
                <button class="filter-button filter-button--dropdown" type="button" id="venueClearFilter">All Sports</button>
            </div>
        </div>

        <div class="gamehub-hero__art">
            <img src="{{ asset('gamehub.png') }}" alt="GameHub artwork">
        </div>
    </section>

    <section class="events-section" id="all-sports">
        <div class="section-shell__header">
            <div>
                <p class="eyebrow eyebrow--soft">Explore by Sport</p>
                <h2>Find specialized facilities curated for your discipline.</h2>
            </div>
        </div>

        @php
            $sportTiles = [
                ['name' => 'Cricket', 'icon' => 'https://cdn-icons-png.flaticon.com/512/5140/5140374.png', 'class' => ''],
                ['name' => 'Football', 'icon' => 'https://cdn-icons-png.flaticon.com/512/7711/7711842.png', 'class' => 'sport-card--football'],
                ['name' => 'Badminton', 'icon' => 'https://cdn-icons-png.flaticon.com/512/10904/10904303.png', 'class' => ''],
                ['name' => 'Swimming', 'icon' => 'https://cdn-icons-png.flaticon.com/512/8317/8317259.png', 'class' => ''],
                ['name' => 'Tennis', 'icon' => 'https://cdn-icons-png.flaticon.com/512/8927/8927653.png', 'class' => ''],
                ['name' => 'Basketball', 'icon' => 'https://cdn-icons-png.flaticon.com/512/9128/9128501.png', 'class' => ''],
            ];
        @endphp
        <div class="sport-grid">
            @foreach($sportTiles as $tile)
                @php $count = (int) (($sportCounts ?? collect())[$tile['name']] ?? 0); @endphp
                <button class="sport-card {{ $tile['class'] }}" type="button" data-sport="{{ $tile['name'] }}">
                    <span class="sport-card__icon" aria-hidden="true">
                        <img src="{{ $tile['icon'] }}" alt="{{ $tile['name'] }} icon" />
                    </span>
                    <span>{{ $tile['name'] }}</span><small>{{ $count }} {{ $count === 1 ? 'venue' : 'venues' }}</small>
                </button>
            @endforeach
        </div>
    </section>

    <section class="events-section" id="featured-venues">
        <div class="section-shell__header">
            <div>
                <p class="eyebrow eyebrow--soft">Featured Venues</p>
                <h2>Top-rated facilities for your perfect game.</h2>
            </div>
            <a href="#all-venues" class="text-link">View all</a>
        </div>

        @if(isset($venues) && count($venues))
            @php
                $sportIcons = [
                    'Cricket' => 'https://cdn-icons-png.flaticon.com/512/5140/5140374.png',
                    'Football' => 'https://cdn-icons-png.flaticon.com/512/7711/7711842.png',
                    'Badminton' => 'https://cdn-icons-png.flaticon.com/512/10904/10904303.png',
                    'Swimming' => 'https://cdn-icons-png.flaticon.com/512/8317/8317259.png',
                    'Tennis' => 'https://cdn-icons-png.flaticon.com/512/8927/8927653.png',
                    'Basketball' => 'https://cdn-icons-png.flaticon.com/512/9128/9128501.png',
                ];
            @endphp
            <div class="events-grid gamehub-venues-grid">
                @foreach($venues as $venue)
                    <a class="event-card gamehub-venue-card" href="/gamehub/{{ $venue->id }}" data-venue-name="{{ \Illuminate\Support\Str::lower($venue->title) }}" data-venue-category="{{ \Illuminate\Support\Str::lower($venue->category) }}">
                        <div class="event-card__thumb">
                            <img src="{{ $venue->image }}" alt="{{ $venue->title }}" />
                            <div class="event-card__thumb-overlay">
                                <div class="event-card__thumb-text">
                                    <div class="event-card__thumb-venue">{{ $venue->location }}</div>
                                    <h3 class="event-card__thumb-title">{{ $venue->title }}</h3>
                                </div>
                            </div>
                            @if(isset($venue->badge))
                                <span class="venue-badge">{{ $venue->badge }}</span>
                            @endif
                        </div>
                        <div class="event-card__body gamehub-venue-card__body">
                            <!-- Category & Rating Row -->
                            <div class="event-card__meta gamehub-venue-card__meta">
                                <span class="event-card__category gamehub-venue-card__category" style="display: flex; align-items: center; gap: 6px;">
                                    @if(isset($sportIcons[$venue->category]))
                                        <img src="{{ $sportIcons[$venue->category] }}" alt="{{ $venue->category }} icon" style="width: 16px; height: 16px; object-fit: contain;" />
                                    @endif
                                    {{ $venue->category }}
                                </span>
                                @if($venue->reviews > 0)
                                    <span class="event-card__rating gamehub-venue-card__rating">
                                        <span class="gamehub-venue-card__star">★</span>
                                        <span class="gamehub-venue-card__rating-value">{{ $venue->rating }}</span>
                                        <span class="gamehub-venue-card__reviews-count">({{ $venue->reviews }})</span>
                                    </span>
                                @else
                                    <span class="event-card__rating gamehub-venue-card__rating gamehub-venue-card__rating--new">New</span>
                                @endif
                            </div>
                            
                            <!-- Price & Action Button Row -->
                            <div class="event-card__actions gamehub-venue-card__actions">
                                <span class="event-card__price gamehub-venue-card__price">
                                    ₹{{ number_format($venue->price) }}/hr
                                </span>
                                <span class="btn btn--solid event-card__btn gamehub-venue-card__btn">
                                    Book Slot
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <p>No facilities found in this area</p>
                <button class="filter-button" type="button">Clear all filters</button>
            </div>
        @endif
    </section>

    <a class="promo-banner" href="#all-venues">
        <img src="{{ asset('gamehub.png') }}" alt="Promo banner">
        <div class="promo-banner__content">
            <span class="eyebrow eyebrow--soft">Limited Access</span>
            <h2>Unlock the Best Arenas in the City</h2>
            <p>Book elite facilities with professional equipment and instant digital scheduling.</p>
            <span class="btn btn--solid">Start Booking</span>
        </div>
    </a>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.gamehub-action-card');
    cards.forEach(card => {
        card.addEventListener('click', (e) => {
            cards.forEach(c => c.classList.remove('active'));
            card.classList.add('active');
        });
    });

    /* ---- Venue filtering: search box + sport tiles ---- */
    const searchInput = document.getElementById('venueSearchInput');
    const clearBtn    = document.getElementById('venueClearFilter');
    const sportTiles  = document.querySelectorAll('.sport-card[data-sport]');
    const venueCards  = Array.from(document.querySelectorAll('.gamehub-venue-card'));
    const venuesGrid  = document.querySelector('.gamehub-venues-grid');

    let activeSport = null;

    // Empty-message element shown when nothing matches.
    let emptyMsg = null;
    if (venuesGrid) {
        emptyMsg = document.createElement('div');
        emptyMsg.className = 'empty-state';
        emptyMsg.style.display = 'none';
        emptyMsg.innerHTML = '<p>No venues match your filters</p>';
        venuesGrid.parentNode.insertBefore(emptyMsg, venuesGrid.nextSibling);
    }

    function applyFilters() {
        const q = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;

        venueCards.forEach(card => {
            const name = card.getAttribute('data-venue-name') || '';
            const cat  = card.getAttribute('data-venue-category') || '';
            const matchesText  = !q || name.includes(q) || cat.includes(q);
            const matchesSport = !activeSport || cat === activeSport.toLowerCase();
            const show = matchesText && matchesSport;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (emptyMsg) emptyMsg.style.display = visible === 0 ? 'block' : 'none';
        if (venuesGrid) venuesGrid.style.display = visible === 0 ? 'none' : '';
    }

    searchInput?.addEventListener('input', applyFilters);

    sportTiles.forEach(tile => {
        tile.addEventListener('click', () => {
            const sport = tile.getAttribute('data-sport');
            if (activeSport === sport) {
                activeSport = null;
                tile.classList.remove('is-active');
            } else {
                activeSport = sport;
                sportTiles.forEach(t => t.classList.remove('is-active'));
                tile.classList.add('is-active');
            }
            applyFilters();
            document.getElementById('featured-venues')?.scrollIntoView({ behavior: 'smooth' });
        });
    });

    clearBtn?.addEventListener('click', () => {
        activeSport = null;
        if (searchInput) searchInput.value = '';
        sportTiles.forEach(t => t.classList.remove('is-active'));
        applyFilters();
    });
});
</script>
@endsection

@include('site.partials.thanna-actionboard')
