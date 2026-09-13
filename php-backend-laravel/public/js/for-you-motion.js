/**
 * Haraan 'For You' HorizontalPager Fluid Motion Design System
 * Handcrafted Mobile Interaction & 3D Spatial Deck
 *
 * Design & Engineering Specifications:
 * - Handcrafted 3-card viewport: Left peek (prev), Center hero, Right peek (next)
 * - 1:1 Direct Manipulation Pointer/Touch tracking with sub-pixel floating kinematics
 * - Frictionless directional disambiguation (7px threshold) for vertical scroll pass-through
 * - Anti-gravity buoyant levitation, organic 3D tilt, and multi-plane depth parallax
 * - Momentum flick detection with critically damped spring deceleration
 * - Infinite seamless circular wrapping
 * - Adaptive iOS/Instagram-style morphing pill pagination dots
 * - Respectful auto-advance with 7s post-interaction reading grace period
 * - Full accessibility with ARIA attributes, keyboard controls, and reduced-motion fallback
 */

(function () {
    'use strict';

    // Kinematics & Physics Tuning
    const FLICK_VELOCITY_THRESHOLD = 0.30; // px/ms
    const DRAG_THRESHOLD_RATIO = 0.22;     // 22% of pitch commits to next/prev
    const SPRING_DURATION_MS = 400;        // Base settling time (ms)
    const AUTO_ADVANCE_INTERVAL_MS = 4600; // Auto-play cycle (ms)
    const USER_INTERACTION_PAUSE_MS = 7000;// Reading grace period after touch interaction (ms)

    class HandcraftedFluidPager {
        constructor(container) {
            this.container = container;
            this.track = container.querySelector('[data-mpager-track]') || container;
            this.rawCards = Array.from(this.track.querySelectorAll('[data-mpager-card], .mfy__page'));

            if (!this.rawCards.length) return;

            // Normalize card count for smooth circular looping
            this.setupCardRing();

            this.currentIndex = 0;
            this.targetIndex = 0;

            // Geometry metrics
            this.containerWidth = 0;
            this.cardWidth = 0;
            this.cardGap = 14;
            this.pitch = 0;

            // Gesture state
            this.isTracking = false;
            this.isSwiping = false;
            this.directionLocked = false;
            this.pointerId = null;
            this.startX = 0;
            this.startY = 0;
            this.currentX = 0;
            this.dragOffset = 0;
            this.maxDragDistance = 0;
            this.velocityHistory = [];

            // Animation state
            this.animating = false;
            this.animStartTime = 0;
            this.animDuration = SPRING_DURATION_MS;
            this.animStartOffset = 0;
            this.animTargetOffset = 0;
            this.rafId = null;

            // Timers & accessibility
            this.autoAdvanceTimer = null;
            this.lastInteractionTime = 0;
            this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // Pagination elements
            this.paginationContainer = null;
            this.dots = [];

            this.init();
        }

        /**
         * Clones cards when total items < 4 to guarantee seamless 3-card viewport
         * (left peek, active hero, right peek) in an infinite circular ring.
         */
        setupCardRing() {
            const initialCount = this.rawCards.length;
            this.originalTotal = initialCount;

            if (initialCount === 2) {
                // Duplicate both cards once to form a 4-node ring
                this.rawCards.forEach((c) => {
                    const clone = c.cloneNode(true);
                    clone.setAttribute('data-mpager-clone', 'true');
                    this.track.appendChild(clone);
                });
            } else if (initialCount === 3) {
                // 3 cards is a natural minimal ring; duplicate to 6 for ultra-wide peek stability
                this.rawCards.forEach((c) => {
                    const clone = c.cloneNode(true);
                    clone.setAttribute('data-mpager-clone', 'true');
                    this.track.appendChild(clone);
                });
            }

            this.cardElements = Array.from(this.track.querySelectorAll('[data-mpager-card], .mfy__page'));
            this.totalCards = this.cardElements.length;

            // Inject specular sheen if not already present in cards
            this.cardElements.forEach((card) => {
                const link = card.querySelector('.mfy');
                if (link && !link.querySelector('.mfy__sheen')) {
                    const sheen = document.createElement('span');
                    sheen.className = 'mfy__sheen';
                    sheen.setAttribute('aria-hidden', 'true');
                    link.appendChild(sheen);
                }
            });
        }

        init() {
            this.container.classList.add('mpager--fluid-ready');
            this.container.setAttribute('role', 'region');
            this.container.setAttribute('aria-roledescription', 'carousel');
            this.container.setAttribute('aria-label', 'For You Curated Events');

            this.measure();
            this.setupPagination();
            this.bindEvents();
            this.render(0, false);
            this.startAutoAdvance();
            this.updateAria();
        }

        measure() {
            const rect = this.container.getBoundingClientRect();
            this.containerWidth = Math.round(rect.width) || window.innerWidth;

            // Proportions matching Android InfiniteLoopBookPager & original web design
            // Card width: containerWidth - 112 (clamped between 250px and 310px)
            // Leaves 56px on each side. With 14px cardGap, the left and right neighbor
            // cards peek exactly 42px into the viewport and bleed smoothly off-screen.
            const targetWidth = Math.min(Math.round(this.containerWidth - 112), 310);
            this.cardWidth = Math.max(targetWidth, 250);
            this.cardGap = 14;
            this.pitch = this.cardWidth + this.cardGap;
            const cardHeight = Math.round((this.cardWidth * 4) / 3);

            // Center track with exact pixel positioning to ensure symmetrical peeks
            const trackLeft = Math.round((this.containerWidth - this.cardWidth) / 2);

            if (this.track) {
                this.track.style.top = '8px';
                this.track.style.left = `${trackLeft}px`;
                this.track.style.width = `${this.cardWidth}px`;
                this.track.style.height = `${cardHeight}px`;
            }
            this.container.style.height = `${cardHeight + 24}px`;
        }

        setupPagination() {
            const parent = this.container.closest('.mpager-container') || this.container.parentElement;
            if (!parent) return;

            let pag = parent.querySelector('.mfy-pagination');
            if (!pag) {
                pag = document.createElement('div');
                pag.className = 'mfy-pagination';
                pag.setAttribute('aria-hidden', 'true');
                const dotsWrapper = document.createElement('div');
                dotsWrapper.className = 'mfy-dots';
                pag.appendChild(dotsWrapper);
                this.container.after(pag);
            }

            this.paginationContainer = pag.querySelector('.mfy-dots');
            if (this.paginationContainer) {
                this.paginationContainer.innerHTML = '';
                this.dots = [];
                const dotCount = this.originalTotal; // Reflect real event count

                for (let i = 0; i < dotCount; i++) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = `mfy-dot ${i === 0 ? 'is-active' : ''}`;
                    dot.setAttribute('aria-label', `Go to slide ${i + 1} of ${dotCount}`);
                    dot.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.goTo(i, true);
                    });
                    this.paginationContainer.appendChild(dot);
                    this.dots.push(dot);
                }
            }
        }

        bindEvents() {
            this.onPointerDown = this.onPointerDown.bind(this);
            this.onPointerMove = this.onPointerMove.bind(this);
            this.onPointerUp = this.onPointerUp.bind(this);
            this.onKeyDown = this.onKeyDown.bind(this);
            this.onResize = this.onResize.bind(this);
            this.onVisibilityChange = this.onVisibilityChange.bind(this);

            this.track.addEventListener('pointerdown', this.onPointerDown, { passive: true });
            window.addEventListener('pointermove', this.onPointerMove, { passive: false });
            window.addEventListener('pointerup', this.onPointerUp, { passive: true });
            window.addEventListener('pointercancel', this.onPointerUp, { passive: true });

            this.container.setAttribute('tabindex', '0');
            this.container.addEventListener('keydown', this.onKeyDown);
            window.addEventListener('resize', this.onResize, { passive: true });
            document.addEventListener('visibilitychange', this.onVisibilityChange);

            this.container.addEventListener('mouseenter', () => this.stopAutoAdvance());
            this.container.addEventListener('mouseleave', () => this.startAutoAdvance());

            // Prevent accidental link navigation on deliberate swipes
            this.track.addEventListener(
                'click',
                (e) => {
                    if (this.maxDragDistance > 8) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                },
                true
            );
        }

        onPointerDown(e) {
            if (!e.isPrimary) return;

            this.lastInteractionTime = Date.now();
            this.stopAutoAdvance();

            // Catch running animation mid-flight with zero jitter
            if (this.animating) {
                this.cancelAnimation();
            }

            this.isTracking = true;
            this.isSwiping = false;
            this.directionLocked = false;
            this.pointerId = e.pointerId;
            this.startX = e.clientX;
            this.startY = e.clientY;
            this.currentX = e.clientX;
            this.dragOffset = 0;
            this.maxDragDistance = 0;
            this.velocityHistory = [{ x: e.clientX, t: performance.now() }];
        }

        onPointerMove(e) {
            if (!this.isTracking || e.pointerId !== this.pointerId) return;

            const dx = e.clientX - this.startX;
            const dy = e.clientY - this.startY;

            // Directional Lock (7px threshold): Differentiates vertical page scroll vs card swipe
            if (!this.directionLocked) {
                const distance = Math.hypot(dx, dy);
                if (distance > 7) {
                    this.directionLocked = true;
                    if (Math.abs(dy) > Math.abs(dx)) {
                        // Vertical scroll wins — surrender control to native browser scrolling instantly
                        this.isTracking = false;
                        return;
                    } else {
                        // Horizontal swipe locked
                        this.isSwiping = true;
                        try {
                            if (this.track.setPointerCapture) {
                                this.track.setPointerCapture(e.pointerId);
                            }
                        } catch (_) {}
                    }
                }
            }

            if (this.isSwiping) {
                if (e.cancelable) e.preventDefault();

                this.currentX = e.clientX;
                this.maxDragDistance = Math.max(this.maxDragDistance, Math.abs(dx));

                // Elastic rubber-banding if only 1 card exists
                if (this.totalCards <= 1) {
                    this.dragOffset = dx * 0.28;
                } else {
                    this.dragOffset = dx;
                }

                // Sample velocity in a sliding 100ms window
                const now = performance.now();
                this.velocityHistory.push({ x: e.clientX, t: now });
                while (this.velocityHistory.length > 1 && now - this.velocityHistory[0].t > 100) {
                    this.velocityHistory.shift();
                }

                this.scheduleRender();
            }
        }

        onPointerUp(e) {
            if (!this.isTracking || (this.pointerId !== null && e.pointerId !== this.pointerId)) return;

            this.isTracking = false;
            this.lastInteractionTime = Date.now();

            if (this.isSwiping) {
                this.isSwiping = false;
                try {
                    if (this.track.hasPointerCapture && this.track.hasPointerCapture(e.pointerId)) {
                        this.track.releasePointerCapture(e.pointerId);
                    }
                } catch (_) {}

                // Compute instantaneous release velocity (px/ms)
                let velocity = 0;
                if (this.velocityHistory.length >= 2) {
                    const first = this.velocityHistory[0];
                    const last = this.velocityHistory[this.velocityHistory.length - 1];
                    const dt = last.t - first.t;
                    if (dt > 10) {
                        velocity = (last.x - first.x) / dt;
                    }
                }

                const offset = this.dragOffset;
                const ratio = offset / (this.pitch || 1);
                let targetIndex = this.currentIndex;

                if (this.totalCards > 1) {
                    // Kinematic flick detection or distance threshold
                    if (velocity < -FLICK_VELOCITY_THRESHOLD || ratio < -DRAG_THRESHOLD_RATIO) {
                        targetIndex = (this.currentIndex + 1) % this.totalCards;
                    } else if (velocity > FLICK_VELOCITY_THRESHOLD || ratio > DRAG_THRESHOLD_RATIO) {
                        targetIndex = (this.currentIndex - 1 + this.totalCards) % this.totalCards;
                    }
                }

                this.animateTo(targetIndex, offset, velocity);
            }

            this.pointerId = null;
            this.startAutoAdvance();
        }

        onKeyDown(e) {
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                this.next();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                this.prev();
            }
        }

        onResize() {
            this.measure();
            this.render(0, false);
        }

        onVisibilityChange() {
            if (document.hidden) {
                this.stopAutoAdvance();
            } else {
                this.startAutoAdvance();
            }
        }

        scheduleRender() {
            if (this.rafId) return;
            this.rafId = requestAnimationFrame(() => {
                this.rafId = null;
                this.render(this.dragOffset, true);
            });
        }

        /**
         * High-order cubic-bezier spring settling with velocity injection
         */
        animateTo(targetIndex, initialOffset, velocity = 0) {
            this.animating = true;
            this.targetIndex = targetIndex;
            this.animStartOffset = initialOffset;

            let diff = targetIndex - this.currentIndex;
            // Shortest circular step
            if (diff > this.totalCards / 2) diff -= this.totalCards;
            if (diff < -this.totalCards / 2) diff += this.totalCards;

            this.animTargetOffset = -diff * this.pitch;
            this.animStartTime = performance.now();

            const distance = Math.abs(this.animTargetOffset - initialOffset);
            const baseDuration = this.reducedMotion ? 180 : SPRING_DURATION_MS;
            const speedAdjustment = Math.min(100, Math.abs(velocity) * 80);
            this.animDuration = Math.max(260, Math.round(baseDuration - speedAdjustment));

            const step = (now) => {
                const elapsed = now - this.animStartTime;
                const progress = Math.min(elapsed / this.animDuration, 1);

                // Handcrafted fluid cubic-bezier deceleration curve
                const ease = 1 - Math.pow(1 - progress, 3.8);
                const currentOffset = this.animStartOffset + (this.animTargetOffset - this.animStartOffset) * ease;

                this.dragOffset = currentOffset;
                this.render(currentOffset, false);

                if (progress < 1) {
                    this.rafId = requestAnimationFrame(step);
                } else {
                    this.animating = false;
                    this.currentIndex = (targetIndex + this.totalCards) % this.totalCards;
                    this.dragOffset = 0;
                    this.render(0, false);
                    this.updatePagination();
                    this.updateAria();
                }
            };

            if (this.rafId) cancelAnimationFrame(this.rafId);
            this.rafId = requestAnimationFrame(step);
        }

        cancelAnimation() {
            if (this.rafId) {
                cancelAnimationFrame(this.rafId);
                this.rafId = null;
            }
            this.animating = false;
        }

        goTo(originalIndex, animate = true) {
            if (originalIndex < 0 || originalIndex >= this.originalTotal) return;
            this.stopAutoAdvance();

            // Find closest index matching original index in the circular ring
            let targetIdx = originalIndex;
            if (this.totalCards > this.originalTotal) {
                const currentCycle = Math.floor(this.currentIndex / this.originalTotal);
                targetIdx = currentCycle * this.originalTotal + originalIndex;
            }

            if (animate) {
                this.animateTo(targetIdx, this.dragOffset, 0);
            } else {
                this.currentIndex = targetIdx;
                this.dragOffset = 0;
                this.render(0, false);
                this.updatePagination();
            }
            this.startAutoAdvance();
        }

        next() {
            const nextIdx = (this.currentIndex + 1) % this.totalCards;
            this.animateTo(nextIdx, this.dragOffset, -0.4);
        }

        prev() {
            const prevIdx = (this.currentIndex - 1 + this.totalCards) % this.totalCards;
            this.animateTo(prevIdx, this.dragOffset, 0.4);
        }

        /**
         * Handcrafted 3D Anti-Gravity & Multi-Plane Parallax Staging
         * Renders Left Peek, Center Hero, and Right Peek with physical precision.
         */
        render(offsetPx, isUserDragging = false) {
            const S = this.pitch || 320;
            const N = this.totalCards;
            const isFloating = isUserDragging || this.animating;
            this.container.classList.toggle('is-elevated', isFloating);

            this.updatePagination();

            for (let i = 0; i < N; i++) {
                const card = this.cardElements[i];
                if (!card) continue;

                // Shortest circular offset from current active card
                let diff = i - this.currentIndex;
                if (diff > N / 2) diff -= N;
                if (diff < -N / 2) diff += N;

                // Physical X position in pixels relative to center
                const cardX = diff * S + offsetPx;
                const k = cardX / S; // Normalized position (-1: left peek, 0: center, +1: right peek)

                // Sub-elements for multi-plane independent parallax
                const media = card.querySelector('.mfy__media') || card.querySelector('.mfy__img');
                const badges = card.querySelector('.mfy__cat');
                const rating = card.querySelector('.mfy__rating');
                const foot = card.querySelector('.mfy__foot');
                const sheen = card.querySelector('.mfy__sheen');

                // Reduced-motion fallback: Clean 2D horizontal translation
                if (this.reducedMotion) {
                    card.style.transform = `translate3d(${cardX.toFixed(1)}px, 0, 0)`;
                    card.style.opacity = Math.abs(k) > 1.4 ? '0' : (1 - Math.abs(k) * 0.35).toFixed(2);
                    card.style.zIndex = Math.round(10 - Math.abs(k));
                    continue;
                }

                // Cull off-screen cards cleanly to free GPU fill-rate
                if (Math.abs(k) > 2.2) {
                    card.style.transform = `translate3d(${cardX.toFixed(1)}px, 0, -100px)`;
                    card.style.opacity = '0';
                    card.style.pointerEvents = 'none';
                    continue;
                }

                card.style.pointerEvents = Math.abs(k) < 0.5 ? 'auto' : 'none';

                // -------------------------------------------------------------
                // 1. Anti-Gravity Dynamics & Organic 3D Levitation
                // -------------------------------------------------------------
                // Active hero card is 1.0; left/right peeking neighbors are 0.92
                const scale = Math.max(0.91, 1.0 - Math.min(Math.abs(k), 1.0) * 0.08);

                // Buoyant lift: ascends slightly when active or manipulated
                const activeFactor = Math.max(0, 1 - Math.min(Math.abs(k), 1.0));
                const ty = -activeFactor * 6;
                const tz = activeFactor * 18 - (1 - activeFactor) * 22;

                // Restrained, natural physical tilt (never extreme/cartoonish)
                const rotY = -Math.max(-3.8, Math.min(3.8, k * 3.4));
                const rotZ = Math.max(-1.8, Math.min(1.8, k * 1.4));

                // Opacity falloff: Center = 1.0, Neighbor peeks = 0.88, outer = fade
                const opacity = Math.max(0, Math.min(1, 1.15 - Math.abs(k) * 0.27));
                const zIndex = Math.round(30 - Math.abs(k) * 10);

                card.style.transform = `translate3d(${cardX.toFixed(2)}px, ${ty.toFixed(2)}px, ${tz.toFixed(2)}px) rotateY(${rotY.toFixed(2)}deg) rotateZ(${rotZ.toFixed(2)}deg) scale(${scale.toFixed(3)})`;
                card.style.opacity = opacity.toFixed(3);
                card.style.zIndex = Math.max(1, zIndex);

                // -------------------------------------------------------------
                // 2. Multi-Plane Independent Parallax Layers
                // -------------------------------------------------------------
                if (Math.abs(k) <= 1.4) {
                    // Layer 1: Poster Window Counter-Parallax (subtle window depth without breaking rounded clipping)
                    if (media) {
                        const imgPanX = -k * 10;
                        media.style.transform = `translate3d(${imgPanX.toFixed(2)}px, 0, 0) scale(1.04)`;
                        media.style.borderRadius = '30px';
                    }

                    // Layer 2: Floating Badges (elevated forward in Z)
                    if (badges) {
                        const badgeShiftX = k * 6;
                        badges.style.transform = `translate3d(${badgeShiftX.toFixed(2)}px, 0, 18px)`;
                    }
                    if (rating) {
                        const ratingShiftX = k * 6;
                        rating.style.transform = `translate3d(${ratingShiftX.toFixed(2)}px, 0, 18px)`;
                    }

                    // Layer 3: Floating Footer Metadata & Price (elevated forward in Z)
                    if (foot) {
                        const footShiftX = k * 4;
                        foot.style.transform = `translate3d(${footShiftX.toFixed(2)}px, 0, 14px)`;
                    }

                    // Layer 4: Ambient Specular Sheen (whisper-thin glass reflection)
                    if (sheen) {
                        const sheenX = -k * 65;
                        sheen.style.transform = `translate3d(${sheenX.toFixed(2)}px, 0, 8px)`;
                        sheen.style.opacity = (0.08 + activeFactor * 0.14).toFixed(2);
                    }
                }
            }
        }

        /**
         * Adaptive iOS / Instagram-style Pagination Dots
         * Center active pill, with subtle scaling on edge indicators
         */
        updatePagination() {
            if (!this.dots || !this.dots.length) return;

            const current = this.currentIndex % this.originalTotal;
            const N = this.originalTotal;

            this.dots.forEach((dot, i) => {
                let dist = Math.abs(i - current);
                if (dist > N / 2) dist = N - dist;

                const isActive = i === current;
                dot.classList.toggle('is-active', isActive);

                if (isActive) {
                    dot.style.width = '20px';
                    dot.style.opacity = '1';
                    dot.style.transform = 'scale(1)';
                } else if (dist === 1) {
                    dot.style.width = '6px';
                    dot.style.opacity = '0.55';
                    dot.style.transform = 'scale(1)';
                } else if (dist === 2) {
                    dot.style.width = '5px';
                    dot.style.opacity = '0.35';
                    dot.style.transform = 'scale(0.85)';
                } else {
                    dot.style.width = '4px';
                    dot.style.opacity = '0.2';
                    dot.style.transform = 'scale(0.7)';
                }
            });
        }

        updateAria() {
            this.cardElements.forEach((card, idx) => {
                const isSelected = idx === this.currentIndex;
                card.setAttribute('aria-hidden', (!isSelected).toString());
                const links = card.querySelectorAll('a, button');
                links.forEach((l) => {
                    if (isSelected) l.removeAttribute('tabindex');
                    else l.setAttribute('tabindex', '-1');
                });
            });
        }

        startAutoAdvance() {
            this.stopAutoAdvance();
            if (this.reducedMotion || this.totalCards <= 1) return;

            this.autoAdvanceTimer = setInterval(() => {
                if (document.hidden) return;
                if (Date.now() - this.lastInteractionTime < USER_INTERACTION_PAUSE_MS) {
                    return;
                }
                this.next();
            }, AUTO_ADVANCE_INTERVAL_MS);
        }

        stopAutoAdvance() {
            if (this.autoAdvanceTimer) {
                clearInterval(this.autoAdvanceTimer);
                this.autoAdvanceTimer = null;
            }
        }

        destroy() {
            this.stopAutoAdvance();
            this.cancelAnimation();
            this.track.removeEventListener('pointerdown', this.onPointerDown);
            window.removeEventListener('pointermove', this.onPointerMove);
            window.removeEventListener('pointerup', this.onPointerUp);
            window.removeEventListener('pointercancel', this.onPointerUp);
            window.removeEventListener('resize', this.onResize);
            document.removeEventListener('visibilitychange', this.onVisibilityChange);
        }
    }

    // Auto-mount on DOM ready
    function initAll() {
        const pagers = document.querySelectorAll('[data-mpager-fluid]');
        pagers.forEach((p) => {
            if (!p.__fluidMotionInstance) {
                p.__fluidMotionInstance = new HandcraftedFluidPager(p);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    window.HaraanFluidMotionPager = HandcraftedFluidPager;
})();
