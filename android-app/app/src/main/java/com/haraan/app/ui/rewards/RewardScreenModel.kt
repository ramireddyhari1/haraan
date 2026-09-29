package com.haraan.app.ui.rewards

import com.haraan.app.data.rewards.MatchRewards
import com.haraan.app.data.rewards.NextBadge
import com.haraan.app.data.rewards.RewardBadge
import com.haraan.app.data.rewards.RewardItem
import java.util.Calendar
import java.util.Locale

/**
 * The post-match screen as a model: what to show, in the order the player should feel it —
 * I won → I earned → I'm progressing → I want to play again. Built by [RewardScreenMapper]
 * from the server's payload only; the composables render it and decide nothing.
 */
data class RewardScreenUi(
    val result: ResultUi,
    val xp: XpUi,
    val verification: VerificationUi?,
    val rewards: List<RewardItem>,
    val readyCount: Int,
    val progress: ProgressUi,
    val nextAction: NextActionUi,
)

data class StatChip(val label: String, val value: String)

data class PlayerImpactUi(
    val title: String,
    val subtitle: String,
    val isPotm: Boolean,
    val playerName: String = "",
    val playerPhoto: String? = null,
    val teamName: String = "",
    val teamShort: String = "",
    val teamLogo: String? = null,
    val role: String = "",
    val battingLine: String? = null,
    val battingDetail: String? = null,
    val bowlingLine: String? = null,
    val bowlingDetail: String? = null,
    /** Scorecard notation: "64 (38)" — runs (balls). */
    val batFigure: String? = null,
    /** "SR 168.4" — only when balls are known. */
    val batNote: String? = null,
    /** "2/28 (4)" — wickets/runs (overs). */
    val bowlFigure: String? = null,
    /** "Econ 7.00" */
    val bowlNote: String? = null,
    val chips: List<StatChip> = emptyList(),
)

enum class Outcome { WON, LOST, TIED, FINISHED }

data class ResultUi(
    val outcome: Outcome,
    /** "Victory" / "Draw" / "Defeat" / "Full time". */
    val word: String,
    /** "FOOTBALL · CASUAL MATCH" */
    val eyebrow: String,
    val home: TeamUi,
    val away: TeamUi,
    val resultLine: String?,
    val shareText: String,
    val shareUrl: String?,
    val impact: PlayerImpactUi? = null,
    /** Raw sport key ("cricket", "table_tennis") — picks the result illustration's ball. */
    val sport: String = "",
)

data class TeamUi(
    val name: String,
    val short: String,
    val logo: String?,
    val score: Int,
    val scoreFormatted: String = score.toString(),
    val isViewer: Boolean,
    val isWinner: Boolean,
)

enum class XpState { SETTLED, PENDING, NOT_ELIGIBLE }

data class XpUi(
    val state: XpState,
    val xp: Int?,
    val ranked: Boolean,
    val bonus: Int,
    val explanation: String,
    /** "Mon 5 Oct, 8:00 PM" — when the result locks, if the server set a window. */
    val deadline: String? = null,
    val bonusTotal: Int = 0,
    val yourTeam: String = "",
    val oppTeam: String = "",
)

data class VerificationUi(val yourCaptain: Boolean, val opposition: Boolean, val needsOrganiser: Boolean)

data class ProgressUi(
    val streakWeeks: Int,
    val bestWeeks: Int,
    val playedThisWeek: Boolean,
    val newBadges: List<RewardBadge>,
    val nextBadge: NextBadgeUi?,
)

data class NextBadgeUi(val badge: NextBadge, val progressLabel: String, val fraction: Float)

enum class NextActionKind { CREATE_MATCH, BOOK_TURF }

data class NextActionUi(
    val headline: String,
    val nudge: String,
    val primaryLabel: String,
    val primary: NextActionKind,
    val secondaryLabel: String,
    val secondary: NextActionKind,
)

object RewardScreenMapper {

