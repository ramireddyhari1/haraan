package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.URL

data class VenueApiItem(
    val id: String,
    val name: String,
    val location: String,
    val rating: String,
    val category: String,
    val price: Int,
    val image: String?,
    val tagline: String,
    val distance: String,
    val isBookable: Boolean = false,
)

/** One bookable slot template for a venue (GET /api/venues/{id} → slots). */
data class VenueSlotItem(
    val id: Int,
    val day: String,
    val time: String,
    val available: Boolean,
    val fillingFast: Boolean,
)

class VenueRepository {
    suspend fun getVenues(): List<VenueApiItem> = withContext(Dispatchers.IO) {
        val body = URL("${ApiConfig.BASE_URL}/api/venues").readText()
        // Endpoint returns {"data":[...]}; tolerate a bare array too for safety.
        val arr = runCatching { JSONObject(body).optJSONArray("data") }.getOrNull()
            ?: runCatching { JSONArray(body) }.getOrNull()
            ?: JSONArray()
        (0 until arr.length()).map { i ->
            val o = arr.getJSONObject(i)
            VenueApiItem(
                id = o.optString("id"),
                name = o.optString("name"),
                location = o.optString("location"),
                rating = o.optString("rating"),
                category = o.optString("category"),
                price = o.optInt("price", 0),
                image = o.optString("image").takeIf { it.isNotBlank() && it != "null" },
                tagline = o.optString("tagline"),
                distance = o.optString("distance"),
                isBookable = o.optBoolean("is_bookable", false),
            )
        }
    }

    /** Slots for a venue, used by the venue-booking flow. Empty on failure. */
    suspend fun getVenueSlots(venueId: String): List<VenueSlotItem> = withContext(Dispatchers.IO) {
        val body = URL("${ApiConfig.BASE_URL}/api/venues/$venueId").readText()
        val data = JSONObject(body).optJSONObject("data") ?: return@withContext emptyList()
        val arr = data.optJSONArray("slots") ?: return@withContext emptyList()
        (0 until arr.length()).map { i ->
            val o = arr.getJSONObject(i)
            VenueSlotItem(
                id = o.optInt("id"),
                day = o.optString("day"),
                time = o.optString("time"),
                available = o.optBoolean("available", true),
                fillingFast = o.optBoolean("filling_fast", false),
            )
        }
    }
}
