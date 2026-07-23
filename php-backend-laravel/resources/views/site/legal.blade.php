@extends('site.layout')

@section('content')
{{-- Terms / Privacy Policy — the web twin of the app's LegalScreen. Body is
     admin-authored plain text; paragraphs are split on blank lines (the app does
     the same rather than shipping a markdown renderer), so both render alike. --}}
<div class="aprof aprof-doc">
    <h1 class="aprof-doc__title">{{ $doc->title }}</h1>
    @if($doc->updated_at)
        <p class="aprof-doc__meta">Updated {{ $doc->updated_at->format('j M Y') }}</p>
    @endif

    <div class="aprof-card aprof-doc__body">
        @forelse($paragraphs as $para)
            <p>{{ $para }}</p>
        @empty
            <p class="aprof-doc__empty">This document hasn't been published yet.</p>
        @endforelse
    </div>

    <p class="aprof-doc__back"><a href="{{ url()->previous() === url()->current() ? route('site.profile') : url()->previous() }}">← Back</a></p>
</div>
@endsection
