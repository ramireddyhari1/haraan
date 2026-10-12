package com.haraan.app.data.membership

import com.haraan.app.ui.membership.MemberArea
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class ClipTrackingAccessTest {
    private fun feature(key: String, enabled: Boolean) = PlanFeature(key = key, name = key, enabled = enabled)

    @Test
    fun onlyAnEnabledEntitlementOpensIt() {
        assertTrue(ClipTrackingAccess.allowedBy(listOf(feature("tracking.clip_upload", true))))
        assertFalse(ClipTrackingAccess.allowedBy(listOf(feature("tracking.clip_upload", false))))
    }

    @Test
    fun aMissingRowLocksIt() {
        // An older server, or the feature switched off in /control, never opens it.
        assertFalse(ClipTrackingAccess.allowedBy(emptyList()))
        assertFalse(ClipTrackingAccess.allowedBy(listOf(feature("app.icon_hero", true))))
    }

    @Test
    fun itIsListedWithTheActionboardBenefits() {
        assertEquals(MemberArea.ACTIONBOARD, MemberArea.of("tracking.clip_upload"))
    }
}