    /** [today] is a Calendar (not java.time) so this runs on minSdk 24 without desugaring. */
    fun map(data: MatchRewards, today: Calendar = Calendar.getInstance()): RewardScreenUi {
        val m = data.match
        val side = data.viewer.side
        val outcome = when (data.viewer.outcome) {
            "won" -> Outcome.WON
            "lost" -> Outcome.LOST
            "tied" -> Outcome.TIED
            else -> Outcome.FINISHED
        }
        val word = when (outcome) {
            Outcome.WON -> "Victory"
            Outcome.LOST -> "Defeat"
            Outcome.TIED -> "Draw"
            Outcome.FINISHED -> "Full time"
        }
        val homeWon = m.homeScore > m.awayScore
        val awayWon = m.awayScore > m.homeScore
        val homeScoreFormatted = m.homeScoreDisplay?.takeIf { it.isNotBlank() }
            ?: when {
                m.sport.equals("cricket", ignoreCase = true) && !m.overs.isNullOrBlank() && m.overs != "0.0" ->
                    "${m.homeScore} (${m.overs} ov)"
                else -> m.homeScore.toString()
            }
        val awayScoreFormatted = m.awayScoreDisplay?.takeIf { it.isNotBlank() } ?: m.awayScore.toString()

        val home = TeamUi(
            m.home.ifBlank { m.homeShort },
            m.homeShort.ifBlank { m.home },
            m.homeLogo,
            m.homeScore,
            homeScoreFormatted,
            side == "home",
            homeWon,
        )
        val away = TeamUi(
            m.away.ifBlank { m.awayShort },
            m.awayShort.ifBlank { m.away },
            m.awayLogo,
            m.awayScore,
            awayScoreFormatted,
            side == "away",
            awayWon,
        )

        val impactUi = data.viewer.impact?.let { imp ->
            val chips = mutableListOf<StatChip>()
            if (imp.runs != null || imp.balls != null) {
                val bStr = imp.balls?.let { " (${it}b)" } ?: ""
                chips.add(StatChip("BAT", "${imp.runs ?: 0}$bStr"))
            }
            if (imp.wickets != null && (imp.wickets > 0 || (imp.oversBowled ?: 0.0) > 0.0)) {
                val cleanOvers = imp.oversBowled?.let { o -> if (o % 1.0 == 0.0) "${o.toInt()}" else "$o" }
                val ovStr = cleanOvers?.let { " (${it}ov)" } ?: ""
                chips.add(StatChip("BOWL", "${imp.wickets} wkts$ovStr"))
            }
            if (imp.runsConceded != null && imp.runsConceded > 0) {
                chips.add(StatChip("CONCEDED", "${imp.runsConceded} runs"))
            }
            if (chips.isEmpty() && imp.summary != null) {
                chips.add(StatChip("MATCH", imp.summary))
            }

            val didBat = imp.runs != null || (imp.balls != null && imp.balls > 0)
            val didBowl = (imp.wickets != null && imp.wickets > 0) || (imp.oversBowled != null && imp.oversBowled > 0.0)

            val batLine = if (didBat) "${imp.runs ?: 0}" else null
            val batDetail = if (didBat) {
                val balls = imp.balls ?: 0
                if (balls > 0 && imp.runs != null) {
                    val sr = String.format(Locale.US, "%.1f", (imp.runs.toDouble() / balls.toDouble()) * 100.0)
                    "$balls balls · SR $sr"
                } else if (balls > 0) {
                    "$balls balls"
                } else null
            } else null

            val bowlLine = if (didBowl) {
                "${imp.wickets ?: 0}/${imp.runsConceded ?: 0}"
            } else null
            val bowlDetail = if (didBowl) {
                val cleanOvers = imp.oversBowled?.let { o -> if (o % 1.0 == 0.0) "${o.toInt()}" else "$o" } ?: "0"
                if (imp.oversBowled != null && imp.oversBowled > 0.0 && imp.runsConceded != null) {
                    val econ = String.format(Locale.US, "%.2f", imp.runsConceded.toDouble() / imp.oversBowled)
                    "$cleanOvers ov · Econ $econ"
                } else {
                    "$cleanOvers overs"
                }
            } else null

            val inferredRole = when {
                !imp.role.isNullOrBlank() -> imp.role.uppercase(Locale.ENGLISH)
                didBat && didBowl -> "ALL-ROUNDER"
                didBowl -> "BOWLER"
                didBat -> "BATTER"
                else -> "MATCH WINNER"
            }

            val assignedTeamName = imp.teamName?.takeIf { it.isNotBlank() }
                ?: if (side == "home") home.name else away.name
            val assignedTeamShort = if (side == "home") home.short else away.short
            val assignedTeamLogo = imp.teamLogo?.takeIf { it.isNotBlank() }
                ?: if (side == "home") home.logo else away.logo

            // Figures exactly as a scorecard prints them; no derived "impact" number — the
            // player can check every digit here against the scorecard.
            val oversText = imp.oversBowled?.takeIf { it > 0.0 }?.let { o -> if (o % 1.0 == 0.0) "${o.toInt()}" else "$o" }
            val batFigure = if (didBat) "${imp.runs ?: 0}" + (imp.balls?.takeIf { it > 0 }?.let { " ($it)" } ?: "") else null
            val batNote = if (didBat && imp.runs != null && (imp.balls ?: 0) > 0) {
                "SR " + String.format(Locale.US, "%.1f", imp.runs * 100.0 / imp.balls!!)
            } else null
            val bowlFigure = if (didBowl) "${imp.wickets ?: 0}/${imp.runsConceded ?: 0}" + (oversText?.let { " ($it)" } ?: "") else null
            val bowlNote = if (didBowl && imp.runsConceded != null && (imp.oversBowled ?: 0.0) > 0.0) {
                "Econ " + String.format(Locale.US, "%.2f", imp.runsConceded / imp.oversBowled!!)
            } else null

            if (chips.isNotEmpty() || imp.isPotm) {
                PlayerImpactUi(
                    title = if (imp.isPotm) "Player of the Match" else "Your Match Performance",
                    subtitle = if (imp.isPotm) "Standout matchwinning performance" else "Attributed to your player profile",
                    isPotm = imp.isPotm,
                    playerName = imp.playerName?.takeIf { it.isNotBlank() } ?: "Player",
                    playerPhoto = imp.playerPhoto,
                    teamName = assignedTeamName,
                    teamShort = assignedTeamShort,
                    teamLogo = assignedTeamLogo,
                    role = inferredRole,
                    battingLine = batLine,
                    battingDetail = batDetail,
                    bowlingLine = bowlLine,
                    bowlingDetail = bowlDetail,
                    batFigure = batFigure,
                    batNote = batNote,
                    bowlFigure = bowlFigure,
                    bowlNote = bowlNote,
                    chips = chips,
                )
            } else null
        }

        val sport = m.sport.replace('_', ' ').uppercase(Locale.ENGLISH)
        val type = when (m.matchType.lowercase()) {
            "league" -> "LEAGUE MATCH"
            "tournament" -> "TOURNAMENT"
            else -> "CASUAL MATCH"
        }
        val resultLine = m.resultLine.takeIf { it.isNotBlank() && !it.equals("completed", true) && !it.equals("finished", true) }

        val xpState = when (data.competitiveXp.state) {
            "settled" -> XpState.SETTLED
            "not_eligible" -> XpState.NOT_ELIGIBLE
            else -> XpState.PENDING
        }
        val xp = XpUi(
            xpState, data.competitiveXp.xp, data.competitiveXp.isRanked == true, data.bonusXp.thisMatch, data.competitiveXp.explanation,
            deadline = data.competitiveXp.deadline?.let(::deadlineLabel),
            bonusTotal = data.bonusXp.total,
            yourTeam = if (side == "away") away.name else home.name,
            oppTeam = if (side == "away") home.name else away.name,
        )

        val lockedOnResult = data.rewards.locked.any { "verification" in it.lockReasons }
        val trustTooLow = data.rewards.locked.any { it.statusReason == "trust_too_low" }
        val verification = if (xpState == XpState.PENDING || lockedOnResult) {
            VerificationUi(
                yourCaptain = if (side == "away") data.competitiveXp.awayConfirmed else data.competitiveXp.homeConfirmed,
                opposition = if (side == "away") data.competitiveXp.homeConfirmed else data.competitiveXp.awayConfirmed,
                needsOrganiser = trustTooLow,
            )
        } else null

        // Bonus XP grants are already summed in the XP block.
        val claimed = data.rewards.claimed.filterNot { it.type == "bonus_xp" }
        val rewards = data.rewards.ready + data.rewards.locked + claimed

        val next = data.nextBadge?.let { b ->
            val t = b.threshold.coerceAtLeast(1)
            NextBadgeUi(b, "${b.value.coerceAtMost(t)} of $t ${unitFor(b.metric, t)}", (b.value.toFloat() / t).coerceIn(0f, 1f))
        }
        val progress = ProgressUi(data.streak.current, data.streak.best, data.streak.playedThisWeek, data.badges, next)

        return RewardScreenUi(
            result = ResultUi(
                outcome, word, "$sport · $type", home, away, resultLine,
                shareText = "${home.short} ${home.score}–${away.score} ${away.short} · $word on Haraan",
                shareUrl = m.shareUrl,
                impact = impactUi,
                sport = m.sport.lowercase(Locale.ENGLISH),
            ),
            xp = xp,
            verification = verification,
            rewards = rewards,
            readyCount = data.rewards.ready.size,
            progress = progress,
            nextAction = nextAction(outcome, data.streak.current, data.streak.playedThisWeek, today),
        )
    }

