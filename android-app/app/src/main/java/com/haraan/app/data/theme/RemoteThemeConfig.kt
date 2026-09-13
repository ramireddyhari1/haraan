package com.haraan.app.data.theme

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import retrofit2.http.GET

/** The home lanes a campaign can re-skin. [key] is the wire value, not the display label. */
enum class ThemeSection(val key: String) {
    Events("events"),

    /** GameHub. "Pulse" is the UI name; routes and keys elsewhere still say gamehub. */
    Pulse("pulse"),
}

/**
 * One campaign skin as served by GET /api/section-themes (backend: `SectionTheme::toApi`).
 *
 * Colours stay hex strings here — this is the wire model. Parsing, fallbacks and contrast
 * correction happen once, in `SectionTheme.resolve`, so nothing downstream ever sees a
 * malformed colour.
 */
@Serializable
data class RemoteThemeConfig(
    val id: Long,
    val section: String,
    @SerialName("campaign_name") val campaignName: String,
    val accent: AccentHex,
    /** Optional strip art under the header: an image, or a Lottie animation. */
    val decoration: Decoration? = null,
    /** Epoch seconds, inclusive. */
    @SerialName("valid_from") val validFrom: Long,
    /** Epoch seconds, exclusive. */
    @SerialName("valid_until") val validUntil: Long,
    val priority: Int = 0,
) {
    fun isValidAt(epochSeconds: Long): Boolean = epochSeconds in validFrom until validUntil
}

@Serializable
data class Decoration(
    val url: String,
    /** "image" | "lottie". Unknown future types are treated as images. */
    val type: String = "image",
)

/** `#RRGGBB` strings. Only [primary] is guaranteed; the rest are derived when absent. */
@Serializable
data class AccentHex(
    val primary: String,
    val deep: String? = null,
    val tint: String? = null,
    @SerialName("on_primary") val onPrimary: String? = null,
)

@Serializable
data class SectionThemesResponse(
    /** Server clock, epoch seconds — corrects a phone whose clock is off. */
    @SerialName("server_time") val serverTime: Long,
    val themes: List<RemoteThemeConfig> = emptyList(),
)

interface SectionThemeApi {
    @GET("api/section-themes")
    suspend fun sectionThemes(): SectionThemesResponse
}
