package com.example.thanna.ui.main

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Air
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Checkroom
import androidx.compose.material.icons.filled.DirectionsCar
import androidx.compose.material.icons.filled.EventSeat
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.filled.FitnessCenter
import androidx.compose.material.icons.filled.FavoriteBorder
import androidx.compose.material.icons.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.Lightbulb
import androidx.compose.material.icons.filled.LocalCafe
import androidx.compose.material.icons.filled.LocalDrink
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Restaurant
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Security
import androidx.compose.material.icons.filled.Share
import androidx.compose.material.icons.filled.Shower
import androidx.compose.material.icons.filled.SportsBasketball
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material.icons.filled.SportsSoccer
import androidx.compose.material.icons.filled.SportsTennis
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.StarBorder
import androidx.compose.material.icons.filled.Wc
import androidx.compose.material.icons.filled.Wifi
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import coil.compose.AsyncImage
import com.example.thanna.ui.components.AutoRefresh
import com.example.thanna.ui.util.openMap
import com.example.thanna.VenueDetail
import com.example.thanna.data.ApiConfig
import com.example.thanna.data.BookingRepository
import com.example.thanna.data.BookingResult
import com.example.thanna.data.FavoritesStore
import com.example.thanna.data.TokenStore
import com.example.thanna.data.ReviewResult
import com.example.thanna.data.VenueDetailData
import com.example.thanna.data.VenueRepository
import com.example.thanna.data.VenueReviewItem
import com.example.thanna.data.VenueSlotItem
import com.example.thanna.ui.theme.HaraanColors
import kotlinx.coroutines.launch
import java.time.LocalDate

// Photo fallback when a venue has no uploaded images yet (mirrors the browse card behaviour).
private fun detailCategoryImage(category: String): String = when {
  category.contains("Cricket", true) -> "https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=800&q=80"
  category.contains("Football", true) -> "https://images.unsplash.com/photo-1522778526097-ce0a22ceb253?w=800&q=80"
  category.contains("Badminton", true) -> "https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=800&q=80"
  else -> "https://images.unsplash.com/photo-1546519638-68e109498ffc?w=800&q=80"
}

/**
 * Venue detail — a "view → trust → book" page (Playo-style). Hero gallery + rating/amenities/
 * about/reviews build trust; a sticky Book Now drops into the slot-picker funnel that posts a
 * real reservation to /api/bookings/venue.
 */