    /** The one thing to do next, with a streak nudge built from the real week boundary. */
    internal fun nextAction(outcome: Outcome, streak: Int, playedThisWeek: Boolean, today: Calendar): NextActionUi {
        val headline = when (outcome) {
            Outcome.WON -> "Keep the run going"
            Outcome.LOST -> "Run it back"
            Outcome.TIED -> "Settle it next time"
            Outcome.FINISHED -> "Play again"
        }
        // Streak weeks are ISO weeks (Monday–Sunday), matching the server.
        val sunday = (today.clone() as Calendar).apply {
            val dow = get(Calendar.DAY_OF_WEEK) // Sunday = 1 … Saturday = 7
            add(Calendar.DAY_OF_MONTH, if (dow == Calendar.SUNDAY) 0 else 8 - dow)
        }
        val nextSunday = (sunday.clone() as Calendar).apply { add(Calendar.DAY_OF_MONTH, 7) }
        val nudge = when {
            playedThisWeek && streak > 0 -> "Play again next week, by ${dayLabel(nextSunday)}, to make it ${streak + 1} weeks in a row."
            streak > 0 -> "Play by ${dayLabel(sunday)} to keep your ${weeks(streak)} streak alive."
            else -> "Play once a week to start a streak."
        }
        return NextActionUi(headline, nudge, "Play again", NextActionKind.CREATE_MATCH, "Book a turf", NextActionKind.BOOK_TURF)
    }

