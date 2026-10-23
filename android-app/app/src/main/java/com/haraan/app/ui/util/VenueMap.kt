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

    /** A place resolved from coordinates. Any field may be blank. */
    data class Place(val city: String, val district: String, val area: String)

    /**
     * Reverse-geocode over the Geocoding WEB API.
     *
     * This exists because the on-device [android.location.Geocoder] is unreliable on
     * real handsets in a way it never is on an emulator: it needs a geocoder backend
     * the OEM may not ship, and its RPC throws IOException whenever the network is
     * poor. When it fails the app has real coordinates but no name for them, and the
     * user is shown "Unknown" — a fix that looks like no fix. Same key and endpoint as
     * [geocode], just the reverse direction.
     */
    suspend fun reverseGeocode(lat: Double, lng: Double): Place? = withContext(Dispatchers.IO) {
        if (!hasKey) return@withContext null
        val url = Uri.parse("https://maps.googleapis.com/maps/api/geocode/json").buildUpon()
            .appendQueryParameter("latlng", "$lat,$lng")
            .appendQueryParameter("key", key)
            .build()
            .toString()
        runCatching {
            val json = JSONObject(URL(url).readText())
            if (json.optString("status") != "OK") return@runCatching null
            val results = json.getJSONArray("results")
            if (results.length() == 0) return@runCatching null

            // Scan EVERY result, not just the first: the first is usually a street
            // address whose components stop at the street, while the locality lives on
            // a later, coarser result. Taking result[0] alone is why a naive port of
            // this returns a house number and no city.
            var city = ""
            var district = ""
            var area = ""
            for (i in 0 until results.length()) {
                val components = results.getJSONObject(i).optJSONArray("address_components") ?: continue
                for (j in 0 until components.length()) {
                    val c = components.getJSONObject(j)
                    val name = c.optString("long_name")
                    if (name.isBlank()) continue
                    val types = c.optJSONArray("types") ?: continue
                    val typeList = buildList { for (t in 0 until types.length()) add(types.optString(t)) }
                    when {
                        city.isBlank() && "locality" in typeList -> city = name
                        city.isBlank() && "postal_town" in typeList -> city = name
                        district.isBlank() && "administrative_area_level_2" in typeList -> district = name
                        area.isBlank() && ("sublocality_level_1" in typeList || "sublocality" in typeList) -> area = name
                        area.isBlank() && "neighborhood" in typeList -> area = name
                    }
                }
                if (city.isNotBlank() && district.isNotBlank() && area.isNotBlank()) break
            }

            // A district with no locality still names the place better than "Unknown".
            if (city.isBlank() && district.isBlank() && area.isBlank()) null
            else Place(city, district, area)
        }.getOrNull()
    }

    /** One row of a Places search: the place's own name and the "District, State" under it. */
    data class PlaceSuggestion(val placeId: String, val main: String, val secondary: String)

    /** A picked place, with the coordinates the search row doesn't carry. */
    data class PickedPlace(
        val name: String,
        val city: String,
        val district: String,
        val lat: Double,
        val lng: Double,
    )

    /**
     * Google Places (New) autocomplete, India only — the same search the web location
     * sheet runs, so a village or neighbourhood that isn't in cities.json is still
     * findable. [sessionToken] ties the keystrokes and the final [placeDetails] call
     * into one billed session. Empty on any failure.
     */
    suspend fun autocomplete(input: String, sessionToken: String): List<PlaceSuggestion> =
        withContext(Dispatchers.IO) {
            if (input.isBlank() || !hasKey) return@withContext emptyList()
            runCatching {
                val body = JSONObject()
                    .put("input", input)
                    .put("includedRegionCodes", org.json.JSONArray().put("in"))
                    .put("languageCode", "en")
                    .put("sessionToken", sessionToken)
                val conn = (URL("https://places.googleapis.com/v1/places:autocomplete")
                    .openConnection() as java.net.HttpURLConnection).apply {
                    requestMethod = "POST"
                    connectTimeout = 8_000
                    readTimeout = 8_000
                    doOutput = true
                    setRequestProperty("Content-Type", "application/json")
                    setRequestProperty("X-Goog-Api-Key", key)
                }
                try {
                    conn.outputStream.use { it.write(body.toString().toByteArray()) }
                    if (conn.responseCode !in 200..299) return@runCatching emptyList()
                    val suggestions = JSONObject(conn.inputStream.bufferedReader().readText())
                        .optJSONArray("suggestions") ?: return@runCatching emptyList()
                    buildList {
                        for (i in 0 until suggestions.length()) {
                            val p = suggestions.getJSONObject(i).optJSONObject("placePrediction") ?: continue
                            val id = p.optString("placeId")
                            val fmt = p.optJSONObject("structuredFormat")
                            val main = fmt?.optJSONObject("mainText")?.optString("text").orEmpty()
                                .ifBlank { p.optJSONObject("text")?.optString("text").orEmpty() }
                            val secondary = fmt?.optJSONObject("secondaryText")?.optString("text").orEmpty()
                            if (id.isNotBlank() && main.isNotBlank()) add(PlaceSuggestion(id, main, secondary))
                        }
                    }
                } finally {
                    conn.disconnect()
                }
            }.getOrDefault(emptyList())
        }

    /**
     * Coordinates and the town a picked suggestion belongs to. Areas often carry no
     * `locality` (Vaddeswaram comes back as a neighbourhood under Guntur's
     * administrative_area_level_3), so the town falls back down the admin levels.
     */
    suspend fun placeDetails(placeId: String, sessionToken: String): PickedPlace? =
        withContext(Dispatchers.IO) {
            if (placeId.isBlank() || !hasKey) return@withContext null
            runCatching {
                val url = Uri.parse("https://places.googleapis.com/v1/places/$placeId").buildUpon()
                    .appendQueryParameter("languageCode", "en")
                    .appendQueryParameter("sessionToken", sessionToken)
                    .build()
                    .toString()
                val conn = (URL(url).openConnection() as java.net.HttpURLConnection).apply {
                    connectTimeout = 8_000
                    readTimeout = 8_000
                    setRequestProperty("X-Goog-Api-Key", key)
                    setRequestProperty("X-Goog-FieldMask", "displayName,location,addressComponents")
                }
                try {
                    if (conn.responseCode !in 200..299) return@runCatching null
                    val json = JSONObject(conn.inputStream.bufferedReader().readText())
                    val loc = json.optJSONObject("location") ?: return@runCatching null
                    val byType = mutableMapOf<String, String>()
                    val components = json.optJSONArray("addressComponents")
                    if (components != null) {
                        for (i in 0 until components.length()) {
                            val c = components.getJSONObject(i)
                            val name = c.optString("longText")
                            val types = c.optJSONArray("types") ?: continue
                            for (t in 0 until types.length()) byType.putIfAbsent(types.optString(t), name)
                        }
                    }
                    val name = json.optJSONObject("displayName")?.optString("text").orEmpty()
                    val district = byType["administrative_area_level_2"].orEmpty()
                    val city = byType["locality"]
                        ?: byType["postal_town"]
                        ?: byType["administrative_area_level_3"]
                        ?: district.ifBlank { name }
                    PickedPlace(
                        name = name.ifBlank { city },
                        city = city,
                        district = district,
                        lat = loc.getDouble("latitude"),
                        lng = loc.getDouble("longitude"),
                    )
                } finally {
                    conn.disconnect()
                }
            }.getOrNull()
        }
}
