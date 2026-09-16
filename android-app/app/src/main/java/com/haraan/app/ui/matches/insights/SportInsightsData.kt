package com.haraan.app.ui.matches.insights

import android.content.Context
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import com.haraan.app.data.ApiConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/**
 * The insights payload for every sport that is not cricket — `/api/live-matches/{id}/insights`.
 *
 * Two layers, mirroring the server:
 *  · the parts every sport shares are typed here (the match flow, player cards, reads);
 *  · the sport's OWN section — football's goal story, basketball's shot mix, kabaddi's raid
 *    ledger, a rally sport's set points saved — stays as [team] and is read by that sport's
 *    tab alone. A new sport adds a tab that reads its own keys; nothing here changes.
 *
 * Every number was counted on the server from the recorded events. Nothing is added up here.
 */
data class SportInsights(
    val sport: String,
    val live: Boolean,
    val finished: Boolean,
    val moments: Int,
    val flow: InsightFlow,
    val players: List<PlayerInsight>,
    val team: JSONObject,
    val reads: List<String>,
    /** Stats this sport's scorer never records — said out loud rather than drawn as zeroes. */
    val untracked: List<String>,
)

data class InsightFlow(
    val leadChanges: Int,
    val ties: Int,
    val biggestLead: Lead?,
    val longestRun: Run?,
    val currentRun: Run?,
    /** Margin (home − away) after each scoring moment, with the segment it fell in. */
    val series: List<Pair<Int, Int>>,
    val segments: List<Segment>,
    val comeback: Comeback?,
    val totalHome: Int,
    val totalAway: Int,
)

data class Lead(val side: String, val margin: Int, val home: Int, val away: Int, val segment: Int, val minute: Int?)
data class Run(val side: String, val value: Int, val count: Int, val segment: Int, val endHome: Int, val endAway: Int)
data class Segment(val index: Int, val label: String, val home: Int, val away: Int)
data class Comeback(val side: String, val deficit: Int, val unit: String, val fromHome: Int, val fromAway: Int, val completed: Boolean)

data class InsightTag(val key: String, val label: String)

data class PlayerInsight(
    val name: String,
    val side: String,
    val avatar: String,
    val headline: Int,
    val headlineLabel: String,
    /** Whole percent of the team's total the player accounts for. */
    val share: Int,
    val stats: List<Pair<String, String>>,
    val tags: List<InsightTag>,
    /** The card's own sport-specific extras (basketball's shot mix, kabaddi's role…). */
    val raw: JSONObject,
) {
    val isHome: Boolean get() = side == "home"
}

private fun JSONObject.optIntOrNull(key: String): Int? = if (has(key) && !isNull(key)) optInt(key) else null

private fun JSONArray?.objects(): List<JSONObject> = buildList {
    for (i in 0 until (this@objects?.length() ?: 0)) this@objects?.optJSONObject(i)?.let { add(it) }
}

private fun JSONArray?.strings(): List<String> = buildList {
    for (i in 0 until (this@strings?.length() ?: 0)) {
        this@strings?.optString(i)?.takeIf { it.isNotBlank() }?.let { add(it) }
    }
}

/** A JSON array of objects, for the sport tabs' own sections. */
fun JSONObject.objectList(key: String): List<JSONObject> = optJSONArray(key).objects()

/** `{home: n, away: n}` → (home, away). */
fun JSONObject.sidePair(key: String): Pair<Int, Int> {
    val o = optJSONObject(key) ?: return 0 to 0
    return o.optInt("home") to o.optInt("away")
}

