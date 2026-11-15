@php
    $a = $getRecord()->assignee;
@endphp
@if ($a)
    @php
        $src = \App\Support\MediaUrl::resolve($a->avatar);
        $src = ($src !== null && $src !== '') ? $src : \App\Filament\Support\AvatarColumn::initials((string) $a->name);
    @endphp
    <span class="stx-owner">
        <img src="{{ $src }}" alt="" loading="lazy">
        <span>{{ $a->id === auth()->id() ? 'You' : $a->name }}</span>
    </span>
@else
    <span class="stx-owner stx-owner--none">Unassigned</span>
@endif