@Composable
fun VenueDetailScreen(venue: VenueDetail, onBack: () -> Unit, onOpenPriceChart: () -> Unit = {}) {
  val ctx = LocalContext.current
  var detail by remember { mutableStateOf<VenueDetailData?>(null) }
  var loading by remember { mutableStateOf(true) }
  var showBooking by remember { mutableStateOf(false) }
  var showRating by remember { mutableStateOf(false) }
  var isFavorite by remember { mutableStateOf(FavoritesStore.isFavorite(ctx, venue.id)) }
  val scope = rememberCoroutineScope()

  val venueRepo = remember { VenueRepository() }
  LaunchedEffect(venue.id) {
    detail = venueRepo.getVenueDetail(venue.id)
    loading = false
  }
  // Re-pull slot availability / details when the user returns to this page or the app
  // comes back to the foreground, so a slot booked elsewhere isn't shown as free.
  // On-resume only (no constant poll) — keeps the last good detail on any failure.
  AutoRefresh(intervalMs = 0L) {
    venueRepo.getVenueDetail(venue.id)?.let { detail = it }
  }

  // Seed the header from the nav args so the page isn't blank while the detail loads.
  val name = detail?.name?.takeIf { it.isNotBlank() } ?: venue.title
  val category = detail?.category?.takeIf { it.isNotBlank() } ?: venue.category
  val rating = detail?.rating?.takeIf { it.isNotBlank() } ?: venue.rating
  val price = detail?.price ?: venue.price
  val images = detail?.images?.takeIf { it.isNotEmpty() }
    ?: listOf(venue.imageUrl.takeIf { it.isNotBlank() } ?: detailCategoryImage(category))

  val scroll = rememberScrollState()
  Surface(modifier = Modifier.fillMaxSize(), color = Color(0xFFE9EEF4)) {
    Box(modifier = Modifier.fillMaxSize()) {
      Column(
        modifier = Modifier
          .fillMaxSize()
          .verticalScroll(scroll)
      ) {
        // ── 1. Hero gallery ────────────────────────────────────────────────────────
        // Parallax: the hero rides up at ~0.6× the scroll speed so the content sheet
        // laps over a still-visible image instead of a hard cut. translationY partially
        // cancels the column's own upward shift.
        Box(
          modifier = Modifier
            .fillMaxWidth()
            .height(280.dp)
            .graphicsLayer { translationY = scroll.value * 0.4f }
        ) {
          if (images.size > 1) {
            val pager = rememberPagerState(pageCount = { images.size })
            HorizontalPager(state = pager, modifier = Modifier.fillMaxSize()) { page ->
              AsyncImage(
                model = images[page],
                contentDescription = name,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize()
              )
            }
            // Page dots.
            Row(
              modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 14.dp),
              horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
              repeat(images.size) { i ->
                Box(
                  modifier = Modifier
                    .size(if (i == pager.currentPage) 8.dp else 6.dp)
                    .clip(CircleShape)
                    .background(if (i == pager.currentPage) Color.White else Color.White.copy(alpha = 0.5f))
                )
              }
            }
          } else {
            AsyncImage(
              model = images.first(),
              contentDescription = name,
              contentScale = ContentScale.Crop,
              modifier = Modifier.fillMaxSize()
            )
          }
          // Top + bottom scrims for control/badge legibility.
          Box(
            modifier = Modifier
              .fillMaxSize()
              .background(
                Brush.verticalGradient(
                  colors = listOf(Color.Black.copy(alpha = 0.35f), Color.Transparent, Color.Black.copy(alpha = 0.25f))
                )
              )
          )
          // Badges bottom-left/right.
          if (detail?.isFeatured == true) {
            Box(
              modifier = Modifier.align(Alignment.BottomStart).padding(16.dp)
                .background(Color(0xFFF5A623), RoundedCornerShape(6.dp))
                .padding(horizontal = 8.dp, vertical = 3.dp)
            ) { Text("Featured", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 11.sp) }
          }
          if (detail?.isBookable == true) {
            Box(
              modifier = Modifier.align(Alignment.BottomEnd).padding(16.dp)
                .background(HaraanColors.GameHubGreen, RoundedCornerShape(6.dp))
                .padding(horizontal = 8.dp, vertical = 3.dp)
            ) { Text("Bookable", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 11.sp) }
          }
        }

        // ── 2. Content sheet (laps 24dp over the hero) ─────────────────────────────
        Column(
          modifier = Modifier
            .fillMaxWidth()
            .offset(y = (-24).dp)
            .clip(RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp))
            .background(Color.White)
            .padding(16.dp)
        ) {
          // Title + rating.
          Text(
            text = name,
            color = HaraanColors.TextPrimary,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 22.sp,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis
          )
          // Rating chip right under the title — the trust signal the eye scans for first,
          // instead of making the user hunt for it in the summary card below.
          rating.toFloatOrNull()?.takeIf { it > 0f }?.let { score ->
            Spacer(Modifier.height(8.dp))
            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clip(RoundedCornerShape(50))
                .background(HaraanColors.RatingGold.copy(alpha = 0.14f))
                .padding(horizontal = 10.dp, vertical = 5.dp)
            ) {
              Icon(Icons.Default.Star, null, tint = HaraanColors.RatingGold, modifier = Modifier.size(14.dp))
              Spacer(Modifier.width(4.dp))
              Text("%.1f".format(score), color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 13.sp)
              (detail?.ratingsCount)?.takeIf { it > 0 }?.let { c ->
                Spacer(Modifier.width(4.dp))
                Text("($c)", color = HaraanColors.TextSecondary, fontSize = 12.sp)
              }
            }
          }
          // Operating hours (Playo-style clock row).
          (detail?.hours)?.takeIf { it.isNotBlank() }?.let { hours ->
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
              Icon(Icons.Default.Schedule, null, tint = HaraanColors.TextSecondary, modifier = Modifier.size(14.dp))
              Spacer(Modifier.width(4.dp))
              Text(hours, color = HaraanColors.TextPrimary, fontWeight = FontWeight.Medium, fontSize = 13.sp)
            }
          }
          // Full address (falls back to the short area label), shown under the timing.
          Spacer(Modifier.height(6.dp))
          Row(verticalAlignment = Alignment.Top) {
            Icon(
              Icons.Default.LocationOn, null,
              tint = HaraanColors.TextSecondary,
              modifier = Modifier.size(14.dp).padding(top = 2.dp)
            )
            Spacer(Modifier.width(4.dp))
            Column {
              Text(
                detail?.address?.takeIf { it.isNotBlank() } ?: detail?.location ?: venue.location,
                color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 18.sp
              )
              val dist = detail?.distance ?: venue.distance
              if (dist.isNotBlank()) {
                Text("$dist away", color = HaraanColors.TextMuted, fontSize = 12.sp)
              }
            }
          }
          // Prominent "Show in Map" pill (mirrors Playo).
          Spacer(Modifier.height(10.dp))
          Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
              .clip(RoundedCornerShape(50))
              .border(BorderStroke(1.dp, HaraanColors.BorderLight), RoundedCornerShape(50))
              .clickable {
                val lat = detail?.latitude; val lng = detail?.longitude
                val addr = detail?.address?.takeIf { it.isNotBlank() } ?: detail?.location ?: venue.location
                val q = if (lat != null && lng != null) "$lat,$lng($name)"
                else "$name $addr"
                openMap(ctx, detail?.mapLink, q)
              }
              .padding(horizontal = 14.dp, vertical = 8.dp)
          ) {
            Icon(Icons.Default.Place, null, tint = HaraanColors.GameHubGreen, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text("Show in Map", color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
          }

          if (loading) {
            Box(Modifier.fillMaxWidth().padding(40.dp), contentAlignment = Alignment.Center) {
              CircularProgressIndicator(color = HaraanColors.GameHubGreen)
            }
          }

          detail?.let { d ->
            // ── 2b. Rating summary + Rate Venue CTA (Playo-style white card) ────
            RatingCard(d, onRate = { showRating = true })

            // ── 3. Available Sports — tap a sport to open the full Price Chart ──
            AvailableSportsSection(d, onOpenPriceChart)

            // ── 4. Amenities ────────────────────────────────────────────────────
            if (d.amenities.isNotEmpty()) {
              SectionCard(title = "Amenities") {
                AmenitiesGrid(d.amenities)
              }
            }

            // ── 5. About ────────────────────────────────────────────────────────
            if (d.about.isNotBlank()) {
              SectionCard(title = "About this venue") {
                Text(d.about, color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 20.sp)
              }
            }

            // ── 5b. Rules & policies (admin-authored checklist) ─────────────────
            if (d.rules.isNotEmpty()) {
              SectionCard(title = "Good to know") {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                  d.rules.forEach { rule ->
                    Row(verticalAlignment = Alignment.Top) {
                      Icon(
                        Icons.Default.Check, null,
                        tint = HaraanColors.GameHubGreen,
                        modifier = Modifier.size(16.dp).padding(top = 2.dp)
                      )
                      Spacer(Modifier.width(8.dp))
                      Text(rule, color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 19.sp)
                    }
                  }
                }
              }
            }

            // ── 6. Location ─────────────────────────────────────────────────────
            SectionCard(title = "Location") {
              Column {
                Text(
                  buildString {
                    append(d.address.takeIf { it.isNotBlank() } ?: d.location)
                    if (d.distance.isNotBlank()) append("  ·  ${d.distance} away")
                  },
                  color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 18.sp
                )
                Spacer(Modifier.height(10.dp))
                Text(
                  "Get directions",
                  color = HaraanColors.GameHubGreen,
                  fontWeight = FontWeight.Bold,
                  fontSize = 13.sp,
                  modifier = Modifier.clickable {
                    val q = if (d.latitude != null && d.longitude != null) "${d.latitude},${d.longitude}(${d.name})"
                    else "${d.name} ${d.location}"
                    openMap(ctx, d.mapLink, q)
                  }
                )
              }
            }

            // ── 7. Reviews ──────────────────────────────────────────────────────
            if (d.reviews.isNotEmpty()) {
              SectionCard(title = "Reviews (${d.reviewsCount})") {
                Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                  d.reviews.forEach { ReviewRow(it) }
                }
              }
            }
          }

          Spacer(Modifier.height(96.dp)) // room for the sticky bar
        }
      }

      // ── Fixed overlays: back + share ─────────────────────────────────────────────
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .statusBarsPadding()
          .padding(12.dp),
        horizontalArrangement = Arrangement.SpaceBetween
      ) {
        CircleButton(Icons.Default.ArrowBack, "Back") { onBack() }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
          CircleButton(
            if (isFavorite) Icons.Default.Favorite else Icons.Default.FavoriteBorder,
            if (isFavorite) "Remove from saved" else "Save venue",
            tint = if (isFavorite) HaraanColors.LiveRed else Color.White
          ) {
            isFavorite = FavoritesStore.toggle(ctx, venue.id)
            Toast.makeText(ctx, if (isFavorite) "Saved" else "Removed", Toast.LENGTH_SHORT).show()
          }
          CircleButton(Icons.Default.Share, "Share") {
            runCatching {
              val share = Intent(Intent.ACTION_SEND).apply {
                type = "text/plain"
                putExtra(Intent.EXTRA_TEXT, "Check out $name on Haraan — ${ApiConfig.BASE_URL}")
              }
              ctx.startActivity(Intent.createChooser(share, "Share venue"))
            }
          }
        }
      }

      // ── 8. Sticky book bar ───────────────────────────────────────────────────────
      // A soft upward shadow lets the bar read as floating chrome over the content
      // rather than just another row under a hairline.
      Column(
        modifier = Modifier
          .align(Alignment.BottomCenter)
          .fillMaxWidth()
          .shadow(elevation = 14.dp, clip = false)
          .background(Color.White)
      ) {
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .navigationBarsPadding()
          .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
      ) {
        Row(verticalAlignment = Alignment.Bottom) {
          // Guard the zero-price case so the bar never shows a bare "₹0 /hr".
          if (price > 0) {
            Text("₹$price", color = HaraanColors.TextPrimary, fontWeight = FontWeight.ExtraBold, fontSize = 22.sp)
            Text("/hr", color = HaraanColors.TextSecondary, fontSize = 13.sp, modifier = Modifier.padding(bottom = 3.dp))
          } else {
            Text("Tap to see slots", color = HaraanColors.TextSecondary, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
          }
        }
        Button(
          onClick = { showBooking = true },
          enabled = detail?.isBookable ?: true,
          colors = ButtonDefaults.buttonColors(containerColor = HaraanColors.GameHubGreen),
          shape = RoundedCornerShape(50),
          modifier = Modifier.height(48.dp)
        ) {
          Text("Book Now", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 15.sp, modifier = Modifier.padding(horizontal = 20.dp))
        }
      }
      }
    }
  }

  if (showBooking && detail != null) {
    BookingSheet(venue = detail!!, onDismiss = { showBooking = false })
  }

  if (showRating && detail != null) {
    RatingDialog(
      venue = detail!!,
      onDismiss = { showRating = false },
      onSubmitted = {
        // Refresh the aggregate + review list after a successful rating.
        scope.launch { detail = VenueRepository().getVenueDetail(venue.id) }
      }
    )
  }
}

