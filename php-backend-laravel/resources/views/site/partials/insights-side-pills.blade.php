{{-- Per-side switch for a cricket Insights section; the last innings shows first, as in the app. --}}
@if(count($innings) > 1)
    <div class="ins-pills" data-group="{{ $group }}">
        @foreach($innings as $k => $inn)
            <button class="{{ $k === count($innings) - 1 ? 'is-on' : '' }}" data-i="{{ $k }}">{{ $sideName($inn) }}</button>
        @endforeach
    </div>
@endif
