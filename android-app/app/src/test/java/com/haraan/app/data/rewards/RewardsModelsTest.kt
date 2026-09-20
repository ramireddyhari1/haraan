package com.haraan.app.data.rewards

import com.haraan.app.push.DeepLinkTarget
import com.haraan.app.push.DeepLinks
import com.haraan.app.ui.rewards.shortDate
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class RewardsModelsTest {

    private val json = Json { ignoreUnknownKeys = true; explicitNulls = false; coerceInputValues = true }

    /** The exact shape the server returns (trimmed), including fields this build doesn't read. */
    private val payload = """
        {"data":{
          "match":{"id":42,"title":"Reds vs Blues","sport":"football","result_line":"Completed","completed_at":"2026-09-19T10:00:00+05:30"},
          "viewer":{"side":"home","outcome":"won"},
          "celebration":{"enabled":true,"animation_url":null,"variant":"won","seen":false},
          "competitive_xp":{"state":"pending_verification","xp":null,"is_ranked":null,"trust_level":"low","deadline":"2026-09-22T10:00:00+05:30","explanation":"XP is added once the result is confirmed."},
          "bonus_xp":{"this_match":20,"total":140,"counts_toward_leaderboard":false},
          "badges":[{"key":"week1","name":"Turned up","icon":"Whatshot","tier":"bronze","unlocked_at":"2026-09-19T10:00:00+05:30","is_new":true}],
          "streak":{"current":3,"best":5,"extended_this_match":true},
          "rewards":{
            "ready":[],
            "locked":[{"id":7,"match_id":42,"type":"haraan_coupon","source":"haraan","status":"locked","lock_reasons":["verification"],"status_reason":null,"title":"₹50 off your next booking","sponsored":false,"sponsor":null,"claimable":false,"can_watch_ad":false,"expires_at":"2026-10-19T10:00:00+05:30","a_future_field":1}],
            "claimed":[],"closed":[]
          },
          "rewarded_ads":{"available":false,"ad_unit_id":null,"ssv_user_id":null}
        }}
    """.trimIndent()

    @Test
    fun decodes_the_match_rewards_payload_and_keeps_the_two_xps_apart() {
        val r = json.decodeFromString(RewardsEnvelope.serializer(MatchRewards.serializer()), payload).data

        assertEquals("pending_verification", r.competitiveXp.state)
        assertNull("no competitive XP before the result is verified", r.competitiveXp.xp)
        assertEquals(20, r.bonusXp.thisMatch)
        assertEquals(listOf("verification"), r.rewards.locked.single().lockReasons)
        assertFalse(r.rewards.locked.single().claimable)
        assertTrue(r.badges.single().isNew)
        assertTrue(r.streak.extendedThisMatch)
        assertFalse(r.rewardedAds.available)
    }

    @Test
    fun server_errors_become_player_facing_messages() {
        val e = RewardsRepository.toRewardsException(409, """{"error":"This reward is still locked.","code":"not_claimable"}""", json)
        assertEquals("This reward is still locked.", e.message)
        assertEquals("not_claimable", e.code)

        val fallback = RewardsRepository.toRewardsException(404, null, json)
        assertEquals("Rewards are for the players in this match.", fallback.message)
    }

    @Test
    fun reward_push_links_open_the_match_rewards() {
        assertEquals(DeepLinkTarget.MatchRewards("42"), DeepLinks.parse("haraan://rewards/match/42"))
        assertEquals(DeepLinkTarget.MatchRewards("7"), DeepLinks.parse("/rewards/match/7/"))
        assertEquals(DeepLinkTarget.Inbox, DeepLinks.parse("haraan://rewards"))
        // A malformed id falls back to the inbox rather than a broken screen.
        assertEquals(DeepLinkTarget.Inbox, DeepLinks.parse("haraan://rewards/match/abc"))
    }

    @Test
    fun dates_are_short_and_never_throw() {
        assertEquals("19 Oct", shortDate("2026-10-19T10:00:00+05:30"))
        assertEquals("not-a-dat", shortDate("not-a-date").take(9))
    }
}
