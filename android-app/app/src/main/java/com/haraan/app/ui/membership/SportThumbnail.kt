package com.haraan.app.ui.membership

import android.annotation.SuppressLint
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.ColorMatrix
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.haraan.app.R
import com.haraan.app.ui.theme.HaraanColors

/**
 * Subtle rounded thumbnail displaying a professional Indian athlete visual
 * alongside a clean, consistent premium sport glyph badge.
 */
@Composable
fun SportThumbnail(sport: String, size: Dp, dimmed: Boolean, modifier: Modifier = Modifier) {
    val photo = rememberSportPhoto(sport)
    val shape = RoundedCornerShape(16.dp)

    Box(
        modifier = modifier.size(size),
        contentAlignment = Alignment.Center,
    ) {
        if (photo != 0) {
            Box(
                Modifier
                    .fillMaxSize()
                    .clip(shape)
                    .background(Color(0xFFF8FAFC))
                    .border(1.dp, Color(0xFFE2E8F0), shape),
            ) {
                Image(
                    painter = painterResource(photo),
                    contentDescription = null,
                    contentScale = ContentScale.Crop,
                    colorFilter = if (dimmed) Greyscale else null,
                    alpha = if (dimmed) 0.5f else 1f,
                    modifier = Modifier.fillMaxSize(),
                )
            }
        } else {
            val (top, bottom) = if (dimmed) Color(0xFFE2E8F0) to Color(0xFFCBD5E1) else SportPalette.of(sport)
            Box(
                Modifier
                    .fillMaxSize()
                    .clip(shape)
                    .background(Brush.linearGradient(listOf(top, bottom)))
                    .border(1.dp, Color(0xFFE2E8F0), shape),
                contentAlignment = Alignment.Center,
            ) {
                SportGlyph(sport, tint = if (dimmed) Color(0xFF94A3B8) else Color.White, size = size * 0.48f)
            }
        }

        // Clean premium sport icon badge pinned at bottom-right of athlete thumbnail
        Box(
            Modifier
                .align(Alignment.BottomEnd)
                .offset(x = 3.dp, y = 3.dp)
                .size(20.dp)
                .shadow(1.5.dp, CircleShape)
                .clip(CircleShape)
                .background(Color.White)
                .border(1.dp, if (dimmed) Color(0xFFE2E8F0) else HaraanColors.EventsBlue.copy(alpha = 0.35f), CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            SportGlyph(
                sport = sport,
                tint = if (dimmed) Color(0xFF94A3B8) else HaraanColors.EventsBlue,
                size = 11.5.dp,
            )
        }
    }
}

private val Greyscale = ColorFilter.colorMatrix(ColorMatrix().apply { setToSaturation(0f) })

@SuppressLint("DiscouragedApi")
@Composable
private fun rememberSportPhoto(sport: String): Int {
    val context = LocalContext.current
    return remember(sport) {
        val staticRes = when (sport.lowercase()) {
            "cricket" -> R.drawable.insight_sport_cricket
            "football" -> R.drawable.insight_sport_football
            "badminton" -> R.drawable.insight_sport_badminton
            "volleyball" -> R.drawable.insight_sport_volleyball
            "basketball" -> R.drawable.insight_sport_basketball
            "kabaddi" -> R.drawable.insight_sport_kabaddi
            "tennis" -> R.drawable.insight_sport_tennis
            "table_tennis" -> R.drawable.insight_sport_table_tennis
            else -> 0
        }
        if (staticRes != 0) staticRes
        else context.resources.getIdentifier("insight_sport_$sport", "drawable", context.packageName)
    }
}

/** Each sport's crest colours — used as graceful illustration fallback. */
internal object SportPalette {
    fun of(sport: String): Pair<Color, Color> = when (sport) {
        "cricket" -> Color(0xFF22A05A) to Color(0xFF0F6B3A)
        "football" -> Color(0xFF3B6FEA) to Color(0xFF1E3FA0)
        "badminton" -> Color(0xFF1CA3D6) to Color(0xFF0B6E9A)
        "volleyball" -> Color(0xFFF2A52A) to Color(0xFFC9700C)
        "basketball" -> Color(0xFFF07A2E) to Color(0xFFB9460F)
        "kabaddi" -> Color(0xFF8B5CF6) to Color(0xFF5B30C4)
        "tennis" -> Color(0xFF84B82B) to Color(0xFF4F7A12)
        "table_tennis" -> Color(0xFF14B8A6) to Color(0xFF0B7A6F)
        else -> Color(0xFF64748B) to Color(0xFF334155)
    }
}
