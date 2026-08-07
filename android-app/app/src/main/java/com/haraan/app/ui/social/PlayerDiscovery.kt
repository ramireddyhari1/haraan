package com.haraan.app.ui.social

import androidx.compose.runtime.Immutable
import com.haraan.app.data.DiscoveredPlayer

/**
 * UI state for player discovery.
 *
 * `following` is held separately from the [DiscoveredPlayer] rows so an optimistic
 * toggle never has to rebuild the result list — the row reads its button state from
 * this map, which is also what lets a follow survive the user editing the query.
 */
@Immutable
data class PlayerDiscoveryState(
  val query: String = "",
  val results: List<DiscoveredPlayer> = emptyList(),
  val isLoading: Boolean = false,
  val hasSearched: Boolean = false,
  val isSignedIn: Boolean = true,
  /**
   * The server rejected our token. Distinct from "no results" — holding a token is
   * not the same as having a valid session, and reporting an expired one as an
   * empty directory sends people hunting for a spelling mistake.
   */
  val sessionExpired: Boolean = false,
  /** The request itself failed (offline, timeout, 5xx). */
  val failed: Boolean = false,
  /** playerId -> is following. Overrides whatever the server last said. */
  val following: Map<String, Boolean> = emptyMap(),
  /** playerIds with a follow request in flight — the button shows a spinner. */
  val pending: Set<String> = emptySet(),
) {
  val isTooShort: Boolean get() = query.trim().length < 2

  fun isFollowing(player: DiscoveredPlayer): Boolean =
    following[player.playerId] ?: (player.isFollowing == true)
}
