{{-- The partner sidebar's brand block. Deliberately plain: the wordmark and the
     word "Partner" — no switcher, no environment badge, nothing that points at
     the admin console. The venue/organiser identity lives in the footer card. --}}
<a href="{{ filament()->getUrl() }}" class="hrn-pbrand" aria-label="Haraan Partner — home">
    <img src="{{ asset('images/haraan-logo-blue.png') }}" alt="Haraan" class="hrn-pbrand-mark" width="112" height="30">
    <span class="hrn-pbrand-tag">Partner</span>
</a>
