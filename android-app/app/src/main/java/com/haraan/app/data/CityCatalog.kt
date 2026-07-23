package com.haraan.app.data

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import java.net.URL

/** One catalog city (mirrors an entry in the server's public/data/cities.json). */
data class CatalogCity(val id: String, val name: String, val popular: Boolean)

/**
 * Single source of truth for the city list, shared by the location picker and the
 * detected-city normaliser. Backed by the same `public/data/cities.json` the admin
 * and website use, fetched once and cached in prefs; a bundled fallback keeps the
 * picker usable offline and on first launch.
 */
object CityCatalog {

    // Small offline fallback (a slice of cities.json incl. the AP/Telangana set that
    // was missing from the old hardcoded Tamil-Nadu picker list). Refreshed from the
    // server on first load.
    private val BUNDLED = listOf(
        "Hyderabad" to true, "Bengaluru" to true, "Chennai" to true, "Vijayawada" to true,
        "Visakhapatnam" to true, "Kochi" to true, "Coimbatore" to true, "Mysuru" to true,
        "Kadapa" to false, "Kurnool" to false, "Anantapur" to false, "Tirupati" to false,
        "Guntur" to false, "Nellore" to false, "Warangal" to false, "Karimnagar" to false,
        "Khammam" to false, "Rajahmundry" to false, "Kakinada" to false, "Eluru" to false,
        "Madurai" to false, "Trichy" to false, "Salem" to false, "Mangaluru" to false,
        "Hubballi" to false, "Belagavi" to false, "Kozhikode" to false, "Thiruvananthapuram" to false,
    ).map { CatalogCity(it.first.lowercase().replace(" ", "-"), it.first, it.second) }

    // Common geocoder-name → catalog-name aliases (geocoder often returns official
    // spellings the catalog doesn't use).
    private val ALIASES = mapOf(
        "cuddapah" to "Kadapa",
        "ysr" to "Kadapa",
        "ysr kadapa" to "Kadapa",
        "bangalore" to "Bengaluru",
        "mysore" to "Mysuru",
        "vizag" to "Visakhapatnam",
        "trivandrum" to "Thiruvananthapuram",
        "calicut" to "Kozhikode",
        "cochin" to "Kochi",
        "gulbarga" to "Kalaburagi",
        "belgaum" to "Belagavi",
        "hubli" to "Hubballi",
        "rajamahendravaram" to "Rajahmundry",
    )

    @Volatile private var cache: List<CatalogCity> = BUNDLED

    private fun prefs(context: Context) =
        context.getSharedPreferences("city_catalog", Context.MODE_PRIVATE)

    /** Load from prefs cache into memory. Cheap; call before reading the list. */
    fun warm(context: Context) {
        val raw = prefs(context).getString("cities_json", null) ?: return
        parse(raw)?.let { if (it.isNotEmpty()) cache = it }
    }

    /** Fetch the latest cities.json from the server and cache it. Silent on failure. */
    suspend fun refresh(context: Context) = withContext(Dispatchers.IO) {
        runCatching {
            val body = URL("${ApiConfig.BASE_URL}/data/cities.json").readText()
            parse(body)?.takeIf { it.isNotEmpty() }?.let { list ->
                cache = list
                prefs(context).edit().putString("cities_json", body).apply()
            }
        }
        Unit
    }

    fun all(): List<CityOption> = cache.map { CityOption(it.name) }

    fun popular(): List<CityOption> =
        cache.filter { it.popular }.map { CityOption(it.name) }
            .ifEmpty { cache.take(10).map { CityOption(it.name) } }

    /**
     * Map a raw geocoder city name to the catalog spelling so it matches how events
     * store their city (e.g. "Cuddapah"/"YSR Kadapa" → "Kadapa"). Falls back to the
     * raw name (title-cased) when there's no catalog match.
     */
    fun normalize(raw: String?): String {
        val r = raw?.trim().orEmpty()
        if (r.isEmpty()) return ""

        val lower = r.lowercase()
        ALIASES[lower]?.let { return it }
        ALIASES.entries.firstOrNull { lower.contains(it.key) }?.let { return it.value }

        cache.firstOrNull { it.name.equals(r, ignoreCase = true) }?.let { return it.name }
        cache.firstOrNull { lower.contains(it.name.lowercase()) || it.name.lowercase().contains(lower) }
            ?.let { return it.name }

        return r
    }

    private fun parse(json: String): List<CatalogCity>? = runCatching {
        val arr = JSONArray(json)
        (0 until arr.length()).mapNotNull { i ->
            val o = arr.optJSONObject(i) ?: return@mapNotNull null
            val name = o.optString("name").trim()
            if (name.isBlank()) return@mapNotNull null
            CatalogCity(
                id = o.optString("id", name.lowercase()),
                name = name,
                popular = o.optBoolean("popular", false),
            )
        }
    }.getOrNull()
}
