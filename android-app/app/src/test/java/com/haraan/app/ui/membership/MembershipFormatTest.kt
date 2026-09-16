package com.haraan.app.ui.membership

import com.haraan.app.data.membership.CataloguePlan
import com.haraan.app.data.membership.Membership
import com.haraan.app.data.membership.MembershipPlanRef
import com.haraan.app.data.membership.MembershipRepository
import com.haraan.app.data.membership.MembershipSubscription
import com.haraan.app.data.membership.NamedPlan
import com.haraan.app.data.membership.PlanFeature
import com.haraan.app.data.membership.PlanPrice
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.ZoneId

class MembershipFormatTest {

    private val ist = ZoneId.of("Asia/Kolkata")

    private val free = CataloguePlan(code = "free", name = "Free", rank = 0, isDefault = true)
    private val pro = CataloguePlan(
        code = "pro", name = "Pro", rank = 10,
        prices = listOf(PlanPrice(1, "month", 9_900, label = "₹99/month"), PlanPrice(2, "year", 99_900, label = "₹999/year")),
    )
    private val hero = CataloguePlan(
        code = "hero", name = "Hero", rank = 20,
        prices = listOf(PlanPrice(3, "month", 24_900), PlanPrice(4, "year", 249_900)),
    )

    private fun onFree() = Membership(plan = MembershipPlanRef("free", "Free", 0))

    private fun onPaid(
        plan: CataloguePlan,
        interval: String = "month",
        cancelAtPeriodEnd: Boolean = false,
        provider: String = "razorpay",
    ) = Membership(
        plan = MembershipPlanRef(plan.code, plan.name, plan.rank),
        source = provider,
        subscription = MembershipSubscription(
            id = 1, status = "active", provider = provider,
            plan = NamedPlan(plan.code, plan.name), interval = interval,
            amountPaise = plan.prices.first { it.interval == interval }.amountPaise,
            renewsAt = if (cancelAtPeriodEnd) null else "2026-10-12T10:00:00+05:30",
            endsAt = if (cancelAtPeriodEnd) "2026-10-12T10:00:00+05:30" else null,
            cancelAtPeriodEnd = cancelAtPeriodEnd,
            canCancel = !cancelAtPeriodEnd,
        ),
    )

    // ── Money & dates ───────────────────────────────────────────────────────

    @Test
    fun rupees_useIndianGroupingAndDropWholePaise() {
        assertEquals("₹99", MembershipFormat.rupees(9_900))
        assertEquals("₹2,499", MembershipFormat.rupees(249_900))
        assertEquals("₹1,00,000", MembershipFormat.rupees(10_000_000))
        assertEquals("₹49.50", MembershipFormat.rupees(4_950))
    }

    @Test
    fun date_formatsInTheGivenZoneAndIgnoresGarbage() {
        assertEquals("12 Oct 2026", MembershipFormat.date("2026-10-12T10:00:00+05:30", ist))
        // 23:30 UTC is already the next day in India.
        assertEquals("13 Oct 2026", MembershipFormat.date("2026-10-12T23:30:00Z", ist))
        assertNull(MembershipFormat.date("not a date", ist))
        assertNull(MembershipFormat.date(null, ist))
    }

    @Test
    fun yearlySaving_isComputedFromRealPricesAndNeverInvented() {
        // Pro: 999 vs 1188 → 15%; Hero: 2499 vs 2988 → 16%. Claim the smaller, true for both.
        assertEquals(15, MembershipFormat.yearlySaving(listOf(free, pro, hero)))
        val noDiscount = pro.copy(prices = listOf(PlanPrice(1, "month", 9_900), PlanPrice(2, "year", 118_800)))
        assertNull(MembershipFormat.yearlySaving(listOf(noDiscount)))
        assertNull(MembershipFormat.yearlySaving(listOf(pro.copy(prices = listOf(PlanPrice(1, "month", 9_900))))))
    }

