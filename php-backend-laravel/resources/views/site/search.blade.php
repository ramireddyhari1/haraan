@extends('site.layout')
@section('content')
<section class="search-results-page">
    <div class="search-header">
        <h1>Search Results</h1>
        @if($query)
            <p class="search-query">Results for "<strong>{{ $query }}</strong>"</p>
        @else
            <p class="search-empty">Enter a search term to find events and venues</p>
        @endif
    </div>

    @if($query)
        <div class="search-filters">
            <form method="GET" action="/search" class="filter-form">
                <input type="hidden" name="q" value="{{ $query }}">
                <button type="submit" name="type" value="all" class="filter-btn {{ $type === 'all' ? 'is-active' : '' }}">All Results</button>
                <button type="submit" name="type" value="events" class="filter-btn {{ $type === 'events' ? 'is-active' : '' }}">Events</button>
                <button type="submit" name="type" value="venues" class="filter-btn {{ $type === 'venues' ? 'is-active' : '' }}">Venues</button>
            </form>
        </div>

        @if(empty($results))
            <div class="no-results">
                <p>No results found for "<strong>{{ $query }}</strong>"</p>
                <p class="help-text">Try different keywords or browse our featured events and venues</p>
            </div>
        @else
            @if(isset($results['events']) && !empty($results['events']))
                <section class="results-section">
                    <h2 class="results-title">Events</h2>
                    <div class="results-grid">
                        @foreach($results['events'] as $event)
                            <a href="/events/{{ $event['id'] }}" class="result-card result-card--event">
                                <div class="result-card__media">
                                    <span class="result-badge">{{ $event['category'] }}</span>
                                </div>
                                <div class="result-card__content">
                                    <h3>{{ $event['title'] }}</h3>
                                    <p class="result-venue">📍 {{ $event['venue'] }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if(isset($results['venues']) && !empty($results['venues']))
                <section class="results-section">
                    <h2 class="results-title">Venues & Facilities</h2>
                    <div class="results-grid">
                        @foreach($results['venues'] as $venue)
                            <a href="/gamehub/{{ $venue['id'] }}" class="result-card result-card--venue">
                                <div class="result-card__media">
                                    <span class="result-badge result-badge--green">{{ $venue['sport'] }}</span>
                                </div>
                                <div class="result-card__content">
                                    <h3>{{ $venue['title'] }}</h3>
                                    <p class="result-venue">📍 {{ $venue['location'] }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    @else
        <div class="empty-state">
            <p>Use the search bar to find events and sports venues</p>
        </div>
    @endif
</section>
@endsection
