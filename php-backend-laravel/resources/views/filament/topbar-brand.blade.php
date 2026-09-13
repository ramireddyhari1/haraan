{{-- Haraan logo in the mobile topbar. On desktop the sidebar already shows the
     brand, so this is only revealed below Filament's lg breakpoint (1024px) where
     the sidebar collapses behind the hamburger. The mark is navy; a dark-mode
     filter flips it to white so it reads on the dark topbar too. --}}
<a href="{{ url('control') }}" class="hrn-topbrand" aria-label="Haraan Control — home">
    <img src="{{ asset('images/haraan-mark.png') }}" alt="Haraan">
</a>
