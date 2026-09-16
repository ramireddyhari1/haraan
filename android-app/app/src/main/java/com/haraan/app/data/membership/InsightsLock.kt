package com.haraan.app.data.membership

import org.json.JSONObject

/** The server refused a match's insights for plan reasons: its sentence, and why. */
data class InsightsLock(val message: String, val code: String?, val sport: String?)

object InsightsLocks {
    private const val FEATURE = "insights.advanced_sports"

    /**
     * A plan refusal from an insights endpoint, or null for anything else (404, 5xx, a body
     * that isn't ours) — those stay "unavailable", never a paywall the server didn't send.
     */
    fun from(status: Int, body: String?): InsightsLock? {
        if (status != 403 || body.isNullOrBlank()) return null
        val o = runCatching { JSONObject(body) }.getOrNull() ?: return null
        if (o.optString("feature") != FEATURE) return null
        val message = o.optString("error").takeIf { it.isNotBlank() && it != "null" } ?: return null
        return InsightsLock(
            message = message,
            code = o.optString("code").takeIf { it.isNotBlank() && it != "null" },
            sport = o.optString("sport").takeIf { it.isNotBlank() && it != "null" },
        )
    }
}
