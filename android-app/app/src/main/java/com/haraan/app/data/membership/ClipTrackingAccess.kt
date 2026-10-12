package com.haraan.app.data.membership

import android.content.Context
import com.haraan.app.data.TokenStore
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/**
 * Who may use Track a clip: the member feature `tracking.clip_upload`, set per plan in /control
 * (Hero only at launch) — never a plan-code check here.
 *
 * The tracking runs on the phone, so the gate is the member's own entitlements. The last answer
 * is remembered: a Hero member at the nets with no signal is not locked out by a failed read,
 * and one whose plan has lapsed loses it the next time the phone can ask.
 */
object ClipTrackingAccess {
    const val FEATURE = "tracking.clip_upload"

    enum class State { ALLOWED, LOCKED, SIGNED_OUT }

    suspend fun check(context: Context, membership: suspend (String) -> Membership = { MembershipRepository().membership(it) }): State {
        val token = TokenStore.getSignedInToken(context) ?: return State.SIGNED_OUT.also { log("signed out") }
        val read = withContext(Dispatchers.IO) { runCatching { membership(token) } }
        read.exceptionOrNull()?.let { log("membership read failed (${it.javaClass.simpleName}); using the last answer") }
        val fresh = read.getOrNull()
        val allowed = if (fresh != null) {
            allowedBy(fresh.entitlements).also { remember(context, it) }
        } else {
            remembered(context)
        }
        return if (allowed) State.ALLOWED else State.LOCKED
    }

    private fun log(line: String) {
        runCatching { android.util.Log.i("ClipTrackingAccess", line) }
    }

    /** Only an entitlement the server marked enabled counts; a missing row locks it. */
    fun allowedBy(entitlements: List<PlanFeature>): Boolean = entitlements.any { it.key == FEATURE && it.enabled }

    private fun remember(context: Context, allowed: Boolean) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putBoolean(KEY, allowed).apply()
    }

    private fun remembered(context: Context): Boolean =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getBoolean(KEY, false)

    private const val PREFS = "member_features"
    private const val KEY = "tracking.clip_upload"
}
