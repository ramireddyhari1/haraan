/**
 * site.js — Haraan front-end interactions
 *
 * Extracted from the inline <script> in layout.blade.php.
 * Handles:
 *   1. Location-selector modal (open / close / fetch cities / search / select)
 *   2. Login (auth) modal (open / close)
 *   3. Phone-login form submission
 */

/* -------------------------------------------------------------------------- */
/*  0. Overlay history — makes Back and Escape close overlays                   */
/* -------------------------------------------------------------------------- */
/**
 * Every modal/sheet on this site is opened by toggling classes, which puts
 * nothing in the browser history. On mobile that means the hardware/gesture Back
 * button LEAVES THE PAGE instead of closing the overlay — the user loses their
 * place (worst on the event ticket sheet, mid-booking). On desktop, Escape did
 * nothing either.
 *
 * This gives each overlay a history entry so Back unwinds it, and closes the
 * topmost one on Escape. Overlays opt in by calling push() when they open and
 * pop() when they close — see openLoginModal/closeLoginModal for the pattern:
 *
 *   function openThing() { ...show DOM...; HaraanOverlay.push('thing', closeThing); }
 *   function closeThing() { ...hide DOM...; HaraanOverlay.pop('thing'); }
 *
 * Both directions route through here, so the ✕ button, the backdrop, Escape and
 * Back all end in the same place and the history stack cannot drift.
 *
 * Exposed on `window` so page-level inline scripts (event detail's sheets) can
 * use it without duplicating the logic.
 */
window.HaraanOverlay = (function () {
    const stack = [];      // names of open overlays, innermost last
    const closers = {};    // name -> the function that hides it
    // True only while we're reacting to a real Back press, so pop() knows not to
    // call history.back() again (which would eat a second, unrelated entry).
    let handlingPop = false;

    function push(name, closeFn) {
        if (stack.indexOf(name) !== -1) return;
        closers[name] = closeFn;
        stack.push(name);
        history.pushState({ haraanOverlay: name }, '');
    }

    function pop(name) {
        const i = stack.lastIndexOf(name);
        if (i === -1) return;           // already closed — keeps close() idempotent
        stack.splice(i, 1);
        if (!handlingPop) history.back();
    }

    window.addEventListener('popstate', function () {
        const name = stack[stack.length - 1];
        if (!name) return;              // not our entry; let the browser navigate
        handlingPop = true;
        try { (closers[name] || function () {})(); } finally { handlingPop = false; }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || stack.length === 0) return;
        (closers[stack[stack.length - 1]] || function () {})();
    });

    return { push: push, pop: pop, isOpen: function (n) { return stack.indexOf(n) !== -1; } };
})();

