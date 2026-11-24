{{-- Payouts list, nothing recorded yet. Plain text, left-aligned, no icon in a circle:
     it says what this ledger is for and how many paid bookings are waiting on it. --}}
@php
    $anyPayout = \App\Models\Payout::query()->exists();
    $waiting = \App\Filament\Resources\Payouts\Pages\ListPayouts::waitingCount();
    $createUrl = \App\Filament\Resources\Payouts\PayoutResource::getUrl('create');
@endphp
<div style="padding: 1.75rem 1.25rem; max-width: 34rem;">
@if($anyPayout)
    <p style="margin: 0; font-size: .875rem; color: var(--hrn-ink-3, var(--gray-500));">No payouts match this search or filter.</p>
@else
    <p style="margin: 0; font-size: .9375rem; font-weight: 650; letter-spacing: -.01em; color: var(--hrn-ink, inherit);">
        No payouts recorded yet
    </p>
    <p style="margin: .375rem 0 0; font-size: .8125rem; line-height: 1.55; color: var(--hrn-ink-3, var(--gray-500));">
        Each row here is one booking's money sent to its partner, with the UTR so either side can trace it.
        @if($waiting > 0)
            {{ $waiting === 1 ? '1 paid booking is' : $waiting.' paid bookings are' }} waiting for one.
        @else
            No paid booking is waiting right now.
        @endif
    </p>
    @if($waiting > 0)
        <a href="{{ $createUrl }}" style="display: inline-block; margin-top: .875rem; font-size: .8125rem; font-weight: 600; color: var(--hrn-brand, var(--primary-600)); text-decoration: none;">
            Record the first payout &rarr;
        </a>
    @endif
@endif
</div>
