package com.haraan.app.ui.theme

import androidx.compose.ui.graphics.Color

object HaraanColors {
    val Background = Color(0xFFF4F7FB)        // Custom premium slate-tinted background (no generic SaaS gray)
    val Surface = Color(0xFFFFFFFF)           // Pure white container cards
    val TextPrimary = Color(0xFF0F172A)       // Midnight slate
    val TextSecondary = Color(0xFF64748B)     // Cool grey
    val TextMuted = Color(0xFF94A3B8)         // Slate 400
    val BorderLight = Color(0xFFE2E8F0)       // Clean divider borders
    
    // Dynamic Mode Branding
    val EventsBlue = Color(0xFF2563EB)        // Brand blue for Events
    val GameHubGreen = Color(0xFF2563EB)      // Pulse blue — SAME as EventsBlue by product decision (was #00C853 green).
    //                                        Name kept because it is referenced widely; it is no longer green.
    val GameHubDeep = Color(0xFF1E3A8A)       // Deep blue (was #1B5E20 forest) — hero band, headers, selected chips
    val LiveRed = Color(0xFFD32F2F)           // Single canonical "live" red (was split EF4444/D32F2F)
    val RatingGold = Color(0xFFF5A623)        // Single canonical rating-star gold — stars are gold everywhere, green is for actions only

    // ── Why this section exists ──────────────────────────────────────────────
    // 21 files were defining their OWN private palettes and the app carried 297
    // distinct hex values. That happened because this object was too thin to build a
    // screen from — no hero surface, no tint, no success colour, no tier scale — so
    // every screen invented its own and drifted. Anything a screen genuinely needs
    // belongs here, so reaching for the system is easier than hand-rolling a hex.

    /**
     * The one dark surface identity sits on — profile hero, and any future banner.
     * A SINGLE hue: the old profile hero ran navy → blue → green, and a gradient that
     * crosses the colour wheel is the most reliable tell of a stock template.
     * Swap this one value to go near-black; nothing else needs to change.
     */
    val HeroSurface = GameHubDeep
    val HeroSurfaceTop = Color(0xFF16296B)    // Slightly lifted top stop — same hue, no hue travel.

    /** On a dark hero. */
    val OnHero = Color(0xFFFFFFFF)
    val OnHeroMuted = Color(0xB3FFFFFF)       // 70% — handles, secondary lines
    val OnHeroFaint = Color(0x1FFFFFFF)       // 12% — inset chips and wells

    /** Success. Earned outcomes ONLY — a win, a verification. Never decoration. */
    val Success = Color(0xFF16A34A)
    val SuccessTint = Color(0xFFE9F7EF)

    val AccentTint = Color(0xFFEAF1FE)        // Quiet blue well behind accent text
    val Hairline = Color(0xFFEDF1F6)          // Lighter than BorderLight, for internal rules

    /** Inert well: search fields, icon buttons, unselected chips. */
    val Field = Color(0xFFF1F5F9)

    /** Attention, not failure — expiring, pending, "needs a look". */
    val Warning = Color(0xFFF59E0B)
    val WarningTint = Color(0xFFFFF6D5)

    /** Failure: validation errors, destructive confirmation. Distinct from LiveRed,
     *  which means a match is in progress and must not read as an error. */
    val Danger = Color(0xFFDC2626)
    val DangerTint = Color(0xFFFDECEF)

    /**
     * Player tiers. A progression you can read at a glance — Rookie is deliberately
     * NEUTRAL so later tiers mean something by contrast. "ROOKIE" in a saturated
     * signal green said "success" about having done nothing yet.
     */
    val TierRookie = Color(0xFF64748B)
    val TierProspect = Color(0xFF0EA5E9)
    val TierRising = Color(0xFF2563EB)
    val TierPro = Color(0xFF7C3AED)
    val TierElite = Color(0xFFB8860B)

    /** Tier colour by the label `deriveExtras` produces. */
    fun tier(label: String): Color = when (label.lowercase()) {
        "prospect" -> TierProspect
        "rising player" -> TierRising
        "pro" -> TierPro
        "elite" -> TierElite
        else -> TierRookie
    }
}
