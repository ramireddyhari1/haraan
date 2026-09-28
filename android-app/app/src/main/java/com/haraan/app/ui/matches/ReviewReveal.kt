package com.haraan.app.ui.matches

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.TransformOrigin
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.DeliveryReview
import com.haraan.app.data.ReviewFactor
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

// ─────────────────────────────────────────────────────────────────────────────
//  THE REVIEW REVEAL
//
//  The moment a review is for. Broadcast DRS does not hand you a table; it builds the
//  decision a question at a time — pitching, impact, wickets — and the pause between
//  them is what makes a whole ground lean in. This does the same with the camera's read,
//  directly under the footage it came from.
//
//  WHAT IT WILL AND WILL NOT SAY. The five chips are the model's readings, in cricket's
//  order, and the colours are the direction each one points: red towards out, green
//  towards not out, amber for "depends", grey for "can't tell". The last chip is the
//  camera's read of those five, and it is deliberately hard to get to OUT: every question
//  has to have been answered, answered with certainty, and answered for out. Anything
//  short of that is UMPIRE'S CALL or CAN'T SAY — a phone at a boundary that printed a
//  confident OUT on a guess would be believed, and the first wrong one costs the feature.
//  The panel under the video still says, in full, that the call stays with the players.
// ─────────────────────────────────────────────────────────────────────────────

/** Which way one reading leans. */
enum class Lean { OUT, NOT_OUT, DEPENDS, UNKNOWN }

/** The camera's read of the whole delivery. */
enum class CameraRead(val word: String) {
    OUT("OUT"),
    NOT_OUT("NOT OUT"),
    UMPIRES_CALL("UMPIRE'S CALL"),
    CANT_SAY("CAN'T SAY"),
}

/** One chip, ready to draw. */
data class RevealChip(val label: String, val value: String, val lean: Lean, val certain: Boolean)

/** Cricket's order, which is the order an umpire decides in. */
private val ORDER = listOf("pitching", "impact", "bat_involved", "height", "line")

private fun chipLabel(key: String) = when (key) {
    "pitching" -> "PITCHING"
    "impact" -> "IMPACT"
    "bat_involved" -> "BAT"
    "height" -> "HEIGHT"
    "line" -> "WICKETS"
    else -> key.uppercase()
}

/** Short enough to sit in a chip; the panel below carries the long form. */
private fun chipValue(reading: String) = when (reading) {
    "cannot_tell" -> "Can't tell"
    "in_line" -> "In line"
    "outside_off" -> "Outside off"
    "outside_leg" -> "Outside leg"
    "bat_first" -> "Bat first"
    "pad_first" -> "Pad first"
    "no_bat" -> "No bat"
    "below_stumps" -> "Below"
    "above_stumps" -> "Over the top"
    "would_hit" -> "Hitting"
    "would_miss" -> "Missing"
    else -> reading.replace('_', ' ').replaceFirstChar { it.uppercase() }
}

/**
 * Which way a reading points, by the Laws.
 *
 * Pitching outside off is still out; outside leg never is. Impact outside off is out only
 * when no shot was offered, which nothing here can see — so it DEPENDS, and says so.
 */
internal fun leanOf(factor: ReviewFactor): Lean = when (factor.reading) {
    "cannot_tell" -> Lean.UNKNOWN
    "in_line", "pad_first", "no_bat", "below_stumps", "would_hit" -> Lean.OUT
    "outside_leg", "bat_first", "above_stumps", "would_miss" -> Lean.NOT_OUT
    "outside_off" -> if (factor.key == "pitching") Lean.OUT else Lean.DEPENDS
    else -> Lean.UNKNOWN
}

/**
 * The read, from the chips.
 *
 * NOT OUT needs only one certain reason: one settled "outside leg" or "missing" ends an
 * LBW whatever else is true. OUT needs all five, all certain, all out. Two or more
 * unanswered questions is too little to say anything. The rest is the umpire's.
 */
internal fun cameraReadOf(chips: List<RevealChip>): CameraRead = when {
    chips.any { it.lean == Lean.NOT_OUT && it.certain } -> CameraRead.NOT_OUT
    chips.size == ORDER.size && chips.all { it.lean == Lean.OUT && it.certain } -> CameraRead.OUT
    chips.count { it.lean == Lean.UNKNOWN } >= 2 -> CameraRead.CANT_SAY
    else -> CameraRead.UMPIRES_CALL
}

internal fun revealChipsOf(review: DeliveryReview): List<RevealChip> =
    ORDER.mapNotNull { key -> review.factors.firstOrNull { it.key == key } }
        .map { f -> RevealChip(chipLabel(f.key), chipValue(f.reading), leanOf(f), f.certain && !f.unknown) }

private val OutRed = Color(0xFFDC2626)
private val NotOutGreen = Color(0xFF16A34A)
private val Depends = Color(0xFFD97706)
private val Unknown = Color(0xFF475569)
private val LabelCell = Color(0xF20B1220)

private fun toneOf(lean: Lean) = when (lean) {
    Lean.OUT -> OutRed
    Lean.NOT_OUT -> NotOutGreen
    Lean.DEPENDS -> Depends
    Lean.UNKNOWN -> Unknown
}

private fun toneOf(read: CameraRead) = when (read) {
    CameraRead.OUT -> OutRed
    CameraRead.NOT_OUT -> NotOutGreen
    CameraRead.UMPIRES_CALL -> Depends
    CameraRead.CANT_SAY -> Unknown
}

/** Gap between chips. Long enough to be a beat, short enough that nobody waits. */
private const val BEAT_MS = 430L

