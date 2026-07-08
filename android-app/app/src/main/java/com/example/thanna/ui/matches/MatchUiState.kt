package com.example.thanna.ui.matches

import androidx.compose.ui.graphics.Color
import com.example.thanna.data.SquadMember

data class BatterStats(val runs: Int, val balls: Int, val fours: Int, val sixes: Int)
data class BowlerStats(val wickets: Int, val runs: Int, val balls: Int) {
    /** Balls bowled rendered as cricket overs, e.g. 23 balls -> "3.5". */
    val overs: String get() = "${balls / 6}.${balls % 6}"
}
data class Partnership(val runs: Int, val balls: Int)
data class LastWicket(val name: String, val runs: Int, val balls: Int)
data class RecentOver(val label: String, val runs: Int, val balls: List<String>)

// ── Full per-innings scorecard, replayed from the ball-by-ball log on the backend ──
data class ScorecardBatter(
    val name: String, val runs: Int, val balls: Int, val fours: Int, val sixes: Int,
    val out: Boolean, val dismissal: String
) {
    val strikeRate: String get() = if (balls <= 0) "—" else String.format("%.1f", runs * 100.0 / balls)
}
data class ScorecardBowler(
    val name: String, val balls: Int, val runs: Int, val wickets: Int, val maidens: Int
) {
    val overs: String get() = "${balls / 6}.${balls % 6}"
    val econ: String get() = if (balls <= 0) "—" else String.format("%.1f", runs * 6.0 / balls)
}
data class FallOfWicket(val wicketNo: Int, val score: Int, val over: String, val batter: String)

/** Real career batting for a player, aggregated from the ball log (null for guests / no history). */
data class CareerBatting(
    val innings: Int,
    val runs: Int,
    val balls: Int,
    val highScore: Int,
    val avg: Double?,   // null until they've been dismissed at least once
    val sr: Double?
)

/**
 * One line in the ball-by-ball commentary feed. [kind] is "ball", "header", or
 * "batter_in" (a new batter arriving — [career] carries their real career line).
 */
data class CommentaryLine(
    val innings: Int,
    val over: String,
    val kind: String,
    val text: String,
    val label: String,
    val runs: Int,
    val wicket: Boolean,
    val boundary: Boolean,
    val battingName: String,
    val playerId: String = "",
    val career: CareerBatting? = null
)
data class InningsExtras(val total: Int, val wides: Int, val noBalls: Int, val byes: Int, val legByes: Int)
data class InningsCard(
    val number: Int,
    val battingTeam: Int,
    val battingName: String,
    val runs: Int,
    val wickets: Int,
    val overs: String,
    val runRate: String,
    val extras: InningsExtras,
    val batters: List<ScorecardBatter>,
    val bowlers: List<ScorecardBowler>,
    val fallOfWickets: List<FallOfWicket>
) {
    val scoreLine: String get() = "$runs/$wickets"
}

data class MatchUiState(
    val team1: String,
    val team1FullName: String,
    val team1Logo: String,
    val team2: String,
    val team2FullName: String,
    val team2Logo: String,
    val score: String,
    val overs: String,
    val target: String,
    val crr: String,
    val rrr: String,
    val status: String,
    val isLive: Boolean = true,
    /** Sport code, e.g. "cricket", "football", "badminton". Drives which scorer/view opens. */
    val sport: String = "cricket",

    // ── Result verification (create → verify → XP) ──
    /** Backend verification state: "" (n/a), "pending", "settled", or "expired". */
    val verificationStatus: String = "",
    /** True when the viewer is a captain who can still confirm this completed result. */
    val canConfirm: Boolean = false,
    /** Where the result settled once verified: "low"/"medium"/"high"/"verified". */
    val trustLevel: String = "low",
    val homeConfirmed: Boolean = false,
    val awayConfirmed: Boolean = false,

    val striker: String = "",
    val strikerStats: BatterStats? = null,
    val nonStriker: String = "",
    val nonStrikerStats: BatterStats? = null,
    val bowler: String = "",
    val bowlerStats: BowlerStats? = null,
    val partnership: Partnership? = null,
    val lastWicket: LastWicket? = null,
    val thisOver: List<String> = emptyList(),
    val recentOvers: List<RecentOver> = emptyList(),
    val ballsLeft: Int? = null,
    val runsNeeded: Int? = null,
    /** Team1's win probability as a 0..100 percent sent by the backend; -1 = not supplied. */
    val winProbability: Int = -1,
    /** True only when the viewer created this match — gates the live-scoring "Score" button. */
    val canScore: Boolean = false,

    // ── Real match metadata (no placeholders) ──
    /** The non-batting side's score, e.g. "174/8"; blank until that innings exists. */
    val opponentScore: String = "",
    /** Toss outcome as sent by the backend, e.g. "ramiredy • Bowl"; blank if unknown. */
    val toss: String = "",
    /** Ground / turf name. */
    val venue: String = "",
    /** Format / competition label, e.g. "20 Over Match". */
    val competition: String = "",

    // ── Live-scoring (ScoringWorkstation) state ──
    // 1 = team1 batting/chasing, 2 = team2. Drives which squad/colour the keypad uses.
    val battingTeam: Int = 1,
    /** How many innings have begun (1 during the first innings, 2 in the chase). */
    val innings: Int = 1,
    val team1Color: Color = Color(0xFF2563EB),   // brand blue
    val team2Color: Color = Color(0xFFEF4444),   // coral red
    val battingColor: Color = Color(0xFF00C853), // mint — the on-strike accent
    val homeSquad: List<SquadMember> = emptyList(),
    val awaySquad: List<SquadMember> = emptyList(),

    /** Complete scorecard for every innings played so far (empty before any ball). */
    val inningsCards: List<InningsCard> = emptyList(),

    /** Ball-by-ball commentary feed, newest first. */
    val commentary: List<CommentaryLine> = emptyList()
)

sealed class MatchScreenState {
    object Loading : MatchScreenState()
    data class Success(val data: MatchUiState) : MatchScreenState()
    data class Error(val message: String) : MatchScreenState()
    object Empty : MatchScreenState()
}
