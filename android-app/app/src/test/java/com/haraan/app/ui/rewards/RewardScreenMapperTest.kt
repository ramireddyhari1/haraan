package com.haraan.app.ui.rewards

import com.haraan.app.data.rewards.BonusXp
import com.haraan.app.data.rewards.Celebration
import com.haraan.app.data.rewards.CompetitiveXp
import com.haraan.app.data.rewards.MatchRewards
import com.haraan.app.data.rewards.NextBadge
import com.haraan.app.data.rewards.RewardGroups
import com.haraan.app.data.rewards.RewardItem
import com.haraan.app.data.rewards.RewardMatch
import com.haraan.app.data.rewards.RewardStreak
import com.haraan.app.data.rewards.RewardViewer
import com.haraan.app.data.rewards.RewardedAdsInfo
import com.haraan.app.push.DeepLinkTarget
import com.haraan.app.push.DeepLinks
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.Calendar

class RewardScreenMapperTest {

    /** Saturday 19 Sep 2026. */
    private val saturday = Calendar.getInstance().apply { set(2026, Calendar.SEPTEMBER, 19, 12, 0, 0) }

    private fun data(
        outcome: String = "won",
        side: String = "home",
        xpState: String = "pending_verification",
        streak: RewardStreak = RewardStreak(current = 2, best = 3, playedThisWeek = true),
        rewards: RewardGroups = RewardGroups(),
        homeConfirmed: Boolean = true,
        awayConfirmed: Boolean = false,
    ) = MatchRewards(
        match = RewardMatch(id = 1, title = "Reds vs Blues", sport = "football", resultLine = "Completed", home = "Kadapa Reds", away = "Tirupati Blues", homeShort = "Reds", awayShort = "Blues", homeScore = 2, awayScore = 1),
        viewer = RewardViewer(side, outcome),
        celebration = Celebration(),
        competitiveXp = CompetitiveXp(state = xpState, xp = if (xpState == "settled") 19 else null, homeConfirmed = homeConfirmed, awayConfirmed = awayConfirmed),
        bonusXp = BonusXp(35, 80),
        streak = streak,
        rewards = rewards,
        rewardedAds = RewardedAdsInfo(),
        nextBadge = NextBadge(key = "veteran", name = "10 Matches", metric = "matches_played", value = 7, threshold = 10),
    )

    private fun item(id: Long, type: String, status: String, reasons: List<String> = emptyList()) =
        RewardItem(id = id, type = type, status = status, lockReasons = reasons, title = "r$id")

    @Test
    fun the_result_reads_as_the_players_own() {
        val won = RewardScreenMapper.map(data(), saturday).result
        assertEquals(Outcome.WON, won.outcome)
        assertEquals("Victory", won.word)
        assertEquals("FOOTBALL · CASUAL MATCH", won.eyebrow)
        assertTrue(won.home.isViewer && won.home.isWinner)
        assertFalse(won.away.isWinner)
        assertNull("'Completed' adds nothing under the score", won.resultLine)
        assertEquals("Reds 2–1 Blues · Victory on Haraan", won.shareText)

        assertEquals("Defeat", RewardScreenMapper.map(data(outcome = "lost", side = "away"), saturday).result.word)
        assertEquals("Draw", RewardScreenMapper.map(data(outcome = "tied"), saturday).result.word)
    }

    @Test
    fun verification_is_told_from_the_viewers_side() {
        val home = RewardScreenMapper.map(data(side = "home"), saturday).verification!!
        assertTrue(home.yourCaptain)
        assertFalse(home.opposition)

        val away = RewardScreenMapper.map(data(side = "away"), saturday).verification!!
        assertFalse(away.yourCaptain)
        assertTrue(away.opposition)

        assertNull("settled with nothing locked: no checklist", RewardScreenMapper.map(data(xpState = "settled"), saturday).verification)
    }

    @Test
    fun rewards_are_ordered_ready_then_locked_then_claimed_without_bonus_xp_noise() {
        val groups = RewardGroups(
            ready = listOf(item(1, "haraan_coupon", "available")),
            locked = listOf(item(2, "sponsor_code", "locked", listOf("verification"))),
            claimed = listOf(item(3, "bonus_xp", "claimed"), item(4, "offer_link", "claimed")),
        )
        val ui = RewardScreenMapper.map(data(xpState = "settled", rewards = groups), saturday)
        assertEquals(listOf(1L, 2L, 4L), ui.rewards.map { it.id })
        assertEquals(1, ui.readyCount)
        assertNotNull("a locked reward still needs the result", ui.verification)
    }

    @Test
    fun the_streak_nudge_uses_the_real_week_boundary() {
        val played = RewardScreenMapper.nextAction(Outcome.WON, 2, playedThisWeek = true, today = saturday)
        assertEquals("Play again next week, by Sun 27 Sep, to make it 3 weeks in a row.", played.nudge)
        assertEquals(NextActionKind.CREATE_MATCH, played.primary)

        val due = RewardScreenMapper.nextAction(Outcome.LOST, 1, playedThisWeek = false, today = saturday)
        assertEquals("Play by Sun 20 Sep to keep your 1-week streak alive.", due.nudge)
        assertEquals("Run it back", due.headline)

        val sunday = (saturday.clone() as Calendar).apply { add(Calendar.DAY_OF_MONTH, 1) }
        assertEquals(
            "Play by Sun 20 Sep to keep your 3-week streak alive.",
            RewardScreenMapper.nextAction(Outcome.TIED, 3, playedThisWeek = false, today = sunday).nudge,
        )
        assertEquals("Play once a week to start a streak.", RewardScreenMapper.nextAction(Outcome.FINISHED, 0, false, saturday).nudge)
    }

    @Test
    fun the_next_badge_shows_real_progress_in_its_own_unit() {
        val next = RewardScreenMapper.map(data(), saturday).progress.nextBadge!!
        assertEquals("7 of 10 matches", next.progressLabel)
        assertEquals(0.7f, next.fraction, 0.001f)
    }

    @Test
    fun play_again_links_to_the_create_wizard() {
        assertEquals(DeepLinkTarget.CreateMatch, DeepLinks.parse("haraan://actionboard/create"))
    }
}
