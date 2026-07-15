package com.haraan.app.ui.matches

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.AdItem
import com.haraan.app.data.ContentRepository
import com.haraan.app.data.MatchRepository
import com.haraan.app.data.SquadMember
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import org.json.JSONArray
import org.json.JSONObject

class MatchDetailsViewModel : ViewModel() {

    private val repo = MatchRepository()
    private val contentRepo = ContentRepository()

    private val _uiState = MutableStateFlow<MatchScreenState>(MatchScreenState.Loading)
    val uiState: StateFlow<MatchScreenState> = _uiState.asStateFlow()

    /** Admin-controlled sponsored ads for the Live tab (GET /api/ads?placement=match_live). */
    private val _liveAds = MutableStateFlow<List<AdItem>>(emptyList())
    val liveAds: StateFlow<List<AdItem>> = _liveAds.asStateFlow()

    /**
     * Load a match for the detail screen. Pass a [code] to open a PRIVATE match by
     * its share code (no auth needed); otherwise it loads by [id] (the [token]
     * keeps a LOCAL match in the viewer's own district reachable).
     *
     * The legacy demo ids ("live-mi-csk", …) aren't real records, so when a fetch
     * returns nothing we fall back to mock data rather than erroring the screen.
     */
    fun load(id: String?, code: String?, token: String?) {
        _uiState.value = MatchScreenState.Loading
        viewModelScope.launch {
            val parsed = fetchParsed(id, code, token)
            _uiState.value = when {
                parsed != null -> MatchScreenState.Success(parsed)
                !code.isNullOrBlank() -> MatchScreenState.Error("That match code isn't valid.")
                else -> MatchScreenState.Error("Couldn't load this match.")
            }
        }
        // Load sponsored ads for the Live tab separately — a failure here must never
        // block or error the match itself, so it just leaves the list empty.
        viewModelScope.launch {
            _liveAds.value = runCatching { contentRepo.getAds("match_live") }.getOrDefault(emptyList())
        }
    }

    /**
     * Silently re-fetch and swap in fresh data WITHOUT a loading flicker — used by the
     * live auto-refresh poll so a LIVE match actually ticks. On any failure we keep the
     * last good data on screen rather than flashing an error.
     */
    fun refresh(id: String?, code: String?, token: String?) {
        viewModelScope.launch {
            val parsed = fetchParsed(id, code, token)
            if (parsed != null) _uiState.value = MatchScreenState.Success(parsed)
        }
    }

    private suspend fun fetchParsed(id: String?, code: String?, token: String?): MatchUiState? {
        val json = when {
            !code.isNullOrBlank() -> repo.getLiveMatchByCode(code)
            !id.isNullOrBlank() -> repo.getLiveMatchJson(id, token)
            else -> null
        } ?: return null
        return runCatching { parse(json) }.getOrNull()
    }

    /** Map the flat detail JSON from /api/live-matches/{id|code} onto [MatchUiState]. */
    private fun parse(body: String): MatchUiState {
        val o = JSONObject(body)
        return MatchUiState(
            team1 = o.optString("team1"),
            team1FullName = o.optString("team1Full", o.optString("team1")),
            // Uploaded logo URL wins; otherwise carry the default emblem key (action1..4)
            // so the hero/crest can render the chosen icon instead of a bare monogram.
            team1Logo = o.optString("team1Logo").ifBlank { o.optString("team1Emblem") },
            team2 = o.optString("team2"),
            team2FullName = o.optString("team2Full", o.optString("team2")),
            team2Logo = o.optString("team2Logo").ifBlank { o.optString("team2Emblem") },
            score = o.optString("score"),
            overs = o.optString("overs"),
            target = "",
            crr = o.optString("crr"),
            rrr = "",
            status = o.optString("status"),
            isLive = o.optBoolean("isLive", false),

            // ── Rich live fields the backend already sends in the flat payload. ──
            // Stats arrive as strings ("34 (19)", "1-41 (3.5)"); MatchStatsMapper
            // turns them into the typed stats the hero + keypad render. Anything
            // absent stays blank/null, so the scrolling layer just hides itself.
            striker = o.optName("striker"),
            strikerStats = MatchStatsMapper.parseBatterStats(o.optString("strikerStats")),
            nonStriker = o.optName("nonStriker"),
            nonStrikerStats = MatchStatsMapper.parseBatterStats(o.optString("nonStrikerStats")),
            bowler = o.optName("bowler"),
            bowlerStats = MatchStatsMapper.parseBowlerStats(o.optString("bowlerStats")),
            thisOver = stringList(o.optJSONArray("thisOver")),
            recentOvers = recentOvers(o.optJSONArray("recentOvers")),
            partnership = o.optJSONObject("partnership")?.let { Partnership(it.optInt("runs"), it.optInt("balls")) },
            lastWicket = o.optJSONObject("lastWicket")?.let { LastWicket(it.optName("name"), it.optInt("runs"), it.optInt("balls")) },
            winProbability = o.optInt("winProbability", -1),
            canScore = o.optBoolean("canScore", false),
            opponentScore = o.optString("opponentScore"),
            toss = o.optString("toss"),
            venue = o.optString("venue"),
            competition = o.optString("formatLabel"),
            battingTeam = o.optInt("battingTeam", 1),
            innings = o.optInt("innings", 1),
            homeSquad = squad(o.optJSONArray("homeSquad")),
            awaySquad = squad(o.optJSONArray("awaySquad")),
            inningsCards = inningsCards(o.optJSONArray("inningsCards")),
            commentary = commentary(o.optJSONArray("commentary")),
        )
    }

