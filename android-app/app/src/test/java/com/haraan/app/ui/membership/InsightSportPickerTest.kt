package com.haraan.app.ui.membership

import com.haraan.app.data.membership.InsightSport
import com.haraan.app.data.membership.InsightSportsStatus
import com.haraan.app.data.membership.InsightsLocks
import com.haraan.app.data.membership.NamedPlan
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.Instant
import java.time.ZoneId

class InsightSportPickerTest {

    private val ist = ZoneId.of("Asia/Kolkata")
    private val now = Instant.parse("2026-09-16T06:00:00Z")
    private val keys = listOf("cricket", "football", "badminton", "volleyball", "basketball", "kabaddi", "tennis", "table_tennis")

    private fun status(
        mode: String = "choose",
        limit: Int? = 3,
        selected: List<String> = emptyList(),
        heldUntil: Map<String, String> = emptyMap(),
        overLimit: Boolean = false,
        cooldownDays: Int = 7,
    ) = InsightSportsStatus(
        mode = mode,
        limit = limit,
        plan = NamedPlan("pro", "Pro"),
        selected = selected,
        slotsLeft = limit?.let { (it - selected.size).coerceAtLeast(0) },
        overLimit = overLimit,
        cooldownDays = cooldownDays,
        sports = keys.map { k ->
            InsightSport(
                key = k, label = k.replace('_', ' ').replaceFirstChar { it.uppercase() },
                selected = k in selected,
                unlocked = mode == "all" || (k in selected && selected.indexOf(k) < (limit ?: 99)),
                lockedUntil = heldUntil[k],
            )
        },
    )

    @Test
    fun pro_addsUpToTheLimitAndNoFurther() {
        val s = status()
        var draft = emptySet<String>()
        listOf("cricket", "football", "kabaddi").forEach { key ->
            draft = (InsightSportPicker.tap(s, draft, key, now) as InsightSportPicker.Tap.Changed).next
        }
        assertEquals(setOf("cricket", "football", "kabaddi"), draft)
        assertEquals(InsightSportPicker.Tap.AtLimit(3), InsightSportPicker.tap(s, draft, "tennis", now))
        assertTrue(InsightSportPicker.canSave(s, draft))
        assertEquals("3 of 3 sports selected", InsightSportPicker.summary(s, draft))
        assertEquals(listOf("cricket", "football", "kabaddi"), InsightSportPicker.ordered(s, setOf("kabaddi", "cricket", "football")))
    }

    @Test
    fun anUnsavedChoiceCanBeTakenBackFreely() {
        val s = status()
        val draft = setOf("tennis")
        assertEquals(InsightSportPicker.Tap.Changed(emptySet()), InsightSportPicker.tap(s, draft, "tennis", now))
    }

    @Test
    fun aSavedSportInsideItsCooldownIsHeld() {
        val s = status(selected = listOf("cricket", "football"), heldUntil = mapOf("football" to "2026-09-20T10:00:00+05:30"))
        val result = InsightSportPicker.tap(s, s.selected.toSet(), "football", now, ist)
        assertEquals(InsightSportPicker.Tap.Held("20 Sep 2026"), result)

        // Past the date, it can go.
        val later = Instant.parse("2026-09-21T00:00:00Z")
        assertTrue(InsightSportPicker.tap(s, s.selected.toSet(), "football", later, ist) is InsightSportPicker.Tap.Changed)
        // Cricket has no hold.
        assertTrue(InsightSportPicker.tap(s, s.selected.toSet(), "cricket", now, ist) is InsightSportPicker.Tap.Changed)
    }

    @Test
    fun overTheLimit_trimmingIsNeverHeld() {
        val s = status(limit = 2, selected = listOf("cricket", "football", "kabaddi"), overLimit = true, heldUntil = mapOf("kabaddi" to "2026-09-20T10:00:00+05:30"))
        assertTrue(InsightSportPicker.tap(s, s.selected.toSet(), "kabaddi", now, ist) is InsightSportPicker.Tap.Changed)
        assertFalse("still over the limit, can't save", InsightSportPicker.canSave(s, s.selected.toSet() - "tennis"))
        assertTrue(InsightSportPicker.canSave(s, setOf("cricket", "football")))
    }

    @Test
    fun heroAndFree_areNotChoosable() {
        val hero = status(mode = "all", limit = null)
        assertEquals(InsightSportPicker.Tap.NotChoosable, InsightSportPicker.tap(hero, emptySet(), "cricket", now))
        assertFalse(InsightSportPicker.canSave(hero, setOf("cricket")))
        assertEquals("Every sport included", InsightSportPicker.summary(hero))

        val free = status(mode = "none", limit = 0)
        assertEquals(InsightSportPicker.Tap.NotChoosable, InsightSportPicker.tap(free, emptySet(), "cricket", now))
        assertEquals("Not on your plan", InsightSportPicker.summary(free))
        assertEquals("0 of 3 sports selected", InsightSportPicker.countLine(0, 3))
        assertEquals("1 of 1 sport selected", InsightSportPicker.countLine(1, 1))
    }

    @Test
    fun dirtyAndAdded() {
        val s = status(selected = listOf("cricket"))
        assertFalse(InsightSportPicker.isDirty(s, setOf("cricket")))
        assertFalse(InsightSportPicker.canSave(s, setOf("cricket")))
        assertEquals(listOf("kabaddi"), InsightSportPicker.added(s, setOf("cricket", "kabaddi")))
    }

    @Test
    fun statusDecodesTheServerPayload() {
        val json = Json { ignoreUnknownKeys = true; explicitNulls = false; coerceInputValues = true }
        val body = """{"mode":"choose","limit":3,"plan":{"code":"pro","name":"Pro"},"selected":["cricket"],"slots_left":2,"over_limit":false,"cooldown_days":7,
            "sports":[{"key":"cricket","label":"Cricket","selected":true,"unlocked":true,"locked_until":"2026-09-23T10:00:00+05:30"},{"key":"football","label":"Football","selected":false,"unlocked":false,"locked_until":null}]}"""
        val s = json.decodeFromString(InsightSportsStatus.serializer(), body)
        assertEquals(2, s.slotsLeft)
        assertEquals("2026-09-23T10:00:00+05:30", s.sports[0].lockedUntil)
        assertNull(s.sports[1].lockedUntil)
    }

    @Test
    fun locks_areOnlyTheServersPlanRefusals() {
        val lock = InsightsLocks.from(
            403,
            """{"error":"Choose Kabaddi as one of your 3 sports to see this.","code":"selection_required","feature":"insights.advanced_sports","plan":"pro","upgrade_plan":"hero","limit":3,"used":1,"sport":"kabaddi"}""",
        )!!
        assertEquals("selection_required", lock.code)
        assertEquals("kabaddi", lock.sport)
        assertTrue(lock.message.startsWith("Choose Kabaddi"))

        // Another gate's refusal, a 404 or a non-JSON body are not insight locks.
        assertNull(InsightsLocks.from(403, """{"error":"x","code":"limit_reached","feature":"ai.delivery_review"}"""))
        assertNull(InsightsLocks.from(404, """{"error":"Match not found"}"""))
        assertNull(InsightsLocks.from(403, "<html>forbidden</html>"))
    }
}