// A thin divider that separates sections on the single white sheet.
@Composable
private fun SectionDivider() {
  Box(
    modifier = Modifier
      .fillMaxWidth()
      .height(1.dp)
      .background(HaraanColors.BorderLight)
  )
}

// A titled content section on the shared white sheet (flat, divider-separated — no card).
@Composable
private fun SectionCard(title: String, content: @Composable () -> Unit) {
  Spacer(Modifier.height(18.dp))
  SectionDivider()
  Spacer(Modifier.height(18.dp))
  Column(modifier = Modifier.fillMaxWidth()) {
    Text(title, color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 16.sp)
    Spacer(Modifier.height(12.dp))
    content()
  }
}

/**
 * "Available Sports" — a tappable sport card (Playo-style). Tapping opens the full Price Chart
 * page. Matches the reference: heading + "(Tap on sport icon to see Price Chart)" + sport tile.
 */
@Composable
private fun AvailableSportsSection(d: VenueDetailData, onOpenPriceChart: () -> Unit) {
  SectionCard(title = "Available Sports") {
    // A full-width tappable row: sport identity on the left, an explicit "View pricing"
    // affordance with a chevron on the right — no parenthetical instruction needed.
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .clip(RoundedCornerShape(14.dp))
        .border(BorderStroke(1.dp, HaraanColors.BorderLight), RoundedCornerShape(14.dp))
        .clickable { onOpenPriceChart() }
        .padding(horizontal = 16.dp, vertical = 16.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      Box(
        modifier = Modifier
          .size(44.dp)
          .clip(RoundedCornerShape(12.dp))
          .background(HaraanColors.GameHubGreen.copy(alpha = 0.10f)),
        contentAlignment = Alignment.Center
      ) {
        Icon(sportIcon(d.category), d.category, tint = HaraanColors.GameHubDeep, modifier = Modifier.size(24.dp))
      }
      Spacer(Modifier.width(14.dp))
      Column(Modifier.weight(1f)) {
        Text(d.category, color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 15.sp)
        Spacer(Modifier.height(2.dp))
        Text("View pricing", color = HaraanColors.TextSecondary, fontSize = 12.sp)
      }
      Icon(Icons.Default.KeyboardArrowRight, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(22.dp))
    }
  }
}