    /** Parse the ball-by-ball commentary feed. */
    private fun commentary(arr: JSONArray?): List<CommentaryLine> {
        if (arr == null) return emptyList()
        return (0 until arr.length()).mapNotNull { i ->
            val o = arr.optJSONObject(i) ?: return@mapNotNull null
            val careerObj = o.optJSONObject("career")
            val career = careerObj?.let {
                CareerBatting(
                    innings = it.optInt("innings"),
                    runs = it.optInt("runs"),
                    balls = it.optInt("balls"),
                    highScore = it.optInt("highScore"),
                    avg = if (it.isNull("avg")) null else it.optDouble("avg"),
                    sr = if (it.isNull("sr")) null else it.optDouble("sr"),
                )
            }
            CommentaryLine(
                innings = o.optInt("innings", 1),
                over = o.optString("over"),
                kind = o.optString("kind", "ball"),
                text = o.optString("text"),
                label = o.optString("label"),
                runs = o.optInt("runs"),
                wicket = o.optBoolean("wicket"),
                boundary = o.optBoolean("boundary"),
                battingName = o.optString("battingName"),
                playerId = o.optString("playerId"),
                career = career,
            )
        }
    }

    /** Parse the replayed per-innings scorecards from the detail payload. */
    private fun inningsCards(arr: JSONArray?): List<InningsCard> {
        if (arr == null) return emptyList()
        return (0 until arr.length()).mapNotNull { i ->
            val o = arr.optJSONObject(i) ?: return@mapNotNull null
            val ex = o.optJSONObject("extras") ?: JSONObject()
            InningsCard(
                number = o.optInt("number", i + 1),
                battingTeam = o.optInt("battingTeam", 1),
                battingName = o.optString("battingName"),
                runs = o.optInt("runs"),
                wickets = o.optInt("wickets"),
                overs = o.optString("overs", "0.0"),
                runRate = o.optString("runRate", "0.00"),
                extras = InningsExtras(
                    total = ex.optInt("total"),
                    wides = ex.optInt("wd"),
                    noBalls = ex.optInt("nb"),
                    byes = ex.optInt("b"),
                    legByes = ex.optInt("lb"),
                ),
                batters = (o.optJSONArray("batters") ?: JSONArray()).let { ba ->
                    (0 until ba.length()).mapNotNull { j ->
                        val b = ba.optJSONObject(j) ?: return@mapNotNull null
                        ScorecardBatter(
                            name = b.optName("name"),
                            runs = b.optInt("runs"), balls = b.optInt("balls"),
                            fours = b.optInt("fours"), sixes = b.optInt("sixes"),
                            out = b.optBoolean("out"), dismissal = b.optString("dismissal", "not out"),
                        )
                    }.filter { it.name.isNotBlank() }
                },
                bowlers = (o.optJSONArray("bowlers") ?: JSONArray()).let { ba ->
                    (0 until ba.length()).mapNotNull { j ->
                        val b = ba.optJSONObject(j) ?: return@mapNotNull null
                        ScorecardBowler(
                            name = b.optName("name"),
                            balls = b.optInt("balls"), runs = b.optInt("runs"),
                            wickets = b.optInt("wickets"), maidens = b.optInt("maidens"),
                        )
                    }.filter { it.name.isNotBlank() }
                },
                fallOfWickets = (o.optJSONArray("fow") ?: JSONArray()).let { fa ->
                    (0 until fa.length()).mapNotNull { j ->
                        val f = fa.optJSONObject(j) ?: return@mapNotNull null
                        FallOfWicket(
                            wicketNo = f.optInt("wicketNo"),
                            score = f.optInt("score"),
                            over = f.optString("over"),
                            batter = f.optName("batter"),
                        )
                    }
                },
            )
        }
    }

    /** Like optString, but a JSON null (which Android stringifies to "null") becomes "". */
    private fun JSONObject.optName(key: String): String =
        optString(key).let { if (it.equals("null", ignoreCase = true)) "" else it }

    /** JSON string array -> non-blank Kotlin list (e.g. this-over balls). */
    private fun stringList(arr: JSONArray?): List<String> {
        if (arr == null) return emptyList()
        return (0 until arr.length()).map { arr.optString(it) }.filter { it.isNotBlank() }
    }

    /** JSON array of {label/over, runs, balls[]} -> [RecentOver] list. */
    private fun recentOvers(arr: JSONArray?): List<RecentOver> {
        if (arr == null) return emptyList()
        return (0 until arr.length()).map { i ->
            val ov = arr.optJSONObject(i) ?: JSONObject()
            RecentOver(
                label = ov.optString("label", ov.optString("over")),
                runs = ov.optInt("runs"),
                balls = stringList(ov.optJSONArray("balls")),
            )
        }
    }

    /** Squad arrives as either plain names or {id,name} objects. */
    private fun squad(arr: JSONArray?): List<SquadMember> {
        if (arr == null) return emptyList()
        return (0 until arr.length()).mapNotNull { i ->
            val ov = arr.optJSONObject(i)
            if (ov == null) {
                arr.optString(i).takeIf { it.isNotBlank() && !it.equals("null", true) }?.let { SquadMember("", it) }
            } else {
                val name = ov.optName("name")
                if (name.isBlank()) null
                else {
                    // Guests come through as {"id":null} — optString would stringify that to
                    // "null", which then gets sent back as a bogus player ref. Treat it as blank.
                    val id = ov.optName("id").ifBlank { ov.optName("player_id") }
                    SquadMember(id = id, name = name, isGuest = id.isBlank())
                }
            }
        }
    }
}