/**
 * The stack, under the footage and built from the bottom up — pitching first, the read
 * last and on top, the way the broadcast builds it.
 *
 * [generation] replays it: bump it and the whole reveal runs again from nothing.
 */
@Composable
fun ReviewReveal(review: DeliveryReview, generation: Int, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val chips = remember(review) { revealChipsOf(review) }
    val read = remember(chips) { cameraReadOf(chips) }

    // One entrance (slide + fade) and one wipe (the coloured value) per chip, plus the read.
    val enter = remember(review, generation) { List(chips.size + 1) { Animatable(0f) } }
    val wipe = remember(review, generation) { List(chips.size + 1) { Animatable(0f) } }
    val pop = remember(review, generation) { Animatable(1f) }
    val flash = remember(review, generation) { Animatable(0f) }

    LaunchedEffect(review, generation) {
        delay(350)
        chips.indices.forEach { i ->
            launch { enter[i].animateTo(1f, spring(dampingRatio = 0.62f, stiffness = 420f)) }
            delay(150)
            // The value lands: one tick in the hand per question answered.
            cricketThud(context, Thud.TICK)
            wipe[i].animateTo(1f, tween(190, easing = FastOutSlowInEasing))
            delay(BEAT_MS - 190)
        }
        // The pause before the read is the drama. Longer than a beat, on purpose.
        delay(380)
        val last = chips.size
        launch { enter[last].animateTo(1f, spring(dampingRatio = 0.55f, stiffness = 380f)) }
        delay(120)
        launch {
            pop.snapTo(0.86f)
            pop.animateTo(1f, spring(dampingRatio = 0.38f, stiffness = 420f))
        }
        launch { flash.snapTo(1f); flash.animateTo(0f, tween(650)) }
        launch {
            cricketThud(
                context,
                when (read) {
                    CameraRead.OUT -> Thud.WICKET
                    CameraRead.NOT_OUT -> Thud.FOUR
                    CameraRead.UMPIRES_CALL -> Thud.UNDO
                    CameraRead.CANT_SAY -> Thud.TICK
                },
            )
        }
        wipe[last].animateTo(1f, tween(220, easing = FastOutSlowInEasing))
    }

    Column(modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        // Top of the stack is the read; below it, the questions in reverse, so the eye
        // reading down meets the answer first once it is all there.
        val readIndex = chips.size
        Box(
            Modifier.padding(bottom = 4.dp).graphicsLayer {
                alpha = enter[readIndex].value.coerceIn(0f, 1f)
                translationX = (1f - enter[readIndex].value) * -28.dp.toPx()
                scaleX = pop.value
                scaleY = pop.value
                transformOrigin = TransformOrigin(0f, 0.5f)
            },
        ) {
            RevealRow(
                label = "CAMERA'S READ",
                value = read.word,
                tone = toneOf(read),
                wipe = wipe[readIndex].value,
                big = true,
                flash = flash.value,
                faded = false,
            )
        }
        chips.indices.reversed().forEach { i ->
            val chip = chips[i]
            Box(
                Modifier.graphicsLayer {
                    alpha = enter[i].value.coerceIn(0f, 1f)
                    translationX = (1f - enter[i].value) * -28.dp.toPx()
                },
            ) {
                RevealRow(
                    label = chip.label,
                    value = chip.value,
                    tone = toneOf(chip.lean),
                    wipe = wipe[i].value,
                    big = false,
                    flash = 0f,
                    // A reading the model would not stand behind is drawn a shade quieter,
                    // so an unsure "in line" never carries the weight of a sure one.
                    faded = !chip.certain && chip.lean != Lean.UNKNOWN,
                )
            }
        }
    }
}

/**
 * One chip: a dark label cell and a coloured result cell, the broadcast's shape.
 *
 * The result cell WIPES in from the label side rather than fading — a wipe has a
 * direction, and a direction reads as something being decided rather than appearing.
 */
@Composable
private fun RevealRow(
    label: String,
    value: String,
    tone: Color,
    wipe: Float,
    big: Boolean,
    flash: Float,
    faded: Boolean,
) {
    val shape = RoundedCornerShape(if (big) 8.dp else 6.dp)
    Row(
        Modifier
            .fillMaxWidth()
            .height(if (big) 44.dp else 32.dp)
            .clip(shape)
            .border(1.dp, Color.White.copy(alpha = 0.16f + 0.6f * flash), shape),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .width(if (big) 128.dp else 104.dp)
                .fillMaxHeight()
                .background(LabelCell),
            contentAlignment = Alignment.CenterStart,
        ) {
            Text(
                label,
                color = Color.White.copy(alpha = 0.78f),
                fontSize = if (big) 10.5.sp else 10.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.sp,
                maxLines = 1,
                modifier = Modifier.padding(start = 12.dp),
            )
        }
        Box(
            Modifier
                .weight(1f)
                .fillMaxHeight()
                .background(LabelCell),
        ) {
            Box(
                Modifier
                    .fillMaxSize()
                    .graphicsLayer {
                        scaleX = wipe
                        transformOrigin = TransformOrigin(0f, 0.5f)
                    }
                    .background(tone.copy(alpha = if (faded) 0.62f else 1f)),
            )
            Text(
                value,
                color = Color.White.copy(alpha = wipe.coerceIn(0f, 1f)),
                fontSize = if (big) 17.sp else 13.5.sp,
                fontWeight = if (big) FontWeight.Black else FontWeight.Bold,
                letterSpacing = if (big) 0.6.sp else 0.sp,
                maxLines = 1,
                modifier = Modifier
                    .align(Alignment.CenterStart)
                    .padding(start = 12.dp),
            )
        }
    }
}
