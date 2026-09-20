package com.haraan.app.data.rewards

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * Post-match rewards, as /api/matches/{id}/rewards shapes them.
 *
 * Competitive XP ([CompetitiveXp]) and Bonus XP ([BonusXp]) are separate on purpose and are
 * never added together: competitive XP ranks you, Bonus XP is reward currency that never does.
 */
@Serializable
data class RewardsEnvelope<T>(val data: T)

@Serializable
data class MatchRewards(
    val match: RewardMatch,
    val viewer: RewardViewer,
    val celebration: Celebration,
    @SerialName("competitive_xp") val competitiveXp: CompetitiveXp,
    @SerialName("bonus_xp") val bonusXp: BonusXp,
    val badges: List<RewardBadge> = emptyList(),
    val streak: RewardStreak,
    val rewards: RewardGroups,
    @SerialName("rewarded_ads") val rewardedAds: RewardedAdsInfo,
    @SerialName("next_badge") val nextBadge: NextBadge? = null,
)

/** The locked badge the player is closest to, with their real value against its threshold. */
@Serializable
data class NextBadge(
    val key: String,
    val name: String,
    val icon: String = "EmojiEvents",
    val tier: String = "bronze",
    val metric: String = "",
    val value: Int = 0,
    val threshold: Int = 1,
)

@Serializable
data class RewardMatch(
    val id: Long,
    val title: String,
    val sport: String = "cricket",
    @SerialName("match_type") val matchType: String = "casual",
    @SerialName("result_line") val resultLine: String = "",
    val home: String = "",
    val away: String = "",
    @SerialName("home_short") val homeShort: String = "",
    @SerialName("away_short") val awayShort: String = "",
    @SerialName("home_score") val homeScore: Int = 0,
    @SerialName("away_score") val awayScore: Int = 0,
    @SerialName("score_text") val scoreText: String? = null,
    val overs: String? = null,
    @SerialName("home_score_display") val homeScoreDisplay: String? = null,
    @SerialName("away_score_display") val awayScoreDisplay: String? = null,
    @SerialName("home_logo") val homeLogo: String? = null,
    @SerialName("away_logo") val awayLogo: String? = null,
    @SerialName("share_url") val shareUrl: String? = null,
)

@Serializable
data class PlayerImpact(
    val runs: Int? = null,
    val balls: Int? = null,
    val wickets: Int? = null,
    @SerialName("overs_bowled") val oversBowled: Double? = null,
    @SerialName("runs_conceded") val runsConceded: Int? = null,
    val summary: String? = null,
    @SerialName("is_potm") val isPotm: Boolean = false,
    @SerialName("player_name") val playerName: String? = null,
    @SerialName("player_photo") val playerPhoto: String? = null,
    @SerialName("team_name") val teamName: String? = null,
    @SerialName("team_logo") val teamLogo: String? = null,
    val role: String? = null,
)

@Serializable
data class RewardViewer(
    val side: String = "",
    val outcome: String = "unknown",
    val impact: PlayerImpact? = null,
)

@Serializable
data class Celebration(
    val enabled: Boolean = true,
    @SerialName("animation_url") val animationUrl: String? = null,
    /** won | lost | tied | finished */
    val variant: String = "finished",
    val seen: Boolean = false,
)

@Serializable
data class CompetitiveXp(
    /** pending_verification | settled | not_eligible */
    val state: String,
    val xp: Int? = null,
    @SerialName("is_ranked") val isRanked: Boolean? = null,
    @SerialName("trust_level") val trustLevel: String? = null,
    val deadline: String? = null,
    @SerialName("home_confirmed") val homeConfirmed: Boolean = false,
    @SerialName("away_confirmed") val awayConfirmed: Boolean = false,
    val explanation: String = "",
)

@Serializable
data class BonusXp(
    @SerialName("this_match") val thisMatch: Int = 0,
    val total: Int = 0,
)

