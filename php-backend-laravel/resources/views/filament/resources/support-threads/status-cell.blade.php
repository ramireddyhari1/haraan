@php
    $s = (string) $getState();
    $label = match ($s) { 'open' => 'Open', 'pending' => 'Pending', 'closed' => 'Closed', default => ucfirst($s) };
@endphp
<span class="stx-status stx-status--{{ $s }}"><i></i>{{ $label }}</span>
