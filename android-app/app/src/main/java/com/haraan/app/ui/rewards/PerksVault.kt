package com.haraan.app.ui.rewards

import android.widget.Toast
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutLinearInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectHorizontalDragGestures
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.GenericShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.PlayCircle
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.RoundRect
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.PathOperation
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.TransformOrigin
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.onClick
import androidx.compose.ui.semantics.role
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.ApiConfig
import com.haraan.app.data.rewards.RewardItem
import com.haraan.app.ui.Feel
import com.haraan.app.ui.openExternalUrl
import com.haraan.app.ui.theme.PlusJakartaSans
import kotlinx.coroutines.launch
import java.util.Locale
import kotlin.math.floor

/**
 * The perks this match earned, as a strip of paper coupons.
 *
 * You claim one the way you would a real one: pull the stub off along the perforation.
 * Each perforation hole ticks under your thumb as it gives, and the stub rips away with a
 * firm knock. A plain tap tears it for you, so nobody has to discover the gesture. What stays
 * behind keeps its ragged edge and shows the code.
 *
 * The header gives two counts the player can act on. It does not add up a "total value"
 * across perks that aren't the same kind of thing.
 */
@Composable
internal fun PerksVaultSection(
    ui: RewardScreenUi,
    state: PostMatchRewardsState,
    adsAvailable: Boolean,
    stage: Int,
    onClaim: (RewardItem) -> Unit,
    onWatch: (RewardItem) -> Unit,
    onReveal: (RewardItem) -> Unit,
    onUse: (scope: String?) -> Unit,
    modifier: Modifier = Modifier,
) {
    val claimed = ui.rewards.count { it.status == "claimed" || it.status == "redeemed" }
    Column(modifier.fillMaxWidth().rise(stage >= 5)) {
        Row(
            Modifier.fillMaxWidth().padding(start = 20.dp, end = 20.dp, top = 28.dp, bottom = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Eyebrow("Perks from this match", modifier = Modifier.weight(1f))
            Text(
                listOfNotNull(
                    ui.readyCount.takeIf { it > 0 }?.let { "$it to claim" },
                    claimed.takeIf { it > 0 }?.let { "$it in wallet" },
                ).joinToString("  ·  "),
                color = Board.InkFaint,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.Medium,
                fontSize = 12.sp,
            )
        }
        LazyRow(
            Modifier.fillMaxWidth(),
            contentPadding = PaddingValues(horizontal = 16.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            items(ui.rewards, key = { "perk_${it.id}" }) { item ->
                PerkCoupon(
                    item = item,
                    full = state.revealed[item.id],
                    actions = TicketActions(
                        claiming = item.id in state.claiming,
                        watching = state.watching == item.id,
                        adsAvailable = adsAvailable,
                        onClaim = { onClaim(item) },
                        onWatch = { onWatch(item) },
                        onReveal = { onReveal(item) },
                        onUse = onUse,
                    ),
                )
            }
        }
    }
}

private val StubWidth = 84.dp
private val Notch = 9.dp
private val Corner = 16.dp
private val CouponHeight = 148.dp
private val TearAt = 64.dp
private const val HOLES = 8

@Composable
private fun PerkCoupon(item: RewardItem, full: RewardItem?, actions: TicketActions) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    val density = LocalDensity.current
    val tearAtPx = with(density) { TearAt.toPx() }

    val locked = item.status == "locked"
    val claimed = item.status == "claimed" || item.status == "redeemed"
    val ready = !locked && !claimed

    var torn by remember(item.id) { mutableStateOf(claimed) }
    var sawClaiming by remember(item.id) { mutableStateOf(false) }
    val pull = remember(item.id) { Animatable(0f) }   // px the stub has been pulled
    val gone = remember(item.id) { Animatable(0f) }   // 0 attached → 1 flown off
    var holes by remember(item.id) { mutableStateOf(0) }

    fun feelHoles(px: Float) {
        val n = floor((px / tearAtPx).coerceIn(0f, 1f) * HOLES).toInt()
        if (n > holes) view.performHapticFeedback(Feel.TICK)
        holes = n
    }

    fun rip() {
        scope.launch {
            view.performHapticFeedback(Feel.COMMIT)
            torn = true
            launch { pull.animateTo(tearAtPx * 3f, tween(260, easing = FastOutLinearInEasing)) }
            gone.animateTo(1f, tween(260, easing = FastOutLinearInEasing))
            actions.onClaim()
        }
    }

    // A claim that fails puts the stub back, so the coupon never lies about what you hold.
    LaunchedEffect(actions.claiming, item.status) {
        if (actions.claiming) sawClaiming = true
        if (claimed) torn = true
        if (!actions.claiming && sawClaiming && ready && torn) {
            sawClaiming = false
            torn = false
            holes = 0
            gone.snapTo(0f)
            pull.snapTo(tearAtPx * 1.4f)
            pull.animateTo(0f, spring(dampingRatio = 0.55f, stiffness = 380f))
        }
    }

    val brand = parseHex(item.brandColor)
    Row(
        Modifier
            .width(304.dp)
            .height(CouponHeight),
    ) {
        CouponBody(
            item = item,
            full = full,
            actions = actions,
            locked = locked,
            claimed = claimed,
            torn = torn,
            modifier = Modifier.weight(1f).fillMaxHeight(),
        )
        if (!claimed) {
            val stubShape = remember { stubShape(density) }
            Box(
                Modifier
                    .width(StubWidth)
                    .fillMaxHeight()
                    .graphicsLayer {
                        val p = pull.value
                        translationX = p
                        // Peeled from the bottom corner, like a stub coming away in the hand.
                        rotationZ = (p / tearAtPx).coerceIn(0f, 2.5f) * 7f
                        transformOrigin = TransformOrigin(0f, 1f)
                        alpha = 1f - gone.value
                    }
                    .shadow(if (pull.value > 1f) 6.dp else 0.dp, stubShape, clip = false)
                    .clip(stubShape)
                    .background(
                        when {
                            locked -> Board.Raised
                            else -> brand?.copy(alpha = 0.10f)?.compositeOverWhite() ?: Board.BlueWell
                        },
                    )
                    .border(1.dp, Board.Line, stubShape)
                    .then(
                        if (ready && !torn) {
                            Modifier
                                .semantics {
                                    role = Role.Button
                                    onClick("Claim perk") { rip(); true }
                                }
                                .pointerInput(item.id) {
                                    detectTapGestures {
                                        scope.launch {
                                            // Tear it for them, hole by hole, so a tap feels the same.
                                            pull.animateTo(tearAtPx, tween(360, easing = LinearEasing)) { feelHoles(value) }
                                            rip()
                                        }
                                    }
                                }
                                .pointerInput(item.id) {
                                    detectHorizontalDragGestures(
                                        onDragEnd = {
                                            if (pull.value >= tearAtPx) {
                                                rip()
                                            } else {
                                                holes = 0
                                                scope.launch { pull.animateTo(0f, spring(dampingRatio = 0.45f, stiffness = 420f)) }
                                            }
                                        },
                                        onDragCancel = {
                                            holes = 0
                                            scope.launch { pull.animateTo(0f, spring(dampingRatio = 0.45f, stiffness = 420f)) }
                                        },
                                    ) { change, dx ->
                                        change.consume()
                                        // Paper resists: past the tear point it barely moves.
                                        val next = (pull.value + dx).coerceAtLeast(0f)
                                        val resisted = if (next > tearAtPx) tearAtPx + (next - tearAtPx) * 0.25f else next
                                        scope.launch { pull.snapTo(resisted) }
                                        feelHoles(resisted)
                                    }
                                }
                        } else Modifier,
                    ),
                contentAlignment = Alignment.Center,
            ) {
                StubFace(item, actions, locked, brand)
            }
        }
    }
}

@Composable
private fun StubFace(item: RewardItem, actions: TicketActions, locked: Boolean, brand: Color?) {
    when {
        !locked -> Column(horizontalAlignment = Alignment.CenterHorizontally) {
            // Three drawn chevrons: the direction to pull.
            Canvas(Modifier.width(26.dp).height(12.dp)) {
                val c = brand ?: Board.Blue
                for (i in 0 until 3) {
                    val x = size.width * (0.15f + i * 0.3f)
                    val a = 0.35f + i * 0.3f
                    drawLine(c.copy(alpha = a), Offset(x, 1f), Offset(x + size.height * 0.45f, size.height / 2f), 2.dp.toPx(), StrokeCap.Round)
                    drawLine(c.copy(alpha = a), Offset(x + size.height * 0.45f, size.height / 2f), Offset(x, size.height - 1f), 2.dp.toPx(), StrokeCap.Round)
                }
            }
            Spacer(Modifier.height(8.dp))
            Text(
                "Tear to\nclaim",
                color = Board.Blue,
                fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.Bold,
                fontSize = 12.sp,
                lineHeight = 14.sp,
                textAlign = TextAlign.Center,
            )
        }
        "rewarded_ad" in item.lockReasons && item.canWatchAd && actions.adsAvailable -> Column(
            Modifier.fillMaxHeight().fillMaxWidth().clickable(enabled = !actions.watching) { actions.onWatch() }.padding(8.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            if (actions.watching) {
                CircularProgressIndicator(color = Board.Blue, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
            } else {
                Icon(Icons.Filled.PlayCircle, null, tint = Board.Blue, modifier = Modifier.size(20.dp))
            }
            Spacer(Modifier.height(6.dp))
            Text(
                if (actions.watching) "Checking…" else "Watch an\nad to unlock",
                color = Board.Blue, fontFamily = PlusJakartaSans, fontWeight = FontWeight.Bold,
                fontSize = 11.sp, lineHeight = 13.sp, textAlign = TextAlign.Center,
            )
        }
        else -> Column(Modifier.padding(horizontal = 8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Icon(Icons.Filled.Lock, null, tint = Board.InkFaint, modifier = Modifier.size(14.dp))
            Spacer(Modifier.height(6.dp))
            Text(
                when {
                    "verification" in item.lockReasons -> "After both\ncaptains sign"
                    item.statusReason == "trust_too_low" -> "After the\norganiser\nconfirms"
                    else -> "Locked"
                },
                color = Board.InkFaint, fontFamily = PlusJakartaSans, fontWeight = FontWeight.SemiBold,
                fontSize = 10.5.sp, lineHeight = 13.sp, textAlign = TextAlign.Center,
            )
        }
    }
}

@Composable
private fun CouponBody(
    item: RewardItem,
    full: RewardItem?,
    actions: TicketActions,
    locked: Boolean,
    claimed: Boolean,
    torn: Boolean,
    modifier: Modifier,
) {
    val density = LocalDensity.current
    val shape = remember(torn, claimed) { bodyShape(density, torn = torn || claimed, seed = item.id.toInt()) }
    Column(
        modifier
            .shadow(3.dp, shape, ambientColor = Color(0x0F0F172A), spotColor = Color(0x1A0F172A))
            .clip(shape)
            .background(Board.Surface)
            .border(1.dp, Board.Line, shape)
            .drawBehind {
                if (!torn && !claimed) {
                    // The perforation: a row of punched holes down the seam.
                    val x = size.width - 0.5f
                    drawLine(
                        Board.LockedStub, Offset(x, Notch.toPx() + 2f), Offset(x, size.height - Notch.toPx() - 2f),
                        strokeWidth = 2.dp.toPx(), cap = StrokeCap.Round,
                        pathEffect = PathEffect.dashPathEffect(floatArrayOf(0.1f, 7.dp.toPx())),
                    )
                }
            }
            .padding(start = 16.dp, end = 14.dp, top = 13.dp, bottom = 12.dp),
    ) {
        val sponsor = item.sponsor
        val partner = sponsor?.name ?: if (item.sponsored) "Partner" else "Haraan"
        Row(verticalAlignment = Alignment.CenterVertically) {
            sponsor?.logo?.takeIf { it.isNotBlank() }?.let { logo ->
                AsyncImage(
                    ApiConfig.mediaUrl(logo), partner,
                    Modifier.size(18.dp).clip(CircleShape).border(0.8.dp, Board.Line, CircleShape),
                    contentScale = ContentScale.Crop,
                )
                Spacer(Modifier.width(6.dp))
            }
            Text(
                listOfNotNull(partner, sponsor?.category?.lowercase()?.replaceFirstChar { it.uppercase() }).joinToString("  ·  "),
                color = if (locked) Board.InkFaint else Board.InkMuted,
                fontFamily = PlusJakartaSans, fontWeight = FontWeight.SemiBold, fontSize = 12.sp,
                maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
        }
        Spacer(Modifier.height(4.dp))

        val (big, small) = valueParts(item)
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                big, color = if (locked) Board.InkFaint else Board.Ink, fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.ExtraBold, fontSize = 30.sp, letterSpacing = (-0.8).sp, maxLines = 1,
            )
            small?.let {
                Text(
                    " $it", color = if (locked) Board.InkFaint else Board.InkMuted, fontFamily = PlusJakartaSans,
                    fontWeight = FontWeight.SemiBold, fontSize = 15.sp, modifier = Modifier.padding(bottom = 5.dp),
                )
            }
        }
        what(item)?.let {
            Text(
                it, color = if (locked) Board.InkFaint else Board.Ink, fontFamily = PlusJakartaSans,
                fontWeight = FontWeight.SemiBold, fontSize = 13.sp, maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
        }
        Spacer(Modifier.weight(1f))
        when {
            claimed -> ClaimedLine(item, full, actions)
            torn -> Text(
                "Saving to your wallet…", color = Board.InkFaint, fontFamily = PlusJakartaSans, fontSize = 11.5.sp,
            )
            else -> {
                val meta = if (item.geo != null) null else listOfNotNull(
                    item.description?.takeIf { it.isNotBlank() },
                    item.expiresAt?.let { "Use by ${shortDate(it)}" },
                ).firstOrNull()
                item.geo?.let { RewardLocationLine(it) }
                meta?.let {
                    Text(
                        it, color = Board.InkFaint, fontFamily = PlusJakartaSans, fontSize = 11.5.sp,
                        maxLines = 2, lineHeight = 14.sp, overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }
    }
}

/** What a claimed coupon is for now: its code, and where to spend it. */
@Composable
private fun ClaimedLine(item: RewardItem, full: RewardItem?, actions: TicketActions) {
    val claim = full?.claim ?: item.claim
    val context = LocalContext.current
    val view = LocalView.current
    val clipboard = LocalClipboardManager.current
    val code = claim?.code

    if (code == null) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("In your wallet", color = Board.Green, fontFamily = PlusJakartaSans, fontWeight = FontWeight.SemiBold, fontSize = 12.sp)
            Spacer(Modifier.weight(1f))
            LinkText("Show code", actions.onReveal)
        }
        return
    }
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(
            code, color = Board.Ink, fontFamily = FontFamily.Monospace, fontWeight = FontWeight.Bold,
            fontSize = 13.5.sp, letterSpacing = 0.8.sp, maxLines = 1, overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f, fill = false),
        )
        Spacer(Modifier.width(10.dp))
        LinkText("Copy") {
            view.performHapticFeedback(Feel.SELECT)
            clipboard.setText(AnnotatedString(code))
            Toast.makeText(context, "Code copied", Toast.LENGTH_SHORT).show()
        }
    }
    val meta = when {
        claim.used == true -> "Used"
        claim.expiresAt != null -> "Use by ${shortDate(claim.expiresAt)}"
        claim.endsAt != null -> "Active until ${shortDate(claim.endsAt)}"
        else -> null
    }
    Row(verticalAlignment = Alignment.CenterVertically) {
        meta?.let { Text(it, color = Board.InkFaint, fontFamily = PlusJakartaSans, fontSize = 11.5.sp) }
        Spacer(Modifier.weight(1f))
        when {
            claim.url != null -> LinkText(claim.ctaText ?: "Open offer") { openExternalUrl(context, claim.url) }
            item.type == "haraan_coupon" && claim.used != true -> LinkText("Use at venue") { actions.onUse(claim.scope) }
        }
    }
}

@Composable
private fun LinkText(text: String, onClick: () -> Unit) {
    Text(
        text, color = Board.Blue, fontFamily = PlusJakartaSans, fontWeight = FontWeight.Bold, fontSize = 12.5.sp,
        modifier = Modifier.clip(androidx.compose.foundation.shape.RoundedCornerShape(6.dp)).clickable(onClick = onClick)
            .padding(horizontal = 4.dp, vertical = 3.dp),
    )
}

/** "₹100" + "off" · "20%" + "off" · "Free" + "kit" · "BOGO" + "drink". */
private fun valueParts(item: RewardItem): Pair<String, String?> {
    val stub = item.stub ?: return item.title to null
    val v = stub.value.trim()
    val cap = stub.caption.trim().lowercase(Locale.ENGLISH).takeIf { it.isNotBlank() }
    return when {
        v.equals("free", true) -> "Free" to cap
        else -> v to cap
    }
}

/** The title with the value phrase taken out, so "₹100 Off Turf Booking" reads "Turf booking". */
private fun what(item: RewardItem): String? {
    val stub = item.stub ?: return null
    val t = item.title.trim()
    val stripped = t
        .replace(Regex("^(flat\\s+)?" + Regex.escape(stub.value.trim()) + "\\s*" + Regex.escape(stub.caption.trim()) + "\\s*", RegexOption.IGNORE_CASE), "")
        .trim()
    val out = stripped.ifBlank { t }
    return out.lowercase(Locale.ENGLISH).replaceFirstChar { it.uppercase() }
        .replace(Regex("\\bpro\\b"), "Pro")
}

private fun Color.compositeOverWhite(): Color = Color(
    red = red * alpha + (1f - alpha), green = green * alpha + (1f - alpha), blue = blue * alpha + (1f - alpha),
)

// ── Shapes: two halves of one ticket, with half-round notches where the perforation runs ──────

private fun bodyShape(density: androidx.compose.ui.unit.Density, torn: Boolean, seed: Int): Shape = GenericShape { size, _ ->
    val r = with(density) { Corner.toPx() }
    val n = with(density) { Notch.toPx() }
    if (!torn) {
        val card = Path().apply {
            addRoundRect(
                RoundRect(
                    Rect(0f, 0f, size.width, size.height),
                    topLeft = CornerRadius(r), bottomLeft = CornerRadius(r),
                    topRight = CornerRadius.Zero, bottomRight = CornerRadius.Zero,
                ),
            )
        }
        val holes = Path().apply {
            addOval(Rect(Offset(size.width, 0f), n))
            addOval(Rect(Offset(size.width, size.height), n))
        }
        addPath(Path().apply { op(card, holes, PathOperation.Difference) })
    } else {
        // Ragged paper where the stub came away: irregular teeth, the same for this perk every time.
        var s = seed * 7919 + 17
        fun rnd(): Float {
            s = s * 1103515245 + 12345
            return ((s ushr 9) and 0x3FF) / 1023f
        }
        val step = with(density) { 5.dp.toPx() }
        val depth = with(density) { 4.dp.toPx() }
        moveTo(r, 0f)
        lineTo(size.width - depth, 0f)
        var y = 0f
        var i = 0
        while (y < size.height) {
            y = (y + step * (0.7f + rnd() * 0.6f)).coerceAtMost(size.height)
            lineTo(if (i % 2 == 0) size.width - depth * (0.2f + rnd() * 0.5f) else size.width - depth * (0.8f + rnd() * 0.6f), y)
            i++
        }
        lineTo(r, size.height)
        arcTo(Rect(0f, size.height - 2 * r, 2 * r, size.height), 90f, 90f, false)
        lineTo(0f, r)
        arcTo(Rect(0f, 0f, 2 * r, 2 * r), 180f, 90f, false)
        close()
    }
}

private fun stubShape(density: androidx.compose.ui.unit.Density): Shape = GenericShape { size, _ ->
    val r = with(density) { Corner.toPx() }
    val n = with(density) { Notch.toPx() }
    val card = Path().apply {
        addRoundRect(
            RoundRect(
                Rect(0f, 0f, size.width, size.height),
                topLeft = CornerRadius.Zero, bottomLeft = CornerRadius.Zero,
                topRight = CornerRadius(r), bottomRight = CornerRadius(r),
            ),
        )
    }
    val holes = Path().apply {
        addOval(Rect(Offset(0f, 0f), n))
        addOval(Rect(Offset(0f, size.height), n))
    }
    addPath(Path().apply { op(card, holes, PathOperation.Difference) })
}
