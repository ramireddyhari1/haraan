package com.haraan.app.ui.rewards

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.content.Intent
import android.graphics.BitmapFactory
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.ApiConfig
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.PlusJakartaSans

/**
 * The reward screen's palette. A sports broadcast look on white, not a game: a clean white
 * page, navy type at three strengths (Haraan's navy carries the identity), Haraan Blue for
 * everything you can tap, and Haraan Green ONLY for what was actually earned — a win, XP, a
 * confirmation, an unlock.
 */
internal object Board {
    val Base = Color(0xFFF8FAFC)        // clean cool slate ground
    val BaseTop = Color(0xFFFFFFFF)     // crisp white header wash
    val Surface = Color(0xFFFFFFFF)     // elevated card surface
    val Raised = Color(0xFFF1F5F9)      // wells, tracks, crest discs (Slate 100)
    val Line = Color(0xFFE2E8F0)        // crisp hairline borders (Slate 200)
    val Ink = Color(0xFF0F172A)         // Slate 900
    val InkMuted = Color(0xFF334155)    // Slate 700 (7.5:1 AA contrast)
    val InkFaint = Color(0xFF64748B)    // Slate 500 (4.6:1 AA contrast, replaces illegible #94A3B8)
    val Blue = HaraanColors.EventsBlue  // #2563EB — actions
    val BlueBright = HaraanColors.EventsBlue
    val BlueWell = Color(0xFFEFF6FF)    // Blue 50
    val Green = Color(0xFF059669)       // Emerald 600
    val GreenWell = Color(0xFFECFDF5)   // Emerald 50
    val Amber = Color(0xFFD97706)       // Amber 600 — projected/pending status
    val AmberWell = Color(0xFFFEF3C7)   // Amber 100
    val Gold = Color(0xFFF59E0B)        // Gold/Amber 500 for POTM
    val GoldWell = Color(0xFFFFFBEB)
    val LockedStub = Color(0xFFCBD5E1)
}

/** Medal metals: a light and a dark stop per tier, so a badge reads as metal, not a flat disc. */
internal fun tierMetal(tier: String): Pair<Color, Color> = when (tier) {
    "gold" -> Color(0xFFF8D56B) to Color(0xFFB7791F)
    "silver" -> Color(0xFFE5E7EB) to Color(0xFF8A94A6)
    else -> Color(0xFFE9A46A) to Color(0xFF8C4A22)
}

internal fun parseHex(hex: String?): Color? = hex?.takeIf { it.isNotBlank() }?.let {
    runCatching { Color(android.graphics.Color.parseColor(it)) }.getOrNull()
}

/**
 * Entrance choreography. [shown] flips once (per step); the element rises smoothly and fades in.
 * Uses a tight 320ms spring-eased transition.
 */
@Composable
internal fun Modifier.rise(shown: Boolean, durationMs: Int = 320): Modifier {
    val t by animateFloatAsState(if (shown) 1f else 0f, tween(durationMs, easing = FastOutSlowInEasing), label = "rise")
    val lift = with(LocalDensity.current) { 14.dp.toPx() }
    return graphicsLayer { alpha = t; translationY = (1f - t) * lift }
}

