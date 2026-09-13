package com.haraan.app.data.theme

import androidx.datastore.preferences.core.PreferenceDataStoreFactory
import com.haraan.app.data.net.HaraanHttp
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TemporaryFolder
import java.io.IOException

class SectionThemeRepositoryTest {

    @get:Rule
    val tmp = TemporaryFolder()

    private lateinit var scope: CoroutineScope
    private var nowMs = 1_000_000_000L
    private var calls = 0
    private var response: () -> SectionThemesResponse = { SectionThemesResponse(serverTime = nowMs / 1000) }

    private val api = object : SectionThemeApi {
        override suspend fun sectionThemes(): SectionThemesResponse {
            calls++
            return response()
        }
    }

    private lateinit var repo: SectionThemeRepository

    @Before
    fun setUp() {
        scope = CoroutineScope(Dispatchers.IO + SupervisorJob())
        val store = PreferenceDataStoreFactory.create(scope = scope) { tmp.newFile("themes.preferences_pb").also { it.delete() } }
        repo = SectionThemeRepository(store, api, HaraanHttp.json, clock = { nowMs }, freshForMs = 60_000L, logWarning = { _, _ -> })
    }

    @After
    fun tearDown() {
        scope.cancel()
    }

    private fun theme(
        id: Long,
        section: String = "events",
        from: Long = nowMs / 1000 - 60,
        until: Long = nowMs / 1000 + 3600,
        priority: Int = 0,
    ) = RemoteThemeConfig(
        id = id,
        section = section,
        campaignName = "Campaign $id",
        accent = AccentHex(primary = "#F59E0B"),
        validFrom = from,
        validUntil = until,
        priority = priority,
    )

    @Test
    fun `no cache and failed fetch means default palette`() = runBlocking {
        response = { throw IOException("offline") }

        assertFalse(repo.revalidate())
        assertNull(repo.activeTheme(ThemeSection.Events).first())
    }

    @Test
    fun `cached campaign survives a failed revalidation`() = runBlocking {
        response = { SectionThemesResponse(nowMs / 1000, listOf(theme(1))) }
        assertTrue(repo.revalidate())

        response = { throw IOException("offline") }
        assertFalse(repo.revalidate(force = true))

        assertEquals(1L, repo.activeTheme(ThemeSection.Events).first()?.id)
    }

    @Test
    fun `fresh cache skips the network unless forced`() = runBlocking {
        response = { SectionThemesResponse(nowMs / 1000, listOf(theme(1))) }
        repo.revalidate()
        repo.revalidate()
        assertEquals(1, calls)

        repo.revalidate(force = true)
        assertEquals(2, calls)

        nowMs += 61_000L
        repo.revalidate()
        assertEquals(3, calls)
    }

    @Test
    fun `sections do not leak into each other`() = runBlocking {
        response = { SectionThemesResponse(nowMs / 1000, listOf(theme(1, section = "pulse"))) }
        repo.revalidate()

        assertNull(repo.activeTheme(ThemeSection.Events).first())
        assertEquals(1L, repo.activeTheme(ThemeSection.Pulse).first()?.id)
    }

    @Test
    fun `window is judged on the server clock, not a wrong phone clock`() = runBlocking {
        val serverSec = nowMs / 1000
        // Campaign started 10 minutes ago by the server; the phone is 1 hour slow.
        response = { SectionThemesResponse(serverSec, listOf(theme(1, from = serverSec - 600, until = serverSec + 3600))) }
        nowMs -= 3_600_000L
        repo.revalidate()

        assertEquals(1L, repo.activeTheme(ThemeSection.Events).first()?.id)
    }

    @Test
    fun `highest priority wins, then the latest start`() {
        val now = 10_000L
        val themes = listOf(
            theme(1, from = now - 100, until = now + 100, priority = 0),
            theme(2, from = now - 50, until = now + 100, priority = 0),
            theme(3, from = now - 200, until = now + 100, priority = 5),
            theme(4, from = now + 10, until = now + 100, priority = 9),
        )
        assertEquals(3L, SectionThemeRepository.pickActive(themes, now)?.id)
        assertEquals(2L, SectionThemeRepository.pickActive(themes.filter { it.priority == 0 }, now)?.id)
    }

    @Test
    fun `end is exclusive and the next boundary is the soonest change`() {
        val t = theme(1, from = 100, until = 200)
        assertTrue(t.isValidAt(100))
        assertFalse(t.isValidAt(200))

        val themes = listOf(t, theme(2, from = 150, until = 400))
        assertEquals(100L, SectionThemeRepository.nextBoundary(themes, 50))
        assertEquals(150L, SectionThemeRepository.nextBoundary(themes, 120))
        assertEquals(200L, SectionThemeRepository.nextBoundary(themes, 150))
        assertNull(SectionThemeRepository.nextBoundary(themes, 400))
    }

    @Test
    fun `payload decodes leniently`() {
        val body = """
            {"server_time": 5, "unknown": true, "themes": [{
              "id": 7, "section": "events", "campaign_name": "Diwali",
              "accent": {"primary": "#F59E0B", "deep": null, "future_field": 1},
              "decoration": {"url": "https://c.test/l.json", "type": "lottie"},
              "banner_image_url": "ignored-legacy-field", "valid_from": 1, "valid_until": 9
            }]}
        """.trimIndent()
        val decoded = HaraanHttp.json.decodeFromString(SectionThemesResponse.serializer(), body)
        assertEquals("Diwali", decoded.themes.single().campaignName)
        assertNull(decoded.themes.single().accent.deep)
        assertEquals("lottie", decoded.themes.single().decoration?.type)
    }
}