    /**
     * "2026-10-05T20:00:00+05:30" → "Mon 5 Oct, 8:00 PM", read as the wall clock in the string
     * (the server sends business time). Anything that isn't ISO is shown as sent.
     */
    internal fun deadlineLabel(raw: String): String? {
        if (raw.isBlank()) return null
        val m = Regex("""^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})""").find(raw) ?: return raw
        val (y, mo, d, h, mi) = m.destructured
        val cal = Calendar.getInstance().apply { clear(); set(y.toInt(), mo.toInt() - 1, d.toInt()) }
        val hour = h.toInt()
        val h12 = if (hour % 12 == 0) 12 else hour % 12
        return "${dayLabel(cal)}, $h12:$mi ${if (hour < 12) "AM" else "PM"}"
    }

    internal fun dayLabel(d: Calendar): String =
        "${d.getDisplayName(Calendar.DAY_OF_WEEK, Calendar.SHORT, Locale.ENGLISH)} ${d.get(Calendar.DAY_OF_MONTH)} ${d.getDisplayName(Calendar.MONTH, Calendar.SHORT, Locale.ENGLISH)}"

    private fun weeks(n: Int) = if (n == 1) "1-week" else "$n-week"

    internal fun unitFor(metric: String, threshold: Int): String = when (metric) {
        "matches_played" -> if (threshold == 1) "match" else "matches"
        "wins" -> if (threshold == 1) "win" else "wins"
        "player_of_match" -> if (threshold == 1) "award" else "awards"
        "best_win_streak" -> "wins in a row"
        "high_score" -> "runs"
        "career_wickets" -> "wickets"
        "play_streak_weeks" -> "weeks in a row"
        else -> ""
    }
}
