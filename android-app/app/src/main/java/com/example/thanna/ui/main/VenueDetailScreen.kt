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
import androidx.compose.material.icons.filled.CalendarMonth
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
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Remove
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
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SelectableDates
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
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
import com.example.thanna.data.LocationRepository
import com.example.thanna.data.LocationState
import com.example.thanna.data.TokenStore
import com.example.thanna.data.ReviewResult
import com.example.thanna.data.VenueCourt
import com.example.thanna.data.VenueDetailData
import com.example.thanna.data.VenueRepository
import com.example.thanna.data.VenueReviewItem
import com.example.thanna.data.VenueSlotItem
import com.example.thanna.ui.theme.HaraanColors
import kotlinx.coroutines.launch
import java.time.Instant
import java.time.LocalDate
import java.time.LocalTime
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter
import java.time.format.TextStyle
import java.util.Locale

/** Great-circle distance in km between two lat/lng points (haversine). */
private fun haversineKm(lat1: Double, lng1: Double, lat2: Double, lng2: Double): Double {
  val r = 6371.0
  val dLat = Math.toRadians(lat2 - lat1)
  val dLng = Math.toRadians(lng2 - lng1)
  val a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(Math.toRadians(lat1)) * Math.cos(Math.toRadians(lat2)) *
    Math.sin(dLng / 2) * Math.sin(dLng / 2)
  return r * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

/** "850 m" under a km, else "3.1 km" — the label the distance rows render. */
private fun formatDistanceKm(km: Double): String =
  if (km < 1.0) "${(km * 1000).toInt()} m" else "%.1f km".format(km)

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

  // Live distance: measured from the user's last-known location to the venue's
  // coordinates (no GPS prompt — cached fix only). Falls back to the backend's
  // static string when either side has no coordinates.
  val userLoc = remember { LocationRepository(ctx).cached() as? LocationState.Resolved }
  val liveDistance: String? = run {
    val ulat = userLoc?.latitude; val ulng = userLoc?.longitude
    val vlat = detail?.latitude; val vlng = detail?.longitude
    if (ulat != null && ulng != null && vlat != null && vlng != null)
      formatDistanceKm(haversineKm(ulat, ulng, vlat, vlng))
    else null
  }

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
          // Title on the left, rating pinned top-right on the same line — the trust
          // signal sits where the eye lands, without a background pill.
          Row(verticalAlignment = Alignment.Top) {
            Text(
              text = name,
              color = HaraanColors.TextPrimary,
              fontWeight = FontWeight.ExtraBold,
              fontSize = 22.sp,
              maxLines = 2,
              overflow = TextOverflow.Ellipsis,
              modifier = Modifier.weight(1f)
            )
            rating.toFloatOrNull()?.takeIf { it > 0f }?.let { score ->
              Spacer(Modifier.width(12.dp))
              Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(top = 4.dp)) {
                Icon(Icons.Default.Star, null, tint = HaraanColors.RatingGold, modifier = Modifier.size(15.dp))
                Spacer(Modifier.width(4.dp))
                Text("%.1f".format(score), color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                (detail?.ratingsCount)?.takeIf { it > 0 }?.let { c ->
                  Spacer(Modifier.width(3.dp))
                  Text("($c)", color = HaraanColors.TextSecondary, fontSize = 12.sp)
                }
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
          // Full address (falls back to the short area label), shown under the timing,
          // with the "Show in Map" pill pulled up beside it.
          Spacer(Modifier.height(6.dp))
          Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
              Icons.Default.LocationOn, null,
              tint = HaraanColors.TextSecondary,
              modifier = Modifier.size(14.dp).align(Alignment.Top).padding(top = 2.dp)
            )
            Spacer(Modifier.width(4.dp))
            Column(Modifier.weight(1f)) {
              Text(
                detail?.address?.takeIf { it.isNotBlank() } ?: detail?.location ?: venue.location,
                color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 18.sp
              )
              val dist = liveDistance ?: detail?.distance ?: venue.distance
              if (dist.isNotBlank()) {
                Text("$dist away", color = HaraanColors.TextMuted, fontSize = 12.sp)
              }
            }
            Spacer(Modifier.width(10.dp))
            // Prominent "Show in Map" pill (mirrors Playo), beside the address.
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
                .padding(horizontal = 12.dp, vertical = 8.dp)
            ) {
              Icon(Icons.Default.Place, null, tint = HaraanColors.GameHubGreen, modifier = Modifier.size(16.dp))
              Spacer(Modifier.width(6.dp))
              Text("Show in Map", color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 13.sp, maxLines = 1, softWrap = false)
            }
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
                    val dist = liveDistance ?: d.distance.takeIf { it.isNotBlank() }
                    if (!dist.isNullOrBlank()) append("  ·  $dist away")
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
      Icon(sportIcon(d.category), d.category, tint = HaraanColors.GameHubDeep, modifier = Modifier.size(28.dp))
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
 * The pricing body used by the full-screen [PriceChartScreen]. Lists each court and its hourly
 * rate, grouped by sport (per-court pricing is the single source of truth). Falls back to
 * slot-derived rows or a flat rate for venues that don't model courts yet.
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

  if (d.courts.isNotEmpty()) {
    // Per-court pricing, grouped by sport. A court appears under every sport it hosts, so a
    // shared court shows under both — matching how it books. Price is the court's own rate.
    val sports = d.sports.ifEmpty { listOf(d.category) }.filter { it.isNotBlank() }
    var shown = 0
    sports.forEach { sport ->
      val courts = d.courts.filter { it.sports.isEmpty() || it.sports.any { s -> s.equals(sport, ignoreCase = true) } }
      if (courts.isEmpty()) return@forEach
      if (shown > 0) Spacer(Modifier.height(20.dp))
      shown++
      Text(sport, color = HaraanColors.EventsBlue, fontWeight = FontWeight.Bold, fontSize = 15.sp)
      Spacer(Modifier.height(10.dp))
      courts.forEachIndexed { ci, court ->
        if (ci > 0) {
          Spacer(Modifier.height(12.dp)); SectionDivider(); Spacer(Modifier.height(12.dp))
        }
        Row(
          modifier = Modifier.fillMaxWidth(),
          horizontalArrangement = Arrangement.SpaceBetween,
          verticalAlignment = Alignment.CenterVertically
        ) {
          Text(court.name, color = HaraanColors.TextPrimary, fontSize = 14.sp)
          Text("INR ${court.price.takeIf { it > 0 } ?: d.price} / hour", color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
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

// ── 9. Booking form — sport → date → start time → duration → court → book ─────────────
// A single-step form (no cart): the chosen start slot is booked directly via
// POST /api/bookings/venue. Duration drives the price estimate; court is required
// only when the venue lists courts.
@Composable
internal fun BookingSheet(venue: VenueDetailData, onDismiss: () -> Unit) {
  val ctx = LocalContext.current
  val scope = rememberCoroutineScope()

  val sports = remember(venue) { venue.sports.ifEmpty { listOf(venue.category) }.filter { it.isNotBlank() } }

  var selectedSport by remember { mutableStateOf(sports.firstOrNull() ?: venue.category) }
  var selectedDate by remember { mutableStateOf(LocalDate.now()) }
  var selectedSlot by remember { mutableStateOf<VenueSlotItem?>(null) }
  var duration by remember { mutableStateOf(1) }
  var selectedCourt by remember { mutableStateOf<VenueCourt?>(null) }
  var submitting by remember { mutableStateOf(false) }
  var result by remember { mutableStateOf<String?>(null) }
  var success by remember { mutableStateOf(false) }

  // Only the courts that can host the chosen sport. A court with no sports listed hosts any.
  // One physical court shared by two sports appears under both — booking it blocks the other.
  val courtsForSport = remember(venue, selectedSport) {
    venue.courts.filter { it.sports.isEmpty() || it.sports.any { s -> s.equals(selectedSport, ignoreCase = true) } }
  }
  val courtNeeded = courtsForSport.isNotEmpty()

  // The slot rows are keyed by a free-text "day" label (Today / Tomorrow / weekday), so we
  // map the calendar date back to that label to find its availability.
  val selectedDayLabel = dayLabelFor(selectedDate)
  // Bookable start times for the chosen date.
  val startTimes = remember(venue, selectedDayLabel) {
    venue.slots.filter { it.day.equals(selectedDayLabel, ignoreCase = true) && it.available }
  }
  // Per-court price wins over the slot/venue price when a court is chosen.
  val perHour = selectedCourt?.price?.takeIf { it > 0 }
    ?: selectedSlot?.price?.takeIf { it > 0 }
    ?: venue.price
  val total = perHour * duration
  val canBook = selectedSlot != null && (!courtNeeded || selectedCourt != null) && !submitting
  // The chosen window as "7:00 PM – 8:00 PM" (null when the time string can't be parsed).
  val endLabel = selectedSlot?.let { slotWindowLabel(it.time, duration) }
  // What the primary button should say — a disabled button always names the next step
  // instead of leaving the user guessing why it's greyed out.
  val bookLabel = when {
    selectedSlot == null -> "Select a start time"
    courtNeeded && selectedCourt == null -> "Select a court"
    else -> "Book now  ·  ₹$total"
  }

  Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.BottomCenter) {
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .clip(RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp))
          .background(Color.White)
          .navigationBarsPadding()
          .verticalScroll(rememberScrollState())
          .padding(20.dp)
      ) {
        Text(
          if (success) "Booking requested" else "Book a slot",
          color = HaraanColors.TextPrimary, fontWeight = FontWeight.ExtraBold, fontSize = 18.sp
        )
        Spacer(Modifier.height(4.dp))
        Text(venue.name, color = HaraanColors.TextSecondary, fontSize = 13.sp)
        Spacer(Modifier.height(18.dp))

        if (!success) {
          Text(
            "Pick a time and how long you'll play — we'll reserve it for you.",
            color = HaraanColors.TextMuted, fontSize = 12.sp, lineHeight = 16.sp
          )
          Spacer(Modifier.height(14.dp))
        }

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
          // Sport — each option carries its sport icon so it's recognisable at a glance.
          // Changing sport clears the court, since courts are filtered to the chosen sport.
          FormField("Sport") {
            FormDropdown(
              value = selectedSport, placeholder = "Select sport", options = sports,
              leadingIcon = { sportIcon(it) },
            ) { picked -> selectedSport = picked; selectedCourt = null }
          }
          // Date — opens a calendar (no past dates).
          FormField("Date") {
            DateField(selected = selectedDate) { selectedDate = it; selectedSlot = null }
          }
          // Start time
          FormField("Start Time") {
            FormDropdown(
              value = selectedSlot?.time,
              placeholder = if (startTimes.isEmpty()) "No slots for this day" else "Select time",
              options = startTimes.map { it.time },
              enabled = startTimes.isNotEmpty(),
            ) { picked -> selectedSlot = startTimes.firstOrNull { it.time == picked } }
          }
          // Duration
          FormField("Duration") {
            DurationStepper(duration) { duration = it }
            endLabel?.let {
              Spacer(Modifier.height(8.dp))
              Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.Schedule, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(13.dp))
                Spacer(Modifier.width(5.dp))
                Text(it, color = HaraanColors.TextSecondary, fontSize = 12.sp, fontWeight = FontWeight.Medium)
              }
            }
          }
          // Court — only when the chosen sport has bookable courts. Each label shows its own
          // price when it differs from the venue price, so the choice is never a surprise.
          if (courtNeeded) {
            FormField("Court") {
              FormDropdown(
                value = selectedCourt?.name,
                placeholder = "Select Court",
                options = courtsForSport.map { it.name },
              ) { picked -> selectedCourt = courtsForSport.firstOrNull { it.name == picked } }
            }
          }

          Spacer(Modifier.height(16.dp))
          SectionDivider()
          Spacer(Modifier.height(12.dp))
          // Price summary — show the math (rate × hours) so the total is never a surprise.
          Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
          ) {
            Column {
              Text("Total", color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
              Text(
                "₹$perHour × $duration hr",
                color = HaraanColors.TextMuted, fontSize = 12.sp
              )
            }
            Text("₹$total", color = HaraanColors.TextPrimary, fontWeight = FontWeight.ExtraBold, fontSize = 20.sp)
          }

          Spacer(Modifier.height(14.dp))
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
                val date = selectedDate.toString()
                when (val r = BookingRepository().bookVenueSlot(
                  token, venue.id.toIntOrNull() ?: 0, slot.id, date,
                  courtId = selectedCourt?.id, duration = duration,
                )) {
                  is BookingResult.Success -> {
                    success = true
                    val court = selectedCourt?.let { " · ${it.name}" } ?: ""
                    result = "${slot.time} · $duration hr$court — ${r.message}"
                    Toast.makeText(ctx, "Slot booked", Toast.LENGTH_SHORT).show()
                  }
                  is BookingResult.Error -> result = r.message
                }
                submitting = false
              }
            },
            enabled = canBook,
            colors = ButtonDefaults.buttonColors(
              containerColor = HaraanColors.GameHubGreen,
              disabledContainerColor = Color(0xFFBFC8D2),
            ),
            shape = RoundedCornerShape(50),
            modifier = Modifier.fillMaxWidth().height(52.dp)
          ) {
            if (submitting) {
              CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
            } else {
              Text(bookLabel, color = Color.White, fontWeight = FontWeight.Bold, fontSize = 15.sp)
            }
          }
        }
      }
    }
  }
}