    // ── Status sentence ─────────────────────────────────────────────────────

    @Test
    fun statusLine_coversEveryState() {
        assertEquals("You're on the free plan.", MembershipFormat.statusLine(onFree(), ist))
        assertEquals("Renews on 12 Oct 2026 · ₹99/month.", MembershipFormat.statusLine(onPaid(pro), ist))
        assertEquals(
            "Ends on 12 Oct 2026. You won't be charged again.",
            MembershipFormat.statusLine(onPaid(pro, cancelAtPeriodEnd = true), ist),
        )
        assertTrue(MembershipFormat.statusLine(onFree().copy(attention = "payment_failed"), ist).contains("didn't go through"))
        assertTrue(MembershipFormat.statusLine(onPaid(pro).copy(attention = "payment_retrying"), ist).contains("retrying"))

        val comp = onPaid(hero, provider = "admin").let {
            it.copy(subscription = it.subscription!!.copy(renewsAt = null, endsAt = null))
        }
        assertEquals("Complimentary plan from Haraan.", MembershipFormat.statusLine(comp, ist))
    }

    // ── Features ────────────────────────────────────────────────────────────

    @Test
    fun featureValue_andUsage() {
        assertEquals("Included", MembershipFormat.featureValue(PlanFeature("ads.hidden", "No ads", enabled = true)))
        assertNull(MembershipFormat.featureValue(PlanFeature("ads.hidden", "No ads", enabled = false)))
        assertEquals("20 per month", MembershipFormat.featureValue(PlanFeature("ai.delivery_review", "Reviews", type = "quota", enabled = true, limit = 20)))
        assertEquals("Unlimited", MembershipFormat.featureValue(PlanFeature("ai.delivery_review", "Reviews", type = "quota", enabled = true, unlimited = true)))
        assertEquals("3 at a time", MembershipFormat.featureValue(PlanFeature("tournaments.active_hosted", "Tournaments", type = "limit", unit = "at a time", enabled = true, limit = 3)))
        assertEquals("1 angle", MembershipFormat.featureValue(PlanFeature("matches.camera_angles", "Camera angles", type = "limit", unit = "angles", enabled = true, limit = 1)))
        assertEquals("2 angles", MembershipFormat.featureValue(PlanFeature("matches.camera_angles", "Camera angles", type = "limit", unit = "angles", enabled = true, limit = 2)))
        assertEquals("1 at a time", MembershipFormat.featureValue(PlanFeature("tournaments.active_hosted", "Tournaments", type = "limit", unit = "at a time", enabled = true, limit = 1)))

        assertEquals("1 of 2 used this month", MembershipFormat.usageLine(PlanFeature("ai.delivery_review", "Reviews", type = "quota", enabled = true, limit = 2, used = 1)))
        assertNull(MembershipFormat.usageLine(PlanFeature("ads.hidden", "No ads", enabled = true)))
    }

    // ── What the button does ────────────────────────────────────────────────

    @Test
    fun cta_forAFreeMember() {
        val m = onFree()
        assertEquals(MembershipFormat.Cta.Current, MembershipFormat.ctaFor(free, m, "month", signedIn = true))
        assertEquals(MembershipFormat.Cta.Buy(pro.prices[0], MembershipFormat.Cta.Kind.NEW), MembershipFormat.ctaFor(pro, m, "month", signedIn = true))
        assertEquals(MembershipFormat.Cta.Buy(hero.prices[1], MembershipFormat.Cta.Kind.NEW), MembershipFormat.ctaFor(hero, m, "year", signedIn = true))
    }

    @Test
    fun cta_forGuestsAndUnsoldPlans() {
        assertEquals(MembershipFormat.Cta.SignIn, MembershipFormat.ctaFor(pro, null, "month", signedIn = false))
        val monthlyOnly = pro.copy(prices = listOf(pro.prices[0]))
        assertEquals(MembershipFormat.Cta.Unavailable, MembershipFormat.ctaFor(monthlyOnly, onFree(), "year", signedIn = true))
    }

