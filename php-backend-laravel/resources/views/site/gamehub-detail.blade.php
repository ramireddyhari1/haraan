@extends('site.layout')
@section('footer_icon_secondary', '#16a34a')

@section('content')
<section class="page-shell gamehub-detail-container theme-gamehub">
    
    <!-- Breadcrumbs / Back navigation -->
    <div class="detail-actions-row">
        <div class="detail-actions-buttons">
            <button onclick="toggleFavorite(this)" class="action-round-btn">
                <svg id="fav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            </button>
            <button onclick="shareVenue()" class="action-round-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
            </button>
        </div>
    </div>

    <!-- Title & Location Header -->
    <div class="detail-header">
        <div class="detail-header__badges">
            <span class="detail-badge">{{ $venue->category }}</span>
            @if(isset($venue->badge))
                <span class="detail-badge detail-badge--dark">{{ $venue->badge }}</span>
            @endif
        </div>
        <h1 class="detail-header__title">{{ $venue->title }}</h1>
        <div class="detail-header__meta">
            <span class="detail-meta-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                {{ $venue->location }}
            </span>
            <span class="detail-meta-divider">|</span>
            <span class="detail-meta-item">
                <span class="detail-meta-star">★</span>
                <strong>{{ $venue->rating }}</strong> ({{ $venue->reviews }} verified reviews)
            </span>
            <span class="detail-meta-divider">|</span>
            <span class="detail-meta-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                {{ $venue->hours }}
            </span>
        </div>
    </div>

    <!-- Gallery Grid (Airbnb Style) -->
    <div class="gallery-airbnb-grid">
        <!-- Large Featured Image -->
        <div class="gallery-featured-wrapper">
            <img class="gallery-featured-img" src="{{ $venue->image }}" alt="{{ $venue->title }} featured">
        </div>
        <!-- Right Column Gallery Images -->
        <div class="gallery-thumbs-wrapper">
            @foreach(array_slice($venue->gallery ?? [], 0, 2) as $index => $galImage)
                <div class="gallery-thumb-wrapper">
                    <img class="gallery-thumb-img" src="{{ $galImage }}" alt="Gallery view {{ $index + 1 }}">
                </div>
            @endforeach
        </div>
    </div>

    <!-- Two-Column Layout (Content vs Sticky Booking Sidebar) -->
    <div class="detail-two-column">
        
        <!-- Left Column: Details, Slots, Amenities, Reviews -->
        <div>
            <!-- About Section -->
            <div class="detail-card-panel">
                <h3 class="detail-card-panel__title">About This Facility</h3>
                <p class="detail-card-panel__text">{{ $venue->description }}</p>
            </div>

            <!-- Court Booking Scheduler (Core Widget) -->
            <div id="booking-widget" class="detail-card-panel">
                <h3 class="detail-card-panel__title detail-card-panel__title--compact">Select Booking Slot</h3>
                <p class="detail-card-panel__subtitle">Click on one or more available green slots below to queue your booking.</p>

                <!-- Date Picker Strip -->
                <div class="date-picker-strip">
                    @php
                        $days = ['Today', 'Tomorrow', 'Fri, 22 May', 'Sat, 23 May', 'Sun, 24 May', 'Mon, 25 May', 'Tue, 26 May'];
                    @endphp
                    @foreach($days as $index => $day)
                        <button onclick="selectDate(this, '{{ $day }}')" class="date-pill {{ $index === 0 ? 'is-active' : '' }}">
                            <span class="date-pill__day">{{ $index === 0 ? 'Today' : ($index === 1 ? 'Tomorrow' : explode(', ', $day)[0]) }}</span>
                            <span class="date-pill__date">
                                {{ $index < 2 ? date('d M', strtotime("+$index days")) : explode(', ', $day)[1] }}
                            </span>
                        </button>
                    @endforeach
                </div>

                <!-- Sport Selector Tabs (if multiple sports exist) -->
                @if(count($venue->sports) > 1)
                    <div class="mb-24">
                        <label class="sidebar-label">Select Sport</label>
                        <div class="sports-tab-strip">
                            @foreach($venue->sports as $sport)
                                <button onclick="selectSport('{{ $sport }}')" class="sport-tab" id="sport-tab-{{ $sport }}">
                                    <img src="{{ 
                                        $sport === 'Cricket' ? 'https://cdn-icons-png.flaticon.com/512/5140/5140374.png' : (
                                        $sport === 'Football' ? 'https://cdn-icons-png.flaticon.com/512/7711/7711842.png' : (
                                        $sport === 'Badminton' ? 'https://cdn-icons-png.flaticon.com/512/3012/3012437.png' : (
                                        $sport === 'Swimming' ? 'https://cdn-icons-png.flaticon.com/512/3144/3144883.png' : (
                                        $sport === 'Tennis' ? 'https://cdn-icons-png.flaticon.com/512/3132/3132644.png' : 'https://cdn-icons-png.flaticon.com/512/889/889505.png'))))
                                    }}" alt="{{ $sport }} Icon" />
                                    {{ $sport }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Court / Sub-Venue Selector Pills -->
                <div class="mb-28">
                    <label class="sidebar-label">Select Court / Pitch / Lane</label>
                    <div id="court-selector-container" class="court-selector-container">
                        <!-- Dynamically populated in JavaScript -->
                    </div>
                </div>

                <!-- Dynamic Slots Grid Container -->
                <div id="slots-grid-container">
                    <!-- Morning, Afternoon, Evening slots rendered here -->
                </div>
            </div>

            <!-- Amenities Section -->
            <div class="detail-card-panel">
                <h3 class="detail-card-panel__title mb-20">Amenities Offered</h3>
                @php
                    $amenityIcons = [
                        'Professional Floodlights' => 'https://cdn-icons-png.flaticon.com/512/14881/14881968.png',
                        'Floodlights' => 'https://cdn-icons-png.flaticon.com/512/14881/14881968.png',
                        'First Aid Kit' => 'https://cdn-icons-png.flaticon.com/512/12252/12252777.png',
                        'First Aid' => 'https://cdn-icons-png.flaticon.com/512/12252/12252777.png',
                        'Washrooms' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Changing Rooms' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Locker Rooms' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Shower Rooms' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Showers' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Separate Steam Rooms' => 'https://cdn-icons-png.flaticon.com/512/13132/13132308.png',
                        'Drinking Water' => 'https://cdn-icons-png.flaticon.com/512/1078/1078844.png',
                        'Drinking Water Station' => 'https://cdn-icons-png.flaticon.com/512/1078/1078844.png',
                        'Free Parking' => 'https://cdn-icons-png.flaticon.com/512/8571/8571768.png',
                        'Valet Parking' => 'https://cdn-icons-png.flaticon.com/512/8571/8571768.png',
                        'Covered Batting Nets' => 'https://cdn-icons-png.flaticon.com/512/9957/9957884.png',
                        'FIFA-Approved Turf' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        'Spectator Seating' => 'https://cdn-icons-png.flaticon.com/512/2822/2822557.png',
                        'Refreshment Lounge' => 'https://cdn-icons-png.flaticon.com/512/2738/2738730.png',
                        'Cafe' => 'https://cdn-icons-png.flaticon.com/512/2738/2738730.png',
                        'Air Conditioning' => 'https://cdn-icons-png.flaticon.com/512/959/959740.png',
                        'Yonex Synthetic Mats' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        'Racket Rental' => 'https://cdn-icons-png.flaticon.com/512/2906/2906803.png',
                        'Shuttle Shop' => 'https://cdn-icons-png.flaticon.com/512/1162/1162456.png',
                        'Temperature Controlled' => 'https://cdn-icons-png.flaticon.com/512/1684/1684375.png',
                        'Olympic Lanes' => 'https://cdn-icons-png.flaticon.com/512/3144/3144860.png',
                        'Qualified Lifeguards' => 'https://cdn-icons-png.flaticon.com/512/1012/1012399.png',
                        'Towels Provided' => 'https://cdn-icons-png.flaticon.com/512/2913/2913508.png',
                        'Imported Red Clay' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        'Ball Boy Service' => 'https://cdn-icons-png.flaticon.com/512/1012/1012399.png',
                        'Tennis Coach Access' => 'https://cdn-icons-png.flaticon.com/512/1012/1012399.png',
                        'Lounge' => 'https://cdn-icons-png.flaticon.com/512/2738/2738730.png',
                        'Acrylic Court Finish' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        'Official Flex Rims' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        'Chain Nets' => 'https://cdn-icons-png.flaticon.com/512/33/33736.png',
                        '24/7 Access' => 'https://cdn-icons-png.flaticon.com/512/3567/3567478.png',
                        'Spectator Fence' => 'https://cdn-icons-png.flaticon.com/512/2822/2822557.png',
                    ];
                @endphp
                <div class="amenities-grid">
                    @foreach($venue->amenities as $amenity)
                        @php
                            $iconUrl = $amenityIcons[$amenity] ?? 'https://cdn-icons-png.flaticon.com/512/109/109602.png';
                        @endphp
                        <div class="amenity-item">
                            <img src="{{ $iconUrl }}" alt="{{ $amenity }} icon">
                            <strong>{{ $amenity }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Rules & Policies Section -->
            <div class="detail-card-panel">
                <h3 class="detail-card-panel__title mb-20">Rules & Cancellation</h3>
                
                <div class="mb-20">
                    <button onclick="toggleAccordion('rules-body', 'rules-chevron')" class="accordion-header">
                        <h4 class="accordion-title">Venue Guidelines</h4>
                        <span id="rules-chevron" class="accordion-chevron">▾</span>
                    </button>
                    <div id="rules-body" class="accordion-body">
                        <ul>
                            <li>Non-marking shoes are strictly mandatory for all indoor court facilities.</li>
                            <li>Please report at least 10 minutes prior to the booked slot duration.</li>
                            <li>No pets, glass containers, or alcoholic beverages allowed inside the playing area.</li>
                            <li>Follow instructions from the ground staff for safety and court allocation.</li>
                        </ul>
                    </div>
                </div>

                <hr class="detail-divider">

                <div class="mb-20">
                    <button onclick="toggleAccordion('cancel-body', 'cancel-chevron')" class="accordion-header">
                        <h4 class="accordion-title">Cancellation Policy</h4>
                        <span id="cancel-chevron" class="accordion-chevron">▾</span>
                    </button>
                    <div id="cancel-body" class="accordion-body">
                        <ul>
                            <li>Free cancellation up to 6 hours before the booked slot time.</li>
                            <li>50% refund for cancellations done between 6 hours and 2 hours of the slot.</li>
                            <li>No refunds allowed for cancellations within 2 hours of the slot.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="detail-card-panel">
                <div class="reviews-section-header">
                    <h3>User Reviews</h3>
                    <span id="reviews-count-badge" class="reviews-count-badge">
                        {{ count($venue->reviews_list) }} Reviews
                    </span>
                </div>

                <!-- Live reviews list -->
                <div id="reviews-list-container">
                    @foreach($venue->reviews_list as $review)
                        <div class="review-card">
                            <div class="review-card__header">
                                <div class="review-user-info">
                                    <div class="review-user-avatar">
                                        {{ substr($review->user, 0, 1) }}
                                    </div>
                                    <div>
                                        <strong class="review-user-name">{{ $review->user }}</strong>
                                        <span class="review-date">{{ $review->date }}</span>
                                    </div>
                                </div>
                                <div class="review-star-rating">
                                    @for($i = 0; $i < 5; $i++)
                                        <span class="review-star {{ $i < $review->rating ? 'is-active' : '' }}">★</span>
                                    @endfor
                                </div>
                            </div>
                            <p class="review-comment">{{ $review->comment }}</p>
                        </div>
                    @endforeach
                </div>

                <!-- Add Review Form (Mock Live Action) -->
                <div class="review-form-card">
                    <h4>Add Your Review</h4>
                    <div class="rating-selector-row">
                        <span class="rating-selector-label">Your Rating:</span>
                        <div class="rating-selector-stars" id="rating-selector">
                            <span onclick="setFormRating(1)" class="star-btn">★</span>
                            <span onclick="setFormRating(2)" class="star-btn">★</span>
                            <span onclick="setFormRating(3)" class="star-btn">★</span>
                            <span onclick="setFormRating(4)" class="star-btn">★</span>
                            <span onclick="setFormRating(5)" class="star-btn">★</span>
                        </div>
                    </div>
                    <div class="mb-16">
                        <input type="text" id="review-user" placeholder="Your Name" class="review-input-field">
                    </div>
                    <div class="mb-16">
                        <textarea id="review-comment" placeholder="Write your review here..." rows="4" class="review-input-field review-comment-textarea"></textarea>
                    </div>
                    <button onclick="submitReview()" class="review-submit-btn">
                        Submit Review
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Column: Sticky Booking Card -->
        <div class="sticky-sidebar-container">
            <div class="sticky-booking-card">
                <div class="price-row">
                    <span class="price-row__label">Rate per hour</span>
                    <div class="price-row__value">
                        <strong id="rate-per-hour">₹{{ number_format($venue->price) }}</strong>
                        <span>/ hr</span>
                    </div>
                </div>

                <hr class="detail-divider mb-20">

                <div class="mb-16">
                    <label class="sidebar-label">Date</label>
                    <div id="selected-date-text" class="selected-date-preview">Today</div>
                </div>

                <div class="mb-24">
                    <label class="sidebar-label">Selected Slots (<span id="slots-count">0</span>)</label>
                    <div id="selected-slots-list" class="selected-slots-preview-list">
                        <span class="no-slots-placeholder">No slots selected. Click slots on the calendar grid.</span>
                    </div>
                </div>

                <!-- Price Calculator -->
                <div id="price-calculator" class="price-calculator-panel">
                    <div class="calc-row">
                        <span>Subtotal (<span id="calc-hours">0</span> hr)</span>
                        <span id="calc-subtotal">₹0</span>
                    </div>
                    <div class="calc-row">
                        <span>GST (18%)</span>
                        <span id="calc-gst">₹0</span>
                    </div>
                    <div class="calc-row">
                        <span>Platform Fee</span>
                        <span>₹50</span>
                    </div>
                    <hr class="dashed-divider">
                    <div class="calc-row calc-row--bold">
                        <span>Estimated Total</span>
                        <span id="calc-total" class="total-green">₹0</span>
                    </div>
                </div>

                <button id="book-now-button" disabled onclick="checkoutBooking()" class="book-now-button-widget">
                    Select slots to book
                </button>

                <p class="booking-notice-text">You won't be charged yet. Instant digital confirmation and invoice will be generated.</p>
            </div>
        </div>

    </div>

</section>

<!-- Success Checkout Overlay Modal -->
<div id="success-modal" class="success-overlay-modal">
    <div class="success-modal-card">
        <div class="success-check-badge">✓</div>
        <h2 class="success-modal-title">Booking Confirmed!</h2>
        <p class="success-modal-description">Your court slots at <strong>{{ $venue->title }}</strong> have been locked and reserved successfully.</p>
        
        <div class="success-receipt-box">
            <div class="success-receipt-row"><strong>Booking Reference:</strong> <span class="receipt-ref-id" id="modal-ref-id">BV-GH-49291</span></div>
            <div class="success-receipt-row"><strong>Date:</strong> <span class="receipt-highlight" id="modal-date">Today</span></div>
            <div class="success-receipt-row"><strong>Slots:</strong> <span class="receipt-highlight" id="modal-slots">...</span></div>
            <div class="success-receipt-row"><strong>Total Amount:</strong> <span class="receipt-total" id="modal-total">₹0</span></div>
        </div>

        <button onclick="closeSuccessModal()" class="success-done-btn">
            Done & Return to GameHub
        </button>
    </div>
</div>

<script>
    const venueCourts = @json($venue->courts);
    const venueSports = @json($venue->sports);
    const courtPrices = @json($venue->court_prices ?? new \stdClass);
    const courtPeak = @json($venue->court_peak ?? new \stdClass);
    const venueBasePrice = {{ (int) $venue->price }};

    // Base hourly rate for the currently-selected court (falls back to the venue base price).
    function currentRate() {
        return courtPrices[selectedCourt] ?? venueBasePrice;
    }

    // "06:00 AM" / "18:00" → minutes-from-midnight, or null.
    function timeMin(label) {
        if (!label) return null;
        const m = String(label).trim().match(/(\d{1,2}):(\d{2})\s*([AaPp][Mm])?/);
        if (!m) return null;
        let h = parseInt(m[1], 10);
        const ap = (m[3] || '').toUpperCase();
        if (ap === 'PM' && h !== 12) h += 12;
        if (ap === 'AM' && h === 12) h = 0;
        return h * 60 + parseInt(m[2], 10);
    }

    // Rate for a specific slot on the selected court: peak when the slot's start time falls in
    // the court's peak window, else the base rate.
    function slotRate(slotStr) {
        const base = currentRate();
        const p = courtPeak[selectedCourt];
        if (!p) return base;
        const t = timeMin(String(slotStr).split(' - ')[0]);
        const s = timeMin(p.start), e = timeMin(p.end);
        if (t != null && s != null && e != null && t >= s && t < e) return p.price;
        return base;
    }
    let selectedDate = 'Today';
    let selectedSport = venueSports[0];
    let selectedCourt = venueCourts[selectedSport][0];
    let selectedSlots = [];
    let currentFormRating = 0;

    function toggleFavorite(btn) {
        const svg = document.getElementById('fav-icon');
        if (svg.getAttribute('fill') === 'none') {
            svg.setAttribute('fill', '#16a34a');
            svg.setAttribute('stroke', '#16a34a');
            btn.style.borderColor = '#bbf7d0';
        } else {
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke', '#666');
            btn.style.borderColor = '#e5e7eb';
        }
    }

    function shareVenue() {
        if (navigator.share) {
            navigator.share({
                title: '{{ $venue->title }}',
                url: window.location.href
            }).catch(console.error);
        } else {
            alert('Sharing link copied to clipboard: ' + window.location.href);
            navigator.clipboard.writeText(window.location.href);
        }
    }

    function selectDate(element, date) {
        document.querySelectorAll('.date-pill').forEach(btn => btn.classList.remove('is-active'));
        element.classList.add('is-active');

        selectedDate = date;
        document.getElementById('selected-date-text').innerText = date;
        
        // Reset selected slots when switching days to simulate a real scheduler
        selectedSlots = [];
        updatePriceBreakdown();
        renderSlots();
    }

    function selectSport(sport) {
        selectedSport = sport;
        selectedCourt = venueCourts[sport][0];

        // Update sport tabs styling
        document.querySelectorAll('.sport-tab').forEach(btn => btn.classList.remove('is-active'));
        
        const activeTab = document.getElementById('sport-tab-' + sport);
        if (activeTab) {
            activeTab.classList.add('is-active');
        }

        renderCourtSelector();
        // Clear selected slots on sport change to avoid invalid court cross-bookings
        selectedSlots = [];
        updatePriceBreakdown();
        renderSlots();
    }

    function selectCourt(court) {
        selectedCourt = court;
        renderCourtSelector();
        renderSlots();
    }

    function renderCourtSelector() {
        const container = document.getElementById('court-selector-container');
        if (!container) return;

        const courts = venueCourts[selectedSport] || [];
        container.innerHTML = courts.map(court => {
            const isActive = court === selectedCourt;
            const activeClass = isActive ? 'is-active' : '';
            return `
                <button onclick="selectCourt('${court}')" class="court-pill ${activeClass}">
                    ${court}
                </button>
            `;
        }).join('');
    }

    const morningSlots = ['06:00 AM - 07:00 AM', '07:00 AM - 08:00 AM', '08:00 AM - 09:00 AM', '09:00 AM - 10:00 AM', '10:00 AM - 11:00 AM', '11:00 AM - 12:00 PM'];
    const afternoonSlots = ['12:00 PM - 01:00 PM', '01:00 PM - 02:00 PM', '02:00 PM - 03:00 PM', '03:00 PM - 04:00 PM', '04:00 PM - 05:00 PM'];
    const eveningSlots = ['05:00 PM - 06:00 PM', '06:00 PM - 07:00 PM', '07:00 PM - 08:00 PM', '08:00 PM - 09:00 PM', '09:00 PM - 10:00 PM', '10:00 PM - 11:00 PM'];

    function isSlotBooked(date, sport, court, slot) {
        let str = date + sport + court + slot;
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = (hash << 5) - hash + str.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash) % 10 < 3; // 30% deterministic booked slots
    }

    function renderSlots() {
        const container = document.getElementById('slots-grid-container');
        if (!container) return;

        let html = '';
        const rate = currentRate();

        // Reflect the selected court's rate in the sticky booking card header.
        const rateEl = document.getElementById('rate-per-hour');
        if (rateEl) rateEl.innerText = '₹' + rate.toLocaleString();

        const renderGroup = (title, slots) => {
            let groupHtml = `
                <div class="slots-group">
                    <h4 class="slots-group__title">${title}</h4>
                    <div class="slots-grid">
            `;

            slots.forEach((slot) => {
                const isBooked = isSlotBooked(selectedDate, selectedSport, selectedCourt, slot);
                const slotKey = `${selectedDate}_${selectedSport}_${selectedCourt}_${slot}`;
                const isSelected = selectedSlots.some(s => s.key === slotKey);
                const r = slotRate(slot);
                const isPeak = r > rate;

                const slotClass = isBooked ? 'is-booked' : (isSelected ? 'is-selected' : '');
                const onclickAttr = isBooked ? '' : `onclick="toggleSlot(this, '${slot}', ${r})"`;

                groupHtml += `
                    <div ${onclickAttr} class="slot-item ${slotClass}" data-key="${slotKey}">
                        <div class="slot-item__time">${slot.split(' - ')[0]}</div>
                        <div class="slot-item__price">${isBooked ? 'Reserved' : '₹' + r.toLocaleString() + (isPeak ? ' <span style=\"color:#16a34a;font-weight:600\">peak</span>' : '')}</div>
                    </div>
                `;
            });

            groupHtml += `
                    </div>
                </div>
            `;
            return groupHtml;
        };

        html += renderGroup('Morning', morningSlots);
        html += renderGroup('Afternoon', afternoonSlots);
        html += renderGroup('Evening', eveningSlots);

        container.innerHTML = html;
    }

    function toggleSlot(element, slot, rate) {
        const slotKey = `${selectedDate}_${selectedSport}_${selectedCourt}_${slot}`;
        if (element.classList.contains('is-selected')) {
            element.classList.remove('is-selected');
            selectedSlots = selectedSlots.filter(s => s.key !== slotKey);
        } else {
            element.classList.add('is-selected');
            selectedSlots.push({
                key: slotKey,
                date: selectedDate,
                sport: selectedSport,
                court: selectedCourt,
                time: slot,
                price: rate
            });
        }
        updatePriceBreakdown();
    }

    function removeSelectedSlot(key) {
        selectedSlots = selectedSlots.filter(s => s.key !== key);
        updatePriceBreakdown();
        renderSlots();
    }

    function updatePriceBreakdown() {
        const slotsCount = selectedSlots.length;
        document.getElementById('slots-count').innerText = slotsCount;
        
        const listDiv = document.getElementById('selected-slots-list');
        const calcBlock = document.getElementById('price-calculator');
        const bookBtn = document.getElementById('book-now-button');

        if (slotsCount === 0) {
            listDiv.innerHTML = '<span class="no-slots-placeholder">No slots selected. Click slots on the calendar grid.</span>';
            calcBlock.style.display = 'none';
            bookBtn.disabled = true;
            bookBtn.className = 'book-now-button-widget';
            bookBtn.innerText = 'Select slots to book';
        } else {
            listDiv.innerHTML = selectedSlots.map(s => `
                <div class="selected-slot-item-pill">
                    <div class="selected-slot-item-pill__header">
                        ${s.sport} • ${s.court}
                    </div>
                    <div class="selected-slot-item-pill__details">
                        <span class="selected-slot-item-pill__time">${s.time} (${s.date})</span>
                        <span class="selected-slot-item-pill__price">₹${s.price.toLocaleString()}</span>
                    </div>
                    <button onclick="removeSelectedSlot('${s.key}')" class="selected-slot-item-pill__remove">×</button>
                </div>
            `).join('');

            const subtotal = selectedSlots.reduce((sum, s) => sum + s.price, 0);
            const gst = Math.round(subtotal * 0.18);
            const platformFee = 50;
            const total = subtotal + gst + platformFee;

            document.getElementById('calc-hours').innerText = slotsCount;
            document.getElementById('calc-subtotal').innerText = '₹' + subtotal.toLocaleString();
            document.getElementById('calc-gst').innerText = '₹' + gst.toLocaleString();
            document.getElementById('calc-total').innerText = '₹' + total.toLocaleString();

            calcBlock.style.display = 'block';
            bookBtn.disabled = false;
            bookBtn.className = 'book-now-button-widget is-ready';
            bookBtn.innerText = 'Confirm & Book Slots';
        }
    }

    function toggleAccordion(bodyId, chevronId) {
        document.getElementById(bodyId).classList.toggle('is-collapsed');
        document.getElementById(chevronId).classList.toggle('is-collapsed');
    }

    function setFormRating(rating) {
        currentFormRating = rating;
        const stars = document.querySelectorAll('#rating-selector .star-btn');
        stars.forEach((star, idx) => {
            if (idx < rating) {
                star.classList.add('is-active');
            } else {
                star.classList.remove('is-active');
            }
        });
    }

    function submitReview() {
        const nameInput = document.getElementById('review-user');
        const commentInput = document.getElementById('review-comment');
        
        const name = nameInput.value.trim();
        const comment = commentInput.value.trim();

        if (!name || !comment || currentFormRating === 0) {
            alert('Please select a rating, enter your name, and write a review.');
            return;
        }

        const listDiv = document.getElementById('reviews-list-container');
        const newCard = document.createElement('div');
        newCard.className = 'review-card is-new';

        let starsHtml = '';
        for (let i = 0; i < 5; i++) {
            starsHtml += `<span class="review-star ${i < currentFormRating ? 'is-active' : ''}">★</span>`;
        }

        newCard.innerHTML = `
            <div class="review-card__header">
                <div class="review-user-info">
                    <div class="review-user-avatar">
                        ${name.substring(0, 1).toUpperCase()}
                    </div>
                    <div>
                        <strong class="review-user-name">${name}</strong>
                        <span class="review-date">Just now</span>
                    </div>
                </div>
                <div class="review-star-rating">
                    ${starsHtml}
                </div>
            </div>
            <p class="review-comment">${comment}</p>
        `;

        listDiv.prepend(newCard);
        setTimeout(() => newCard.classList.remove('is-new'), 50);

        const badge = document.getElementById('reviews-count-badge');
        const countStr = badge.innerText;
        const currentCount = parseInt(countStr) || 0;
        badge.innerText = (currentCount + 1) + ' Reviews';

        nameInput.value = '';
        commentInput.value = '';
        setFormRating(0);
        
        alert('Thank you for your feedback! Your review has been added.');
    }

    function checkoutBooking() {
        const refId = 'BV-GH-' + Math.floor(10000 + Math.random() * 90000);
        const subtotal = selectedSlots.reduce((sum, s) => sum + s.price, 0);
        const gst = Math.round(subtotal * 0.18);
        const total = subtotal + gst + 50;

        document.getElementById('modal-ref-id').innerText = refId;
        
        const uniqueDates = selectedSlots.map(s => s.date).filter((value, index, self) => self.indexOf(value) === index);
        document.getElementById('modal-date').innerText = uniqueDates.join(', ');
        
        const slotsDesc = selectedSlots.map(s => `${s.sport} - ${s.court} (${s.time.split(' - ')[0]})`).join(', ');
        document.getElementById('modal-slots').innerText = slotsDesc;
        document.getElementById('modal-total').innerText = '₹' + total.toLocaleString();

        const modal = document.getElementById('success-modal');
        modal.style.display = 'flex';
    }

    function closeSuccessModal() {
        document.getElementById('success-modal').style.display = 'none';
        window.location.href = '/gamehub';
    }

    // Initialize Scheduler selectors on load
    window.addEventListener('DOMContentLoaded', () => {
        selectSport(selectedSport);
    });
</script>
@endsection