/** Sport-appropriate icon for the Available Sports tile. */
private fun sportIcon(category: String): androidx.compose.ui.graphics.vector.ImageVector = when {
  category.contains("Cricket", true) -> Icons.Default.SportsCricket
  category.contains("Football", true) || category.contains("Soccer", true) -> Icons.Default.SportsSoccer
  category.contains("Basketball", true) -> Icons.Default.SportsBasketball
  else -> Icons.Default.SportsTennis // badminton / pickleball / racquet sports
}

/**
 * The price-chart body used by the full-screen [PriceChartScreen]. Renders the admin-authored
 * structured chart (variants → day groups → time bands), falling back to slot-derived rows.
 */
@Composable
internal fun PriceChartBody(d: VenueDetailData) {
  // "Controlled by venue" disclaimer.
  val note = d.priceNote.takeIf { it.isNotBlank() }
    ?: "Pricing is subject to change and is controlled by the venue"
  Box(
    modifier = Modifier
      .fillMaxWidth()
      .clip(RoundedCornerShape(10.dp))
      .border(BorderStroke(1.dp, HaraanColors.BorderLight), RoundedCornerShape(10.dp))
      .padding(horizontal = 12.dp, vertical = 10.dp)
  ) {
    Text(note, color = HaraanColors.TextSecondary, fontSize = 12.sp, lineHeight = 16.sp)
  }
  Spacer(Modifier.height(18.dp))

  if (d.priceChart.isNotEmpty()) {
    d.priceChart.forEachIndexed { vi, variant ->
      if (vi > 0) Spacer(Modifier.height(20.dp))
      Text(variant.label, color = HaraanColors.EventsBlue, fontWeight = FontWeight.Bold, fontSize = 15.sp)
      variant.groups.forEach { group ->
        Spacer(Modifier.height(14.dp))
        Text(group.days, color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 15.sp)
        Spacer(Modifier.height(10.dp))
        group.rows.forEachIndexed { ri, band ->
          if (ri > 0) {
            Spacer(Modifier.height(12.dp)); SectionDivider(); Spacer(Modifier.height(12.dp))
          }
          Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
          ) {
            Text(band.time, color = HaraanColors.TextPrimary, fontSize = 14.sp)
            Text("INR ${band.price} / hour", color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
          }
        }
      }
    }
  } else {
    // Fallback: slot-derived rows or a flat rate.
    val rows = d.slots.map { it.time to (it.price.takeIf { p -> p > 0 } ?: d.price) }.distinctBy { it.first }
    Text(d.category, color = HaraanColors.EventsBlue, fontWeight = FontWeight.Bold, fontSize = 15.sp)
    Spacer(Modifier.height(12.dp))
    if (rows.isEmpty() || rows.map { it.second }.toSet().size <= 1) {
      Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        Text("All slots", color = HaraanColors.TextPrimary, fontSize = 14.sp)
        Text("INR ${d.price} / hour", color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
      }
    } else {
      rows.forEachIndexed { i, (time, price) ->
        if (i > 0) { Spacer(Modifier.height(12.dp)); SectionDivider(); Spacer(Modifier.height(12.dp)) }
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
          Text(time, color = HaraanColors.TextPrimary, fontSize = 14.sp)
          Text("INR $price / hour", color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
        }
      }
    }
  }
}

