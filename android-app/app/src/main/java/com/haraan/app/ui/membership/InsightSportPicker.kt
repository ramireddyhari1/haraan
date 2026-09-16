package com.haraan.app.ui.membership

import com.haraan.app.data.membership.InsightSportsStatus
import java.time.Instant
import java.time.OffsetDateTime
import java.time.ZoneId

/**
 * The sport picker's rules, mirrored from the server so the screen never offers a change the
 * server will refuse — the server still has the final say on save. Pure, so every rule is
 * unit-tested.
 */
object InsightSportPicker {

    sealed interface Tap {
        /** The change is allowed; [next] is the new draft. */
        data class Changed(val next: Set<String>) : Tap

        /** Already at the plan's number of sports. */
        data class AtLimit(val limit: Int) : Tap

        /** A saved sport inside its cooldown; can be removed from [until]. */
        data class Held(val until: String) : Tap

        /** The plan doesn't ask the member to choose (every sport, or none). */
        data object NotChoosable : Tap
    }

    fun tap(status: InsightSportsStatus, draft: Set<String>, key: String, now: Instant = Instant.now(), zone: ZoneId = ZoneId.systemDefault()): Tap {
        if (status.mode != "choose") return Tap.NotChoosable
        val limit = status.limit ?: return Tap.NotChoosable

        if (key in draft) {
            val saved = status.sports.firstOrNull { it.key == key }
            val heldUntil = saved?.takeIf { it.selected && !status.overLimit }?.lockedUntil
                ?.let { runCatching { OffsetDateTime.parse(it).toInstant() }.getOrNull() }
            if (heldUntil != null && heldUntil.isAfter(now)) {
                return Tap.Held(MembershipFormat.date(saved.lockedUntil, zone) ?: "later")
            }
            return Tap.Changed(draft - key)
        }

        if (draft.size >= limit) return Tap.AtLimit(limit)
        return Tap.Changed(draft + key)
    }

    fun isDirty(status: InsightSportsStatus, draft: Set<String>): Boolean = draft != status.selected.toSet()

    fun canSave(status: InsightSportsStatus, draft: Set<String>): Boolean {
        val limit = status.limit ?: return false
        return status.mode == "choose" && isDirty(status, draft) && draft.size <= limit
    }

    /** Sports newly added by this save — they're the ones a cooldown will start on. */
    fun added(status: InsightSportsStatus, draft: Set<String>): List<String> =
        status.sports.map { it.key }.filter { it in draft && it !in status.selected }

    /** "2 of 3 sports selected", "Every sport included", "Not on your plan". */
    fun summary(status: InsightSportsStatus, draft: Set<String> = status.selected.toSet()): String = when (status.mode) {
        "all" -> "Every sport included"
        "choose" -> countLine(draft.size, status.limit ?: 0)
        else -> "Not on your plan"
    }

    /** "1 of 3 sports selected" — singular only where it reads that way ("1 of 1 sport selected"). */
    fun countLine(count: Int, limit: Int): String =
        "$count of $limit ${if (limit == 1) "sport" else "sports"} selected"

    /** Keep the server's sport order in what we send, so saved order stays stable. */
    fun ordered(status: InsightSportsStatus, draft: Set<String>): List<String> =
        status.sports.map { it.key }.filter { it in draft }
}
