package com.haraan.app.ui.theme

import androidx.compose.ui.graphics.Color
import com.haraan.app.data.theme.AccentHex
import com.haraan.app.data.theme.RemoteThemeConfig
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertSame
import org.junit.Assert.assertTrue
import org.junit.Test

class SectionThemeTest {

    private fun remote(accent: AccentHex) = RemoteThemeConfig(
        id = 1,
        section = "events",
        campaignName = "  Diwali Nights ",
        accent = accent,
        decoration = com.haraan.app.data.theme.Decoration(url = " ", type = "lottie"),
        validFrom = 0,
        validUntil = 1,
    )

    @Test
    fun `no campaign falls back to the default palette`() {
        assertSame(SectionTheme.EventsDefault, SectionTheme.resolve(null, SectionTheme.EventsDefault))
    }

    @Test
    fun `unparseable primary falls back entirely`() {
        val resolved = SectionTheme.resolve(remote(AccentHex(primary = "orange")), SectionTheme.PulseDefault)
        assertSame(SectionTheme.PulseDefault, resolved)
        assertNull(resolved.campaign)
    }

    @Test
    fun `missing optional colours are derived and the campaign is carried`() {
        val resolved = SectionTheme.resolve(remote(AccentHex(primary = "#7C3AED")), SectionTheme.EventsDefault)
        assertEquals(Color(0xFF7C3AED), resolved.primary)
        assertTrue("deep is darker", resolved.deep != resolved.primary)
        assertEquals("Diwali Nights", resolved.campaign?.name)
        assertNull("blank decoration url is treated as none", resolved.campaign?.decorationUrl)
    }

    @Test
    fun `unreadable text colour on a pale accent is corrected`() {
        // White on amber-300 is ~1.6:1 — an admin picking it must not ship unreadable CTAs.
        val resolved = SectionTheme.resolve(
            remote(AccentHex(primary = "#FCD34D", onPrimary = "#FFFFFF")),
            SectionTheme.EventsDefault,
        )
        assertTrue(SectionTheme.contrastRatio(resolved.onPrimary, resolved.primary) >= SectionTheme.MIN_TEXT_CONTRAST)
        assertTrue(SectionTheme.contrastRatio(resolved.accentText, Color.White) >= SectionTheme.MIN_TEXT_CONTRAST)
    }

    @Test
    fun `a legible admin text colour is respected`() {
        val resolved = SectionTheme.resolve(
            remote(AccentHex(primary = "#1E3A8A", onPrimary = "#FDE68A")),
            SectionTheme.EventsDefault,
        )
        assertEquals(Color(0xFFFDE68A), resolved.onPrimary)
    }

    @Test
    fun `hex parsing accepts only 6 or 8 hex digits`() {
        assertNotNull(parseHexColor("#2563eb"))
        assertNotNull(parseHexColor("FF2563EB"))
        assertNull(parseHexColor("#256"))
        assertNull(parseHexColor("#-12345"))
        assertNull(parseHexColor("null"))
        assertNull(parseHexColor(null))
    }
}
