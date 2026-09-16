package com.haraan.app.ui.main.eventdetail

import android.content.Intent
import android.widget.Toast
import androidx.compose.foundation.layout.*
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.ApiConfig
import com.haraan.app.data.FavoritesStore
import com.haraan.app.ui.components.HeroActionButton
import com.haraan.app.ui.components.HeroActionDefaults
import com.haraan.app.ui.components.HeroActionIcons
import com.haraan.app.ui.components.HeroToggleActionButton
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.HaraanTypography

/**
 * Top navigation that starts as translucent circular buttons floating over the
 * hero, and collapses into a solid titled app bar as the poster scrolls away.
 * One source of truth for back / share / save — no second button set.
 *
 * @param eventId server id; saves are keyed on it and numeric ids get a web link.
 * @param shareDetails "when · where" line for the share message; blank is skipped.
 * @param collapseProgress 0 over the poster, 1 when fully collapsed.
 */
@Composable
fun EventFloatingNav(
    onBack: () -> Unit,
    eventId: String,
    title: String,
    shareDetails: String,
    collapseProgress: Float,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    var isSaved by remember(eventId) { mutableStateOf(FavoritesStore.isEventSaved(context, eventId)) }

    val p = collapseProgress

    Surface(
        color = HaraanColors.Surface.copy(alpha = p),
        shadowElevation = (4f * p).dp,
        modifier = modifier.fillMaxWidth()
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
        ) {
            // Collapsed title — fades in, centered, leaving room for the buttons.
            Text(
                text = title,
                style = HaraanTypography.TitleMedium.copy(
                    fontSize = 16.sp,
                    color = HaraanColors.TextPrimary,
                    fontWeight = FontWeight.Bold
                ),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier
                    .align(Alignment.Center)
                    .padding(horizontal = 64.dp, vertical = 8.dp)
                    .graphicsLayer { alpha = ((p - 0.4f) / 0.6f).coerceIn(0f, 1f) }
            )

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = HeroActionDefaults.EdgePadding, vertical = 4.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                HeroActionButton(
                    icon = HeroActionIcons.Back,
                    contentDescription = "Back",
                    onClick = onBack,
                    collapse = p,
                    iconSize = HeroActionDefaults.BackIconSize
                )

                Row(
                    horizontalArrangement = Arrangement.spacedBy(HeroActionDefaults.Spacing),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    HeroActionButton(
                        icon = HeroActionIcons.Share,
                        contentDescription = "Share",
                        onClick = { shareEvent(context, eventId, title, shareDetails) },
                        collapse = p
                    )

                    HeroToggleActionButton(
                        checked = isSaved,
                        onToggle = {
                            isSaved = FavoritesStore.toggleEvent(context, eventId)
                            Toast.makeText(
                                context,
                                if (isSaved) "Saved" else "Removed",
                                Toast.LENGTH_SHORT
                            ).show()
                        },
                        iconOff = HeroActionIcons.BookmarkOutline,
                        iconOn = HeroActionIcons.BookmarkFilled,
                        activeColor = HaraanColors.EventsBlue,
                        contentDescriptionOff = "Save event",
                        contentDescriptionOn = "Remove from saved",
                        collapse = p
                    )
                }
            }
        }
    }
}

/**
 * Opens the system share sheet (which carries its own Copy action) with the event's
 * name, when/where, and its public page. Only numeric ids have a page on the site;
 * anything else is shared as text rather than as a link that would 404.
 */
private fun shareEvent(context: android.content.Context, eventId: String, title: String, details: String) {
    val link = eventId.toIntOrNull()?.let { "${ApiConfig.PUBLIC_WEB_URL}/events/$it" }
    val text = buildString {
        append(title)
        if (details.isNotBlank()) append('\n').append(details)
        append("\n\n").append(link ?: "Find it on Haraan.")
    }
    val send = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_SUBJECT, title)
        putExtra(Intent.EXTRA_TEXT, text)
    }
    runCatching { context.startActivity(Intent.createChooser(send, "Share event")) }
        .onFailure { Toast.makeText(context, "No app available to share", Toast.LENGTH_SHORT).show() }
}