/**
 * Rating summary + "Rate Venue" CTA on a white card (Playo's stats row, minus the games
 * counters). Left = score, stars, count; right = an outlined RATE VENUE pill.
 */
@Composable
private fun RatingCard(d: VenueDetailData, onRate: () -> Unit) {
  val score = d.rating.toFloatOrNull() ?: 0f
  val hasRatings = d.ratingsCount > 0

  Spacer(Modifier.height(18.dp))
  SectionDivider()
  Spacer(Modifier.height(18.dp))
  Row(
    modifier = Modifier.fillMaxWidth(),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.SpaceBetween
  ) {
    Column(Modifier.weight(1f)) {
      if (hasRatings) {
        Row(verticalAlignment = Alignment.CenterVertically) {
          Text(d.rating, color = HaraanColors.TextPrimary, fontWeight = FontWeight.ExtraBold, fontSize = 24.sp)
          Spacer(Modifier.width(8.dp))
          Row {
            repeat(5) { i ->
              Icon(
                if (i < score.toInt()) Icons.Default.Star else Icons.Default.StarBorder,
                null,
                tint = HaraanColors.RatingGold,
                modifier = Modifier.size(16.dp)
              )
            }
          }
        }
        Spacer(Modifier.height(4.dp))
        Text(
          "${d.ratingsCount} ratings · ${d.reviewsCount} reviews",
          color = HaraanColors.TextSecondary, fontSize = 12.sp
        )
      } else {
        Text("No ratings yet", color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 15.sp)
        Spacer(Modifier.height(2.dp))
        Text("Be the first to rate this venue", color = HaraanColors.TextSecondary, fontSize = 12.sp)
      }
    }
    Spacer(Modifier.width(12.dp))
    Row(
      verticalAlignment = Alignment.CenterVertically,
      modifier = Modifier
        .clip(RoundedCornerShape(50))
        .border(BorderStroke(1.dp, HaraanColors.GameHubGreen), RoundedCornerShape(50))
        .clickable { onRate() }
        .padding(horizontal = 16.dp, vertical = 10.dp)
    ) {
      Icon(Icons.Default.Star, null, tint = HaraanColors.GameHubGreen, modifier = Modifier.size(16.dp))
      Spacer(Modifier.width(6.dp))
      Text("RATE VENUE", color = HaraanColors.GameHubDeep, fontWeight = FontWeight.Bold, fontSize = 12.sp)
    }
  }
}

