package com.example.thanna.ui.main

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import android.content.Intent
import android.net.Uri
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.dp
import com.example.thanna.EventDetail
import com.example.thanna.OrderSummary
import com.example.thanna.data.EventDetailInfo
import com.example.thanna.data.EventRepository
import com.example.thanna.ui.main.eventdetail.*
import com.example.thanna.ui.util.openMap
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.HaraanSpacing
import kotlinx.coroutines.delay

@Composable
fun EventDetailScreen(
    event: EventDetail,
    onBack: () -> Unit,
    onCheckout: (OrderSummary) -> Unit = {},
) {
    val scrollState = rememberScrollState()

    // Load full detail once: sellable ticket tiers for the booking bar plus the
    // admin-authored "Good to Know" attributes and T&C notes. Blank for
    // flat-price / mock events, in which case we fall back to the nav-key notes.
    val detail by produceState(initialValue = EventDetailInfo(), event.id) {
        value = runCatching { EventRepository().getEventDetail(event.id) }.getOrDefault(EventDetailInfo())
    }
    val ticketTypes = detail.ticketTypes
    val infoNotes = detail.infoNotes.ifEmpty { event.infoNotes }
    val eventIdInt = remember(event.id) { event.id.toIntOrNull() }

    val context = LocalContext.current
    var showSchedule by remember { mutableStateOf(false) }

    // Schedule sheet — opened from the "Doors Open" card when a run-of-show exists.
    if (showSchedule && detail.schedule.isNotEmpty()) {
        EventScheduleSheet(
            entries = detail.schedule,
            onDismiss = { showSchedule = false }
        )
    }

    val configuration = LocalConfiguration.current
    val density = LocalDensity.current
    val posterHeight = (configuration.screenHeightDp * 0.42f).dp

    // Collapse progress: 0 over the poster, 1 once the hero has mostly scrolled
    // away. Drives the top bar fade-in and the nav buttons' style transition.
    val collapseProgress by remember {
        derivedStateOf {
            val triggerPx = with(density) { posterHeight.toPx() } * 0.6f
            (scrollState.value / triggerPx).coerceIn(0f, 1f)
        }
    }

    // Content entrance — single fade+rise, then static
    var contentIn by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { delay(90); contentIn = true }
    val contentAlpha by animateFloatAsState(
        targetValue = if (contentIn) 1f else 0f,
        animationSpec = tween(460),
        label = "contentAlpha"
    )
    val contentRise by animateFloatAsState(
        targetValue = if (contentIn) 0f else 40f,
        animationSpec = tween(460),
        label = "contentRise"
    )

    // Booking bar slide-in — once, then static
    var barIn by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { barIn = true }
    val barRise by animateFloatAsState(
        targetValue = if (barIn) 0f else 1f,
        animationSpec = tween(420),
        label = "barRise"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(HaraanColors.Background)
    ) {
        // 1. Parallax Hero (behind everything)
        EventHeroSection(
            imageUrl = event.imageUrl,
            title = event.title,
            scrollState = scrollState
        )

        // 2. Scrollable Content
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(scrollState)
        ) {
            // Spacer for poster area — pulled up 28dp so the sheet genuinely
            // overlaps the poster (single clean curve at the seam).
            Spacer(modifier = Modifier.height(posterHeight - 28.dp))

            // Content sheet — overlaps poster with rounded top
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .graphicsLayer {
                        alpha = contentAlpha
                        translationY = contentRise
                    }
                    .clip(
                        RoundedCornerShape(
                            topStart = HaraanRadius.Hero,
                            topEnd = HaraanRadius.Hero
                        )
                    )
                    .background(HaraanColors.Surface)
                    .padding(top = HaraanSpacing.Medium, bottom = 180.dp)
            ) {
                // Identity Row — category pill + aggregate rating (from detail).
                EventIdentityRow(
                    category = event.category,
                    rating = detail.rating,
                    ratingsCount = detail.ratingsCount
                )

                Spacer(modifier = Modifier.height(HaraanSpacing.Compact))

                // Title + rich date line ("Sun, 5 Jul, 8:00 PM")
                EventHeader(
                    title = event.title,
                    date = detail.fullDate.ifBlank { event.date },
                    city = detail.city
                )

                Spacer(modifier = Modifier.height(HaraanSpacing.Medium))

                // Trust Indicators
                EventTrustIndicators()

                Spacer(modifier = Modifier.height(HaraanSpacing.Medium))

                // Metadata Cards — date, tappable venue (→ maps), doors-open
                // time (→ schedule sheet when the host defined one)
                EventMetadataCards(
                    date = event.date,
                    venue = event.venue,
                    scheduleAvailable = detail.schedule.isNotEmpty(),
                    onVenueClick = {
                        if (event.venue.isNotBlank() || detail.mapLink.isNotBlank()) {
                            openMap(context, detail.mapLink, event.venue)
                        }
                    },
                    onScheduleClick = { showSchedule = true }
                )

                Spacer(modifier = Modifier.height(HaraanSpacing.Large))

                // About Section — the admin's real description from the detail API;
                // the nav-key value is only a fallback for sample/offline events.
                EventAboutSection(
                    title = event.title,
                    venue = event.venue,
                    description = detail.description.ifBlank { event.description }
                )

                Spacer(modifier = Modifier.height(HaraanSpacing.Large))

                // Organizer — who's running / selling the event (hides if unknown)
                EventOrganizerSection(
                    organizer = event.organizer,
                    subtitle = event.organizerSubtitle
                )

                if (event.organizer.isNotBlank()) {
                    Spacer(modifier = Modifier.height(HaraanSpacing.Large))
                }

                // Who takes the stage — coverflow lineup (hides when empty)
                if (detail.lineup.isNotEmpty()) {
                    EventLineupSection(artists = detail.lineup)
                    Spacer(modifier = Modifier.height(HaraanSpacing.Large))
                }

                // Venue — name, address & a working "Get directions" action
                EventVenueSection(venue = event.venue, mapLink = detail.mapLink)

                Spacer(modifier = Modifier.height(HaraanSpacing.Large))

                // Good to Know — admin-authored attribute grid (hides when empty)
                if (detail.goodToKnow.isNotEmpty()) {
                    EventGoodToKnowCard(items = detail.goodToKnow)
                    Spacer(modifier = Modifier.height(HaraanSpacing.Large))
                }

                // Important Info Card — single source for event notes
                EventImportantInfoCard(
                    infoNotes = infoNotes
                )
            }
        }

        // 3. Floating Nav — collapses into a titled top bar on scroll
        EventFloatingNav(
            onBack = onBack,
            title = event.title,
            collapseProgress = collapseProgress
        )

        // 4. Sticky Booking Bar
        EventStickyBookingBar(
            price = event.price,
            barRise = barRise,
            eventId = eventIdInt,
            ticketTypes = ticketTypes,
            onCheckout = { lines ->
                onCheckout(
                    OrderSummary(
                        eventId = eventIdInt ?: -1,
                        title = event.title,
                        date = detail.fullDate.ifBlank { event.date },
                        venue = event.venue,
                        imageUrl = event.imageUrl,
                        lines = lines,
                        feeType = detail.feeType,
                        feeValue = detail.feeValue,
                        infoNotes = infoNotes,
                    )
                )
            },
            modifier = Modifier.align(Alignment.BottomCenter)
        )
    }
}
