package com.haraan.app.vision

/**
 * A wicket, of either kind the game is actually played with.
 *
 * WHY A SEALED TYPE RATHER THAN ONE LENIENT DETECTOR. A set of stumps and a stone on the
 * ground are not the same claim wearing different clothes. Three bars are found by the
 * RELATIONSHIP between them and hand back a known 0.2286 m; a stone is found by its shape
 * and where it stands and hands back no distance at all. Collapsing them into one type
 * would mean every later caller silently inheriting the weaker guarantee, which is how a
 * scale that does not exist ends up being multiplied by something.
 *
 * So callers are made to ask which one they got. [Stumps] can be measured from. [Stone]
 * marks a place on the ground and nothing more.
 */
sealed interface WicketSighting {

    /** How well it fitted its own kind's rules, 0..1. Not comparable across the two. */
    val score: Float

    /**
     * Distance to the nearest crease-angled segment in frame widths, or null when the
     * pitch detector offered none. Null means nobody asked, not "far away".
     */
    val creaseDistance: Float?

    /** Where it meets the ground. The only part of either kind worth anchoring to. */
    val base: Point2

    /** Whether this kind of sighting carries a real-world scale. Only stumps do. */
    val givesScale: Boolean

    data class Stumps(val set: StumpSet) : WicketSighting {
        override val score: Float get() = set.score
        override val creaseDistance: Float? get() = set.creaseDistance
        override val base: Point2 get() = set.baseMiddle
        override val givesScale: Boolean get() = true
    }

    data class Stone(val mark: StoneMark) : WicketSighting {
        override val score: Float get() = mark.score
        override val creaseDistance: Float? get() = mark.creaseDistance
        override val base: Point2 get() = mark.base
        override val givesScale: Boolean get() = false
    }
}
