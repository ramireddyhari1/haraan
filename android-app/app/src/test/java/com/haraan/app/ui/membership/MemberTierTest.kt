package com.haraan.app.ui.membership

import com.haraan.app.data.membership.Membership
import com.haraan.app.data.membership.MembershipPlanRef
import com.haraan.app.data.membership.PlanFeature
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class MemberTierTest {

    private fun membership(
        code: String,
        rank: Int,
        badge: String? = null,
        attention: String? = null,
        entitlements: List<PlanFeature> = emptyList(),
    ) = Membership(
        plan = MembershipPlanRef(code, code.replaceFirstChar { it.uppercase() }, rank),
        badge = badge,
        attention = attention,
        entitlements = entitlements,
    )

    // ── Identity: only the server's badge puts a mark on someone ──

    @Test
    fun `badge codes resolve to their tier, case and whitespace tolerant`() {
        assertEquals(MemberTier.PRO, MemberTier.fromBadge("pro"))
        assertEquals(MemberTier.HERO, MemberTier.fromBadge(" HERO "))
    }

    @Test
    fun `missing blank or unknown badge is regular`() {
        assertEquals(MemberTier.REGULAR, MemberTier.fromBadge(null))
        assertEquals(MemberTier.REGULAR, MemberTier.fromBadge(""))
        assertEquals(MemberTier.REGULAR, MemberTier.fromBadge("free"))
        assertEquals(MemberTier.REGULAR, MemberTier.fromBadge("legend"))
    }

    @Test
    fun `no membership answer is regular identity`() {
        assertEquals(MemberTier.REGULAR, MemberTier.identityOf(null))
    }

    @Test
    fun `a paid plan without the badge entitlement wears no mark`() {
        // /control switched profile.member_badge off for Pro: the server returns badge = null.
        assertEquals(MemberTier.REGULAR, MemberTier.identityOf(membership("pro", 10, badge = null)))
    }

    @Test
    fun `identity follows the badge not the plan code`() {
        assertEquals(MemberTier.HERO, MemberTier.identityOf(membership("hero", 20, badge = "hero")))
        assertEquals(MemberTier.PRO, MemberTier.identityOf(membership("pro", 10, badge = "pro")))
        assertEquals(MemberTier.REGULAR, MemberTier.identityOf(membership("free", 0)))
    }

    // ── Own plan screen ──

    @Test
    fun `plan screen follows the current paid plan`() {
        assertEquals(MemberTier.PRO, MemberTier.planOf(membership("pro", 10)))
        assertEquals(MemberTier.HERO, MemberTier.planOf(membership("hero", 20)))
    }

    @Test
    fun `free plan, unknown paid plan and no answer are regular on the plan screen`() {
        assertEquals(MemberTier.REGULAR, MemberTier.planOf(null))
        assertEquals(MemberTier.REGULAR, MemberTier.planOf(membership("free", 0)))
        assertEquals(MemberTier.REGULAR, MemberTier.planOf(membership("club", 30)))
        // A plan coded "pro" but reported at rank 0 is not a paid plan.
        assertEquals(MemberTier.REGULAR, MemberTier.planOf(membership("pro", 0)))
    }

    @Test
    fun `a paused plan never wears the premium treatment`() {
        assertEquals(MemberTier.REGULAR, MemberTier.planOf(membership("hero", 20, attention = "payment_failed")))
        // Retrying keeps the plan on, so it keeps its identity.
        assertEquals(MemberTier.HERO, MemberTier.planOf(membership("hero", 20, attention = "payment_retrying")))
    }

    @Test
    fun `isMember is false only for regular`() {
        assertFalse(MemberTier.REGULAR.isMember)
        assertTrue(MemberTier.PRO.isMember)
        assertTrue(MemberTier.HERO.isMember)
    }

    // ── Benefits across Haraan ──

    private fun feature(
        key: String,
        enabled: Boolean = true,
        type: String = "boolean",
        limit: Int? = null,
        unlimited: Boolean = false,
        unit: String? = null,
        description: String? = null,
    ) = PlanFeature(
        key = key, name = key, description = description, type = type, unit = unit,
        enabled = enabled, limit = limit, unlimited = unlimited,
    )

    @Test
    fun `known keys land in the area that enforces them`() {
        assertEquals(MemberArea.EVERYWHERE, MemberArea.of("ads.hidden"))
        listOf("ai.career_read", "ai.delivery_review", "tournaments.active_hosted", "matches.camera_angles", "insights.advanced_sports")
            .forEach { assertEquals(it, MemberArea.ACTIONBOARD, MemberArea.of(it)) }
        assertEquals(MemberArea.ACCOUNT, MemberArea.of("profile.member_badge"))
        assertEquals(MemberArea.ACCOUNT, MemberArea.of("support.priority"))
    }

    @Test
    fun `future keys are placed by namespace and nothing is dropped`() {
        assertEquals(MemberArea.EVENTS, MemberArea.of("events.early_access"))
        assertEquals(MemberArea.PULSE, MemberArea.of("bookings.discount"))
        assertEquals(MemberArea.PULSE, MemberArea.of("venues.priority_slots"))
        assertEquals(MemberArea.ACTIONBOARD, MemberArea.of("matches.highlights"))
        assertEquals(MemberArea.ACCOUNT, MemberArea.of("something.new"))
    }

    @Test
    fun `plan sheet lists every catalogue feature, grouped in area order`() {
        val sections = PlanSheet.of(
            listOf(
                feature("support.priority", enabled = false),
                feature("profile.member_badge"),
                feature("tournaments.active_hosted", type = "limit", limit = 3, unit = "at a time"),
                feature("ads.hidden"),
                feature("matches.camera_angles", type = "limit", enabled = false),
                feature("ai.delivery_review", type = "quota", unlimited = true),
            ),
        )

        assertEquals(listOf(MemberArea.EVERYWHERE, MemberArea.ACTIONBOARD, MemberArea.ACCOUNT), sections.map { it.area })
        // Server order is kept inside a section, and excluded features stay listed.
        assertEquals(
            listOf("tournaments.active_hosted", "matches.camera_angles", "ai.delivery_review"),
            sections[1].rows.map { it.key },
        )
        assertEquals(listOf("support.priority", "profile.member_badge"), sections[2].rows.map { it.key })
    }

    @Test
    fun `plan sheet values come straight from the server figures`() {
        val rows = PlanSheet.of(
            listOf(
                feature("ads.hidden"),
                feature("tournaments.active_hosted", type = "limit", limit = 3, unit = "at a time"),
                feature("ai.delivery_review", type = "quota", limit = 20, unit = "reviews / month"),
                feature("insights.advanced_sports", type = "limit", unlimited = true),
                feature("support.priority", enabled = false),
                // The server reports a zero ceiling as not enabled; it reads "Not included", never "0".
                feature("matches.camera_angles", type = "limit", enabled = false, limit = 0),
            ),
        ).flatMap { it.rows }.associateBy { it.key }

        assertEquals("Included", rows.getValue("ads.hidden").value)
        assertEquals("3 at a time", rows.getValue("tournaments.active_hosted").value)
        assertEquals("20 per month", rows.getValue("ai.delivery_review").value)
        assertEquals("Unlimited", rows.getValue("insights.advanced_sports").value)
        assertFalse(rows.getValue("support.priority").included)
        assertNull(rows.getValue("support.priority").value)
        assertFalse(rows.getValue("matches.camera_angles").included)
    }

    @Test
    fun `an empty catalogue entry yields an empty sheet`() {
        assertTrue(PlanSheet.of(emptyList()).isEmpty())
    }

    @Test
    fun `events and pulse sections only appear when the server lists a feature there`() {
        val today = PlanSheet.of(listOf(feature("ads.hidden"), feature("ai.career_read")))
        assertFalse(today.any { it.area == MemberArea.EVENTS || it.area == MemberArea.PULSE })

        val withBooking = PlanSheet.of(listOf(feature("bookings.discount", type = "limit", limit = 10, unit = "% off")))
        assertEquals(listOf(MemberArea.PULSE), withBooking.map { it.area })
    }

    @Test
    fun `comparing with your own plan names only what differs, in server figures`() {
        val free = listOf(
            feature("ads.hidden", enabled = false),
            feature("ai.delivery_review", type = "quota", limit = 2, unit = "reviews / month"),
            feature("tournaments.active_hosted", type = "limit", limit = 1, unit = "at a time"),
            feature("profile.member_badge", enabled = false),
        )
        val pro = listOf(
            feature("ads.hidden"),
            feature("ai.delivery_review", type = "quota", limit = 20, unit = "reviews / month"),
            feature("tournaments.active_hosted", type = "limit", limit = 1, unit = "at a time"),
            feature("profile.member_badge", enabled = false),
        )

        val upgrade = PlanSheet.of(pro, yours = free).flatMap { it.rows }.associateBy { it.key }
        assertEquals("New for you", upgrade.getValue("ads.hidden").yours)
        assertTrue(upgrade.getValue("ads.hidden").gain)
        assertFalse(upgrade.getValue("ai.delivery_review").gain)
        assertEquals("You have 2 per month", upgrade.getValue("ai.delivery_review").yours)
        assertNull(upgrade.getValue("tournaments.active_hosted").yours)
        assertNull(upgrade.getValue("profile.member_badge").yours)

        // Looking down from Pro at Free: what they would lose reads as what they have today.
        val downgrade = PlanSheet.of(free, yours = pro).flatMap { it.rows }.associateBy { it.key }
        assertEquals("On your plan now", downgrade.getValue("ads.hidden").yours)
        assertFalse(downgrade.getValue("ads.hidden").gain)
        assertEquals("You have 20 per month", downgrade.getValue("ai.delivery_review").yours)
    }

    @Test
    fun `no comparison without your plan, or against the same plan`() {
        val pro = listOf(feature("ads.hidden"), feature("support.priority", enabled = false))
        assertTrue(PlanSheet.of(pro).flatMap { it.rows }.all { it.yours == null })
        assertTrue(PlanSheet.of(pro, yours = pro).flatMap { it.rows }.all { it.yours == null })
    }

    @Test
    fun `a feature missing from your plan's list reads as new for you`() {
        val rows = PlanSheet.of(listOf(feature("events.early_access")), yours = emptyList()).flatMap { it.rows }
        assertEquals("New for you", rows.single().yours)
        assertTrue(rows.single().gain)
    }
}
