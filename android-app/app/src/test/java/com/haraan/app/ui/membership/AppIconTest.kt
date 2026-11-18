package com.haraan.app.ui.membership

import com.haraan.app.data.membership.AppIconCatalog
import com.haraan.app.data.membership.AppIconDecision
import com.haraan.app.data.membership.AppIconOffer
import com.haraan.app.data.membership.AppIconPolicy
import com.haraan.app.data.membership.AppIconSlot
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class AppIconTest {

    private val hour = 3_600_000L
    private val now = 1_000 * hour

    private fun catalog(auto: String? = null, minHours: Int = 72, vararg unlocked: String, locked: List<String> = emptyList()) =
        AppIconCatalog(
            auto = auto,
            minHoursBetweenAuto = minHours,
            icons = (listOf("default") + unlocked).map { AppIconOffer(it, it, unlocked = true) } +
                locked.map { AppIconOffer(it, it, unlocked = false) },
        )

    @Test
    fun `every server key has art and unknown keys are ignored`() {
        listOf("default", "pro", "hero", "onyx", "paper", "turf", "leather", "festive", "sunrise", "monsoon", "royal", "silver")
            .forEach { assertEquals(it, AppIconSlot.of(it)?.key) }
        val c = AppIconCatalog(auto = "neon", icons = listOf(AppIconOffer("neon", "Neon", unlocked = true)))
        assertNull(c.autoSlot)
        assertEquals(setOf(AppIconSlot.DEFAULT), c.unlocked)
    }

    @Test
    fun `an icon the member can no longer wear is replaced straight away`() {
        // Plan lapsed: hero no longer unlocked, no auto → default, even inside the swap cooldown.
        assertEquals(
            AppIconDecision(AppIconSlot.DEFAULT, null),
            AppIconPolicy.decide(AppIconSlot.HERO, catalog(locked = listOf("hero")), null, now - hour, now),
        )
        // Season over but the plan has a usual auto icon → that one.
        assertEquals(
            AppIconDecision(AppIconSlot.HERO, "hero"),
            AppIconPolicy.decide(AppIconSlot.FESTIVE, catalog("hero", 72, "hero"), "festive", now - hour, now),
        )
    }

    @Test
    fun `a new automatic icon goes on once, then the member's choice stands`() {
        val c = catalog("onyx", 72, "onyx", "pro")
        assertEquals(AppIconDecision(AppIconSlot.ONYX, "onyx"), AppIconPolicy.decide(AppIconSlot.DEFAULT, c, null, 0, now))
        // Already applied once; the member then picked Pro — leave it.
        assertNull(AppIconPolicy.decide(AppIconSlot.PRO, c, "onyx", now - 500 * hour, now))
    }

    @Test
    fun `automatic swaps are spaced out by the admin's cooldown`() {
        val c = catalog("turf", 72, "turf")
        assertNull(AppIconPolicy.decide(AppIconSlot.DEFAULT, c, "onyx", now - 10 * hour, now))
        assertEquals(AppIconDecision(AppIconSlot.TURF, "turf"), AppIconPolicy.decide(AppIconSlot.DEFAULT, c, "onyx", now - 73 * hour, now))
    }

    @Test
    fun `already wearing the automatic icon just records it`() {
        assertEquals(
            AppIconDecision(null, "pro"),
            AppIconPolicy.decide(AppIconSlot.PRO, catalog("pro", 72, "pro"), null, 0, now),
        )
    }

    @Test
    fun `no automatic icon and an allowed choice changes nothing`() {
        assertNull(AppIconPolicy.decide(AppIconSlot.PRO, catalog(null, 72, "pro"), null, 0, now))
        assertNull(AppIconPolicy.decide(AppIconSlot.DEFAULT, catalog(), null, 0, now))
    }
}