/** Bottom-sheet rating dialog — pick 1–5 stars + optional note, POST to the venue reviews API. */
@Composable
private fun RatingDialog(venue: VenueDetailData, onDismiss: () -> Unit, onSubmitted: () -> Unit) {
  val ctx = LocalContext.current
  val scope = rememberCoroutineScope()
  var stars by remember { mutableStateOf(0) }
  var note by remember { mutableStateOf("") }
  var submitting by remember { mutableStateOf(false) }
  var error by remember { mutableStateOf<String?>(null) }

  Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.BottomCenter) {
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .clip(RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp))
          .background(Color.White)
          .navigationBarsPadding()
          .padding(20.dp)
      ) {
        Text("Rate this venue", color = HaraanColors.TextPrimary, fontWeight = FontWeight.ExtraBold, fontSize = 18.sp)
        Spacer(Modifier.height(4.dp))
        Text(venue.name, color = HaraanColors.TextSecondary, fontSize = 13.sp)
        Spacer(Modifier.height(18.dp))

        // Star picker.
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
          (1..5).forEach { i ->
            Icon(
              if (i <= stars) Icons.Default.Star else Icons.Default.StarBorder,
              "$i star",
              tint = HaraanColors.RatingGold,
              modifier = Modifier
                .size(38.dp)
                .clip(CircleShape)
                .clickable { stars = i; error = null }
            )
          }
        }
        Spacer(Modifier.height(18.dp))
        OutlinedTextField(
          value = note,
          onValueChange = { note = it },
          placeholder = { Text("Add a note (optional)") },
          modifier = Modifier.fillMaxWidth(),
          minLines = 2,
          maxLines = 4
        )
        error?.let {
          Spacer(Modifier.height(8.dp))
          Text(it, color = HaraanColors.LiveRed, fontSize = 12.sp)
        }
        Spacer(Modifier.height(18.dp))
        Button(
          onClick = {
            if (stars == 0) { error = "Tap a star to rate."; return@Button }
            submitting = true
            error = null
            scope.launch {
              val token = TokenStore.getToken(ctx)
              if (token.isNullOrBlank()) {
                error = "Please log in to rate."
                submitting = false
                return@launch
              }
              when (val r = VenueRepository().submitReview(token, venue.id, stars, note)) {
                is ReviewResult.Success -> {
                  Toast.makeText(ctx, "Thanks for rating!", Toast.LENGTH_SHORT).show()
                  onSubmitted()
                  onDismiss()
                }
                is ReviewResult.Error -> { error = r.message; submitting = false }
              }
            }
          },
          enabled = !submitting,
          colors = ButtonDefaults.buttonColors(containerColor = HaraanColors.GameHubGreen),
          shape = RoundedCornerShape(50),
          modifier = Modifier.fillMaxWidth().height(52.dp)
        ) {
          if (submitting) {
            CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
          } else {
            Text("Submit rating", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 15.sp)
          }
        }
      }
    }
  }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun AmenitiesGrid(amenities: List<String>) {
  // Two-per-row grid; each amenity carries its own icon so the list reads as a
  // real feature set, not a column of identical checkmarks.
  FlowRow(
    horizontalArrangement = Arrangement.spacedBy(12.dp),
    verticalArrangement = Arrangement.spacedBy(16.dp),
    maxItemsInEachRow = 2
  ) {
    amenities.forEach { a ->
      Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.widthIn(min = 140.dp).weight(1f)
      ) {
        Box(
          modifier = Modifier
            .size(34.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(HaraanColors.GameHubGreen.copy(alpha = 0.10f)),
          contentAlignment = Alignment.Center
        ) {
          Icon(amenityIcon(a), null, tint = HaraanColors.GameHubDeep, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(10.dp))
        Text(
          a, color = HaraanColors.TextPrimary, fontSize = 13.sp,
          maxLines = 2, overflow = TextOverflow.Ellipsis
        )
      }
    }
  }
}

/** Map a free-text amenity label to the closest Material glyph; falls back to a check. */
private fun amenityIcon(amenity: String): androidx.compose.ui.graphics.vector.ImageVector {
  val a = amenity.lowercase()
  return when {
    "wifi" in a || "wi-fi" in a || "internet" in a -> Icons.Default.Wifi
    "park" in a -> Icons.Default.DirectionsCar
    "wash" in a || "toilet" in a || "restroom" in a || "rest room" in a -> Icons.Default.Wc
    "shower" in a -> Icons.Default.Shower
    "chang" in a || "locker" in a -> Icons.Default.Checkroom
    "cafe" in a || "coffee" in a || "canteen" in a -> Icons.Default.LocalCafe
    "food" in a || "restaurant" in a || "kitchen" in a -> Icons.Default.Restaurant
    "water" in a || "drink" in a -> Icons.Default.LocalDrink
    "light" in a || "flood" in a -> Icons.Default.Lightbulb
    "ac" == a || "a/c" in a || "air" in a || "cool" in a -> Icons.Default.Air
    "cctv" in a || "secur" in a || "guard" in a || "safe" in a -> Icons.Default.Security
    "seat" in a || "seating" in a || "gallery" in a -> Icons.Default.EventSeat
    "equip" in a || "gear" in a || "gym" in a || "kit" in a -> Icons.Default.FitnessCenter
    else -> Icons.Default.Check
  }
}

@Composable
private fun ReviewRow(review: VenueReviewItem) {
  Column {
    Row(verticalAlignment = Alignment.CenterVertically) {
      Box(
        modifier = Modifier.size(32.dp).clip(CircleShape).background(HaraanColors.GameHubGreen.copy(alpha = 0.15f)),
        contentAlignment = Alignment.Center
      ) {
        Text(review.name.take(1).uppercase(), color = HaraanColors.GameHubDeep, fontWeight = FontWeight.Bold, fontSize = 13.sp)
      }
      Spacer(Modifier.width(10.dp))
      Column(Modifier.weight(1f)) {
        Text(review.name, color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
        Text(review.ago, color = HaraanColors.TextSecondary, fontSize = 11.sp)
      }
      Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Default.Star, null, tint = HaraanColors.RatingGold, modifier = Modifier.size(13.dp))
        Spacer(Modifier.width(3.dp))
        Text(review.rating.toString(), color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 12.sp)
      }
    }
    if (review.text.isNotBlank()) {
      Spacer(Modifier.height(6.dp))
      Text(review.text, color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 19.sp)
    }
  }
}