    @Test
    fun cta_forAPayingMember_mirrorsTheServersChangeRules() {
        val m = onPaid(pro)
        assertEquals(MembershipFormat.Cta.Current, MembershipFormat.ctaFor(pro, m, "month", signedIn = true))
        assertEquals(MembershipFormat.Cta.Kind.SWITCH_INTERVAL, (MembershipFormat.ctaFor(pro, m, "year", signedIn = true) as MembershipFormat.Cta.Buy).kind)
        assertEquals(MembershipFormat.Cta.Kind.UPGRADE, (MembershipFormat.ctaFor(hero, m, "month", signedIn = true) as MembershipFormat.Cta.Buy).kind)
        assertEquals(MembershipFormat.Cta.IncludedAfterCancel, MembershipFormat.ctaFor(free, m, "month", signedIn = true))

        val onHero = onPaid(hero)
        assertEquals(MembershipFormat.Cta.Kind.DOWNGRADE, (MembershipFormat.ctaFor(pro, onHero, "month", signedIn = true) as MembershipFormat.Cta.Buy).kind)
    }

    @Test
    fun cta_aCancelledPlanCanBeChosenAgain() {
        val m = onPaid(pro, cancelAtPeriodEnd = true)
        val cta = MembershipFormat.ctaFor(pro, m, "month", signedIn = true)
        assertTrue(cta is MembershipFormat.Cta.Buy)
    }

    @Test
    fun commitActionAndTerms_sayWhatTheTapDoesAndWhen() {
        val onHero = onPaid(hero)
        val down = MembershipFormat.ctaFor(pro, onHero, "month", signedIn = true) as MembershipFormat.Cta.Buy
        assertEquals("Switch", MembershipFormat.commitAction(pro, down))
        assertEquals("Starts 12 Oct 2026 · No charge until then", MembershipFormat.commitTerms(down, onHero, ist))

        val up = MembershipFormat.ctaFor(hero, onPaid(pro), "month", signedIn = true) as MembershipFormat.Cta.Buy
        assertEquals("Upgrade", MembershipFormat.commitAction(hero, up))
        assertEquals("Starts today · Current plan stops renewing", MembershipFormat.commitTerms(up, onPaid(pro), ist))

        val newMonthly = MembershipFormat.ctaFor(pro, onFree(), "month", signedIn = true) as MembershipFormat.Cta.Buy
        assertEquals("Get Pro", MembershipFormat.commitAction(pro, newMonthly))
        assertEquals("Renews monthly · Cancel anytime", MembershipFormat.commitTerms(newMonthly, onFree(), ist))

        val newYearly = MembershipFormat.ctaFor(hero, onFree(), "year", signedIn = true) as MembershipFormat.Cta.Buy
        assertEquals("Renews yearly · Cancel anytime", MembershipFormat.commitTerms(newYearly, onFree(), ist))
    }

    // ── Errors ──────────────────────────────────────────────────────────────

    @Test
    fun serverErrors_keepTheServersWordsAndCode() {
        val json = Json { ignoreUnknownKeys = true }
        val e = MembershipRepository.toMembershipException(409, """{"error":"You're already on this plan.","code":"already_subscribed"}""", json)
        assertEquals("You're already on this plan.", e.message)
        assertEquals("already_subscribed", e.code)
        assertEquals(409, e.httpStatus)

        val gate = MembershipRepository.toMembershipException(
            403,
            """{"error":"AI delivery reviews is part of Pro.","code":"upgrade_required","feature":"ai.delivery_review","plan":"free","upgrade_plan":"pro","limit":null,"used":null}""",
            json,
        )
        assertEquals("upgrade_required", gate.code)

        val html = MembershipRepository.toMembershipException(502, "<html>Bad gateway</html>", json)
        assertEquals("Payments are having trouble right now. Try again shortly.", html.message)
        assertNull(html.code)
    }
}
