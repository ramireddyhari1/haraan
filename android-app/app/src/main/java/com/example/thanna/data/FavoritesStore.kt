package com.example.thanna.data

import android.content.Context

/**
 * Device-local venue favourites (the hero heart). Persisted in plain SharedPreferences —
 * these are non-sensitive ids. Not yet synced to the server; a future /api/favorites can
 * hydrate from this set.
 */
object FavoritesStore {
  private const val PREFS_FILE = "haraan_favorites"
  private const val KEY = "favorite_venue_ids"

  private fun prefs(context: Context) =
    context.getSharedPreferences(PREFS_FILE, Context.MODE_PRIVATE)

  fun isFavorite(context: Context, venueId: String): Boolean =
    prefs(context).getStringSet(KEY, emptySet())?.contains(venueId) == true

  /** Toggles the venue and returns the new favourite state. */
  fun toggle(context: Context, venueId: String): Boolean {
    val current = prefs(context).getStringSet(KEY, emptySet())?.toMutableSet() ?: mutableSetOf()
    val nowFavorite = if (current.contains(venueId)) {
      current.remove(venueId); false
    } else {
      current.add(venueId); true
    }
    prefs(context).edit().putStringSet(KEY, current).apply()
    return nowFavorite
  }
}
