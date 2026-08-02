@extends('site.layout')

@section('content')
{{-- Landing page for the emailed confirmation link. Reached signed out, by someone
     who may no longer have the app, so it links back to nothing that needs auth. --}}
<div class="aprof aprof-doc">
    <h1 class="aprof-doc__title">{{ $title }}</h1>

    <div class="aprof-card aprof-doc__body">
        <p>{{ $message }}</p>

        @if($ok)
            <p>Your personal data has been removed and all notifications have stopped.
               Records of past paid bookings are retained for tax purposes only and are
               no longer linked to you — see
               <a href="{{ route('site.account.delete') }}">what we keep and for how long</a>.</p>
            <p>Thanks for having played with us.</p>
        @else
            <p>You can <a href="{{ route('site.account.delete') }}">start a new deletion
               request</a>, or email <a href="mailto:support@haraan.app">support@haraan.app</a>
               if you'd rather we handled it.</p>
        @endif
    </div>

    <p class="aprof-doc__back"><a href="{{ url('/') }}">← Haraan</a></p>
</div>
@endsection
