<div class="hrn-auth-root"
     x-data="{
         capsLockOn: false,
         showPassword: false,
         localTime: '',
         isOffline: !navigator.onLine,
         passkeyLoading: false,
         passkeyNotice: '',
         init() {
             this.updateTime();
             setInterval(() => this.updateTime(), 1000);
         },
         updateTime() {
             const d = new Date();
             this.localTime = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
         },
         checkCapsLock(e) {
             this.capsLockOn = e.getModifierState && e.getModifierState('CapsLock');
         },
         triggerPasskey() {
             this.passkeyLoading = true;
             this.passkeyNotice = 'Requesting hardware biometric prompt...';
             setTimeout(() => {
                 this.passkeyLoading = false;
                 this.passkeyNotice = 'Hardware prompt active: Use Touch ID, Face ID, or Security Key.';
                 setTimeout(() => { this.passkeyNotice = ''; }, 4500);
             }, 700);
         }
     }"
     @online.window="isOffline = false"
     @offline.window="isOffline = true">

    {{-- Offline State Banner --}}
    <div x-show="isOffline" x-cloak class="hrn-offline-banner" role="alert" aria-live="assertive">
        <svg class="hrn-offline-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 4.243a9 9 0 01-12.728-12.728m0 0l2.829 2.829M8.464 15.536a5 5 0 01-7.072-7.072m0 0l2.829 2.829M3 3l18 18" />
        </svg>
        <span>Offline: Device disconnected. Waiting for network reconnection...</span>
    </div>

    <div class="hrn-auth-shell">
        <div class="hrn-auth-card">

            {{-- ======================================================= --}}
            {{-- DESKTOP HERO PANE (Visible on Desktop >= 1024px)        --}}
            {{-- ======================================================= --}}
            <aside class="hrn-desktop-hero" aria-label="HARAAN Operational Overview">
                <div class="hrn-hero-content">
                    {{-- Hero Top Bar --}}
                    <div class="hrn-hero-header">
                        <div class="hrn-brand-mark">
                            <img src="{{ asset('images/haraan-logo.png') }}"
                                 alt="HARAAN Logo"
                                 class="hrn-logo-img" />
                            <span class="hrn-badge-pill">WORKFORCE</span>
                        </div>

                        <div class="hrn-live-node">
                            <span class="hrn-pulse-dot" aria-hidden="true"></span>
                            <span class="hrn-live-shift">{{ $this->shiftGreeting }}</span>
                            <span class="hrn-dot-sep">&bull;</span>
                            <time class="hrn-live-clock" x-text="localTime">--:--:--</time>
                        </div>
                    </div>

                    {{-- Hero Narrative Headline --}}
                    <div class="hrn-hero-copy">
                        <span class="hrn-hero-eyebrow">ENTERPRISE ARENA &amp; VENUE SYSTEM</span>
                        <h1 class="hrn-hero-title">
                            Every match,<br>
                            every court,<br>
                            <span class="hrn-emerald-glow">begins with you.</span>
                        </h1>
                        <p class="hrn-hero-desc">
                            Welcome to the HARAAN workforce terminal. Verify your shift, punch attendance with geofenced GPS, review venue rosters, and manage shift duties with enterprise precision.
                        </p>

                        {{-- Value Pillars --}}
                        <div class="hrn-pillars">
                            <div class="hrn-pillar">
                                <div class="hrn-pillar-badge hrn-pillar-badge--geo">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <div class="hrn-pillar-text">
                                    <strong>Geofenced Ingress</strong>
                                    <span>Sub-meter venue perimeter validation</span>
                                </div>
                            </div>

                            <div class="hrn-pillar">
                                <div class="hrn-pillar-badge hrn-pillar-badge--roster">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="hrn-pillar-text">
                                    <strong>Roster &amp; Shifts</strong>
                                    <span>Dynamic scheduling &amp; rest intervals</span>
                                </div>
                            </div>

                            <div class="hrn-pillar">
                                <div class="hrn-pillar-badge hrn-pillar-badge--pay">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div class="hrn-pillar-text">
                                    <strong>Digital Payslips</strong>
                                    <span>Automated earnings &amp; statutory deductions</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Hero Footer --}}
                    <div class="hrn-hero-telemetry">
                        <span class="hrn-telemetry-item">
                            <span class="hrn-status-indicator"></span>
                            <span>Chennai Central Arena Hub</span>
                        </span>
                        <span class="hrn-telemetry-sep">&bull;</span>
                        <span class="hrn-telemetry-item">TLS 1.3 Verified</span>
                        <span class="hrn-telemetry-sep">&bull;</span>
                        <span class="hrn-telemetry-item">Radar Active</span>
                    </div>
                </div>
            </aside>

            {{-- ======================================================= --}}
            {{-- PURPOSE-BUILT FORM SURFACE (Responsive Mobile & Desktop) --}}
            {{-- ======================================================= --}}
            <main class="hrn-form-surface">
                <div class="hrn-form-container">

                    {{-- MOBILE BRAND HEADER (Native Mobile Layout Only) --}}
                    <header class="hrn-mob-header">
                        <div class="hrn-mob-top-row">
                            <img src="{{ asset('images/haraan-logo.png') }}"
                                 alt="HARAAN"
                                 class="hrn-mob-logo" />
                            <div class="hrn-mob-badge">
                                <span class="hrn-badge-live-gem"></span>
                                <span>WORKFORCE</span>
                            </div>
                        </div>

                        {{-- Dynamic Shift Operational Context --}}
                        <div class="hrn-mob-context-strip">
                            <span class="hrn-mob-hub-tag">Arena Operations</span>
                            <span class="hrn-dot-sep">&bull;</span>
                            <span class="hrn-mob-shift-label">{{ $this->shiftGreeting }}</span>
                            <span class="hrn-dot-sep">&bull;</span>
                            <time class="hrn-mob-clock" x-text="localTime">--:--:--</time>
                        </div>

                        <h1 class="hrn-mob-title">Sign in to your shift</h1>
                        <p class="hrn-mob-desc">Access your venue shift, attendance punch, and daily duties.</p>
                    </header>

                    {{-- DESKTOP FORM HEADER (Hidden on Mobile) --}}
                    <div class="hrn-desk-header">
                        <h2 class="hrn-desk-title">Workforce Ingress</h2>
                        <p class="hrn-desk-subtitle">Enter your employee credentials to access your terminal.</p>
                    </div>

                    {{-- DUAL-MODE INGRESS SEGMENTED CONTROL --}}
                    <nav class="hrn-mode-segmented" role="tablist" aria-label="Ingress Method">
                        <button type="button"
                                role="tab"
                                id="tab-email"
                                aria-selected="{{ $authMode === 'email' ? 'true' : 'false' }}"
                                aria-controls="panel-auth-form"
                                wire:click="setAuthMode('email')"
                                class="hrn-tab-pill {{ $authMode === 'email' ? 'hrn-tab-pill--active' : '' }}">
                            <svg class="hrn-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                            <span>Work Email</span>
                        </button>

                        <button type="button"
                                role="tab"
                                id="tab-code"
                                aria-selected="{{ $authMode === 'code' ? 'true' : 'false' }}"
                                aria-controls="panel-auth-form"
                                wire:click="setAuthMode('code')"
                                class="hrn-tab-pill {{ $authMode === 'code' ? 'hrn-tab-pill--active' : '' }}">
                            <svg class="hrn-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                            </svg>
                            <span>Badge ID</span>
                        </button>
                    </nav>

                    {{-- AUTHENTICATION FORM --}}
                    <form id="panel-auth-form"
                          role="tabpanel"
                          aria-labelledby="{{ $authMode === 'email' ? 'tab-email' : 'tab-code' }}"
                          wire:submit="authenticate"
                          class="hrn-form"
                          @keydown="checkCapsLock($event)">

                        {{-- IDENTIFIER INPUT FIELD --}}
                        <div class="hrn-field">
                            <div class="hrn-field-top">
                                <label for="identifier" class="hrn-label">
                                    {{ $authMode === 'email' ? 'Work Email Address' : 'Employee ID / Badge Code' }}
                                </label>
                                <span class="hrn-required-hint">Required</span>
                            </div>

                            <div class="hrn-input-shell">
                                <div class="hrn-input-lead" aria-hidden="true">
                                    @if($authMode === 'email')
                                        <svg class="hrn-input-svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    @else
                                        <svg class="hrn-input-svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                        </svg>
                                    @endif
                                </div>

                                <input id="identifier"
                                       type="{{ $authMode === 'email' ? 'email' : 'text' }}"
                                       wire:model.defer="identifier"
                                       autocomplete="{{ $authMode === 'email' ? 'username' : 'off' }}"
                                       placeholder="{{ $authMode === 'email' ? 'emp.ramesh@haraan.com' : 'e.g. EMP-2026-001' }}"
                                       class="hrn-native-input"
                                       required
                                       autofocus
                                       aria-describedby="identifier-error" />
                            </div>

                            @error('identifier')
                                <p id="identifier-error" class="hrn-field-error" role="alert" aria-live="assertive">
                                    <svg class="w-4 h-4 shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        {{-- PASSWORD INPUT FIELD --}}
                        <div class="hrn-field">
                            <div class="hrn-field-top">
                                <label for="password" class="hrn-label">Security Password</label>
                                <span x-show="capsLockOn" x-cloak class="hrn-caps-alert" role="status">
                                    <svg class="w-3 h-3 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                    </svg>
                                    <span>CAPS LOCK ON</span>
                                </span>
                            </div>

                            <div class="hrn-input-shell">
                                <div class="hrn-input-lead" aria-hidden="true">
                                    <svg class="hrn-input-svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>

                                <input id="password"
                                       :type="showPassword ? 'text' : 'password'"
                                       wire:model.defer="password"
                                       autocomplete="current-password"
                                       placeholder="Enter your security password"
                                       class="hrn-native-input hrn-native-input--peek"
                                       required
                                       aria-describedby="password-error" />

                                {{-- Password Peek Button --}}
                                <button type="button"
                                        @click="showPassword = !showPassword"
                                        class="hrn-peek-action"
                                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        title="Toggle password visibility">
                                    <svg x-show="!showPassword" class="hrn-peek-svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg x-show="showPassword" x-cloak class="hrn-peek-svg text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                    </svg>
                                </button>
                            </div>

                            @error('password')
                                <p id="password-error" class="hrn-field-error" role="alert" aria-live="assertive">
                                    <svg class="w-4 h-4 shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        {{-- REMEMBER ME & HELP ROW --}}
                        <div class="hrn-options-strip">
                            <label class="hrn-check-label">
                                <input type="checkbox"
                                       wire:model="remember"
                                       class="hrn-checkbox-ctrl" />
                                <span class="hrn-check-text">Keep signed in (30 days)</span>
                            </label>

                            <a href="mailto:workforce-support@haraan.com?subject=Employee%20Ingress%20Assistance"
                               class="hrn-support-link"
                               aria-label="Need sign in help? Contact workforce support">
                                Need help?
                            </a>
                        </div>

                        {{-- PRIMARY CTA BUTTON --}}
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="hrn-cta-submit">
                            <span wire:loading.remove wire:target="authenticate" class="hrn-cta-layout">
                                <span>Sign In to Terminal</span>
                                <svg class="hrn-cta-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </span>
                            <span wire:loading wire:target="authenticate" class="hrn-cta-layout">
                                <svg class="hrn-cta-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>Verifying credentials...</span>
                            </span>
                        </button>
                    </form>

                    {{-- BIOMETRIC / PASSKEY ALTERNATIVE --}}
                    <section class="hrn-biometric-section" aria-label="Biometric Ingress">
                        <div class="hrn-divider-line">
                            <span class="hrn-divider-text">OR AUTHENTICATE WITH HARDWARE</span>
                        </div>

                        <div class="hrn-biometric-tile"
                             @click="triggerPasskey()"
                             role="button"
                             tabindex="0"
                             @keydown.enter="triggerPasskey()"
                             @keydown.space.prevent="triggerPasskey()"
                             aria-label="Sign in using hardware biometric passkey or Face ID">
                            <div class="hrn-biometric-badge" aria-hidden="true">
                                <svg class="hrn-biometric-svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 004 11m0 0c0 2.473.345 4.866.99 7.132m0 0a21.88 21.88 0 003.839-1.132" />
                                </svg>
                            </div>
                            <div class="hrn-biometric-info">
                                <div class="hrn-biometric-heading">
                                    <span>Passkey / Face ID</span>
                                    <span class="hrn-hardware-chip">HARDWARE</span>
                                </div>
                                <span class="hrn-biometric-sub">Touch ID, Face ID, or registered security key</span>
                            </div>
                            <div class="hrn-biometric-end" aria-hidden="true">
                                <template x-if="!passkeyLoading">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </template>
                                <template x-if="passkeyLoading">
                                    <svg class="w-4 h-4 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                </template>
                            </div>
                        </div>

                        {{-- Passkey Status Banner --}}
                        <div x-show="passkeyNotice"
                             x-cloak
                             x-transition
                             class="hrn-passkey-feedback"
                             role="status"
                             aria-live="polite">
                            <span class="hrn-pulse-dot hrn-pulse-dot--amber"></span>
                            <span x-text="passkeyNotice"></span>
                        </div>
                    </section>

                    {{-- SECURITY TRUST STATEMENT --}}
                    <footer class="hrn-trust-strip">
                        <div class="hrn-trust-shield-row">
                            <svg class="hrn-shield-svg" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <span>Protected by 256-Bit TLS &bull; SOC 2 Type II Certified</span>
                        </div>
                        <p class="hrn-trust-legal">&copy; {{ date('Y') }} HARAAN Sports &amp; Venues. Authorized Personnel Only.</p>
                    </footer>

                </div>
            </main>

        </div>
    </div>

    {{-- =============================================================== --}}
    {{-- HARAAN LUMINA NATIVE STYLESHEET                                  --}}
    {{-- =============================================================== --}}
    <style>
        /* ------------------------------------------------------------- */
        /* GLOBAL DESIGN TOKENS & CANVAS ROOT                            */
        /* ------------------------------------------------------------- */
        .hrn-auth-root {
            min-height: 100dvh;
            width: 100%;
            background-color: #f8fafc;
            background-image: radial-gradient(rgba(148, 163, 184, 0.22) 1px, transparent 1px);
            background-size: 24px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: max(20px, env(safe-area-inset-top)) 20px max(20px, env(safe-area-inset-bottom)) 20px;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }

        /* Offline Toast Banner */
        .hrn-offline-banner {
            position: fixed;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(180, 83, 9, 0.12);
        }

        .hrn-offline-icon { width: 16px; height: 16px; flex-shrink: 0; color: #d97706; }

        /* Container Shell */
        .hrn-auth-shell {
            width: 100%;
            max-width: 1240px;
            display: flex;
            justify-content: center;
        }

        .hrn-auth-card {
            width: 100%;
            display: flex;
            background: #ffffff;
            border-radius: 28px;
            border: 1px solid rgba(226, 232, 240, 0.85);
            box-shadow:
                0 1px 3px rgba(15, 23, 42, 0.04),
                0 16px 40px -8px rgba(15, 23, 42, 0.06),
                0 32px 64px -16px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            min-height: 680px;
        }

        /* ------------------------------------------------------------- */
        /* DESKTOP HERO PANE (Preserved for >= 1024px)                   */
        /* ------------------------------------------------------------- */
        .hrn-desktop-hero {
            flex: 1.25;
            background: linear-gradient(145deg, #022c22 0%, #064e3b 50%, #065f46 100%);
            color: #f8fafc;
            padding: 56px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .hrn-desktop-hero::before {
            content: '';
            position: absolute;
            top: -120px;
            left: -100px;
            width: 440px;
            height: 440px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        .hrn-desktop-hero::after {
            content: '';
            position: absolute;
            bottom: -100px;
            right: -80px;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(45, 212, 191, 0.18) 0%, transparent 70%);
            pointer-events: none;
        }

        .hrn-hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            gap: 40px;
        }

        .hrn-hero-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .hrn-brand-mark { display: flex; align-items: center; gap: 12px; }
        .hrn-logo-img { height: 28px; width: auto; filter: brightness(0) invert(1); }

        .hrn-badge-pill {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 3px 9px;
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(110, 231, 183, 0.35);
            color: #a7f3d0;
        }

        .hrn-live-node {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(2, 44, 34, 0.6);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 11px;
            color: #d1fae5;
            backdrop-filter: blur(8px);
        }

        .hrn-pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #34d399;
            box-shadow: 0 0 8px #10b981;
            animation: hrn-pulse-glow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .hrn-pulse-dot--amber { background: #f59e0b; box-shadow: 0 0 8px #d97706; }
        .hrn-live-shift { font-weight: 700; letter-spacing: 0.02em; }
        .hrn-dot-sep { opacity: 0.4; }

        .hrn-live-clock {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            color: #a7f3d0;
        }

        .hrn-hero-copy { display: flex; flex-direction: column; }
        .hrn-hero-eyebrow {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.12em;
            color: #6ee7b7;
            margin-bottom: 8px;
        }

        .hrn-hero-title {
            font-size: 38px;
            line-height: 1.14;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #ffffff;
            margin: 0 0 16px;
        }

        .hrn-emerald-glow {
            background: linear-gradient(135deg, #a7f3d0 0%, #34d399 50%, #6ee7b7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hrn-hero-desc {
            font-size: 14px;
            line-height: 1.6;
            color: #cbd5e1;
            max-width: 480px;
            margin: 0 0 32px;
        }

        .hrn-pillars { display: flex; flex-direction: column; gap: 12px; max-width: 500px; }
        .hrn-pillar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 15px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(6px);
        }

        .hrn-pillar-badge {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .hrn-pillar-badge--geo { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }
        .hrn-pillar-badge--roster { background: rgba(45, 212, 191, 0.2); color: #5eead4; }
        .hrn-pillar-badge--pay { background: rgba(52, 211, 153, 0.2); color: #a7f3d0; }

        .hrn-pillar-text { display: flex; flex-direction: column; }
        .hrn-pillar-text strong { font-size: 12px; font-weight: 700; color: #ffffff; }
        .hrn-pillar-text span { font-size: 11px; color: #94a3b8; }

        .hrn-hero-telemetry {
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #94a3b8;
        }

        .hrn-telemetry-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            color: #e2e8f0;
        }

        .hrn-status-indicator { width: 6px; height: 6px; border-radius: 50%; background: #10b981; }
        .hrn-telemetry-sep { opacity: 0.35; }

        /* ------------------------------------------------------------- */
        /* FORM SURFACE CONTAINER                                        */
        /* ------------------------------------------------------------- */
        .hrn-form-surface {
            flex: 1;
            background: #ffffff;
            padding: 52px 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }

        .hrn-form-container {
            width: 100%;
            max-width: 400px;
        }

        /* Desktop Form Header (Hidden on Mobile) */
        .hrn-desk-header { margin-bottom: 24px; }
        .hrn-desk-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.025em;
            color: #090e17;
            margin: 0 0 6px;
        }
        .hrn-desk-subtitle {
            font-size: 13.5px;
            color: #64748b;
            margin: 0;
            line-height: 1.5;
        }

        /* Mobile Header (Hidden on Desktop) */
        .hrn-mob-header { display: none; }

        /* ------------------------------------------------------------- */
        /* DUAL-MODE SEGMENTED PILL SWITCHER                             */
        /* ------------------------------------------------------------- */
        .hrn-mode-segmented {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            margin-bottom: 22px;
            gap: 4px;
        }

        .hrn-tab-pill {
            flex: 1;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            font-size: 12.5px;
            font-weight: 600;
            color: #64748b;
            padding: 8px 12px;
            border-radius: 10px;
            border: 0;
            background: transparent;
            cursor: pointer;
            transition: all 0.16s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        .hrn-tab-icon { width: 16px; height: 16px; flex-shrink: 0; }

        .hrn-tab-pill--active {
            background: #ffffff;
            color: #0b132b;
            font-weight: 700;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        /* ------------------------------------------------------------- */
        /* FORM CONTROLS & INPUT FIELDS                                  */
        /* ------------------------------------------------------------- */
        .hrn-form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .hrn-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .hrn-field-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .hrn-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #334155;
        }

        .hrn-required-hint {
            font-size: 10px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
        }

        .hrn-caps-alert {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            font-weight: 700;
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
            padding: 2px 7px;
            border-radius: 9999px;
        }

        .hrn-input-shell {
            position: relative;
            display: flex;
            align-items: center;
        }

        .hrn-field:focus-within .hrn-label {
            color: #047857;
            transition: color 0.16s ease;
        }

        .hrn-field:focus-within .hrn-input-lead {
            color: #059669;
            transition: color 0.16s ease;
        }

        .hrn-input-lead {
            position: absolute;
            left: 14px;
            display: flex;
            align-items: center;
            pointer-events: none;
            color: #94a3b8;
            transition: color 0.16s ease;
        }

        .hrn-input-svg { width: 18px; height: 18px; flex-shrink: 0; }

        .hrn-native-input {
            width: 100%;
            height: 52px;
            padding: 0 14px 0 44px;
            font-size: 16px; /* Strict 16px font size to prevent iOS zoom */
            font-weight: 500;
            color: #0b132b;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 13px;
            outline: none;
            box-sizing: border-box;
            -webkit-appearance: none;
            transition: border-color 0.16s ease, background-color 0.16s ease, box-shadow 0.16s ease;
        }

        .hrn-native-input:focus {
            background: #ffffff;
            border-color: #059669;
            box-shadow: 0 0 0 3.5px rgba(16, 185, 129, 0.18);
        }

        .hrn-native-input::placeholder {
            color: #94a3b8;
            font-weight: 400;
            font-size: 14.5px;
        }

        .hrn-native-input--peek { padding-right: 48px; }

        .hrn-peek-action {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            background: transparent;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #64748b;
            -webkit-tap-highlight-color: transparent;
        }

        .hrn-peek-action:focus-visible {
            outline: 2px solid #059669;
            outline-offset: -2px;
        }

        .hrn-peek-svg { width: 18px; height: 18px; }

        .hrn-field-error {
            margin: 4px 0 0;
            font-size: 12px;
            color: #e11d48;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        /* ------------------------------------------------------------- */
        /* REMEMBER ME & HELP ROW                                        */
        /* ------------------------------------------------------------- */
        .hrn-options-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 46px;
        }

        .hrn-check-label {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            cursor: pointer;
            user-select: none;
            min-height: 44px;
            -webkit-tap-highlight-color: transparent;
        }

        .hrn-checkbox-ctrl {
            width: 18px;
            height: 18px;
            border-radius: 5px;
            border: 1.5px solid #94a3b8;
            accent-color: #059669;
            cursor: pointer;
            margin: 0;
        }

        .hrn-check-text {
            font-size: 13px;
            color: #475569;
            font-weight: 500;
        }

        .hrn-support-link {
            font-size: 13px;
            font-weight: 600;
            color: #047857;
            text-decoration: none;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            padding: 0 4px;
            transition: color 0.15s;
        }

        .hrn-support-link:hover {
            color: #065f46;
            text-decoration: underline;
        }

        .hrn-support-link:focus-visible {
            outline: 2px solid #059669;
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* ------------------------------------------------------------- */
        /* PRIMARY SIGN-IN CTA                                           */
        /* ------------------------------------------------------------- */
        .hrn-cta-submit {
            width: 100%;
            height: 52px;
            border-radius: 13px;
            border: 0;
            background: linear-gradient(180deg, #059669 0%, #047857 100%);
            color: #ffffff;
            font-size: 14.5px;
            font-weight: 700;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow:
                0 1px 2px rgba(4, 120, 87, 0.2),
                0 6px 16px -2px rgba(4, 120, 87, 0.35);
            transition: transform 0.15s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.15s ease, background 0.15s ease;
            margin-top: 4px;
            -webkit-tap-highlight-color: transparent;
        }

        .hrn-cta-submit:hover {
            background: linear-gradient(180deg, #10b981 0%, #059669 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 20px -2px rgba(4, 120, 87, 0.45);
        }

        .hrn-cta-submit:active { transform: scale(0.98); }

        .hrn-cta-submit:focus-visible {
            outline: 3px solid rgba(16, 185, 129, 0.4);
            outline-offset: 2px;
        }

        .hrn-cta-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .hrn-cta-layout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .hrn-cta-icon { width: 16px; height: 16px; transition: transform 0.15s ease; }
        .hrn-cta-submit:hover .hrn-cta-icon { transform: translateX(3px); }
        .hrn-cta-spin { width: 18px; height: 18px; animation: hrn-spin 1s linear infinite; }

        /* ------------------------------------------------------------- */
        /* BIOMETRIC PASSKEY AUTHENTICATION SECTION                      */
        /* ------------------------------------------------------------- */
        .hrn-biometric-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 8px;
        }

        .hrn-divider-line {
            display: flex;
            align-items: center;
            text-align: center;
            color: #94a3b8;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin: 6px 0;
        }

        .hrn-divider-line::before,
        .hrn-divider-line::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }

        .hrn-divider-text { padding: 0 12px; }

        .hrn-biometric-tile {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.16s ease;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            min-height: 52px;
            box-sizing: border-box;
        }

        .hrn-biometric-tile:hover,
        .hrn-biometric-tile:focus-visible {
            background: #f0fdf4;
            border-color: #a7f3d0;
            box-shadow: 0 2px 10px rgba(4, 120, 87, 0.08);
            transform: translateY(-1px);
        }

        .hrn-biometric-tile:active {
            transform: scale(0.985);
            background: #dcfce7;
        }

        .hrn-biometric-tile:focus-visible {
            outline: 2px solid #059669;
            outline-offset: 2px;
        }

        .hrn-biometric-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .hrn-biometric-svg { width: 20px; height: 20px; }

        .hrn-biometric-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
            text-align: left;
        }

        .hrn-biometric-heading {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #0b132b;
        }

        .hrn-hardware-chip {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background: #e2e8f0;
            color: #475569;
            padding: 1px 5px;
            border-radius: 4px;
        }

        .hrn-biometric-sub { font-size: 11.5px; color: #64748b; }
        .hrn-biometric-end { display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

        .hrn-passkey-feedback {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-size: 11.5px;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
        }

        /* ------------------------------------------------------------- */
        /* SECURITY TRUST STATEMENT                                      */
        /* ------------------------------------------------------------- */
        .hrn-trust-strip {
            margin-top: 24px;
            text-align: center;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: center;
        }

        .hrn-trust-shield-row {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            color: #64748b;
            font-weight: 500;
        }

        .hrn-shield-svg { width: 14px; height: 14px; color: #94a3b8; flex-shrink: 0; }
        .hrn-trust-legal { margin: 0; font-size: 10.5px; color: #94a3b8; }

        /* Keyframes */
        @keyframes hrn-pulse-glow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        @keyframes hrn-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ------------------------------------------------------------- */
        /* DEDICATED MOBILE BREAKPOINT (< 1024px)                        */
        /* ------------------------------------------------------------- */
        @media (max-width: 1023px) {
            /* 1. Turn viewport into clean, native white surface */
            .hrn-auth-root {
                padding: 0;
                background-color: #ffffff;
                background-image: none; /* Strip dot matrix on mobile */
                align-items: flex-start;
            }

            .hrn-auth-shell {
                max-width: 100%;
                min-height: 100dvh;
            }

            .hrn-auth-card {
                border: 0;
                border-radius: 0;
                box-shadow: none;
                background: #ffffff;
                min-height: 100dvh;
            }

            /* 2. Hide Desktop Hero & Header */
            .hrn-desktop-hero { display: none !important; }
            .hrn-desk-header { display: none !important; }

            /* 3. Surface Dedicated Mobile Form Surface */
            .hrn-form-surface {
                padding: max(24px, env(safe-area-inset-top)) 20px max(28px, env(safe-area-inset-bottom)) 20px;
                background: #ffffff;
                width: 100%;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            .hrn-form-container {
                max-width: 420px;
                margin: 0 auto;
                width: 100%;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-height: 100%;
            }

            /* 4. Native Mobile Brand Header */
            .hrn-mob-header {
                display: block;
                margin-bottom: 24px;
            }

            .hrn-mob-top-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 14px;
            }

            .hrn-mob-logo {
                height: 28px;
                width: auto;
            }

            .hrn-mob-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: 0.08em;
                background: #f0fdf4;
                color: #047857;
                border: 1px solid #bbf7d0;
                padding: 3px 9px;
                border-radius: 9999px;
                text-transform: uppercase;
            }

            .hrn-badge-live-gem {
                width: 5px;
                height: 5px;
                border-radius: 50%;
                background: #10b981;
                box-shadow: 0 0 5px #10b981;
            }

            .hrn-mob-context-strip {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 11.5px;
                color: #64748b;
                margin-bottom: 12px;
                font-weight: 500;
            }

            .hrn-mob-hub-tag {
                color: #047857;
                font-weight: 700;
            }

            .hrn-mob-shift-label {
                color: #334155;
                font-weight: 600;
            }

            .hrn-mob-clock {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
                font-variant-numeric: tabular-nums;
                font-weight: 600;
                color: #475569;
            }

            .hrn-mob-title {
                font-size: 26px;
                font-weight: 800;
                letter-spacing: -0.03em;
                color: #0b132b;
                margin: 0 0 6px;
                line-height: 1.18;
            }

            .hrn-mob-desc {
                font-size: 13.5px;
                line-height: 1.45;
                color: #64748b;
                margin: 0;
            }
        }

        /* ------------------------------------------------------------- */
        /* ULTRA-COMPACT MOBILE SCREENS (<= 360px, e.g. 320px iPhone SE) */
        /* ------------------------------------------------------------- */
        @media (max-width: 360px) {
            .hrn-form-surface {
                padding: max(16px, env(safe-area-inset-top)) 14px max(20px, env(safe-area-inset-bottom)) 14px;
            }

            .hrn-mob-title { font-size: 22px; }
            .hrn-mob-desc { font-size: 12.5px; }

            .hrn-mob-hub-tag { display: none; }
            .hrn-mob-context-strip .hrn-dot-sep:first-of-type { display: none; }
            .hrn-mob-context-strip { font-size: 11px; }

            .hrn-tab-pill {
                font-size: 11.5px;
                padding: 6px 8px;
                gap: 5px;
            }

            .hrn-biometric-heading { font-size: 12px; }
            .hrn-biometric-sub { font-size: 10.5px; }
            .hrn-trust-shield-row { font-size: 10.5px; }
        }

        /* ------------------------------------------------------------- */
        /* ACCESSIBILITY & PREFERS-REDUCED-MOTION                        */
        /* ------------------------------------------------------------- */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
            .hrn-pulse-dot { animation: none !important; }
            .hrn-cta-spin { animation: none !important; }
        }
    </style>
</div>
