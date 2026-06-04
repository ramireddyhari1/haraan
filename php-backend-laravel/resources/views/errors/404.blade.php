@extends('site.layout')

@section('content')
<div style="min-height:60vh; display:flex; align-items:center; justify-content:center; padding:40px;">
    <div style="max-width:900px; width:100%; display:grid; grid-template-columns: 1fr 1fr; gap:20px; align-items:center;">
        <div>
            <h1 style="font-size:48px; margin:0 0 8px;">Page not found</h1>
            <p style="color:var(--muted); margin:0 0 16px;">We couldn't find the page you're looking for.</p>
            <a href="/" class="btn btn--ghost">Go home</a>
            <a href="/events" class="btn btn--solid" style="margin-left:8px">Browse events</a>
        </div>

        <div>
            <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
            <lottie-player src="/animations/404.json" background="transparent" speed="1" style="width:100%; height:320px;" loop autoplay></lottie-player>
        </div>
    </div>
</div>
@endsection
