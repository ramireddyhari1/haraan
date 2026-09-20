package com.haraan.app.ui.rewards

import android.app.Activity
import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.TokenStore
import com.haraan.app.data.rewards.MatchRewards
import com.haraan.app.data.rewards.RewardItem
import com.haraan.app.data.rewards.RewardsException
import com.haraan.app.data.rewards.RewardsRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class PostMatchRewardsState(
    val loading: Boolean = true,
    val error: String? = null,
    val data: MatchRewards? = null,
    /** First view of this match: play the staged entrance once. */
    val animateEntrance: Boolean = false,
    val claiming: Set<Long> = emptySet(),
    /** Grant id → a video is being played / verified for it. */
    val watching: Long? = null,
    /** Claimed rewards' details (codes), fetched on demand. */
    val revealed: Map<Long, RewardItem> = emptyMap(),
    val message: String? = null,
)

class PostMatchRewardsViewModel(app: Application) : AndroidViewModel(app) {

    private val repo = RewardsRepository()
    private val _state = MutableStateFlow(PostMatchRewardsState())
    val state: StateFlow<PostMatchRewardsState> = _state.asStateFlow()

    private var matchId: String = ""
    private var loadJob: Job? = null

    private fun token(): String? = TokenStore.getSignedInToken(getApplication())

    /**
     * Called on every visit. The ViewModel is Activity-scoped (NavDisplay has no per-entry
     * store here), so a second match must never show the first one's rewards.
     */
    fun enter(matchId: String) {
        this.matchId = matchId
        _state.value = PostMatchRewardsState()
        load(firstVisit = true)
    }

    fun retry() = load(firstVisit = false)

    private fun enrichData(data: MatchRewards): MatchRewards {
        val active = com.haraan.app.data.AccountStore.active(getApplication())
        val imp = data.viewer.impact ?: return data
        if (imp.playerName.isNullOrBlank() && active != null && active.name.isNotBlank()) {
            val updated = imp.copy(
                playerName = active.name,
                playerPhoto = imp.playerPhoto ?: active.avatar?.takeIf { it.isNotBlank() },
            )
            return data.copy(viewer = data.viewer.copy(impact = updated))
        }
        return data
    }

    private fun load(firstVisit: Boolean) {
        if (com.haraan.app.BuildConfig.DEBUG && (matchId == "1" || matchId == "demo" || matchId.isBlank())) {
            val demo = enrichData(DemoRewardsData.create(matchId))
            _state.update {
                it.copy(
                    loading = false,
                    data = demo,
                    animateEntrance = firstVisit,
                )
            }
            return
        }
        val tok = token()
        if (tok == null) {
            if (com.haraan.app.BuildConfig.DEBUG) {
                val demo = enrichData(DemoRewardsData.create(matchId))
                _state.update {
                    it.copy(
                        loading = false,
                        data = demo,
                        animateEntrance = firstVisit,
                    )
                }
                return
            }
            _state.value = PostMatchRewardsState(loading = false, error = "Sign in to see your match rewards.")
            return
        }
        loadJob?.cancel()
        loadJob = viewModelScope.launch {
            _state.update { it.copy(loading = true, error = null) }
            try {
                val data = enrichData(repo.forMatch(tok, matchId))
                _state.update {
                    it.copy(
                        loading = false,
                        data = data,
                        animateEntrance = if (firstVisit) data.celebration.enabled && !data.celebration.seen else it.animateEntrance,
                    )
                }
                if (firstVisit && (!data.celebration.enabled || data.celebration.seen)) repo.markSeen(tok, matchId)
            } catch (e: Throwable) {
                if (e is kotlinx.coroutines.CancellationException) throw e
                if (com.haraan.app.BuildConfig.DEBUG) {
                    val demo = enrichData(DemoRewardsData.create(matchId))
                    _state.update {
                        it.copy(
                            loading = false,
                            data = demo,
                            animateEntrance = firstVisit,
                        )
                    }
                    return@launch
                }
                _state.update { it.copy(loading = false, error = (e as? RewardsException)?.message ?: e.message ?: "Couldn't load your rewards.") }
            }
        }
    }

    /** The entrance has played; don't play it again for this match. */
    fun entranceDone() {
        if (!_state.value.animateEntrance) return
        _state.update { it.copy(animateEntrance = false) }
        val tok = token() ?: return
        viewModelScope.launch { repo.markSeen(tok, matchId) }
    }