@Composable
private fun CircleButton(
  icon: androidx.compose.ui.graphics.vector.ImageVector,
  cd: String,
  tint: Color = Color.White,
  onClick: () -> Unit
) {
  Box(
    modifier = Modifier
      .size(40.dp)
      .clip(CircleShape)
      .background(Color.Black.copy(alpha = 0.35f))
      .clickable { onClick() },
    contentAlignment = Alignment.Center
  ) {
    Icon(icon, cd, tint = tint, modifier = Modifier.size(20.dp))
  }
}

// ── 9. Booking funnel — date → slot → confirm → POST /api/bookings/venue ──────────────
@OptIn(ExperimentalLayoutApi::class)
@Composable
internal fun BookingSheet(venue: VenueDetailData, onDismiss: () -> Unit) {
  val ctx = LocalContext.current
  val scope = rememberCoroutineScope()
  val days = remember(venue) { venue.slots.map { it.day }.distinct().ifEmpty { listOf("Today") } }
  var selectedDay by remember { mutableStateOf(days.first()) }
  var selectedSlot by remember { mutableStateOf<VenueSlotItem?>(null) }
  var submitting by remember { mutableStateOf(false) }
  var result by remember { mutableStateOf<String?>(null) }
  var success by remember { mutableStateOf(false) }

  val daySlots = venue.slots.filter { it.day == selectedDay }

  Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.BottomCenter) {
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .clip(RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp))
          .background(Color.White)
          .navigationBarsPadding()
          .padding(20.dp)
      ) {
        Text(
          if (success) "Booking requested" else "Select a slot",
          color = HaraanColors.TextPrimary, fontWeight = FontWeight.ExtraBold, fontSize = 18.sp
        )
        Spacer(Modifier.height(4.dp))
        Text(venue.name, color = HaraanColors.TextSecondary, fontSize = 13.sp)
        Spacer(Modifier.height(16.dp))

        if (success) {
          Icon(Icons.Default.Check, null, tint = HaraanColors.GameHubGreen, modifier = Modifier.size(40.dp))
          Spacer(Modifier.height(8.dp))
          Text(result ?: "Your slot is reserved.", color = HaraanColors.TextSecondary, fontSize = 14.sp)
          Spacer(Modifier.height(20.dp))
          Button(
            onClick = onDismiss,
            colors = ButtonDefaults.buttonColors(containerColor = HaraanColors.GameHubGreen),
            shape = RoundedCornerShape(50),
            modifier = Modifier.fillMaxWidth().height(50.dp)
          ) { Text("Done", color = Color.White, fontWeight = FontWeight.Bold) }
        } else {
          // Date chips.
          Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            days.forEach { day ->
              val sel = day == selectedDay
              Box(
                modifier = Modifier
                  .clip(RoundedCornerShape(50))
                  .background(if (sel) HaraanColors.GameHubDeep else Color(0xFFF1F5F9))
                  .clickable { selectedDay = day; selectedSlot = null }
                  .padding(horizontal = 16.dp, vertical = 8.dp)
              ) {
                Text(day, color = if (sel) Color.White else HaraanColors.TextSecondary, fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
              }
            }
          }
          Spacer(Modifier.height(16.dp))
          // Slot grid.
          if (daySlots.isEmpty()) {
            Text("No slots for this day.", color = HaraanColors.TextSecondary, fontSize = 13.sp)
          } else {
            FlowRow(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
              daySlots.forEach { slot -> SlotChip(slot, selectedSlot?.id == slot.id) { if (slot.available) selectedSlot = slot } }
            }
          }
          Spacer(Modifier.height(20.dp))
          result?.let {
            Text(it, color = HaraanColors.LiveRed, fontSize = 12.sp)
            Spacer(Modifier.height(8.dp))
          }
          Button(
            onClick = {
              val slot = selectedSlot ?: return@Button
              submitting = true
              result = null
              scope.launch {
                val token = TokenStore.getToken(ctx)
                if (token.isNullOrBlank()) {
                  result = "Please log in to book."
                  submitting = false
                  return@launch
                }
                val date = when (slot.day.lowercase()) {
                  "today" -> LocalDate.now()
                  "tomorrow" -> LocalDate.now().plusDays(1)
                  else -> LocalDate.now()
                }.toString()
                when (val r = BookingRepository().bookVenueSlot(token, venue.id.toIntOrNull() ?: 0, slot.id, date)) {
                  is BookingResult.Success -> {
                    success = true
                    result = r.message
                    Toast.makeText(ctx, "Slot booked", Toast.LENGTH_SHORT).show()
                  }
                  is BookingResult.Error -> result = r.message
                }
                submitting = false
              }
            },
            enabled = selectedSlot != null && !submitting,
            colors = ButtonDefaults.buttonColors(containerColor = HaraanColors.GameHubGreen),
            shape = RoundedCornerShape(50),
            modifier = Modifier.fillMaxWidth().height(52.dp)
          ) {
            if (submitting) {
              CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
            } else {
              Text(
                selectedSlot?.let { "Confirm • ${it.time} · ₹${it.price.takeIf { p -> p > 0 } ?: venue.price}" } ?: "Select a slot",
                color = Color.White, fontWeight = FontWeight.Bold, fontSize = 15.sp
              )
            }
          }
        }
      }
    }
  }
}

