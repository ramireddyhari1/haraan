package com.haraan.app.ui.social

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.DiscoveredPlayer
import com.haraan.app.data.DiscoveryOutcome
import com.haraan.app.data.PlayerRepository
import com.haraan.app.data.TokenStore
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/**
 * Search-as-you-type over the player directory, with follow.
 *
 * Two things this deliberately gets right, because both are what make a search feel
 * cheap or expensive on a phone:
 *
 *  - **Debounce + cancel.** Every keystroke cancels the in-flight request before
 *    starting a new one, so typing "virat" fires one search, not five, and a slow
 *    early response can never overwrite a newer one.
 *  - **Optimistic follow that rolls back.** The button flips instantly, then settles
 *    on whatever the server actually says. A failed request reverts it — silently
 *    leaving a filled "Following" pill on a call that never landed is the one
 *    outcome worse than a slow button.
 */
class PlayerDiscoveryViewModel(app: Application) : AndroidViewModel(app) {

  private val repo = PlayerRepository()

  private val _state = MutableStateFlow(PlayerDiscoveryState())
  val state: StateFlow<PlayerDiscoveryState> = _state.asStateFlow()

  private var searchJob: Job? = null

  /** Guests can browse the directory but not follow — see TokenStore.isSignedIn. */
  private fun signedInToken(): String? = TokenStore.getSignedInToken(getApplication())

  init {
    _state.update { it.copy(isSignedIn = signedInToken() != null) }
  }

  fun onQueryChange(query: String) {
    _state.update { it.copy(query = query) }

    searchJob?.cancel()

    if (query.trim().length < 2) {
      _state.update {
        it.copy(results = emptyList(), isLoading = false, hasSearched = false, failed = false)
      }
      return
    }

    // The directory itself is behind auth — a guest cannot search at all, so don't
    // fire a request that is guaranteed to 401.
    val token = signedInToken()
    if (token == null) {
      _state.update { it.copy(isSignedIn = false, isLoading = false, hasSearched = false) }
      return
    }

    searchJob = viewModelScope.launch {
      // Long enough to swallow a fast typist's keystrokes, short enough that the
      // list feels like it is keeping up.
      delay(280)

      _state.update { it.copy(isLoading = true, failed = false) }

      val outcome = repo.discover(token, query)

      _state.update { current ->
        // A stale response from a query the user has since edited must not land.
        if (current.query != query) return@update current

        when (outcome) {
          is DiscoveryOutcome.Success -> current.copy(
            results = outcome.players, isLoading = false, hasSearched = true,
            sessionExpired = false, failed = false,
          )
          DiscoveryOutcome.Unauthorized -> current.copy(
            results = emptyList(), isLoading = false, hasSearched = false,
            sessionExpired = true,
          )
          DiscoveryOutcome.Failed -> current.copy(
            results = emptyList(), isLoading = false, hasSearched = false, failed = true,
          )
        }
      }
    }
  }

  fun clear() {
    searchJob?.cancel()
    _state.update { it.copy(query = "", results = emptyList(), isLoading = false, hasSearched = false) }
  }

  fun toggleFollow(player: DiscoveredPlayer) {
    val token = signedInToken() ?: return
    if (_state.value.pending.contains(player.playerId)) return

    val wasFollowing = _state.value.isFollowing(player)
    val optimistic = !wasFollowing

    _state.update {
      it.copy(
        following = it.following + (player.playerId to optimistic),
        pending = it.pending + player.playerId,
      )
    }

    viewModelScope.launch {
      val settled = repo.setFollowing(token, player.playerId, optimistic)

      _state.update {
        it.copy(
          // Null = the call failed; roll back rather than lie about the state.
          following = it.following + (player.playerId to (settled ?: wasFollowing)),
          pending = it.pending - player.playerId,
        )
      }
    }
  }
}
