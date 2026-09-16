package com.haraan.app.data

import android.content.Context

/**
 * Device-local saves: venue favourites (the venue hero heart) and saved events (the
 * event hero bookmark). Persisted in plain SharedPreferences — these are non-sensitive
 * ids. Not yet synced to the server; a future /api/favorites can hydrate from these sets.
 */
object FavoritesStore {
  private const val PREFS_FILE = "haraan_favorites"
  private const val VENUES_KEY = "favorite_venue_ids"
  private const val EVENTS_KEY = "saved_event_ids"

  private fun prefs(context: Context) =
    context.getSharedPreferences(PREFS_FILE, Context.MODE_PRIVATE)

  fun isFavorite(context: Context, venueId: String): Boolean = contains(context, VENUES_KEY, venueId)

  /** Toggles the venue and returns the new favourite state. */
  fun toggle(context: Context, venueId: String): Boolean = toggle(context, VENUES_KEY, venueId)

  fun isEventSaved(context: Context, eventId: String): Boolean = contains(context, EVENTS_KEY, eventId)

  /** Toggles the event and returns the new saved state. */
  fun toggleEvent(context: Context, eventId: String): Boolean = toggle(context, EVENTS_KEY, eventId)

  private fun contains(context: Context, key: String, id: String): Boolean =
    prefs(context).getStringSet(key, emptySet())?.contains(id) == true

  private fun toggle(context: Context, key: String, id: String): Boolean {
    // Copy before mutating: the set getStringSet returns must not be modified in place.
    val current = prefs(context).getStringSet(key, emptySet())?.toMutableSet() ?: mutableSetOf()
    val nowSaved = if (current.contains(id)) {
      current.remove(id); false
    } else {
      current.add(id); true
    }
    prefs(context).edit().putStringSet(key, current).apply()
    return nowSaved
  }
}