/** A labelled form row: caption above, the control below. */
@Composable
private fun FormField(label: String, content: @Composable () -> Unit) {
  Spacer(Modifier.height(14.dp))
  Text(label, color = HaraanColors.TextSecondary, fontSize = 13.sp, fontWeight = FontWeight.Medium)
  Spacer(Modifier.height(6.dp))
  content()
}

/**
 * Bordered dropdown that shows [value] (or [placeholder]) and opens a menu of [options].
 * When [leadingIcon] is supplied, the icon for the current value (and for each option) is
 * shown before its label — used to make the Sport list recognisable at a glance.
 */
@Composable
private fun FormDropdown(
  value: String?,
  placeholder: String,
  options: List<String>,
  enabled: Boolean = true,
  leadingIcon: ((String) -> androidx.compose.ui.graphics.vector.ImageVector)? = null,
  onSelect: (String) -> Unit,
) {
  var open by remember { mutableStateOf(false) }
  Box {
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .clip(RoundedCornerShape(12.dp))
        .border(BorderStroke(1.dp, HaraanColors.BorderLight), RoundedCornerShape(12.dp))
        .clickable(enabled = enabled && options.isNotEmpty()) { open = true }
        .padding(horizontal = 14.dp, vertical = 14.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      if (leadingIcon != null && value != null) {
        Icon(leadingIcon(value), null, tint = HaraanColors.GameHubDeep, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(10.dp))
      }
      Text(
        value ?: placeholder,
        color = if (value != null) HaraanColors.TextPrimary else HaraanColors.TextMuted,
        fontSize = 14.sp,
        fontWeight = if (value != null) FontWeight.SemiBold else FontWeight.Normal,
        maxLines = 1, overflow = TextOverflow.Ellipsis,
        modifier = Modifier.weight(1f),
      )
      Icon(Icons.Default.KeyboardArrowDown, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(20.dp))
    }
    DropdownMenu(expanded = open, onDismissRequest = { open = false }) {
      options.forEach { opt ->
        DropdownMenuItem(
          text = { Text(opt, fontSize = 14.sp, color = HaraanColors.TextPrimary) },
          leadingIcon = leadingIcon?.let { icon ->
            { Icon(icon(opt), null, tint = HaraanColors.GameHubDeep, modifier = Modifier.size(20.dp)) }
          },
          onClick = { onSelect(opt); open = false },
        )
      }
    }
  }
}

