package com.haraan.app.ui.matches

import androidx.compose.ui.graphics.Color
import com.haraan.app.data.SquadMember

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
    /** The player's real profile photo, when they have one. Blank for guests. */
    val photoUrl: String = "",
    /** Milestone cards only: "fifty" | "century" | "partnership" | "target". */
    val milestoneKind: String = "",
    /** Milestone cards only: the supporting figures, e.g. "54 off 15 · 3x4 · 6x6". */
    val detail: String = "",
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

/**
 * One row of the MVP (impact) ranking, computed on the backend from the same replayed
 * ball log the scorecard uses. [batLine] / [bowlLine] are pre-formatted and blank when
 * the player didn't bat / bowl. Fielding isn't included — the scorer never records the
 * fielder, so catches can't be credited to anyone.
 */
data class MvpPlayer(
    val name: String,
    /**
     * The player's Haraan id, when the scorer picked them from a linked squad. Blank for a
     * name typed in free-hand, which is most of grassroots cricket - so everything keyed on
     * it ([photoUrl], the follow action) has to be optional rather than assumed.
     */
    val playerId: String = "",
    /** Their real profile photo. Blank for guests and anyone who hasn't uploaded one. */
    val photoUrl: String = "",
    /**
     * Whether a Follow button can honestly be offered: a real account behind the name, a
     * signed-in viewer, and not the viewer themselves. The server decides, because only it
     * knows who is asking.
     */
    val canFollow: Boolean = false,
    /** Whether the viewer already follows them — the button's resting state. */
    val isFollowing: Boolean = false,
    /** 1 = team1/home, 2 = team2/away — drives the row's team accent. */
    val team: Int,
    val teamName: String,
    val points: Int,
    val batPoints: Int,
    val bowlPoints: Int,
    val batLine: String,
    val bowlLine: String,
    val strikeRate: String,
    val econ: String,
    val runs: Int,
    val ballsFaced: Int,
    val fours: Int,
    val sixes: Int,
    val wickets: Int,
    val ballsBowled: Int,
    val runsConceded: Int,
    val maidens: Int
) {
    val didBat: Boolean get() = ballsFaced > 0
    val didBowl: Boolean get() = ballsBowled > 0
    /** "ALL-ROUND" / "BATTER" / "BOWLER" — what this player's impact actually came from. */
    val roleLabel: String
        get() = when {
            batPoints > 0 && bowlPoints > 0 -> "ALL-ROUND"
            bowlPoints > 0 && !didBat -> "BOWLER"
            bowlPoints > batPoints -> "BOWLER"
            else -> "BATTER"
        }
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
    /**
     * Football's own state — scoreline, clock and timeline. Null for every other
     * sport. Held here rather than folded into the cricket fields above, because a
     * football match has no runs, overs or economy and blanking those out is how a
     * screen ends up showing cricket furniture with the numbers removed.
     */
    val football: FootballState? = null,

    /**
     * The scoreboard for volleyball, basketball, kabaddi, tennis and table tennis — the
     * sports that are neither cricket's ball-by-ball nor football's goal tally. Null for
     * those two, so a screen picks its board by which one is present.
     */
    val board: SportBoard? = null,

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
    /**
     * The Haraan venue this match was BOOKED at - blank for every other match.
     * Server-resolved from the confirmed booking, never from the typed venue text.
     */
    val venueBadgeName: String = "",
    val venueBadgeArea: String = "",
    /** Format / competition label, e.g. "20 Over Match". */
    val competition: String = "",
    /** "21 Aug 2026, 5:15 PM", or blank when the match records no time at all. */
    val startLabel: String = "",
    /** True when [startLabel] is a time the creator SET, false when it is when they began. */
    val startIsScheduled: Boolean = false,

    // ── Live-scoring (ScoringWorkstation) state ──
    // 1 = team1 batting/chasing, 2 = team2. Drives which squad/colour the keypad uses.
    val battingTeam: Int = 1,
    /** How many innings have begun (1 during the first innings, 2 in the chase). */
    val innings: Int = 1,
    val team1Color: Color = Color(0xFF2563EB),   // brand blue
    val team2Color: Color = Color(0xFFEF4444),   // coral red
    val battingColor: Color = Color(0xFF2563EB), // mint — the on-strike accent
    val homeSquad: List<SquadMember> = emptyList(),
    val awaySquad: List<SquadMember> = emptyList(),

    /** Complete scorecard for every innings played so far (empty before any ball). */
    val inningsCards: List<InningsCard> = emptyList(),

    /** Ball-by-ball commentary feed, newest first. */
    val commentary: List<CommentaryLine> = emptyList(),

    /** Impact ranking for the MVP tab, highest points first (empty before any ball). */
    val mvp: List<MvpPlayer> = emptyList()
)

sealed class MatchScreenState {
    object Loading : MatchScreenState()
    data class Success(val data: MatchUiState) : MatchScreenState()
    data class Error(val message: String) : MatchScreenState()
    object Empty : MatchScreenState()
}