document.addEventListener('DOMContentLoaded', () => {
    /* ------------------------------------------------------------------ */
    /*  1. Location Modal                                                  */
    /* ------------------------------------------------------------------ */

    const locationModal    = document.getElementById('locationModal');
    const locationCard     = locationModal?.querySelector('.location-sheet');
    // Two triggers open the picker: the desktop pill and the mobile greeting's
    // location line. Bind by attribute so either can open it.
    const locationToggles  = document.querySelectorAll('[data-location-toggle]');
    const locationBackdrop = document.getElementById('locationBackdrop');
    const closeLocationBtn = document.getElementById('closeLocation');
    const locationSearch   = document.getElementById('locationSearch');
    const popularGrid      = document.getElementById('popularCities');
    const allList          = document.getElementById('allCities');
    const useCurrentBtn    = document.getElementById('useCurrent');
    const sheetBody        = document.getElementById('locationBody');
    const recentWrap       = document.getElementById('locRecentWrap');
    const recentList       = document.getElementById('locRecent');
    const emptyState       = document.getElementById('locEmpty');

    /** Cache for the cities data fetched from /data/cities.json */
    let cachedCities = [];

    /** Show the location-selector modal and focus the search field. */
    function openLocationModal() {
        if (!locationModal) return;
        locationModal.setAttribute('aria-hidden', 'false');
        locationModal.style.display = 'block';
        locationCard?.classList.add('show');
        window.HaraanOverlay.push('location', closeLocationModal);
        // Always open scrolled to the top of the sheet.
        if (sheetBody) sheetBody.scrollTop = 0;
        // Only pull up the keyboard on pointer/desktop — on touch it would slam
        // the on-screen keyboard over the sheet the instant it opens.
        if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            locationSearch?.focus();
        }
    }

    /** Hide the location-selector modal. */
    function closeLocationModal() {
        if (!locationModal) return;
        locationModal.setAttribute('aria-hidden', 'true');
        locationModal.style.display = 'none';
        locationCard?.classList.remove('show');
        window.HaraanOverlay.pop('location');
    }

    /**
     * Restore the previously-selected city from localStorage so the
     * header pill shows the right label on every page load.
     */
    function loadSelectedCity() {
        try {
            const stored = localStorage.getItem('bv_selected_city');
            if (!stored) return;

            const city  = JSON.parse(stored);
            const label = document.querySelector('.location-pill__label');
            if (label) {
                label.querySelector('strong').textContent = city.name;
                label.querySelector('small').textContent  = city.country;
            }
        } catch (_) { /* ignore parse errors */ }
    }

    /**
     * Fetch the city list from the server and cache it.
     * @returns {Promise<Array>}
     */
    async function fetchCities() {
        try {
            const res    = await fetch('/data/cities.json');
            const cities = await res.json();
            cachedCities = cities;
            renderCities(cities);
            return cities;
        } catch (err) {
            console.error('Failed to load cities', err);
            return [];
        }
    }

    /**
     * Render both the "Popular Cities" grid and the full alphabetical
     * list, plus the A-Z sidebar index.
     */
    function renderCities(cities) {
        if (!popularGrid || !allList) return;

        popularGrid.innerHTML = '';
        allList.innerHTML     = '';
        if (recentList) recentList.innerHTML = '';

        // --- Recent (recently chosen cities) — personal, only if we have any. ---
        const recents = recentCities()
            .map(r => cities.find(c => c.name === r.name))
            .filter(Boolean)
            .slice(0, 4);
        if (recentWrap) {
            if (recents.length && recentList) {
                recents.forEach(c => recentList.appendChild(buildCityCard(c)));
                recentWrap.hidden = false;
            } else {
                recentWrap.hidden = true;
            }
        }

        // --- Popular cities (top 8) ---
        cities
            .filter(c => c.popular)
            .slice(0, 8)
            .forEach(c => popularGrid.appendChild(buildCityCard(c)));

        // --- All cities, sorted A-Z, grouped under quiet letter headers ---
        const sorted = cities.slice().sort((a, b) => a.name.localeCompare(b.name));
        let lastLetter = null;
        sorted.forEach(c => {
            const first = (c.name || '').charAt(0).toUpperCase();
            if (first !== lastLetter) {
                lastLetter = first;
                const header = document.createElement('div');
                header.className = 'loc-letter';
                header.dataset.divider = '1';
                header.textContent = first;
                allList.appendChild(header);
            }
            allList.appendChild(buildCityRow(c));
        });

        // Current-city context line under the title.
        if (locationCurrentLine()) {
            const sel = selectedCityName();
            locationCurrentLine().textContent = sel
                ? 'Currently browsing ' + sel
                : 'Set where you want to play & attend';
        }

        // --- Search: instant filter + collapse browse sections + empty state ---
        if (locationSearch) {
            locationSearch.value   = '';
            if (sheetBody) sheetBody.classList.remove('is-searching');
            if (emptyState) emptyState.hidden = true;
            locationSearch.oninput = function () {
                const query = this.value.trim().toLowerCase();
                if (sheetBody) sheetBody.classList.toggle('is-searching', !!query);

                let visible = 0;
                Array.from(allList.children).forEach(row => {
                    // Letter headers only make sense in the full, unfiltered list.
                    if (row.dataset.divider) {
                        row.style.display = query ? 'none' : '';
                        return;
                    }
                    const strong = row.querySelector('.loc-row__name');
                    const name   = strong ? strong.textContent.toLowerCase() : '';
                    const hit    = name.includes(query);
                    row.style.display = hit ? '' : 'none';
                    if (hit) visible++;
                });
                if (emptyState) emptyState.hidden = !(query && visible === 0);
            };
        }
    }

    /** The subtitle line element under the sheet title. */
    function locationCurrentLine() {
        return document.getElementById('locationCurrent');
    }

    /** Recently-selected cities (most-recent first), from localStorage. */
    function recentCities() {
        try { return JSON.parse(localStorage.getItem('bv_recent_cities') || '[]'); }
        catch (_) { return []; }
    }

    /** Remember a city as recently chosen (dedup, most-recent first, cap 4). */
    function pushRecentCity(city) {
        try {
            const list = recentCities().filter(c => c.name !== city.name);
            list.unshift({ name: city.name, country: city.country || '' });
            localStorage.setItem('bv_recent_cities', JSON.stringify(list.slice(0, 4)));
        } catch (_) {}
    }

    /** Name of the currently-selected city, for highlighting in the list. */
    function selectedCityName() {
        try { return (JSON.parse(localStorage.getItem('bv_selected_city') || '{}').name) || ''; }
        catch (_) { return ''; }
    }

    /** A tick glyph for the currently-selected city. */
    const CHECK_SVG =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" ' +
        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<polyline points="20 6 9 17 4 12"></polyline></svg>';

    /**
     * A curated city chip. Typography does the work — the selected city fills
     * with ink so the choice reads instantly without a coloured badge on every
     * one. Used for both the "Recent" and "Popular" rows.
     */
    function buildCityCard(city) {
        const selected = city.name === selectedCityName();
        const btn = document.createElement('button');
        btn.className = 'loc-chip' + (selected ? ' is-selected' : '');
        btn.type = 'button';
        btn.setAttribute('role', 'listitem');
        if (selected) btn.setAttribute('aria-current', 'true');
        btn.innerHTML = `<span class="loc-chip__name">${city.name}</span>` +
            (selected ? `<span class="loc-chip__tick">${CHECK_SVG}</span>` : '');
        btn.addEventListener('click', () => selectCity(city));
        return btn;
    }

    /**
     * A city row in the A-Z list. No avatar, no repeated country label — just a
     * confident name, generous height, and a quiet tick when it's the current
     * city. Alignment and rhythm carry it.
     */
    function buildCityRow(city) {
        const selected = city.name === selectedCityName();
        const div = document.createElement('div');
        div.className = 'loc-row' + (selected ? ' is-selected' : '');
        div.setAttribute('role', 'listitem');
        div.dataset.letter = (city.name || '?').charAt(0).toUpperCase();
        div.innerHTML =
            `<span class="loc-row__name">${city.name}</span>` +
            (selected ? `<span class="loc-row__tick">${CHECK_SVG}</span>` : '');
        div.addEventListener('click', () => selectCity(city));
        return div;
    }

    /**
     * Apply the chosen city to the header pill, persist it, and close
     * the modal.
     */
    function selectCity(city) {
        // Persist the choice in a cookie so the server can scope content
        // (events/venues) to this city and render the pill on every page.
        try {
            const maxAge = 60 * 60 * 24 * 365; // 1 year
            document.cookie = 'haraan_city=' + encodeURIComponent(city.name) +
                '; path=/; max-age=' + maxAge + '; SameSite=Lax';
            localStorage.setItem('bv_selected_city', JSON.stringify(city));
            pushRecentCity(city);
        } catch (_) {}
        closeLocationModal();
        // Reload so the server re-filters listings and updates the header pill.
        window.location.reload();
    }

    // --- Location modal event wiring ---
    locationToggles.forEach((toggle) => toggle.addEventListener('click', (e) => {
        e.preventDefault();
        if (cachedCities.length) {
            renderCities(cachedCities);
            openLocationModal();
        } else {
            fetchCities().then(() => openLocationModal());
        }
    }));

    locationBackdrop?.addEventListener('click', closeLocationModal);
    closeLocationBtn?.addEventListener('click', closeLocationModal);

    /**
     * Map free-text place names from a reverse geocoder onto one of the served
     * cities (public/data/cities.json). Returns the matching city object, or
     * null when the viewer is outside every city Haraan serves.
     */
    function matchServedCity(candidates) {
        const names = (candidates || [])
            .filter(Boolean)
            .map((s) => String(s).toLowerCase().trim());
        if (!names.length || !cachedCities.length) return null;

        // Known localities / administrative names → served city label.
        const aliases = {
            'mumbai': 'Mumbai', 'bombay': 'Mumbai', 'navi mumbai': 'Mumbai', 'thane': 'Mumbai',
            'delhi': 'Delhi NCR', 'new delhi': 'Delhi NCR', 'gurgaon': 'Delhi NCR',
            'gurugram': 'Delhi NCR', 'noida': 'Delhi NCR', 'ghaziabad': 'Delhi NCR', 'faridabad': 'Delhi NCR',
            'bengaluru': 'Bengaluru', 'bangalore': 'Bengaluru',
            'pune': 'Pune', 'pimpri': 'Pune', 'chinchwad': 'Pune',
            'goa': 'Goa', 'panaji': 'Goa', 'panjim': 'Goa', 'mapusa': 'Goa', 'margao': 'Goa', 'vasco': 'Goa',
        };
        const findCity = (label) =>
            cachedCities.find((c) => c.name.toLowerCase() === label.toLowerCase()) || null;

        // 1) Alias hit (longest alias first so "navi mumbai" wins over "mumbai").
        const needles = Object.keys(aliases).sort((a, b) => b.length - a.length);
        for (const name of names) {
            for (const needle of needles) {
                if (name.includes(needle)) {
                    const city = findCity(aliases[needle]);
                    if (city) return city;
                }
            }
        }
        // 2) Direct match against a served city's own name.
        for (const name of names) {
            for (const c of cachedCities) {
                if (name.includes(c.name.toLowerCase())) return c;
            }
        }
        return null;
    }

    /**
     * Apply a set of geocoder place-name candidates: prefer a curated served
     * city, otherwise fall back to the raw detected name so the pill still
     * reflects the viewer's actual city. Returns true when a city was applied
     * (which triggers a reload), false when nothing usable was found.
     */
    function applyDetectedCity(candidates, detectedName, countryName) {
        const matched = matchServedCity(candidates);
        if (matched) {
            selectCity(matched); // persists cookie + reloads (updates the pill)
            return true;
        }
        const name = (detectedName || '').trim();
        if (name) {
            selectCity({ name: name, country: countryName || 'India' });
            return true;
        }
        return false;
    }

    /** Resolve a city from precise GPS coordinates (browser geolocation). */
    async function resolveCityByCoords(latitude, longitude) {
        if (!cachedCities.length) await fetchCities();
        const resp = await fetch(
            'https://api.bigdatacloud.net/data/reverse-geocode-client' +
            `?latitude=${latitude}&longitude=${longitude}&localityLanguage=en`
        );
        if (!resp.ok) return false;
        const data = await resp.json();
        const admin = (data.localityInfo && data.localityInfo.administrative) || [];
        const candidates = [data.city, data.locality, data.principalSubdivision, ...admin.map((a) => a && a.name)];
        return applyDetectedCity(candidates, data.city || data.locality, data.countryName);
    }

    /**
     * Resolve a city from the viewer's IP address — the fallback when browser
     * geolocation is denied, blocked (e.g. inside an embedded frame), or times
     * out. Less precise than GPS but works without any permission prompt.
     */
    async function resolveCityByIp() {
        if (!cachedCities.length) await fetchCities();
        const resp = await fetch('https://ipwho.is/');
        if (!resp.ok) return false;
        const data = await resp.json();
        if (data && data.success === false) return false;
        const candidates = [data.city, data.region, data.country];
        return applyDetectedCity(candidates, data.city, data.country);
    }

    useCurrentBtn?.addEventListener('click', () => {
        // Update only the title line so the icon/subtext/structure survive.
        const titleEl  = useCurrentBtn.querySelector('.loc-current__title');
        const subEl    = useCurrentBtn.querySelector('.loc-current__sub');
        const label0   = titleEl ? titleEl.textContent : '';
        const sub0     = subEl ? subEl.textContent : '';
        const setText  = (t, s) => {
            if (titleEl) titleEl.textContent = t;
            if (subEl && s != null) subEl.textContent = s;
        };
        const resetBtn = () => {
            setText(label0, sub0);
            useCurrentBtn.disabled = false;
            useCurrentBtn.classList.remove('is-loading');
        };
        const fail = () => {
            resetBtn();
            alert('Could not detect your location. Please pick a city from the list.');
        };
        setText('Detecting your location…', 'Hang tight for a moment');
        useCurrentBtn.disabled = true;
        useCurrentBtn.classList.add('is-loading');

        // IP fallback — used when precise geolocation is unavailable/denied.
        const tryIp = () => resolveCityByIp().then((ok) => { if (!ok) fail(); }).catch(fail);

        if (!navigator.geolocation) {
            tryIp();
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                resolveCityByCoords(pos.coords.latitude, pos.coords.longitude)
                    .then((ok) => { if (!ok) return tryIp(); })
                    .catch(tryIp);
            },
            // Denied / unavailable / timeout → fall back to IP-based lookup.
            () => tryIp(),
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    });

    // Close location modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLocationModal();
    });

    // The header pill is rendered server-side from the city cookie, so we no
    // longer override it from localStorage on load (that would fight the server
    // value). loadSelectedCity() is kept for reference but intentionally unused.
    void loadSelectedCity;

    /* ------------------------------------------------------------------ */
    /*  Topbar elevation on scroll (flat at top, subtle shadow once moved) */
    /* ------------------------------------------------------------------ */
    const topbarEl = document.querySelector('.topbar');
    if (topbarEl) {
        let ticking = false;
        const syncTopbar = () => {
            topbarEl.classList.toggle('is-scrolled', window.scrollY > 4);
            ticking = false;
        };
        window.addEventListener('scroll', () => {
            if (!ticking) { ticking = true; requestAnimationFrame(syncTopbar); }
        }, { passive: true });
        syncTopbar(); // reflect state on load (e.g. restored scroll position)
    }

    /* ------------------------------------------------------------------ */
    /*  Header search autocomplete (live suggestions)                      */
    /* ------------------------------------------------------------------ */
    const searchForm  = document.querySelector('.topbar__search');
    const searchInput = searchForm ? searchForm.querySelector('.topbar__search-input') : null;
    const suggestBox  = document.getElementById('searchSuggest');
    if (searchForm && searchInput && suggestBox) {
        let items = [];        // flat list of {title, meta, url} in render order
        let activeIdx = -1;    // keyboard-highlighted item
        let debounceId = null;
        let lastQuery = '';
        let reqToken = 0;      // guards against out-of-order responses

        const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => (
            { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        ));

        const closeSuggest = () => {
            suggestBox.hidden = true;
            suggestBox.innerHTML = '';
            items = []; activeIdx = -1;
            searchInput.setAttribute('aria-expanded', 'false');
        };

        const render = (data) => {
            const ev = data.events || [];
            const vn = data.venues || [];
            items = [...ev, ...vn];
            if (!items.length) {
                suggestBox.innerHTML = '<div class="search-suggest__empty">No matches — press Enter to search everything</div>';
                suggestBox.hidden = false;
                searchInput.setAttribute('aria-expanded', 'true');
                activeIdx = -1;
                return;
            }
            const group = (label, rows) => rows.length
                ? '<div class="search-suggest__group-label">' + label + '</div>' + rows.map(r =>
                    '<a class="search-suggest__item" role="option" href="' + esc(r.url) + '">' +
                        '<span class="search-suggest__title">' + esc(r.title) + '</span>' +
                        '<span class="search-suggest__meta">' + esc(r.meta) + '</span>' +
                    '</a>').join('')
                : '';
            suggestBox.innerHTML = group('Events', ev) + group('Venues', vn);
            suggestBox.hidden = false;
            searchInput.setAttribute('aria-expanded', 'true');
            activeIdx = -1;
        };

        const highlight = () => {
            const nodes = suggestBox.querySelectorAll('.search-suggest__item');
            nodes.forEach((n, i) => n.classList.toggle('is-active', i === activeIdx));
            if (activeIdx >= 0 && nodes[activeIdx]) nodes[activeIdx].scrollIntoView({ block: 'nearest' });
        };

        const fetchSuggest = (q) => {
            const token = ++reqToken;
            fetch('/api/search/suggest?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(r => r.ok ? r.json() : { events: [], venues: [] })
                // Only render if this is still the newest request AND the box's
                // query is still what the user has in the field (guards against
                // stale results after the input was cleared or changed).
                .then(data => { if (token === reqToken && searchInput.value.trim() === q) render(data); })
                .catch(() => { if (token === reqToken) closeSuggest(); });
        };

        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim();
            lastQuery = q;
            clearTimeout(debounceId);
            if (q.length < 2) { closeSuggest(); return; }
            debounceId = setTimeout(() => fetchSuggest(q), 180);
        });

        searchInput.addEventListener('keydown', (e) => {
            const nodes = suggestBox.querySelectorAll('.search-suggest__item');
            if (suggestBox.hidden || !nodes.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = (activeIdx + 1) % nodes.length; highlight(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = (activeIdx - 1 + nodes.length) % nodes.length; highlight(); }
            else if (e.key === 'Enter' && activeIdx >= 0) { e.preventDefault(); window.location.href = nodes[activeIdx].getAttribute('href'); }
            else if (e.key === 'Escape') { closeSuggest(); }
        });

        searchInput.addEventListener('focus', () => {
            if (searchInput.value.trim().length >= 2 && items.length) suggestBox.hidden = false;
        });

        document.addEventListener('click', (e) => {
            if (!searchForm.contains(e.target)) closeSuggest();
        });
    }

    /* ------------------------------------------------------------------ */
    /*  5. Mobile action buttons (switch behavior)                         */
    /* ------------------------------------------------------------------ */
    const mobileActionWrap = document.querySelector('.mobile-action-buttons');
    const mobileActionBtns = mobileActionWrap ? Array.from(mobileActionWrap.querySelectorAll('.mobile-action-btn')) : [];
    if (mobileActionBtns.length) {
        // Determine active tab from the current URL, not just click state,
        // so a direct visit/refresh on /gamehub highlights GameHub (not Events).
        mobileActionBtns.forEach(b => b.classList.remove('is-active'));
        const isGamehubPath = /^\/gamehub(\/|$)/.test(window.location.pathname);
        const initialBtn = mobileActionBtns.find(b => isGamehubPath
            ? b.classList.contains('mobile-action-btn--gamehub')
            : b.classList.contains('mobile-action-btn--events'));
        (initialBtn || mobileActionBtns[0]).classList.add('is-active');
        // These are real anchor links — let the browser navigate natively.
        // We only nudge the active thumb on tap for instant feedback while the
        // next page loads (no preventDefault, so no slide-then-flash double action).
        mobileActionBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                mobileActionBtns.forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
            });
        });
        // Ensure initial body mode reflects the active button on load
        const active = mobileActionBtns.find(b => b.classList.contains('is-active'));
        if (active && document && document.body) {
            if (active.classList.contains('mobile-action-btn--events')) {
                document.body.classList.add('mode-events');
                updateFooterLordIcon('events');
            } else if (active.classList.contains('mobile-action-btn--gamehub')) {
                document.body.classList.add('mode-gamehub');
                updateFooterLordIcon('gamehub');
            }
        }
    }

    /** Update footer lord-icon colors to match current mode */
    function updateFooterLordIcon(mode) {
        try {
            const icon = document.querySelector('.footer-star-icon lord-icon');
            if (!icon) return;
            if (mode === 'events') {
                icon.setAttribute('colors', 'primary:#ffffff,secondary:#2563EB');
            } else if (mode === 'gamehub') {
                icon.setAttribute('colors', 'primary:#ffffff,secondary:#00C853');
            }
        } catch (e) { /* ignore */ }
    }

    /* ------------------------------------------------------------------ */
    /*  2. Login (Auth) Modal                                              */
    /* ------------------------------------------------------------------ */

    const loginModal    = document.getElementById('loginModal');
    const loginCard     = loginModal?.querySelector('.auth-modal__card');
    const loginBtn      = document.getElementById('loginBtn');
    const loginBackdrop = document.getElementById('loginBackdrop');
    const closeLoginBtn = document.getElementById('closeLogin');

    /** Show the login modal with a slight animation delay. */
    function openLoginModal() {
        if (!loginModal) return;
        loginModal.setAttribute('aria-hidden', 'false');
        loginModal.style.display = 'grid';
        // Now that the slot has a measurable width, GIS can size its button to fit.
        renderGoogleButtons();
        window.HaraanOverlay.push('login', closeLoginModal);
        setTimeout(() => loginCard?.classList.add('show'), 20);
    }

    /** Hide the login modal with a fade-out transition. */
    function closeLoginModal() {
        if (!loginModal) return;
        loginCard?.classList.remove('show');
        window.HaraanOverlay.pop('login');
        setTimeout(() => {
            loginModal.setAttribute('aria-hidden', 'true');
            loginModal.style.display = 'none';
        }, 300);
    }

    loginBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        openLoginModal();
    });
    // Guest taps on the mobile greeting's avatar / icons route into the same modal.
    document.querySelectorAll('[data-login-open]').forEach((el) => el.addEventListener('click', (e) => {
        e.preventDefault();
        openLoginModal();
    }));
    loginBackdrop?.addEventListener('click', closeLoginModal);
    closeLoginBtn?.addEventListener('click', closeLoginModal);
    // Re-open the modal after a failed email/password sign-in so the error shows
    // (the POST redirects back to whatever page the modal lives on).
    if (loginModal?.dataset.openOnError) { openLoginModal(); }

    /* ------------------------------------------------------------------ */
    /*  2a0. Page header back button                                       */
    /* ------------------------------------------------------------------ */

    /** Pops the history stack, but a page opened directly (shared link, new tab)
     *  has nothing to pop — fall back to the account screen rather than dead-end. */
    document.querySelector('[data-back]')?.addEventListener('click', () => {
        if (window.history.length > 1) window.history.back();
        else window.location.assign('/profile');
    });

    /* ------------------------------------------------------------------ */
    /*  2a. Member ID — tap to copy                                        */
    /* ------------------------------------------------------------------ */

    /** Mirrors the app's MemberIdBand: tapping the ID copies it. */
    document.querySelector('.aprof-memberid')?.addEventListener('click', async function () {
        const id = this.dataset.copy;
        if (!id) return;
        try {
            await navigator.clipboard.writeText(id);
        } catch {
            return; // clipboard blocked (insecure context / denied) — say nothing rather than lie
        }
        this.classList.add('is-copied');
        const label = this.querySelector('small');
        if (!label) return;
        const original = label.textContent;
        label.textContent = 'COPIED';
        setTimeout(() => {
            label.textContent = original;
            this.classList.remove('is-copied');
        }, 1400);
    });

    /* ------------------------------------------------------------------ */
    /*  2b. Continue with Google                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Draws the Google button into EVERY slot that's visible and not yet drawn.
     *
     * There are two slots on a page — the site-wide login drawer and the standalone
     * /login card — so this iterates `.auth-google__btn` rather than a single id
     * (ids can't be unique across both). GIS needs a real pixel width, and a hidden
     * drawer measures 0, so each slot is drawn only once it has width; safe to call
     * repeatedly (e.g. on modal open and once GIS lands).
     */
    function renderGoogleButtons() {
        const cfg = window.HaraanGoogleAuth;
        if (!cfg || !window.google?.accounts?.id) return;

        document.querySelectorAll('.auth-google__btn').forEach((slot) => {
            if (slot.dataset.gRendered) return;
            const width = slot.clientWidth;
            if (!width) return; // still hidden — we'll be called again on open
            slot.dataset.gRendered = '1';
            window.google.accounts.id.renderButton(slot, {
                theme: 'outline',
                size: 'large',
                shape: 'pill',
                text: 'continue_with',
                logo_alignment: 'center',
                width: Math.min(width, 400),
            });
        });
    }

    /**
     * Initialises Google Identity Services and trades the ID token it returns for a
     * Laravel session. GIS loads async, so we poll briefly rather than racing it.
     * A slot only exists when GOOGLE_CLIENT_ID is configured (see layout.blade.php).
     */
    (function initGoogleSignIn() {
        const cfg  = window.HaraanGoogleAuth;
        const slots = document.querySelectorAll('.auth-google__btn');
        if (!slots.length) return;
        // No OAuth config on this page (e.g. the visitor is already signed in, so
        // layout.blade.php omits HaraanGoogleAuth). Drop the empty Google slot so it
        // never leaves a blank gap where the button would be.
        if (!cfg) {
            document.querySelectorAll('.auth-google').forEach((el) => el.remove());
            return;
        }

        const showError = (msg) => {
            document.querySelectorAll('.auth-google__error').forEach((el) => {
                el.textContent = msg;
                el.hidden = false;
            });
        };

        /** Exchange the Google ID token for a session, then go where the user was headed. */
        async function onCredential(response) {
            document.querySelectorAll('.auth-google__error').forEach((el) => { el.hidden = true; });
            slots.forEach((s) => s.classList.add('is-busy'));
            try {
                const res = await fetch(cfg.postUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ credential: response.credential }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    showError(data.error || 'That sign-in did not work. Please try again.');
                    return;
                }
                window.location.assign(data.redirect || '/');
            } catch {
                showError('Network error. Please check your connection and try again.');
            } finally {
                slots.forEach((s) => s.classList.remove('is-busy'));
            }
        }

        let waited = 0;
        const timer = setInterval(() => {
            if (window.google?.accounts?.id) {
                clearInterval(timer);
                window.google.accounts.id.initialize({
                    client_id: cfg.clientId,
                    callback: onCredential,
                });
                // Draw now (standalone /login is already visible) and shortly after, to
                // catch a slot that gains width when the drawer opens.
                renderGoogleButtons();
                let tries = 0;
                const draw = setInterval(() => {
                    renderGoogleButtons();
                    if (++tries > 30) clearInterval(draw);
                }, 200);
            } else if ((waited += 100) > 6000) {
                // GIS blocked (offline, blocker, or an unauthorised origin) — leave the
                // other sign-in paths working instead of showing a dead button.
                clearInterval(timer);
                document.querySelectorAll('.auth-google').forEach((el) => el.remove());
            }
        }, 100);
    })();

    /* ------------------------------------------------------------------ */
    /*  3. Phone Login Form                                                */
    /* ------------------------------------------------------------------ */

    document.getElementById('phoneLoginForm')?.addEventListener('submit', (e) => {
        const phone = document.getElementById('phoneNumber')?.value ?? '';
        if (phone.length !== 10) {
            e.preventDefault();
            alert('Please enter a valid 10-digit mobile number');
        } else {
            const hiddenPhone = document.getElementById('hiddenPhoneField');
            if (hiddenPhone) {
                hiddenPhone.value = '91' + phone;
            }
        }
    });
});