/** Human label for a date relative to today, matched against the slot rows' free-text "day". */
private fun dayLabelFor(date: LocalDate): String = when (date) {
  LocalDate.now() -> "Today"
  LocalDate.now().plusDays(1) -> "Tomorrow"
  else -> date.dayOfWeek.getDisplayName(TextStyle.FULL, Locale.ENGLISH)
}

/**
 * A bordered field that reads "Today · 14 Jul" and opens a Material date picker on tap.
 * Past dates are disabled — you can only book today onward.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DateField(selected: LocalDate, onPick: (LocalDate) -> Unit) {
  var open by remember { mutableStateOf(false) }
  val label = "${dayLabelFor(selected)}  ·  ${selected.format(DateTimeFormatter.ofPattern("d MMM", Locale.ENGLISH))}"

  Row(
    modifier = Modifier
      .fillMaxWidth()
      .clip(RoundedCornerShape(12.dp))
      .border(BorderStroke(1.dp, HaraanColors.BorderLight), RoundedCornerShape(12.dp))
      .clickable { open = true }
      .padding(horizontal = 14.dp, vertical = 14.dp),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    Icon(Icons.Default.CalendarMonth, null, tint = HaraanColors.GameHubDeep, modifier = Modifier.size(20.dp))
    Spacer(Modifier.width(10.dp))
    Text(
      label, color = HaraanColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold,
      maxLines = 1, modifier = Modifier.weight(1f),
    )
    Icon(Icons.Default.KeyboardArrowDown, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(20.dp))
  }

  if (open) {
    val todayUtc = LocalDate.now().atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli()
    val state = rememberDatePickerState(
      initialSelectedDateMillis = selected.atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli(),
      selectableDates = object : SelectableDates {
        override fun isSelectableDate(utcTimeMillis: Long): Boolean = utcTimeMillis >= todayUtc
      },
    )
    DatePickerDialog(
      onDismissRequest = { open = false },
      confirmButton = {
        TextButton(onClick = {
          state.selectedDateMillis?.let { onPick(Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate()) }
          open = false
        }) { Text("OK", color = HaraanColors.GameHubDeep, fontWeight = FontWeight.Bold) }
      },
      dismissButton = {
        TextButton(onClick = { open = false }) { Text("Cancel", color = HaraanColors.TextSecondary) }
      },
    ) {
      DatePicker(state = state, showModeToggle = false)
    }
  }
}

/** −  N Hr  +  stepper, 1..12 hours. */
@Composable
private fun DurationStepper(value: Int, onChange: (Int) -> Unit) {
  Row(
    modifier = Modifier.fillMaxWidth(),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.spacedBy(18.dp),
  ) {
    StepperButton(Icons.Default.Remove, enabled = value > 1) { onChange((value - 1).coerceAtLeast(1)) }
    Text(
      "$value Hr", color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 15.sp,
      modifier = Modifier.weight(1f), textAlign = androidx.compose.ui.text.style.TextAlign.Center,
    )
    StepperButton(Icons.Default.Add, enabled = value < 12) { onChange((value + 1).coerceAtMost(12)) }
  }
}

