package com.haraan.app.ui.util

import android.net.Uri
import com.haraan.app.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.net.URL

/**
 * Helpers for the event-detail venue map preview.
 *
 * Coordinates are resolved in priority order by [EventVenueMap]:
 *   1. explicit event lat/lng from the API (host-set, exact),
 *   2. coordinates parsed out of a pasted Google Maps [mapLink],
 *   3. a Geocoding-API lookup of the venue string.
 *
 * The image itself is a Google **Static Maps** PNG loaded with Coil — no Maps SDK,
 * no manifest key, and it caches like any other remote image. Everything degrades
 * to nothing (the card hides) when the key is blank or no coordinates resolve.
 */
object VenueMap {

    val hasKey: Boolean get() = BuildConfig.GOOGLE_MAPS_API_KEY.isNotBlank()

    private val key: String get() = BuildConfig.GOOGLE_MAPS_API_KEY

    /** A red-marker static map centred on [lat],[lng]. Retina scale for crisp text. */
    fun staticMapUrl(
        lat: Double,
        lng: Double,
        widthPx: Int = 640,
        heightPx: Int = 320,
        zoom: Int = 15,
    ): String {
        val center = "$lat,$lng"
        return Uri.parse("https://maps.googleapis.com/maps/api/staticmap").buildUpon()
            .appendQueryParameter("center", center)
            .appendQueryParameter("zoom", zoom.toString())
            .appendQueryParameter("size", "${widthPx}x${heightPx}")
            .appendQueryParameter("scale", "2")
            .appendQueryParameter("maptype", "roadmap")
            .appendQueryParameter("markers", "color:red|$center")
            .appendQueryParameter("key", key)
            .build()
            .toString()
    }

    /**
     * Pull `lat,lng` out of a Google Maps URL when it carries them — handles the
     * common `@lat,lng,zoom`, `?q=lat,lng`, `query=lat,lng` and `!3dlat!4dlng`
     * shapes. Returns null for place-name / shortened links that have no coords.
     */
    fun coordsFromMapLink(mapLink: String?): Pair<Double, Double>? {
        val link = mapLink?.trim().orEmpty()
        if (link.isBlank()) return null

        val patterns = listOf(
            Regex("""@(-?\d+\.\d+),(-?\d+\.\d+)"""),
            Regex("""[?&](?:q|query|ll|center|daddr)=(-?\d+\.\d+),(-?\d+\.\d+)"""),
            Regex("""!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)"""),
        )
        for (p in patterns) {
            val m = p.find(link) ?: continue
            val lat = m.groupValues[1].toDoubleOrNull() ?: continue
            val lng = m.groupValues[2].toDoubleOrNull() ?: continue
            if (lat in -90.0..90.0 && lng in -180.0..180.0) return lat to lng
        }
        return null
    }

    /** Geocode a venue/address string to coordinates. Null on any failure. */
    suspend fun geocode(query: String): Pair<Double, Double>? = withContext(Dispatchers.IO) {
        if (query.isBlank() || !hasKey) return@withContext null
        val url = Uri.parse("https://maps.googleapis.com/maps/api/geocode/json").buildUpon()
            .appendQueryParameter("address", query)
            .appendQueryParameter("key", key)
            .build()
            .toString()
        runCatching {
            val json = JSONObject(URL(url).readText())
            if (json.optString("status") != "OK") return@runCatching null
            val loc = json.getJSONArray("results")
                .getJSONObject(0)
                .getJSONObject("geometry")
                .getJSONObject("location")
            loc.getDouble("lat") to loc.getDouble("lng")
        }.getOrNull()
    }
}
