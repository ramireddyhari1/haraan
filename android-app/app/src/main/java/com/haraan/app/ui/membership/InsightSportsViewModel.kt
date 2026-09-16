package com.haraan.app.ui.membership

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.TokenStore
import com.haraan.app.data.membership.InsightSportsStatus
import com.haraan.app.data.membership.MembershipException
import com.haraan.app.data.membership.MembershipRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class InsightSportsUiState(
    val loading: Boolean = true,
    val loadError: String? = null,
    val signedIn: Boolean = true,
    val status: InsightSportsStatus? = null,
    val draft: Set<String> = emptySet(),
    val saving: Boolean = false,
    /** A one-off line for a toast; cleared once shown. */
    val message: String? = null,
    /** Set right after a successful save, for the confirmation state. */
    val justSaved: Boolean = false,
)

class InsightSportsViewModel(app: Application) : AndroidViewModel(app) {

    private val repo = MembershipRepository()
    private val _state = MutableStateFlow(InsightSportsUiState())
    val state: StateFlow<InsightSportsUiState> = _state.asStateFlow()

    private fun token(): String? {
        val app = getApplication<Application>()
        var tok = TokenStore.getSignedInToken(app)
        if (tok == null && com.haraan.app.BuildConfig.DEBUG) {
            tok = DEV_FALLBACK_TOKEN
            TokenStore.saveToken(app, tok)
        }
        return tok
    }

    /**
     * Called each time the screen is entered. The ViewModel can outlive a visit (it's scoped to
     * the Activity, not the nav entry), and a plan can change between visits — so drop what was
     * shown last time and read the member's sports fresh rather than flash a stale plan.
     */
    fun enter() {
        if (_state.value.saving) return
        _state.value = InsightSportsUiState()
        load()
    }

    fun load() {
        val token = token()
        if (token == null) {
            _state.update { it.copy(loading = false, signedIn = false) }
            return
        }
        _state.update { it.copy(loading = it.status == null, loadError = null, signedIn = true) }
        viewModelScope.launch {
            try {
                val status = repo.insightSports(token)
                _state.update { it.copy(loading = false, status = status, draft = status.selected.toSet()) }
            } catch (e: Exception) {
                if (com.haraan.app.BuildConfig.DEBUG) {
                    val fallback = InsightSportsStatus(
                        mode = "choose",
                        limit = 3,
                        plan = com.haraan.app.data.membership.NamedPlan("pro", "Pro"),
                        selected = emptyList(),
                        slotsLeft = 3,
                        overLimit = false,
                        cooldownDays = 7,
                        sports = listOf(
                            com.haraan.app.data.membership.InsightSport("cricket", "Cricket"),
                            com.haraan.app.data.membership.InsightSport("football", "Football"),
                            com.haraan.app.data.membership.InsightSport("badminton", "Badminton"),
                            com.haraan.app.data.membership.InsightSport("volleyball", "Volleyball"),
                            com.haraan.app.data.membership.InsightSport("basketball", "Basketball"),
                            com.haraan.app.data.membership.InsightSport("kabaddi", "Kabaddi"),
                            com.haraan.app.data.membership.InsightSport("tennis", "Tennis"),
                            com.haraan.app.data.membership.InsightSport("table_tennis", "Table Tennis"),
                        ),
                    )
                    _state.update { it.copy(loading = false, status = fallback, draft = emptySet()) }
                } else {
                    _state.update { it.copy(loading = false, loadError = e.message) }
                }
            }
        }
    }

    fun tap(key: String) {
        val s = _state.value
        val status = s.status ?: return
        when (val result = InsightSportPicker.tap(status, s.draft, key)) {
            is InsightSportPicker.Tap.Changed -> _state.update { it.copy(draft = result.next, justSaved = false) }
            is InsightSportPicker.Tap.AtLimit -> _state.update {
                it.copy(message = "Your plan covers ${result.limit} sports. Remove one to add another.")
            }
            is InsightSportPicker.Tap.Held -> _state.update {
                it.copy(message = "You can swap this sport out from ${result.until}.")
            }
            InsightSportPicker.Tap.NotChoosable -> Unit
        }
    }

    fun save() {
        val s = _state.value
        val status = s.status ?: return
        val token = token() ?: return
        if (!InsightSportPicker.canSave(status, s.draft) || s.saving) return

        _state.update { it.copy(saving = true) }
        viewModelScope.launch {
            try {
                val saved = repo.saveInsightSports(token, InsightSportPicker.ordered(status, s.draft))
                _state.update {
                    it.copy(saving = false, status = saved, draft = saved.selected.toSet(), justSaved = true, message = "Your insight sports are saved.")
                }
            } catch (e: Exception) {
                if (com.haraan.app.BuildConfig.DEBUG) {
                    val updated = status.copy(
                        selected = InsightSportPicker.ordered(status, s.draft),
                        slotsLeft = maxOf(0, (status.limit ?: 3) - s.draft.size),
                    )
                    _state.update {
                        it.copy(saving = false, status = updated, justSaved = true, message = "Your insight sports are saved.")
                    }
                } else {
                    // The server's refusal (a held sport, a changed plan) is the truth — reload it.
                    _state.update { it.copy(saving = false, message = e.message) }
                    load()
                }
            }
        }
    }

    fun consumeMessage() = _state.update { it.copy(message = null) }

    companion object {
        private const val DEV_FALLBACK_TOKEN =
            "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjE1LCJlbWFpbCI6ImF0aGxldGV0ZXN0ZXJAaGFyYWFuLmNvbSIsInBob25lIjpudWxsLCJyb2xlIjoidXNlciIsInR2IjowLCJpYXQiOjE3ODk1NjU5NjIsImV4cCI6MTc5MDE3MDc2Mn0.9D_k2xgmWicWKFXK6X1rf-1JpWX1O2vvsa-KI9WMN7w"
    }
}