    fun claim(item: RewardItem) {
        val tok = token()
        val isDemo = com.haraan.app.BuildConfig.DEBUG && (matchId == "1" || matchId == "demo" || matchId.isBlank())
        if (tok == null || isDemo) {
            if (com.haraan.app.BuildConfig.DEBUG) {
                val brandSlug = item.sponsor?.name?.filter { it.isLetterOrDigit() }?.take(4)?.uppercase() ?: "PERK"
                val claimed = item.copy(
                    status = "claimed",
                    claimable = false,
                    claim = com.haraan.app.data.rewards.ClaimDetail(
                        code = "HARAAN-$brandSlug-${item.id}",
                        instructions = "Show this digital pass at the venue desk or enter promo code at checkout",
                        scope = "venue",
                        ctaText = "Redeem Pass",
                        used = false,
                    ),
                )
                _state.update { s ->
                    s.copy(
                        claiming = s.claiming - item.id,
                        revealed = s.revealed + (item.id to claimed),
                        data = s.data?.let { d ->
                            d.copy(rewards = d.rewards.copy(
                                ready = d.rewards.ready.filterNot { it.id == item.id },
                                claimed = listOf(claimed) + d.rewards.claimed.filterNot { it.id == item.id },
                            ))
                        },
                        message = "Reward claimed! Pass added to wallet.",
                    )
                }
            }
            return
        }
        if (item.id in _state.value.claiming) return
        _state.update { it.copy(claiming = it.claiming + item.id) }
        viewModelScope.launch {
            try {
                val claimed = repo.claim(tok, item.id)
                _state.update { s ->
                    s.copy(
                        claiming = s.claiming - item.id,
                        revealed = s.revealed + (claimed.id to claimed),
                        data = s.data?.let { d ->
                            d.copy(rewards = d.rewards.copy(
                                ready = d.rewards.ready.filterNot { it.id == claimed.id },
                                claimed = listOf(claimed) + d.rewards.claimed.filterNot { it.id == claimed.id },
                            ))
                        },
                        message = "Reward claimed",
                    )
                }
            } catch (e: RewardsException) {
                _state.update { it.copy(claiming = it.claiming - item.id, message = e.message) }
                load(firstVisit = false)
            }
        }
    }

    /** Fetch a claimed reward's code again (it isn't carried in lists). */
    fun reveal(item: RewardItem) {
        val tok = token()
        if (tok == null) {
            if (com.haraan.app.BuildConfig.DEBUG) {
                _state.update { it.copy(revealed = it.revealed + (item.id to item)) }
            }
            return
        }
        if (_state.value.revealed.containsKey(item.id)) return
        viewModelScope.launch {
            try {
                val full = repo.detail(tok, item.id)
                _state.update { it.copy(revealed = it.revealed + (full.id to full)) }
            } catch (e: RewardsException) {
                _state.update { it.copy(message = e.message) }
            }
        }
    }

    /**
     * The player chose to watch a video. The server issues a session, the ad plays with it,
     * and then we ASK the server whether Google verified it — the ad finishing proves nothing.
     */
    fun watchAd(activity: Activity, item: RewardItem) {
        val isDemo = com.haraan.app.BuildConfig.DEBUG && (matchId == "1" || matchId == "demo" || matchId.isBlank())
        if (isDemo) {
            val unlocked = item.copy(
                status = "ready",
                claimable = true,
                lockReasons = emptyList(),
            )
            _state.update { s ->
                s.copy(
                    watching = null,
                    data = s.data?.let { d ->
                        d.copy(rewards = d.rewards.copy(
                            locked = d.rewards.locked.filterNot { it.id == item.id },
                            ready = d.rewards.ready + unlocked,
                        ))
                    },
                    message = "Bonus perk unlocked!",
                )
            }
            return
        }
        val tok = token() ?: return
        if (_state.value.watching != null) return
        _state.update { it.copy(watching = item.id) }
        viewModelScope.launch {
            try {
                val session = repo.startAd(tok, item.id)
                when (val outcome = RewardedAdRunner.play(activity, session)) {
                    RewardedAdRunner.Outcome.Watched -> {
                        var verified = false
                        repeat(VERIFY_POLLS) {
                            if (verified) return@repeat
                            delay(VERIFY_INTERVAL_MS)
                            verified = runCatching { repo.adStatus(tok, session.nonce).status == "verified" }.getOrDefault(false)
                        }
                        _state.update {
                            it.copy(message = if (verified) "Unlocked" else "Still checking the video — your reward unlocks as soon as it's confirmed.")
                        }
                    }
                    RewardedAdRunner.Outcome.Closed -> _state.update { it.copy(message = "Watch to the end to unlock the reward.") }
                    RewardedAdRunner.Outcome.NoConsent -> _state.update { it.copy(message = "Videos need your ad consent. You can change it any time.") }
                    is RewardedAdRunner.Outcome.Failed -> _state.update { it.copy(message = outcome.message) }
                }
            } catch (e: RewardsException) {
                _state.update { it.copy(message = e.message) }
            } finally {
                _state.update { it.copy(watching = null) }
                load(firstVisit = false)
            }
        }
    }

    fun messageShown() = _state.update { it.copy(message = null) }

    private companion object {
        const val VERIFY_POLLS = 12
        const val VERIFY_INTERVAL_MS = 1_500L
    }
}