@Composable
private fun StepperButton(icon: androidx.compose.ui.graphics.vector.ImageVector, enabled: Boolean, onClick: () -> Unit) {
  Box(
    modifier = Modifier
      .size(38.dp)
      .clip(CircleShape)
      .background(if (enabled) HaraanColors.GameHubGreen else Color(0xFFE2E8F0))
      .clickable(enabled = enabled) { onClick() },
    contentAlignment = Alignment.Center,
  ) {
    Icon(icon, null, tint = Color.White, modifier = Modifier.size(20.dp))
  }
}

/**
 * "7:00 PM – 8:00 PM" for a start-time string plus a duration in hours. Tries the common
 * clock formats; returns null (caller hides the line) when the string can't be parsed.
 */
private fun slotWindowLabel(start: String, hours: Int): String? {
  val out = DateTimeFormatter.ofPattern("h:mm a", Locale.ENGLISH)
  val patterns = listOf("h:mm a", "hh:mm a", "H:mm", "HH:mm", "h a", "ha")
  val cleaned = start.trim().uppercase(Locale.ENGLISH)
  for (p in patterns) {
    try {
      val t = LocalTime.parse(cleaned, DateTimeFormatter.ofPattern(p, Locale.ENGLISH))
      return "${t.format(out)} – ${t.plusHours(hours.toLong()).format(out)}"
    } catch (_: Exception) { /* try the next pattern */ }
  }
  return null
}
