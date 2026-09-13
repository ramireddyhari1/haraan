package com.haraan.app.ui.theme

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.AnimationSpec
import androidx.compose.animation.core.snap
import androidx.compose.animation.core.tween
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.compositionLocalOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.withFrameNanos
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.luminance
import com.haraan.app.data.theme.RemoteThemeConfig
import com.haraan.app.data.theme.ThemeSection

/**
 * The accent palette one home lane (Events or Pulse) paints with — the built-in brand blue,
 * or a campaign's colours while one is live. Screens read it through [LocalSectionTheme]
 * instead of reaching for `HaraanColors.EventsBlue` / `GameHubGreen` directly.
 *
 * Only accents are server-driven. Surfaces, text and status colours (live red, success,
 * danger) stay compile-time: a campaign can change the mood of a lane, never what "live" or
 * "failed" looks like.
 */
@Immutable
data class SectionTheme(
    /** CTAs, selected chips, the active switch pill, banner fill. */
    val primary: Color,
    /** Selected sport chips, headers, emphasis text on light surfaces. */
    val deep: Color,
    /** Soft wash behind the lane header. */
    val tint: Color,
    /** Text/icons on [primary]. Always meets [MIN_TEXT_CONTRAST] against it. */
    val onPrimary: Color,
    /** Text/icons on [deep]. Always meets [MIN_TEXT_CONTRAST] against it. */
    val onDeep: Color,
    /** Accent-coloured TEXT on white surfaces (dates, prices, labels). Always legible. */
    val accentText: Color,
    /** Present only while a campaign is live on this lane. */
    val campaign: Campaign? = null,
) {
    @Immutable
    data class Campaign(
        val id: Long,
        /** Not drawn — used as the decoration's accessibility description. */
        val name: String,
        val decorationUrl: String?,
        val decorationIsLottie: Boolean,
    )

    companion object {
        /** WCAG AA for normal-size text. */
        const val MIN_TEXT_CONTRAST = 4.5f

        val EventsDefault = SectionTheme(
            primary = HaraanColors.EventsBlue,
            deep = HaraanColors.GameHubDeep,
            tint = HaraanColors.AccentTint,
            onPrimary = Color.White,
            onDeep = Color.White,
            accentText = HaraanColors.EventsBlue,
        )

        val PulseDefault = SectionTheme(
            primary = HaraanColors.GameHubGreen,
            deep = HaraanColors.GameHubDeep,
            tint = HaraanColors.AccentTint,
            onPrimary = Color.White,
            onDeep = Color.White,
            accentText = HaraanColors.GameHubGreen,
        )

        fun defaultFor(section: ThemeSection): SectionTheme = when (section) {
            ThemeSection.Events -> EventsDefault
            ThemeSection.Pulse -> PulseDefault
        }

        /**
         * Turn a wire config into a paintable theme. Never fails: a missing config or an
         * unparseable primary yields [fallback]; a bad optional colour is derived from the
         * primary; admin-chosen text colours that would be unreadable are replaced.
         */
        fun resolve(remote: RemoteThemeConfig?, fallback: SectionTheme): SectionTheme {
            remote ?: return fallback
            val primary = parseHexColor(remote.accent.primary) ?: return fallback
            val deep = parseHexColor(remote.accent.deep) ?: lerp(primary, Color.Black, 0.35f)
            val tint = parseHexColor(remote.accent.tint) ?: lerp(primary, Color.White, 0.90f)
            return SectionTheme(
                primary = primary,
                deep = deep,
                tint = tint,
                onPrimary = readableOn(primary, parseHexColor(remote.accent.onPrimary)),
                onDeep = readableOn(deep, null),
                accentText = listOf(primary, deep)
                    .firstOrNull { contrastRatio(it, Color.White) >= MIN_TEXT_CONTRAST }
                    ?: HaraanColors.TextPrimary,
                campaign = Campaign(
                    id = remote.id,
                    name = remote.campaignName.trim(),
                    decorationUrl = remote.decoration?.url?.trim()?.takeIf { it.isNotEmpty() },
                    decorationIsLottie = remote.decoration?.type.equals("lottie", ignoreCase = true),
                ),
            )
        }

        /** [preferred] when it is legible on [background]; otherwise the more legible of white / ink. */
        internal fun readableOn(background: Color, preferred: Color?): Color {
            if (preferred != null && contrastRatio(preferred, background) >= MIN_TEXT_CONTRAST) return preferred
            val ink = HaraanColors.TextPrimary
            return if (contrastRatio(Color.White, background) >= contrastRatio(ink, background)) Color.White else ink
        }

        internal fun contrastRatio(a: Color, b: Color): Float {
            val la = a.luminance()
            val lb = b.luminance()
            return (maxOf(la, lb) + 0.05f) / (minOf(la, lb) + 0.05f)
        }
    }
}

/**
 * Parse `#RRGGBB` / `#AARRGGBB` (hash optional). Null for anything else — callers decide
 * the fallback, so a typo in /control can never paint an invalid colour.
 */
fun parseHexColor(hex: String?): Color? {
    val h = hex?.trim()?.removePrefix("#")?.takeIf { it.isNotEmpty() } ?: return null
    if (!h.all { it.isDigit() || it.lowercaseChar() in 'a'..'f' }) return null
    val v = h.toLongOrNull(16) ?: return null
    return when (h.length) {
        6 -> Color(0xFF000000L or v)
        8 -> Color(v)
        else -> null
    }
}

/**
 * The active lane theme. Outside a [ProvideSectionTheme] it is the Events default, so a
 * composable previewed or reused elsewhere still paints the brand palette, never nothing.
 */
val LocalSectionTheme = compositionLocalOf { SectionTheme.EventsDefault }

/**
 * Provide [theme] to [content], cross-fading accents when a campaign starts, ends, or the
 * user switches lanes. [theme] null means the cache hasn't been read yet: [fallback] shows,
 * and the first real value SNAPS in rather than animating, so a campaign doesn't visibly
 * "arrive" on every cold start.
 */
@Composable
fun ProvideSectionTheme(
    theme: SectionTheme?,
    fallback: SectionTheme,
    content: @Composable () -> Unit,
) {
    val target = theme ?: fallback
    var animate by remember { mutableStateOf(false) }
    LaunchedEffect(theme != null) {
        if (theme != null && !animate) {
            withFrameNanos { }
            animate = true
        }
    }
    val spec: AnimationSpec<Color> = if (animate) tween(durationMillis = 450) else snap()

    val primary by animateColorAsState(target.primary, spec, label = "sectionPrimary")
    val deep by animateColorAsState(target.deep, spec, label = "sectionDeep")
    val tint by animateColorAsState(target.tint, spec, label = "sectionTint")
    val onPrimary by animateColorAsState(target.onPrimary, spec, label = "sectionOnPrimary")
    val onDeep by animateColorAsState(target.onDeep, spec, label = "sectionOnDeep")
    val accentText by animateColorAsState(target.accentText, spec, label = "sectionAccentText")

    val provided = SectionTheme(primary, deep, tint, onPrimary, onDeep, accentText, target.campaign)
    CompositionLocalProvider(LocalSectionTheme provides provided, content = content)
}