/** A team's crest: its logo/emblem when present, else asset club logo or athletic monogram disc. */
@Composable
internal fun Crest(name: String, logo: String?, size: Dp, dimmed: Boolean = false) {
    val context = LocalContext.current
    val base = Modifier.size(size).clip(CircleShape).graphicsLayer { alpha = if (dimmed) 0.55f else 1f }

    val cleanLogo = logo?.trim().orEmpty()
    val emblemRes = remember(cleanLogo) {
        if (cleanLogo.isNotBlank()) com.haraan.app.ui.matches.create.emblemDrawableFor(cleanLogo) else null
    }
    val resolvedUrl = remember(cleanLogo) {
        if (cleanLogo.isNotBlank() && (cleanLogo.startsWith("http") || cleanLogo.startsWith("/"))) {
            ApiConfig.mediaUrl(cleanLogo)
        } else if (cleanLogo.isNotBlank() && !cleanLogo.startsWith("action")) {
            ApiConfig.mediaUrl(cleanLogo)
        } else null
    }
    val code = remember(name) { name.filter { it.isLetterOrDigit() }.take(3).lowercase() }
    val assetBitmap = remember(code) {
        if (code.isNotBlank()) {
            runCatching {
                context.assets.open("logos/$code.png").use { BitmapFactory.decodeStream(it) }?.asImageBitmap()
            }.getOrNull()
        } else null
    }

    Box(
        modifier = base
            .background(Color.White)
            .border(1.2.dp, Board.Line, CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        when {
            emblemRes != null -> {
                Image(
                    painter = painterResource(emblemRes),
                    contentDescription = name,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize().clip(CircleShape),
                )
            }
            resolvedUrl != null -> {
                AsyncImage(
                    model = resolvedUrl,
                    contentDescription = name,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize().clip(CircleShape),
                )
            }
            assetBitmap != null -> {
                Image(
                    bitmap = assetBitmap,
                    contentDescription = name,
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.fillMaxSize().padding((size.value * 0.12f).dp).clip(CircleShape),
                )
            }
            else -> {
                Box(
                    Modifier.fillMaxSize().background(Board.Raised),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        name.filter { it.isLetterOrDigit() }.take(2).uppercase().ifBlank { "·" },
                        color = Board.Ink,
                        fontFamily = PlusJakartaSans,
                        fontWeight = FontWeight.ExtraBold,
                        fontSize = (size.value * 0.32f).sp,
                    )
                }
            }
        }
    }
}

/** Buttons. Blue is the only action colour; [primary] is filled, otherwise a quiet blue well. */
@Composable
internal fun ActionButton(
    text: String,
    primary: Boolean,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    enabled: Boolean = true,
    compact: Boolean = false,
    onClick: () -> Unit,
) {
    val shape = RoundedCornerShape(if (compact) 50 else 16)
    Row(
        modifier
            .pressable(enabled = enabled, haptic = if (primary) Feel.COMMIT else Feel.SELECT) { if (enabled) onClick() }
            .clip(shape)
            .background(
                when {
                    primary && enabled -> Board.Blue
                    primary -> Board.Blue.copy(alpha = 0.45f)
                    else -> Board.BlueWell
                },
            )
            .padding(horizontal = if (compact) 16.dp else 18.dp, vertical = if (compact) 9.dp else 15.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.Center,
    ) {
        icon?.let {
            Icon(it, null, tint = if (primary) Color.White else Board.BlueBright, modifier = Modifier.size(if (compact) 15.dp else 18.dp))
            Spacer(Modifier.width(7.dp))
        }
        Text(
            text,
            color = if (primary) Color.White else Board.BlueBright,
            fontFamily = PlusJakartaSans, fontWeight = FontWeight.Bold, fontSize = if (compact) 13.sp else 15.sp,
            maxLines = 1,
        )
    }
}

/** Section eyebrow: small caps, generous tracking, optional count chip. */
@Composable
internal fun Eyebrow(text: String, modifier: Modifier = Modifier, chip: String? = null) {
    Row(modifier, verticalAlignment = Alignment.CenterVertically) {
        Text(text.uppercase(), color = Board.InkFaint, fontFamily = PlusJakartaSans, fontWeight = FontWeight.Bold, fontSize = 11.sp, letterSpacing = 1.6.sp)
        chip?.let {
            Spacer(Modifier.width(8.dp))
            Text(
                it, color = Board.Green, fontFamily = PlusJakartaSans, fontWeight = FontWeight.Bold, fontSize = 11.sp,
                modifier = Modifier.clip(RoundedCornerShape(50)).background(Board.GreenWell).padding(horizontal = 8.dp, vertical = 2.dp),
            )
        }
    }
}

/** "2026-10-19T10:00:00+05:30" → "19 Oct". Never throws; no java.time (minSdk 24). */
internal fun shortDate(iso: String): String {
    val m = Regex("""^(\d{4})-(\d{2})-(\d{2})""").find(iso) ?: return iso.take(10)
    val months = listOf("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec")
    val month = m.groupValues[2].toInt()
    return if (month in 1..12) "${m.groupValues[3].toInt()} ${months[month - 1]}" else iso.take(10)
}

internal tailrec fun Context.findActivity(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> baseContext.findActivity()
    else -> null
}

internal fun shareText(context: Context, text: String, url: String?, title: String) {
    val body = listOfNotNull(text, url).joinToString("\n")
    val send = Intent(Intent.ACTION_SEND).setType("text/plain").putExtra(Intent.EXTRA_TEXT, body)
    runCatching { context.startActivity(Intent.createChooser(send, title)) }
}
