{{-- Real-time refresh for the /control admin panel (Reverb). Injected into every panel page
     via a HEAD_END render hook. No-op unless BROADCAST_CONNECTION=reverb — mirrors the public
     site's wiring (resources/views/site/layout.blade.php) so both share one config source. --}}
@php($reverb = config('broadcasting.connections.reverb'))
<script>
    window.HaraanRealtime = {
        enabled: {{ config('broadcasting.default') === 'reverb' ? 'true' : 'false' }},
        key: @json($reverb['key'] ?? null),
        host: @json($reverb['options']['host'] ?? null),
        port: {{ (int) ($reverb['options']['port'] ?? 443) }},
        scheme: @json($reverb['options']['scheme'] ?? 'https'),
        channel: 'content',
    };
</script>
@if(config('broadcasting.default') === 'reverb')
    <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
    <script src="{{ asset('js/filament-realtime.js') }}?v={{ @filemtime(public_path('js/filament-realtime.js')) ?: 1 }}"></script>
@endif
