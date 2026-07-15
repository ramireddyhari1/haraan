@extends('site.layout')

@section('content')
{{-- The web twin of the app's bell inbox. Same rows, same audience targeting —
     composed by the team in the Filament "Notifications" resource. --}}
<div class="notif-page">
    <header class="notif-page__head">
        <h1 class="notif-page__title">Notifications</h1>
        <p class="notif-page__sub">Updates from the Haraan team.</p>
    </header>

    @forelse($notifications as $notification)
        @php($isNew = isset($wasUnread[$notification->id]))
        <{{ $notification->deep_link ? 'a' : 'div' }}
            class="notif-card {{ $isNew ? 'notif-card--new' : '' }}"
            @if($notification->deep_link) href="{{ $notification->deep_link }}" @endif>

            @if($notification->image_url)
                <img class="notif-card__img" src="{{ $notification->image_url }}" alt="">
            @endif

            <div class="notif-card__body">
                <div class="notif-card__top">
                    <h2 class="notif-card__title">{{ $notification->title }}</h2>
                    @if($isNew)<span class="notif-card__new">New</span>@endif
                </div>
                <p class="notif-card__text">{{ $notification->body }}</p>
                <time class="notif-card__time">{{ ($notification->sent_at ?? $notification->created_at)?->diffForHumans() }}</time>
            </div>
        </{{ $notification->deep_link ? 'a' : 'div' }}>
    @empty
        <div class="notif-empty">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <h2>Nothing yet</h2>
            <p>Match updates and announcements from the team will show up here.</p>
        </div>
    @endforelse
</div>
@endsection