@Serializable
data class RewardBadge(
    val key: String,
    val name: String,
    val description: String? = null,
    val icon: String = "EmojiEvents",
    val tier: String = "bronze",
    @SerialName("is_new") val isNew: Boolean = false,
)

@Serializable
data class RewardStreak(
    val current: Int = 0,
    val best: Int = 0,
    @SerialName("extended_this_match") val extendedThisMatch: Boolean = false,
    @SerialName("played_this_week") val playedThisWeek: Boolean = false,
)

@Serializable
data class RewardGroups(
    val ready: List<RewardItem> = emptyList(),
    val locked: List<RewardItem> = emptyList(),
    val claimed: List<RewardItem> = emptyList(),
    val closed: List<RewardItem> = emptyList(),
)

/** The big number on a ticket-style card: "₹50" / "OFF". Null when there's no single figure. */
@Serializable
data class RewardStub(val value: String, val caption: String)

@Serializable
data class RewardSponsor(val name: String? = null, val logo: String? = null, val category: String? = null)

@Serializable
data class RewardItem(
    val id: Long,
    val type: String,
    val status: String,
    @SerialName("lock_reasons") val lockReasons: List<String> = emptyList(),
    @SerialName("status_reason") val statusReason: String? = null,
    val title: String,
    val description: String? = null,
    val sponsored: Boolean = false,
    val sponsor: RewardSponsor? = null,
    val disclosure: String? = null,
    @SerialName("terms_url") val termsUrl: String? = null,
    @SerialName("card_image") val cardImage: String? = null,
    @SerialName("brand_color") val brandColor: String? = null,
    @SerialName("bonus_xp") val bonusXp: Int? = null,
    val geo: RewardGeo? = null,
    val stub: RewardStub? = null,
    val claimable: Boolean = false,
    @SerialName("can_watch_ad") val canWatchAd: Boolean = false,
    @SerialName("expires_at") val expiresAt: String? = null,
    val claim: ClaimDetail? = null,
)

/**
 * Where a location-targeted reward came from, and where it is redeemed.
 *
 * `distanceKm` is how far the MATCH was from the zone when the reward was won — not where the
 * player is now. The server never learns the player's position, and nothing here is ever used to
 * decide what a reward is worth.
 */
@Serializable
data class RewardGeo(
    val zone: String,
    @SerialName("distance_km") val distanceKm: Double? = null,
    @SerialName("redeem_at") val redeemAt: RewardPlace? = null,
)

@Serializable
data class RewardPlace(
    val name: String,
    val latitude: Double,
    val longitude: Double,
) {
    /** A geo: URI every Android maps app understands, with the name as the pin label. */
    fun directionsUri(): String =
        "geo:0,0?q=" + latitude + "," + longitude + "(" + android.net.Uri.encode(name) + ")"
}

/** What a claimed reward gives: a code (coupon or sponsor), a trial end date, or a link. */
@Serializable
data class ClaimDetail(
    val code: String? = null,
    val instructions: String? = null,
    val scope: String? = null,
    @SerialName("expires_at") val expiresAt: String? = null,
    @SerialName("ends_at") val endsAt: String? = null,
    val url: String? = null,
    @SerialName("cta_text") val ctaText: String? = null,
    val used: Boolean? = null,
)

@Serializable
data class RewardedAdsInfo(
    val available: Boolean = false,
    @SerialName("ad_unit_id") val adUnitId: String? = null,
    @SerialName("ssv_user_id") val ssvUserId: String? = null,
)

@Serializable
data class AdSession(
    val nonce: String,
    @SerialName("ad_unit_id") val adUnitId: String,
    @SerialName("ssv_user_id") val ssvUserId: String,
)

@Serializable
data class AdSessionStatus(
    /** pending | verified | expired | rejected */
    val status: String,
    @SerialName("grant_status") val grantStatus: String? = null,
)

@Serializable
data class RewardsErrorBody(val error: String? = null, val code: String? = null)
