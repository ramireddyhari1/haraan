@extends('site.layout')

@section('body_class', 'theme-minimal event-detail-page')
@section('footer_icon_primary', '#000000')

@section('content')
<style>
    /* Remove top margin/padding from the main container on this page */
    main.container {
        padding-top: 0 !important;
    }
    /* Page-scoped accent (MI-blue) for this event detail page */
    .district-event-page {
        --mi-accent: #0d6efd; /* MI-like blue */
        --mi-accent-lite: rgba(13,110,253,0.08);
    }

    /* Apply MI blue to primary headings and accents on this page */
    .district-event-page .dr-main-title {
        color: var(--mi-accent) !important;
    }

    .district-event-page .dr-section-title {
        color: var(--mi-accent) !important;
    }

    /* Make all H4 headings on this page use the MI blue (overrides inline styles) */
    .district-event-page h4 {
        color: var(--mi-accent) !important;
    }

    /* Replace gold stroke/fill used in some inline SVGs with MI blue on this page */
    .district-event-page svg[stroke="#E2B13C"],
    .district-event-page svg[fill="#E2B13C"] {
        stroke: var(--mi-accent) !important;
        fill: var(--mi-accent) !important;
    }

    .district-event-page .dr-tabs .dr-tab.active,
    .district-event-page .dr-tabs .dr-tab:hover {
        color: var(--mi-accent) !important;
        border-bottom: 2px solid var(--mi-accent) !important;
    }

    .district-event-page .dr-hero-badge {
        background: var(--mi-accent) !important;
        color: #fff !important;
        display: inline-block !important;
        padding: 6px 10px !important;
        border-radius: 6px !important;
        font-weight: 800 !important;
    }

    /* Header adjustments (page-scoped) - change black header elements to MI blue on this page only */
    body.event-detail-page .topbar .brand__text strong {
        color: var(--mi-accent) !important;
    }

    body.event-detail-page .topbar .topnav__link {
        color: var(--mi-accent) !important;
    }

    body.event-detail-page .topbar .topnav__link.is-active,
    body.event-detail-page .topbar .topnav__link:hover {
        color: var(--mi-accent) !important;
    }

    body.event-detail-page .topbar .topnav__link.is-active::after,
    body.event-detail-page .topbar .topnav__link:hover::after {
        background: var(--mi-accent) !important;
    }

    body.event-detail-page .topbar__actions .btn--solid {
        background: var(--mi-accent) !important;
        border-color: var(--mi-accent) !important;
        box-shadow: 0 10px 20px rgba(13,110,253,0.12) !important;
    }

    body.event-detail-page .location-pill__label strong {
        color: var(--mi-accent) !important;
    }

    /* Organizer card: apply MI-blue to name and stat values */
    .district-event-page .dr-organizer-card .dr-organizer-name {
        color: var(--mi-accent) !important;
    }

    .district-event-page .dr-organizer-card .dr-stat-value {
        color: var(--mi-accent) !important;
        font-weight: 800 !important;
    }

    .district-event-page .dr-organizer-card .dr-stat-label {
        color: #6b7280 !important; /* keep labels muted */
        font-weight: 500 !important;
    }
    
    /* Floating Back Button styling */
    .floating-left-btn {
        width: 44px !important;
        height: 44px !important;
        border: 1px solid var(--dr-border) !important;
        background: #ffffff !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: var(--dr-text) !important;
        text-decoration: none !important;
        z-index: 50 !important;
        transition: all 0.2s ease !important;
    }

    /* Floating/back button placement: fixed on viewport to avoid affecting header flow.
       Desktop: place below header; Large screens: nudge outside centered container. */
        .floating-left-btn {
            display: none !important;
        }

    .floating-left-btn:hover {
        background: #000000 !important;
        color: #ffffff !important;
        border-color: #000000 !important;
    }
    @media (min-width: 1440px) {
        .floating-left-btn {
            /* 50% - 650px is the left edge of the centered 1300px container */
            left: calc(50% - 650px - 64px) !important;
            top: 24px !important;
        }
    }

    /* Mobile: keep button near top-left and above content */
        @media (max-width: 1024px) {
            .floating-left-btn {
                display: block !important;
                position: fixed !important;
                left: 12px !important;
                top: 12px !important;
                z-index: 100 !important;
            }
        }

    /* Small elegant gap between header and event poster */
    .dr-hero-banner {
        margin-top: 24px !important;
        margin-bottom: 12px !important;
        border-radius: 16px !important;
        overflow: hidden !important;
    }

    /* Restore full color to event poster image */
    .dr-hero-banner img {
        filter: none !important;
        border-radius: 16px !important;
    }

    /* Tighten the info row spacing below the hero banner */
    .dr-info-row {
        margin-bottom: 16px !important;
        padding-bottom: 12px !important;
    }

    /* Shrink CHECK IN button to reduce title block height */
    .dr-checkin-btn {
        padding: 12px 28px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        border-radius: 14px !important;
        background: var(--mi-accent, #121620) !important;
        color: #ffffff !important;
        border: none !important;
        text-transform: none !important;
        letter-spacing: -0.01em !important;
        cursor: pointer !important;
        transition: background-color 0.2s ease !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    /* Reduce tab bar spacing and item gaps */
    .dr-tabs {
        gap: 24px !important;
        margin-bottom: 16px !important;
    }

    .dr-tab {
        padding: 8px 0 !important;
    }

    /* Tighten the main content layout and columns gap */
    .dr-content-grid {
        gap: 28px !important;
    }

    .dr-section-title {
        margin-bottom: 12px !important;
    }

    .dr-artist-item {
        padding: 12px 0 !important;
    }

    /* Decrease font size of the main title */
    .dr-main-title {
        font-size: 32px !important;
        font-weight: 800 !important;
        letter-spacing: -0.03em !important;
        line-height: 1.25 !important;
        color: #121620 !important;
    }

    /* Ensure mobile header pieces are hidden on desktop widths and
       desktop nav is visible — page-scoped safety net for header issues. */
    @media (min-width: 1025px) {
        .mobile-action-buttons,
        .mobile-menu-toggle,
        .mobile-nav,
        .mobile-nav-backdrop {
            display: none !important;
        }

        .topnav {
            display: flex !important;
            position: absolute !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
        }
    }
</style>

<div class="district-event-page" style="position: relative;">
    <div class="container" style="max-width: 1300px; margin: 0 auto; padding: 0; position: relative;">
        
        {{-- Floating Back Button on the Left --}}
        <a href="/events" class="floating-left-btn">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>

        <main class="dr-card-body">
            {{-- Hero Banner --}}
            <div class="dr-hero-banner" style="margin-top: 0; margin-bottom: 12px;">
                <img src="{{ asset('events.png') }}" alt="{{ $event->title }}">
                <div class="dr-hero-badge">Starts in 3H</div>
            </div>

            {{-- Info Header --}}
            <div class="dr-info-row" style="margin-bottom: 16px; padding-bottom: 12px;">
                <div>
                    <h1 class="dr-main-title" style="margin-bottom: 8px;">{{ $event->title }}</h1>
                    <p class="dr-meta-text">{{ optional($event->date)->format('D, d M Y') }} • {{ optional($event->date)->format('g:i A') }}</p>
                </div>
                <button class="dr-checkin-btn">
                    Book Tickets
                </button>
            </div>

            {{-- Tabs --}}
            <nav class="dr-tabs" style="gap: 24px; margin-bottom: 16px;">
                <a href="#" class="dr-tab active" data-target="pane-details" style="padding: 8px 0;">Details</a>
                <a href="#" class="dr-tab" data-target="pane-know" style="padding: 8px 0;">Know Before You Go</a>
            </nav>

            {{-- Content Pane: Details --}}
            <div id="pane-details" class="dr-tab-pane" style="display: block;">
                
                {{-- Content Grid --}}
                <div class="dr-content-grid" style="gap: 28px; display: grid;">
                    <div>
                        <section style="margin-bottom: 0px;">
                            <h3 class="dr-section-title" style="margin-bottom: 12px;">Event Details</h3>
                            <div class="dr-description">
                                {!! $event->description !!}
                            </div>

                            {{-- Premium Horizontal Maps Card --}}
                            <div style="margin-top: 24px; border: 1px solid var(--dr-border); border-radius: 24px; background: #ffffff; overflow: hidden; display: flex; height: 160px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.03); border: 1px solid #f0f0f0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 35px rgba(0, 0, 0, 0.06)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 8px 30px rgba(0, 0, 0, 0.03)';">
                                {{-- Left Half: GPS Map Preview with pulsing marker --}}
                                <div style="width: 45%; position: relative; height: 100%; background: #e0f2fe; overflow: hidden;">
                                    {{-- Real OpenStreetMap Embed for South Mumbai (South Bombay Studio location) --}}
                                    <iframe width="100%" height="100%" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://www.openstreetmap.org/export/embed.html?bbox=72.8100%2C18.9200%2C72.8700%2C18.9800&amp;layer=mapnik&amp;marker=18.9500%2C72.8400" style="border: 0; filter: contrast(1.05) brightness(0.98); pointer-events: none; width: 100%; height: 100%;"></iframe>
                                    
                                    {{-- Soft blue translucent circular overlay like the mockup --}}
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 70px; height: 70px; border-radius: 50%; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 10;">
                                        {{-- Glowing central marker --}}
                                        <div style="width: 24px; height: 24px; border-radius: 50%; background: #3b82f6; border: 3px solid #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(59, 130, 246, 0.5);">
                                            <div style="width: 6px; height: 6px; border-radius: 50%; background: #ffffff;"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Right Half: Address details --}}
                                <div style="width: 55%; padding: 20px 24px; display: flex; flex-direction: column; justify-content: center; position: relative; height: 100%;">
                                    <div style="font-size: 11px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 4px;">Venue Location</div>
                                    <h4 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 800; color: #121620; letter-spacing: -0.01em; line-height: 1.3;">{{ $event->venue }}</h4>
                                    
                                    {{-- Address detail with tiny arrow icon --}}
                                    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #71717a;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="color: #71717a; transform: rotate(45deg);"><path d="M21 3L3 10.53v.98l6.84 2.65L12.48 21h.98L21 3z"/></svg>
                                        <span>Mumbai, Maharashtra, India</span>
                                    </div>
                                    
                                    {{-- Share / Open Map button on bottom right corner --}}
                                    <div style="position: absolute; right: 20px; bottom: 20px;">
                                        <div style="width: 44px; height: 44px; border-radius: 14px; background: #ffffff; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.06); color: #3b82f6; transition: all 0.2s;" onmouseover="this.style.borderColor='#3b82f6'; this.style.background='#f0f9ff';" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='#ffffff';">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                                                <polyline points="16 6 12 2 8 6"></polyline>
                                                <line x1="12" y1="2" x2="12" y2="15"></line>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div>
                        <section>
                            <h3 class="dr-section-title" style="margin-bottom: 16px; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; text-transform: none; color: #121620;">Organized by</h3>
                            <div class="dr-organizer-card" style="background: #ffffff; border: 1px solid var(--dr-border); border-radius: 24px; padding: 24px; display: flex; align-items: center; gap: 24px; box-shadow: 0 6px 24px rgba(0, 0, 0, 0.02); min-height: 180px;">
                                {{-- Left column: Avatar and Name --}}
                                    <div style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px; width: 45%;">
                                    <div style="width: 84px; height: 84px; border-radius: 50%; overflow: hidden; border: 2px solid #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); background: #22252a; display: flex; align-items: center; justify-content: center;">
                                        @if($event->artist && $event->artist->image)
                                            <img src="{{ $event->artist->image }}" alt="{{ $event->artist->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                        @else
                                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        @endif
                                    </div>
                                    <div class="dr-organizer-name" style="font-size: 12px; font-weight: 900; color: #121620; text-transform: uppercase; letter-spacing: 0.05em; line-height: 1.2;">
                                        {{ Str::limit($event->artist->name ?? 'SMAAASH ENT', 14, '...') }}
                                    </div>
                                </div>

                                {{-- Right column: Stats list --}}
                                <div style="display: flex; flex-direction: column; flex-grow: 1; gap: 0; width: 55%;">
                                    {{-- Stat 1 --}}
                                    <div style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 13.5px; font-family: 'Inter', sans-serif;">
                                        <span class="dr-stat-value">84%</span> <span class="dr-stat-label">liked</span>
                                    </div>
                                    {{-- Stat 2 --}}
                                    <div style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 13.5px; font-family: 'Inter', sans-serif;">
                                        <span class="dr-stat-value">345</span> <span class="dr-stat-label">events</span>
                                    </div>
                                    {{-- Stat 3 --}}
                                    <div style="padding: 10px 0; font-size: 13.5px; font-family: 'Inter', sans-serif;">
                                        <span class="dr-stat-value">7.8 years</span> <span class="dr-stat-label">hosting</span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                {{-- Highlights Section --}}
                <section style="margin-top: 8px; border-top: none; padding-top: 0px; margin-bottom: 8px;">
                    <h3 class="dr-section-title" style="margin-bottom: 20px; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; text-transform: none;">Highlights</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                        
                        {{-- Card 1: Perfect For --}}
                        <div style="position: relative; border: 1px solid var(--dr-border); padding: 28px 24px; border-radius: 12px; background: #ffffff; min-height: 130px; overflow: hidden; display: flex; flex-direction: column; justify-content: center; gap: 8px;">
                            <!-- Top Right Golden Wave Pattern -->
                            <svg width="120" height="90" viewBox="0 0 120 90" fill="none" style="position: absolute; top: 0; right: 0; pointer-events: none; opacity: 0.35;">
                                <path d="M40 0 C 65 30, 90 20, 120 45" stroke="#E2B13C" stroke-width="1.2" stroke-linecap="round"/>
                                <path d="M25 0 C 55 40, 85 25, 120 60" stroke="#E2B13C" stroke-width="1.2" stroke-linecap="round"/>
                                <path d="M10 0 C 45 50, 80 30, 120 75" stroke="#E2B13C" stroke-width="1.2" stroke-linecap="round"/>
                            </svg>
                            
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #000000; font-family: 'Inter', sans-serif;">Perfect For</h4>
                            </div>
                            <p style="margin: 0; font-size: 13.5px; line-height: 1.6; color: #555555; max-width: 82%;">Perfect for families, friends, parties, and corporate outings.</p>
                        </div>

                        {{-- Card 2: Get Ready To --}}
                        <div style="position: relative; border: 1px solid var(--dr-border); padding: 28px 24px; border-radius: 12px; background: #ffffff; min-height: 130px; overflow: hidden; display: flex; flex-direction: column; justify-content: center; gap: 8px;">
                            <!-- Top Right Golden Wave Pattern -->
                            <svg width="120" height="90" viewBox="0 0 120 90" fill="none" style="position: absolute; top: 0; right: 0; pointer-events: none; opacity: 0.35;">
                                <path d="M40 0 C 65 30, 90 20, 120 45" stroke="#E2B13C" stroke-width="1.2" stroke-linecap="round"/>
                                <path d="M25 0 C 55 40, 85 25, 120 60" stroke="#E2B13C" stroke-width="1.2" stroke-linecap="round"/>
                                <path d="M10 0 C 45 50, 80 30, 120 75" stroke="#E2B13C" stroke-width="1.2" stroke-linecap="round"/>
                            </svg>
                            
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <svg width="16" height="16" fill="none" stroke="#E2B13C" stroke-width="2.2" viewBox="0 0 24 24" style="margin-top: -1px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #000000; font-family: 'Inter', sans-serif;">Get Ready To</h4>
                            </div>
                            <p style="margin: 0; font-size: 13.5px; line-height: 1.6; color: #555555; max-width: 82%;">Experience high-energy gaming, world-class bowling, and all-in-one entertainment.</p>
                        </div>

                    </div>

                    {{-- Carousel Control Row --}}
                    <div style="display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 24px;">
                        <button type="button" style="width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--dr-border); background: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #000000; outline: none; transition: background 0.2s;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                        </button>
                        <div style="display: flex; gap: 6px; align-items: center;">
                            <span style="width: 5px; height: 5px; border-radius: 50%; background: #dddddd;"></span>
                            <span style="width: 5px; height: 5px; border-radius: 50%; background: #dddddd;"></span>
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #000000;"></span>
                        </div>
                    </div>
                </section>

                {{-- Gallery Section --}}
                <section style="margin-top: 12px; border-top: none; padding-top: 0px; margin-bottom: 8px;">
                    <h3 class="dr-section-title" style="margin-bottom: 20px; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; text-transform: none;">Gallery</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                        
                        {{-- Image 1 --}}
                        <div style="border-radius: 16px; overflow: hidden; height: 180px; border: 1px solid var(--dr-border);">
                            <img src="https://images.unsplash.com/photo-1593341606579-7e90d290c078?auto=format&fit=crop&w=500&q=80" alt="Sports Arena" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>

                        {{-- Image 2 --}}
                        <div style="border-radius: 16px; overflow: hidden; height: 180px; border: 1px solid var(--dr-border);">
                            <img src="https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=500&q=80" alt="Arcade Zone" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>

                        {{-- Image 3 --}}
                        <div style="border-radius: 16px; overflow: hidden; height: 180px; border: 1px solid var(--dr-border);">
                            <img src="https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=500&q=80" alt="Retro Gaming" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>

                        {{-- Image 4 --}}
                        <div style="border-radius: 16px; overflow: hidden; height: 180px; border: 1px solid var(--dr-border);">
                            <img src="https://images.unsplash.com/photo-1593508512255-86ab42a8e620?auto=format&fit=crop&w=500&q=80" alt="Virtual Reality" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>

                        {{-- Image 5 --}}
                        <div style="border-radius: 16px; overflow: hidden; height: 180px; border: 1px solid var(--dr-border);">
                            <img src="https://images.unsplash.com/photo-1553481187-be93c21490a9?auto=format&fit=crop&w=500&q=80" alt="Arcade Gaming Setups" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>

                        {{-- Show all photos card --}}
                        <div style="border-radius: 16px; border: 1px solid var(--dr-border); height: 180px; display: flex; align-items: center; justify-content: center; background: #ffffff;">
                            <button type="button" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border: 1px solid var(--dr-border); border-radius: 99px; background: #ffffff; cursor: pointer; font-size: 13px; font-weight: 700; color: #333333; outline: none; transition: all 0.2s;">
                                <!-- 3x3 dot grid icon -->
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                    <circle cx="4" cy="4" r="2"/>
                                    <circle cx="12" cy="4" r="2"/>
                                    <circle cx="20" cy="4" r="2"/>
                                    <circle cx="4" cy="12" r="2"/>
                                    <circle cx="12" cy="12" r="2"/>
                                    <circle cx="20" cy="12" r="2"/>
                                    <circle cx="4" cy="20" r="2"/>
                                    <circle cx="12" cy="20" r="2"/>
                                    <circle cx="20" cy="20" r="2"/>
                                </svg>
                                <span>Show All Photos</span>
                            </button>
                        </div>

                    </div>
                </section>
            </div>

            {{-- Content Pane: Know Before You Go --}}
            <div id="pane-know" class="dr-tab-pane" style="display: none; margin-top: 24px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 32px;">
                    
                    {{-- Entry & Timing --}}
                    <div style="border: 1px solid var(--dr-border); padding: 24px; border-radius: 0px; background: #ffffff;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                            <svg width="20" height="20" fill="none" stroke="#000000" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <h4 style="margin: 0; font-size: 14px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: #000000;">Entry & Timings</h4>
                        </div>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: var(--dr-text-mute);">Gates open exactly 90 minutes before the scheduled start. We recommend early arrival to facilitate smooth entry. Latecomers may be restricted once the performance commences.</p>
                    </div>

                    {{-- Dynamic Ticket Policy --}}
                    <div style="border: 1px solid var(--dr-border); padding: 24px; border-radius: 0px; background: #ffffff;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                            <svg width="20" height="20" fill="none" stroke="#000000" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="16" y1="2" x2="16" y2="4"></line><line x1="8" y1="2" x2="8" y2="4"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            <h4 style="margin: 0; font-size: 14px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: #000000;">Digital Ticket Policy</h4>
                        </div>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: var(--dr-text-mute);">Keep your dynamic QR ticket ready on your mobile screen at entry gates. Printed PDFs or static screenshots are strictly invalid as secure QR codes refresh automatically.</p>
                    </div>

                    {{-- Prohibited Items --}}
                    <div style="border: 1px solid var(--dr-border); padding: 24px; border-radius: 0px; background: #ffffff;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                            <svg width="20" height="20" fill="none" stroke="#000000" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                            <h4 style="margin: 0; font-size: 14px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: #000000;">Prohibited Items</h4>
                        </div>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: var(--dr-text-mute);">Professional cameras, recorders, outside food or drinks, plastic bags, and large luggage are strictly banned inside the arena. Mandatory security check is active.</p>
                    </div>

                    {{-- Venue & Parking --}}
                    <div style="border: 1px solid var(--dr-border); padding: 24px; border-radius: 0px; background: #ffffff;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                            <svg width="20" height="20" fill="none" stroke="#000000" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <h4 style="margin: 0; font-size: 14px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: #000000;">Venue & Parking</h4>
                        </div>
                        <p style="margin: 0; font-size: 14px; line-height: 1.6; color: var(--dr-text-mute);">On-site parking is limited and operates on a first-come, first-served basis. Cabs, local transport, or rideshares are strongly recommended for seamless travel.</p>
                    </div>

                </div>
            </div>

        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.dr-tab');
        const panes = document.querySelectorAll('.dr-tab-pane');

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Reset active tab states
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Hide all panes
                panes.forEach(pane => pane.style.display = 'none');
                
                // Display target pane
                const targetId = this.getAttribute('data-target');
                const targetPane = document.getElementById(targetId);
                if (targetPane) {
                    if (targetId === 'pane-details') {
                        targetPane.style.display = 'grid';
                    } else {
                        targetPane.style.display = 'block';
                    }
                }
            });
        });
    });
</script>
@endsection
