package com.haraan.app.ui.membership

import com.haraan.app.data.membership.Membership
import com.haraan.app.data.membership.PlanFeature

/**
 * The member identity layer: which mark (if any) a person wears, and how their plan's real
 * entitlements read across the app. Pure rules — no Android types — so every decision is
 * unit-tested.
 *
 * Nothing here grants anything. A tier is only ever read back from what the server said:
 * the `badge` it returns (set only while a granting subscription includes the member badge)
 * or, on the member's own Membership screen, the plan it reports them on. Anything the app
 * doesn't recognise resolves to [REGULAR], so an unknown or missing answer can never put a
 * Pro or Hero mark on someone.
 */
enum class MemberTier(val label: String) {
    REGULAR(""),
    PRO("Pro"),
    HERO("Hero");

    val isMember: Boolean get() = this != REGULAR

    companion object {
        /** A server badge code ("pro" / "hero") → tier. Null, blank or unknown → [REGULAR]. */
        fun fromBadge(code: String?): MemberTier = when (code?.trim()?.lowercase()) {
            "pro" -> PRO
            "hero" -> HERO
            else -> REGULAR
        }

        /**
         * The tier a member's public identity shows (account card, player profile): exactly the
         * server's badge decision. A plan whose badge is switched off in /control shows none.
         */
        fun identityOf(m: Membership?): MemberTier = fromBadge(m?.badge)

        /**
         * The tier the member's own plan screen is dressed in. It follows the plan they are on
         * right now, but a paused plan (payment failed) never wears the premium treatment — the
         * card has to lead with the problem instead.
         */
        fun planOf(m: Membership?): MemberTier {
            if (m == null || m.plan.rank <= 0 || m.attention == "payment_failed") return REGULAR
            return fromBadge(m.plan.code)
        }
    }
}

/** Where in Haraan a benefit is felt. Order is the order the member sees them in. */
enum class MemberArea(val title: String) {
    EVERYWHERE("Across Haraan"),
    EVENTS("Events"),
    PULSE("Pulse"),
    ACTIONBOARD("Actionboard"),
    ACCOUNT("Your account");

    companion object {
        /**
         * The area a server feature key belongs to. Known keys are placed by where the app
         * actually enforces them; a key added on the server later is placed by its namespace,
         * and anything else still shows — under [ACCOUNT] — rather than disappearing.
         */
        fun of(key: String): MemberArea = when (key) {
            "ads.hidden" -> EVERYWHERE
            "ai.career_read",
            "ai.delivery_review",
            "tournaments.active_hosted",
            "matches.camera_angles",
            "insights.advanced_sports" -> ACTIONBOARD
            "profile.member_badge",
            "support.priority" -> ACCOUNT
            else -> when (key.substringBefore('.')) {
                "events", "tickets", "passes" -> EVENTS
                "pulse", "venues", "bookings", "gamehub" -> PULSE
                "matches", "tournaments", "insights", "actionboard", "career" -> ACTIONBOARD
                else -> ACCOUNT
            }
        }
    }
}

/** One line of a plan's sheet: a feature, and what this plan gives of it. */
data class PlanSheetRow(
    val key: String,
    val name: String,
    /** False when the plan doesn't include it; the row still shows so plans compare honestly. */
    val included: Boolean,
    /** "Included", "Unlimited", "3 at a time", "20 per month"; null when not included. */
    val value: String?,
    /**
     * How this line differs from the member's own plan — "New for you", "You have 2 per month",
     * "On your plan now". Null when there is nothing to compare or nothing differs.
     */
    val yours: String? = null,
    /** True when this plan adds something the member's plan doesn't have at all. */
    val gain: Boolean = false,
)

data class PlanSheetSection(val area: MemberArea, val rows: List<PlanSheetRow>)

object PlanSheet {

    /**
     * Everything a plan's catalogue entry lists, grouped by where in Haraan it applies. Every
     * feature the server lists appears, included or not, so each plan reads against the others
     * line for line; nothing is added that the server didn't send. Sections keep [MemberArea]
     * order, rows keep the server's order.
     *
     * [yours] is the member's own plan's feature list from the same catalogue. When given, each
     * line that differs says what they have today, so looking at another plan answers "what would
     * change for me" in the server's own figures.
     */
    fun of(features: List<PlanFeature>, yours: List<PlanFeature>? = null): List<PlanSheetSection> {
        val own = yours?.associateBy { it.key }
        return features
            .groupBy { MemberArea.of(it.key) }
            .toSortedMap(compareBy { it.ordinal })
            .map { (area, group) ->
                PlanSheetSection(
                    area = area,
                    rows = group.map { f ->
                        val value = MembershipFormat.featureValue(f)
                        PlanSheetRow(
                            key = f.key,
                            name = f.name,
                            included = value != null,
                            value = value,
                            yours = own?.let { comparison(value, it[f.key]) },
                            gain = own != null && value != null && own[f.key]?.let { MembershipFormat.featureValue(it) } == null,
                        )
                    },
                )
            }
    }

    private fun comparison(value: String?, mine: PlanFeature?): String? {
        val have = mine?.let { MembershipFormat.featureValue(it) }
        return when {
            have == value -> null
            have == null -> "New for you"
            have == "Included" -> "On your plan now"
            else -> "You have $have"
        }
    }
}
