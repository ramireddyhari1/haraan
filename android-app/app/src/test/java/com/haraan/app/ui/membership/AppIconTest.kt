package com.haraan.app.ui.membership

import com.haraan.app.data.membership.AppIcon
import com.haraan.app.data.membership.PlanFeature
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class AppIconTest {

    private fun on(vararg keys: String) = keys.map { PlanFeature(key = it, name = it, enabled = true) }
    private fun off(vararg keys: String) = keys.map { PlanFeature(key = it, name = it, enabled = false) }

    @Test
    fun `the default icon is always unlocked, even with no entitlements at all`() {
        assertEquals(setOf(AppIcon.DEFAULT), AppIcon.unlockedBy(emptyList()))
    }

    @Test
    fun `only an entitlement the server marked enabled unlocks an icon`() {
        assertEquals(setOf(AppIcon.DEFAULT), AppIcon.unlockedBy(off("app.icon_pro", "app.icon_hero")))
        assertEquals(setOf(AppIcon.DEFAULT, AppIcon.PRO), AppIcon.unlockedBy(on("app.icon_pro") + off("app.icon_hero")))
        assertEquals(AppIcon.entries.toSet(), AppIcon.unlockedBy(on("app.icon_pro", "app.icon_hero")))
    }

    @Test
    fun `a lapsed plan hands the default back and a live one changes nothing`() {
        assertEquals(AppIcon.DEFAULT, AppIcon.correction(AppIcon.HERO, on("app.icon_pro")))
        assertEquals(AppIcon.DEFAULT, AppIcon.correction(AppIcon.PRO, emptyList()))
        assertNull(AppIcon.correction(AppIcon.PRO, on("app.icon_pro")))
        assertNull(AppIcon.correction(AppIcon.DEFAULT, emptyList()))
    }
}
