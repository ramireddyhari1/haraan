package com.haraan.app.ui.rewards

import com.haraan.app.data.rewards.BonusXp
import com.haraan.app.data.rewards.Celebration
import com.haraan.app.data.rewards.ClaimDetail
import com.haraan.app.data.rewards.CompetitiveXp
import com.haraan.app.data.rewards.MatchRewards
import com.haraan.app.data.rewards.NextBadge
import com.haraan.app.data.rewards.PlayerImpact
import com.haraan.app.data.rewards.RewardBadge
import com.haraan.app.data.rewards.RewardGroups
import com.haraan.app.data.rewards.RewardItem
import com.haraan.app.data.rewards.RewardMatch
import com.haraan.app.data.rewards.RewardSponsor
import com.haraan.app.data.rewards.RewardStreak
import com.haraan.app.data.rewards.RewardStub
import com.haraan.app.data.rewards.RewardViewer
import com.haraan.app.data.rewards.RewardedAdsInfo

internal object DemoRewardsData {
    fun create(matchId: String = "1"): MatchRewards {
        val id = matchId.toLongOrNull() ?: 1L
        return MatchRewards(
            match = RewardMatch(
                id = id,
                title = "Thunder CC vs Lightning XI",
                sport = "cricket",
                matchType = "ranked",
                resultLine = "Thunder CC won by 14 runs",
                home = "Thunder CC",
                away = "Lightning XI",
                homeShort = "THU",
                awayShort = "LGT",
                homeScore = 184,
                awayScore = 170,
                scoreText = "184/5",
                overs = "20.0",
                homeScoreDisplay = "184/5 (20.0)",
                awayScoreDisplay = "170/8 (20.0)",
                homeLogo = "action1",
                awayLogo = "action2",
                shareUrl = "https://haraan.app/matches/$id",
            ),
            viewer = RewardViewer(
                side = "home",
                outcome = "won",
                impact = PlayerImpact(
                    runs = 64,
                    balls = 38,
                    wickets = 2,
                    oversBowled = 4.0,
                    runsConceded = 28,
                    summary = "64 (38b) & 2/28",
                    isPotm = true,
                    playerName = "Hariharan R.",
                    playerPhoto = "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&auto=format&fit=crop&q=80",
                    teamName = "Thunder CC",
                    teamLogo = "action1",
                    role = "ALL-ROUNDER",
                ),
            ),
            celebration = Celebration(
                enabled = true,
                animationUrl = null,
                variant = "won",
                seen = false,
            ),
            competitiveXp = CompetitiveXp(
                state = "pending_verification",
                xp = null,
                isRanked = true,
                trustLevel = "medium",
                deadline = "Today, 8:00 PM",
                homeConfirmed = true,
                awayConfirmed = false,
                explanation = "Opponent captain confirmation pending",
            ),
            bonusXp = BonusXp(
                thisMatch = 35,
                total = 280,
            ),
            badges = listOf(
                RewardBadge(
                    key = "potm",
                    name = "Player of the Match",
                    description = "Decisive performance in victory",
                    icon = "EmojiEvents",
                    tier = "gold",
                    isNew = true,
                ),
                RewardBadge(
                    key = "fifty",
                    name = "Half Century",
                    description = "64 runs at a strike rate of 168.4",
                    icon = "Whatshot",
                    tier = "silver",
                    isNew = true,
                ),
            ),
            streak = RewardStreak(
                current = 4,
                best = 6,
                extendedThisMatch = true,
                playedThisWeek = true,
            ),
            rewards = RewardGroups(
                ready = listOf(
                    RewardItem(
                        id = 101L,
                        type = "venue_discount",
                        status = "ready",
                        title = "₹100 Off Turf Booking",
                        description = "Valid across all registered cricket turf slots on Haraan",
                        sponsored = true,
                        sponsor = RewardSponsor(name = "TurfTown", category = "Venues"),
                        brandColor = "#00B894",
                        stub = RewardStub(value = "₹100", caption = "OFF"),
                        claimable = true,
                        expiresAt = "2026-10-01T23:59:59Z",
                    ),
                    RewardItem(
                        id = 104L,
                        type = "sponsor_perk",
                        status = "ready",
                        title = "Flat ₹500 Off Running Shoes",
                        description = "Applicable on spikes & footwear above ₹2,499",
                        sponsored = true,
                        sponsor = RewardSponsor(name = "Puma", category = "Footwear"),
                        brandColor = "#E11D48",
                        stub = RewardStub(value = "₹500", caption = "OFF"),
                        claimable = true,
                        expiresAt = "2026-10-12T23:59:59Z",
                    ),
                    RewardItem(
                        id = 105L,
                        type = "fitness_pass",
                        status = "ready",
                        title = "1 Month Pro Pass Free",
                        description = "Access elite strength, conditioning & recovery centers",
                        sponsored = true,
                        sponsor = RewardSponsor(name = "Cult.fit", category = "Fitness"),
                        brandColor = "#6366F1",
                        stub = RewardStub(value = "1 MO", caption = "FREE"),
                        claimable = true,
                        expiresAt = "2026-10-15T23:59:59Z",
                    ),
                ),
                locked = listOf(
                    RewardItem(
                        id = 102L,
                        type = "sponsor_perk",
                        status = "locked",
                        lockReasons = listOf("verification"),
                        title = "Fast&Up Hydration Pack",
                        description = "Exclusive electrolyte kit for match-winners",
                        sponsored = true,
                        sponsor = RewardSponsor(name = "Fast&Up", category = "Nutrition"),
                        brandColor = "#F39C12",
                        stub = RewardStub(value = "FREE", caption = "KIT"),
                        claimable = false,
                        canWatchAd = false,
                        expiresAt = "2026-10-05T23:59:59Z",
                    ),
                    RewardItem(
                        id = 106L,
                        type = "equipment_discount",
                        status = "locked",
                        lockReasons = listOf("rewarded_ad"),
                        title = "20% Off English Willow Bats",
                        description = "Watch a partner spotlight to unlock this pass",
                        sponsored = true,
                        sponsor = RewardSponsor(name = "SS Cricket", category = "Equipment"),
                        brandColor = "#DC2626",
                        stub = RewardStub(value = "20%", caption = "OFF"),
                        claimable = false,
                        canWatchAd = true,
                        expiresAt = "2026-10-20T23:59:59Z",
                    ),
                ),
                claimed = listOf(
                    RewardItem(
                        id = 103L,
                        type = "community_coupon",
                        status = "claimed",
                        title = "15% Off Cricket Gear",
                        description = "Redeemable in-store or online at SportsHub",
                        sponsored = true,
                        sponsor = RewardSponsor(name = "SportsHub", category = "Gear"),
                        brandColor = "#3B82F6",
                        stub = RewardStub(value = "15%", caption = "OFF"),
                        claimable = false,
                        claim = ClaimDetail(
                          code = "HARAAN-WIN-64",
                          instructions = "Show this digital pass at checkout or apply promo code online",
                          scope = "venue",
                          ctaText = "Use Coupon",
                          used = false,
                        ),
                    ),
                    RewardItem(
                        id = 107L,
                        type = "beverage_perk",
                        status = "claimed",
                        title = "Buy 1 Get 1 Free Drink",
                        description = "Rehydrate at any partner arena cafeteria post match",
                        sponsored = true,
                        sponsor = RewardSponsor(name = "Gatorade", category = "Beverage"),
                        brandColor = "#F97316",
                        stub = RewardStub(value = "BOGO", caption = "DRINK"),
                        claimable = false,
                        claim = ClaimDetail(
                          code = "GATOR-PLAY-26",
                          instructions = "Show digital voucher at venue canteen counter",
                          scope = "venue",
                          ctaText = "Redeem Pass",
                          used = false,
                        ),
                    ),
                ),
                closed = emptyList(),
            ),
            rewardedAds = RewardedAdsInfo(available = true),
            nextBadge = NextBadge(
                key = "centurion",
                name = "Centurion Club",
                icon = "MilitaryTech",
                tier = "gold",
                metric = "Career runs",
                value = 820,
                threshold = 1000,
            ),
        )
    }
}