fun parseSportInsights(o: JSONObject): SportInsights {
    val f = o.optJSONObject("flow") ?: JSONObject()

    val series = f.optJSONArray("series").objects().map { it.optInt("d") to it.optInt("s") }
    val lead = f.optJSONObject("biggest_lead")?.let {
        Lead(it.optString("side"), it.optInt("margin"), it.optInt("home"), it.optInt("away"), it.optInt("segment"), it.optIntOrNull("minute"))
    }
    fun run(key: String) = f.optJSONObject(key)?.let {
        Run(it.optString("side"), it.optInt("value"), it.optInt("count"), it.optInt("segment"), it.optInt("end_home"), it.optInt("end_away"))
    }
    val comeback = f.optJSONObject("comeback")?.let {
        val from = it.optJSONObject("from") ?: JSONObject()
        Comeback(it.optString("side"), it.optInt("deficit"), it.optString("unit"), from.optInt("home"), from.optInt("away"), it.optBoolean("completed"))
    }

    val players = o.optJSONArray("players").objects().map { p ->
        PlayerInsight(
            name = p.optString("name"),
            side = p.optString("side"),
            avatar = p.optString("avatar").takeIf { it.startsWith("http") }.orEmpty(),
            headline = p.optInt("headline"),
            headlineLabel = p.optString("headline_label"),
            share = p.optInt("share"),
            stats = p.optJSONArray("stats").objects().map { it.optString("label") to it.optString("value") },
            tags = p.optJSONArray("tags").objects().map { InsightTag(it.optString("key"), it.optString("label")) },
            raw = p,
        )
    }.filter { it.name.isNotBlank() }

    return SportInsights(
        sport = o.optString("sport"),
        live = o.optBoolean("live"),
        finished = o.optBoolean("finished"),
        moments = o.optInt("moments"),
        flow = InsightFlow(
            leadChanges = f.optInt("lead_changes"),
            ties = f.optInt("ties"),
            biggestLead = lead,
            longestRun = run("longest_run"),
            currentRun = run("current_run"),
            series = series,
            segments = f.optJSONArray("segments").objects().map {
                Segment(it.optInt("index"), it.optString("label"), it.optInt("home"), it.optInt("away"))
            },
            comeback = comeback,
            totalHome = f.optInt("total_home"),
            totalAway = f.optInt("total_away"),
        ),
        players = players,
        // The server sends `{}` as an object; an empty PHP array arrives as `[]`.
        team = o.optJSONObject("team") ?: JSONObject(),
        reads = o.optJSONArray("reads").strings(),
        untracked = o.optJSONArray("untracked").strings(),
    )
}

private suspend fun fetchSportInsights(context: Context, matchId: String): Any? = withContext(Dispatchers.IO) {
    if (matchId.isBlank()) return@withContext null
    try {
        val token = com.haraan.app.data.TokenStore.getToken(context)
        val connection = (URL("${ApiConfig.BASE_URL.trimEnd('/')}/api/live-matches/$matchId/insights")
            .openConnection() as HttpURLConnection).apply {
            requestMethod = "GET"
            connectTimeout = 15000
            readTimeout = 20000
            setRequestProperty("Accept", "application/json")
            // A private match's creator and squad may read its insights; a guest may not.
            if (!token.isNullOrBlank() && token != "skipped_guest") {
                setRequestProperty("Authorization", "Bearer $token")
            }
        }
        val code = connection.responseCode
        val body = (if (code >= 400) connection.errorStream else connection.inputStream)
            ?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } }.orEmpty()
        connection.disconnect()
        // A plan refusal is its own state; any other failure is just "unavailable".
        com.haraan.app.data.membership.InsightsLocks.from(code, body)
            ?: if (code !in 200..299) null else parseSportInsights(JSONObject(body))
    } catch (_: Exception) {
        null
    }
}

/** Where the tab stands: still loading the first read, unavailable, or holding data. */
sealed interface InsightsLoad {
    data object Loading : InsightsLoad
    data object Unavailable : InsightsLoad
    data class Ready(val data: SportInsights) : InsightsLoad

    /** The member's plan doesn't cover this sport's advanced insights. */
    data class Locked(val lock: com.haraan.app.data.membership.InsightsLock) : InsightsLoad
}

/**
 * Loads a match's insights and re-reads them whenever [liveKey] changes.
 *
 * The key is whatever moves when the scorer taps — the newest event's sequence, the score —
 * and the screen already refetches the match on every realtime push and on its live poll.
 * So the insights follow the match with no second socket and no timer of their own. A
 * refetch keeps the previous read on screen until the new one lands, so a live tab updates
 * in place instead of flashing a spinner at every point.
 */
@Composable
fun rememberSportInsights(matchId: String, liveKey: Any?): InsightsLoad {
    val context = LocalContext.current
    var load by remember(matchId) { mutableStateOf<InsightsLoad>(InsightsLoad.Loading) }
    LaunchedEffect(matchId, liveKey) {
        val fresh = fetchSportInsights(context, matchId)
        load = when {
            fresh is com.haraan.app.data.membership.InsightsLock -> InsightsLoad.Locked(fresh)
            fresh is SportInsights -> InsightsLoad.Ready(fresh)
            load is InsightsLoad.Ready -> load   // a dropped refetch never blanks a tab
            else -> InsightsLoad.Unavailable
        }
    }
    return load
}
