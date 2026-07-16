@extends('site.layout')

@section('content')
{{-- Privacy — the web twin of the app's PrivacySettingsScreen. Same four toggles,
     same copy, same two groups. The app saves per-toggle over the API; the web
     posts the form, so there's an explicit Save. --}}
<div class="aprof">
    <h1 class="aprof-doc__title">Privacy</h1>
    <p class="aprof-doc__lede">Haraan is a public leaderboard. These controls decide how much of your play other people can see.</p>

    @if(session('success'))
        <div class="aprof-flash">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('site.account.privacy.save') }}">
        @csrf

        <h2 class="aprof-heading">Your profile</h2>
        <div class="aprof-card">
            @include('site.partials.privacy-toggle', [
                'name' => 'privacy_public_profile',
                'title' => 'Public profile',
                'description' => 'Anyone can open your player profile from a match or a leaderboard.',
                'checked' => (bool) $user->privacy_public_profile,
            ])
            <i class="aprof-hr" style="margin-left:16px"></i>
            @include('site.partials.privacy-toggle', [
                'name' => 'privacy_show_stats',
                'title' => 'Show career stats',
                'description' => 'Your runs, wickets and matches appear on your profile.',
                'checked' => (bool) $user->privacy_show_stats,
            ])
            <i class="aprof-hr" style="margin-left:16px"></i>
            @include('site.partials.privacy-toggle', [
                'name' => 'privacy_show_district',
                'title' => 'Show my district',
                'description' => 'Your district and state are shown next to your name.',
                'checked' => (bool) $user->privacy_show_district,
            ])
        </div>

        <h2 class="aprof-heading">Discovery</h2>
        <div class="aprof-card">
            @include('site.partials.privacy-toggle', [
                'name' => 'privacy_discoverable',
                'title' => 'Findable in search',
                'description' => 'Other players can find you by name or Member ID to add you to a squad.',
                'checked' => (bool) $user->privacy_discoverable,
            ])
        </div>

        <button type="submit" class="btn btn--solid btn--full btn--large aprof-save">Save</button>
    </form>

    <p class="aprof-doc__back"><a href="{{ route('site.profile') }}">← Account</a></p>
</div>
@endsection
