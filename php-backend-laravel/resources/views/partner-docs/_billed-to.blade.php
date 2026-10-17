<div class="party">
    <h3>{{ $label ?? 'Billed to' }}</h3>
    <p>
        <strong>{{ $partner['legal_name'] ?: ($partner['name'] ?? 'Partner') }}</strong><br>
        @if (! empty($partner['address'])){!! nl2br(e($partner['address'])) !!}<br>@endif
        @if (! empty($partner['gstin']))GSTIN {{ $partner['gstin'] }}<br>@endif
        @if (! empty($partner['pan']))PAN {{ $partner['pan'] }}<br>@else PAN not on file<br>@endif
        @if (! empty($partner['email'])){{ $partner['email'] }}@endif
    </p>
</div>
