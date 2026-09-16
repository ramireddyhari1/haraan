package com.haraan.app.ui.membership

import com.haraan.app.data.membership.CataloguePlan
import com.haraan.app.data.membership.Membership
import com.haraan.app.data.membership.PlanFeature
import com.haraan.app.data.membership.PlanPrice
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter

/**
 * Pure presentation rules for membership: money, dates, the status sentence, and what the
 * one commit button on a plan should do. No Android types, so every rule is unit-tested.
 */
object MembershipFormat {

    const val MONTH = "month"
    const val YEAR = "year"

    private val dateFormat = DateTimeFormatter.ofPattern("d MMM yyyy", java.util.Locale.ENGLISH)

    /** 9900 → "₹99", 249950 → "₹2,499.50" — Indian digit grouping, no stray ".00". */
    fun rupees(paise: Long): String {
        // Grouped by hand: java.text can't express lakh grouping (1,00,000) and Android's ICU
        // can, so a locale formatter would print differently on device and in tests.
        val sign = if (paise < 0) "-" else ""
        val abs = kotlin.math.abs(paise)
        val digits = (abs / 100).toString()
        val grouped = if (digits.length <= 3) digits else {
            val head = digits.dropLast(3)
            head.reversed().chunked(2).joinToString(",").reversed() + "," + digits.takeLast(3)
        }
        val fraction = abs % 100
        return sign + "₹" + grouped + if (fraction == 0L) "" else "." + fraction.toString().padStart(2, '0')
    }

    fun perInterval(interval: String?): String = if (interval == YEAR) "/year" else "/month"

    fun priceLabel(price: PlanPrice): String = rupees(price.amountPaise) + perInterval(price.interval)

    /** "2026-10-12T10:00:00+05:30" → "12 Oct 2026". Unparseable input is dropped, not shown raw. */
    fun date(iso: String?, zone: ZoneId = ZoneId.systemDefault()): String? {
        if (iso.isNullOrBlank()) return null
        return runCatching { OffsetDateTime.parse(iso).atZoneSameInstant(zone).format(dateFormat) }.getOrNull()
    }

    /** The price for [interval], if that plan is on sale at that interval. */
    fun priceFor(plan: CataloguePlan, interval: String): PlanPrice? = plan.prices.firstOrNull { it.interval == interval }

    /**
     * Whole-percent saving of a yearly price over twelve monthly ones, computed from the real
     * prices — null when there isn't one to claim.
     */
    fun yearlySaving(plans: List<CataloguePlan>): Int? = plans.mapNotNull { plan ->
        val monthly = priceFor(plan, MONTH)?.amountPaise ?: return@mapNotNull null
        val yearly = priceFor(plan, YEAR)?.amountPaise ?: return@mapNotNull null
        val full = monthly * 12
        if (full <= 0 || yearly >= full) null else (((full - yearly) * 100) / full).toInt()
    }.minOrNull()?.takeIf { it > 0 }

    fun hasYearly(plans: List<CataloguePlan>): Boolean = plans.any { plan -> plan.prices.any { it.interval == YEAR } }

    /** One sentence on where the member stands. */
    fun statusLine(m: Membership, zone: ZoneId = ZoneId.systemDefault()): String {
        val sub = m.subscription
        return when {
            m.attention == "payment_failed" ->
                "Your last payment didn't go through, so your plan is paused. Choose it again below to restart."
            m.attention == "payment_retrying" ->
                "We couldn't charge your renewal yet. Razorpay is retrying, and your plan stays on meanwhile."
            sub == null -> "You're on the free plan."
            sub.provider == "admin" -> date(sub.endsAt, zone)?.let { "Complimentary until $it." } ?: "Complimentary plan from Haraan."
            sub.cancelAtPeriodEnd -> date(sub.endsAt, zone)?.let { "Ends on $it. You won't be charged again." } ?: "Ends at the end of this period."
            sub.renewsAt != null -> {
                val on = date(sub.renewsAt, zone)
                val amount = sub.amountPaise?.let { rupees(it) + perInterval(sub.interval) }
                listOfNotNull(on?.let { "Renews on $it" }, amount).joinToString(" · ") + "."
            }
            else -> "Active."
        }
    }

