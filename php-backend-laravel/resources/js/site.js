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
        const label = document.querySelector('.location-pill__label');
        if (label) {
            label.querySelector('strong').textContent = city.name;
            label.querySelector('small').textContent  = city.country;
        }
        try { localStorage.setItem('bv_selected_city', JSON.stringify(city)); } catch (_) {}
        closeLocationModal();
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

    useCurrentBtn?.addEventListener('click', () => {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((pos) => {
            alert(
                'Using current location — lat:' +
                pos.coords.latitude.toFixed(3) +
                ', lon:' +
                pos.coords.longitude.toFixed(3)
            );
            closeLocationModal();
        });
    });

    // Close location modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLocationModal();
    });

    // Restore previously selected city on load
    loadSelectedCity();

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
        e.preventDefault();
        const phone = document.getElementById('phoneNumber')?.value ?? '';
        if (phone.length === 10) {
            alert('OTP sent to ' + phone);
        } else {
            alert('Please enter a valid 10-digit mobile number');
        }
    });
});