@Composable
private fun SlotChip(slot: VenueSlotItem, selected: Boolean, onClick: () -> Unit) {
  val bg = when {
    !slot.available -> Color(0xFFF1F5F9)
    selected -> HaraanColors.GameHubGreen
    else -> Color.White
  }
  val border = when {
    !slot.available -> BorderStroke(1.dp, HaraanColors.BorderLight)
    selected -> BorderStroke(1.dp, HaraanColors.GameHubGreen)
    else -> BorderStroke(1.dp, HaraanColors.BorderLight)
  }
  Column(
    modifier = Modifier
      .clip(RoundedCornerShape(12.dp))
      .background(bg)
      .then(Modifier.border(border, RoundedCornerShape(12.dp)))
      .clickable(enabled = slot.available) { onClick() }
      .padding(horizontal = 14.dp, vertical = 10.dp),
    horizontalAlignment = Alignment.CenterHorizontally
  ) {
    Text(
      slot.time,
      color = when {
        !slot.available -> HaraanColors.TextMuted
        selected -> Color.White
        else -> HaraanColors.TextPrimary
      },
      fontWeight = FontWeight.Bold,
      fontSize = 13.sp
    )
    if (slot.price > 0) {
      Text(
        "₹${slot.price}",
        color = when { !slot.available -> HaraanColors.TextMuted; selected -> Color.White; else -> HaraanColors.TextPrimary },
        fontSize = 11.sp, fontWeight = FontWeight.SemiBold,
      )
    }
    if (!slot.available) {
      Text("Booked", color = HaraanColors.TextMuted, fontSize = 10.sp)
    } else if (slot.fillingFast) {
      Text("Filling fast", color = if (selected) Color.White else HaraanColors.LiveRed, fontSize = 10.sp, fontWeight = FontWeight.SemiBold)
    } else if (slot.capacity > 1) {
      Text("${slot.capacity} courts", color = if (selected) Color.White.copy(alpha = 0.85f) else HaraanColors.TextMuted, fontSize = 10.sp)
    }
  }
}
