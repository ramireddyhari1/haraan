package com.haraan.app.ui.membership

import androidx.annotation.DrawableRes
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.R

/**
 * The look of each member tier. PRO is midnight navy with an ice-blue edge — Haraan's own blue,
 * taken darker. HERO is onyx and champagne gold. Both are identity colours only: commit buttons
 * stay the app's blue/green everywhere.
 */
data class MemberTierStyle(
    @param:DrawableRes val mark: Int,
    /** Dark band gradient, top-left → bottom-right. */
    val band: List<Color>,
    /** The tier's metal on a dark band: eyebrows, rules. */
    val metal: Color,
    /** The tier's colour on a light surface: chip text, accents. */
    val accent: Color,
    /** Quiet well behind [accent] on a light surface. */
    val tint: Color,
    /** Hairline edge on a light surface. */
    val rim: Color,
)

object MemberTierStyles {
    val Pro = MemberTierStyle(
        mark = R.drawable.ic_member_pro,
        band = listOf(Color(0xFF1C3F95), Color(0xFF102A66), Color(0xFF081634)),
        metal = Color(0xFFBFDBFE),
        accent = Color(0xFF1E40AF),
        tint = Color(0xFFEEF3FF),
        rim = Color(0xFFC7D7FE),
    )

    val Hero = MemberTierStyle(
        mark = R.drawable.ic_member_hero,
        band = listOf(Color(0xFF332B20), Color(0xFF1A1712), Color(0xFF0A0908)),
        metal = Color(0xFFE9C779),
        accent = Color(0xFF8A6424),
        tint = Color(0xFFFBF6EA),
        rim = Color(0xFFEAD7A8),
    )

    /** Null for [MemberTier.REGULAR] — a regular member has no tier styling at all. */
    fun of(tier: MemberTier): MemberTierStyle? = when (tier) {
        MemberTier.PRO -> Pro
        MemberTier.HERO -> Hero
        MemberTier.REGULAR -> null
    }
}

/** The tier's mark. Draws nothing for a regular member. */
@Composable
fun MemberMark(
    tier: MemberTier,
    size: Dp,
    modifier: Modifier = Modifier,
    contentDescription: String? = "${tier.label} member",
) {
    val style = MemberTierStyles.of(tier) ?: return
    Image(
        painter = painterResource(style.mark),
        contentDescription = contentDescription,
        modifier = modifier.size(size),
    )
}

/**
 * "PRO MEMBER" with its mark, for a light surface. [compact] is the inline size that sits
 * beside a name. Draws nothing for a regular member.
 */
@Composable
fun MemberTierChip(tier: MemberTier, modifier: Modifier = Modifier, compact: Boolean = false) {
    val style = MemberTierStyles.of(tier) ?: return
    val text = if (compact) tier.label.uppercase() else "${tier.label.uppercase()} MEMBER"
    Row(
        modifier
            .semantics(mergeDescendants = true) { contentDescription = "${tier.label} member" }
            // Same 24dp as the regular "OFFICIAL MEMBER" pill it replaces, so nothing around it moves.
            .then(if (compact) Modifier else Modifier.height(24.dp))
            .clip(RoundedCornerShape(100.dp))
            .background(style.tint)
            .border(1.dp, style.rim, RoundedCornerShape(100.dp))
            .padding(start = 3.dp, end = if (compact) 7.dp else 9.dp, top = 1.dp, bottom = 1.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Image(
            painter = painterResource(style.mark),
            contentDescription = null,
            modifier = Modifier.size(if (compact) 13.dp else 14.dp),
        )
        Spacer(Modifier.width(if (compact) 4.dp else 5.dp))
        Text(
            text,
            color = style.accent,
            fontSize = if (compact) 9.sp else 9.5.sp,
            lineHeight = 12.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = if (compact) 0.6.sp else 0.9.sp,
            maxLines = 1,
        )
    }
}