    /** What a feature is worth on a plan, in a few words: "Included", "20 per month", "Unlimited". */
    fun featureValue(f: PlanFeature): String? = when {
        !f.enabled -> null
        f.type == "boolean" -> "Included"
        f.unlimited -> "Unlimited"
        f.limit == null -> "Included"
        f.type == "quota" -> "${f.limit} per month"
        // Units are stored plural ("angles", "sports"); one of them reads singular.
        else -> listOfNotNull(
            f.limit.toString(),
            f.unit?.let { u -> if (f.limit == 1 && u.endsWith("s") && ' ' !in u) u.dropLast(1) else u },
        ).joinToString(" ")
    }

    /** "1 of 2 used this month" for a member's own quota; null for anything else. */
    fun usageLine(f: PlanFeature): String? {
        if (f.type != "quota" || !f.enabled || f.used == null) return null
        return if (f.unlimited) "${f.used} used this month" else "${f.used} of ${f.limit} used this month"
    }

    sealed interface Cta {
        /** The member's current plan at this interval — nothing to buy. */
        data object Current : Cta

        /** The free plan while a paid one is live: reached by cancelling, not by buying. */
        data object IncludedAfterCancel : Cta

        /** No price at the chosen interval (or the plan isn't sold). */
        data object Unavailable : Cta

        /** Signed out: plans are browsable, buying needs an account. */
        data object SignIn : Cta

        data class Buy(val price: PlanPrice, val kind: Kind) : Cta

        enum class Kind { NEW, UPGRADE, DOWNGRADE, SWITCH_INTERVAL }
    }

    /**
     * What choosing [plan] at [interval] would do. Mirrors the server's classification so the
     * button says truthfully what will happen — the server still decides.
     */
    fun ctaFor(plan: CataloguePlan, membership: Membership?, interval: String, signedIn: Boolean): Cta {
        val currentRank = membership?.plan?.rank ?: 0
        val sub = membership?.subscription
        val paidLive = sub != null && sub.provider == "razorpay"

        if (plan.isDefault) {
            return when {
                membership == null || membership.plan.code == plan.code -> Cta.Current
                else -> Cta.IncludedAfterCancel
            }
        }

        val price = priceFor(plan, interval) ?: return if (membership?.plan?.code == plan.code) Cta.Current else Cta.Unavailable
        if (!signedIn) return Cta.SignIn

        if (membership?.plan?.code == plan.code) {
            val sameInterval = sub?.interval == null || sub.interval == interval
            // A paid plan that's already set to end can be picked again to keep it.
            if (sameInterval && !(paidLive && sub!!.cancelAtPeriodEnd)) return Cta.Current
            return Cta.Buy(price, Cta.Kind.SWITCH_INTERVAL)
        }

        if (!paidLive) return Cta.Buy(price, Cta.Kind.NEW)

        return when {
            plan.rank > currentRank -> Cta.Buy(price, Cta.Kind.UPGRADE)
            plan.rank < currentRank -> Cta.Buy(price, Cta.Kind.DOWNGRADE)
            else -> Cta.Buy(price, Cta.Kind.SWITCH_INTERVAL)
        }
    }

    /** The checkout button's words: short, because the price and terms sit beside it. */
    fun commitAction(plan: CataloguePlan, buy: Cta.Buy): String = when (buy.kind) {
        Cta.Kind.NEW -> "Get ${plan.name}"
        Cta.Kind.UPGRADE -> "Upgrade"
        Cta.Kind.DOWNGRADE, Cta.Kind.SWITCH_INTERVAL -> "Switch"
    }

    /** The one line of terms under the price — what happens when they tap, and when. */
    fun commitTerms(buy: Cta.Buy, membership: Membership?, zone: ZoneId = ZoneId.systemDefault()): String = when (buy.kind) {
        Cta.Kind.NEW -> (if (buy.price.interval == YEAR) "Renews yearly" else "Renews monthly") + " · Cancel anytime"
        Cta.Kind.UPGRADE -> "Starts today · Current plan stops renewing"
        Cta.Kind.DOWNGRADE, Cta.Kind.SWITCH_INTERVAL ->
            date(membership?.subscription?.endsAt ?: membership?.subscription?.renewsAt, zone)
                ?.let { "Starts $it · No charge until then" }
                ?: "Starts when your plan ends · No charge until then"
    }
}
