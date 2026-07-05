/**
 * site.js — Haraan front-end interactions
 *
 * Extracted from the inline <script> in layout.blade.php.
 * Handles:
 *   1. Location-selector modal (open / close / fetch cities / search / select)
 *   2. Login (auth) modal (open / close)
 *   3. Phone-login form submission
 */

document.addEventListener('DOMContentLoaded', () => {
    /* ------------------------------------------------------------------ */
    /*  1. Location Modal                                                  */
    /* ------------------------------------------------------------------ */

    const locationModal    = document.getElementById('locationModal');
    const locationCard     = locationModal?.querySelector('.location-modal__card');
    const locationToggle   = document.getElementById('locationToggle');
    const locationBackdrop = document.getElementById('locationBackdrop');
    const closeLocationBtn = document.getElementById('closeLocation');
    const locationSearch   = document.getElementById('locationSearch');
    const popularGrid      = document.getElementById('popularCities');
    const allList          = document.getElementById('allCities');
    const useCurrentBtn    = document.getElementById('useCurrent');
    const alphaIndex       = document.getElementById('alphaIndex');

    /** Cache for the cities data fetched from /data/cities.json */
    let cachedCities = [];

    /** Show the location-selector modal and focus the search field. */
    function openLocationModal() {
        if (!locationModal) return;
        locationModal.setAttribute('aria-hidden', 'false');
        locationModal.style.display = 'block';
        locationCard?.classList.add('show');
        locationSearch?.focus();
    }

    /** Hide the location-selector modal. */
    function closeLocationModal() {
        if (!locationModal) return;
        locationModal.setAttribute('aria-hidden', 'true');
        locationModal.style.display = 'none';
        locationCard?.classList.remove('show');
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
        if (!popularGrid || !allList || !alphaIndex) return;

        popularGrid.innerHTML = '';
        allList.innerHTML     = '';
        alphaIndex.innerHTML  = '';

        // --- Popular cities (top 8) ---
        cities
            .filter(c => c.popular)
            .slice(0, 8)
            .forEach(c => popularGrid.appendChild(buildCityCard(c)));

        // --- All cities, sorted A-Z ---
        const sorted  = cities.slice().sort((a, b) => a.name.localeCompare(b.name));
        const letters = {};
        sorted.forEach(c => {
            const first = (c.name || '').charAt(0).toUpperCase();
            letters[first] = true;
            allList.appendChild(buildCityRow(c));
        });

        // --- Alphabet quick-jump sidebar ---
        'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').forEach(letter => {
            const btn       = document.createElement('button');
            btn.textContent = letter;
            btn.className   = letters[letter] ? '' : 'disabled';

            if (letters[letter]) {
                btn.addEventListener('click', () => filterByLetter(letter));
            }
            alphaIndex.appendChild(btn);
        });

        // --- Wire up the search input ---
        if (locationSearch) {
            locationSearch.value   = '';
            locationSearch.oninput = function () {
                const query = this.value.toLowerCase();
                Array.from(allList.children).forEach(row => {
                    const name = row.querySelector('strong').textContent.toLowerCase();
                    row.style.display = name.includes(query) ? '' : 'none';
                });
            };
        }
    }

    /** Build a card element for the "Popular Cities" grid. */
    function buildCityCard(city) {
        const btn = document.createElement('button');
        btn.className = 'city-card';
        btn.setAttribute('role', 'listitem');
        btn.innerHTML = `
            <div class="icon"><img src="${city.icon}" alt="${city.name}"/></div>
            <div>
                <strong>${city.name}</strong>
                <small style="color:#6b7280">${city.country}</small>
            </div>`;
        btn.addEventListener('click', () => selectCity(city));
        return btn;
    }

    /** Build a row element for the "All Cities" list. */
    function buildCityRow(city) {
        const div = document.createElement('div');
        div.className = 'city-row';
        div.setAttribute('role', 'listitem');
        div.innerHTML = `
            <div class="thumb"><img src="${city.icon}" alt="${city.name}"/></div>
            <div>
                <strong>${city.name}</strong>
                <small style="color:#6b7280">${city.country}</small>
            </div>`;
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
        } catch (_) {}
        closeLocationModal();
        // Reload so the server re-filters listings and updates the header pill.
        window.location.reload();
    }

    /** Filter the "All Cities" list to show only a given starting letter. */
    function filterByLetter(letter) {
        Array.from(allList.children).forEach(row => {
            const name = row.querySelector('strong').textContent;
            row.style.display = name.charAt(0).toUpperCase() === letter ? '' : 'none';
        });
    }

    // --- Location modal event wiring ---
    locationToggle?.addEventListener('click', (e) => {
        e.preventDefault();
        if (cachedCities.length) {
            renderCities(cachedCities);
            openLocationModal();
        } else {
            fetchCities().then(() => openLocationModal());
        }
    });

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
        const originalLabel = useCurrentBtn.textContent;
        const resetBtn = () => {
            useCurrentBtn.textContent = originalLabel;
            useCurrentBtn.disabled = false;
        };
        const fail = () => {
            resetBtn();
            alert('Could not detect your location. Please pick a city from the list.');
        };
        useCurrentBtn.textContent = 'Locating…';
        useCurrentBtn.disabled = true;

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
        setTimeout(() => loginCard?.classList.add('show'), 20);
    }

    /** Hide the login modal with a fade-out transition. */
    function closeLoginModal() {
        if (!loginModal) return;
        loginCard?.classList.remove('show');
        setTimeout(() => {
            loginModal.setAttribute('aria-hidden', 'true');
            loginModal.style.display = 'none';
        }, 300);
    }

    loginBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        openLoginModal();
    });
    loginBackdrop?.addEventListener('click', closeLoginModal);
    closeLoginBtn?.addEventListener('click', closeLoginModal);

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
