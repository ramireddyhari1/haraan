package com.haraan.app.ui.main

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
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.horizontalScroll
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
import com.haraan.app.ui.Feel
import com.haraan.app.ui.components.AutoRefresh
import com.haraan.app.ui.pressable
import com.haraan.app.ui.util.openMap
import com.haraan.app.VenueDetail
import com.haraan.app.data.ApiConfig
import com.haraan.app.data.BookingRepository
import com.haraan.app.data.BookingResult
import com.haraan.app.data.FavoritesStore
import com.haraan.app.data.LocationRepository
import com.haraan.app.data.LocationState
import com.haraan.app.data.TokenStore
import com.haraan.app.data.ReviewResult
import com.haraan.app.data.VenueCourt
import com.haraan.app.data.VenueDetailData
import com.haraan.app.data.VenueRepository
import com.haraan.app.data.VenueReviewItem
import com.haraan.app.data.VenueSlotItem
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.premiumCardShadow
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
  // Set when the booking sheet is opened from a slot chip rather than from Book Now, so
  // the sheet can open on that exact time instead of an empty form.
  var pickedSlot by remember { mutableStateOf<VenueSlotItem?>(null) }
  var pickedDate by remember { mutableStateOf<LocalDate?>(null) }
  // Set when booking hit the login wall: the selection is parked here, the sign-in
  // screen goes up, and the sheet reopens on top of it afterwards.
  var pendingBooking by remember { mutableStateOf<PendingBooking?>(null) }
  var showLoginGate by remember { mutableStateOf(false) }
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
          // Scrim. Heavier at the bottom than it used to be because the venue's identity
          // now sits ON the photograph — the type has to hold at any brightness, including
          // a floodlit white court.
          Box(
            modifier = Modifier
              .fillMaxSize()
              .background(
                Brush.verticalGradient(
                  colorStops = arrayOf(
                    0f to Color.Black.copy(alpha = 0.42f),
                    0.34f to Color.Transparent,
                    0.62f to Color.Black.copy(alpha = 0.38f),
                    1f to Color.Black.copy(alpha = 0.78f),
                  )
                )
              )
          )

          // ── Identity, over the photo ──────────────────────────────────────────────
          // Playo (and the old version of this screen) prints the name on a white sheet
          // below the image, so the photo is decoration and the page opens on a form.
          // Putting the name, rating and distance on the picture makes the venue itself
          // the first thing you see, and buys back a whole block of vertical space.
          Column(
            modifier = Modifier
              .align(Alignment.BottomStart)
              .fillMaxWidth()
              .padding(start = 16.dp, end = 16.dp, bottom = 34.dp)
          ) {
            Text(
              text = name,
              color = Color.White,
              fontWeight = FontWeight.ExtraBold,
              fontSize = 26.sp,
              lineHeight = 30.sp,
              letterSpacing = (-0.5).sp,
              maxLines = 2,
              overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.height(7.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
              rating.toFloatOrNull()?.takeIf { it > 0f }?.let { score ->
                Icon(Icons.Default.Star, null, tint = HaraanColors.RatingGold, modifier = Modifier.size(15.dp))
                Spacer(Modifier.width(4.dp))
                Text(
                  "%.1f".format(score),
                  color = Color.White,
                  fontWeight = FontWeight.Bold,
                  fontSize = 14.sp,
                )
                (detail?.ratingsCount)?.takeIf { it > 0 }?.let { c ->
                  Spacer(Modifier.width(4.dp))
                  Text("($c)", color = Color.White.copy(alpha = 0.75f), fontSize = 13.sp)
                }
                Spacer(Modifier.width(10.dp))
                Box(Modifier.size(3.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.5f)))
                Spacer(Modifier.width(10.dp))
              }
              // The one number that decides whether they'll actually go.
              val heroDist = liveDistance ?: detail?.distance?.takeIf { it.isNotBlank() }
                ?: venue.distance.takeIf { it.isNotBlank() }
              if (heroDist != null) {
                Icon(
                  Icons.Default.LocationOn, null,
                  tint = Color.White.copy(alpha = 0.85f),
                  modifier = Modifier.size(14.dp),
                )
                Spacer(Modifier.width(3.dp))
                Text(
                  "$heroDist away",
                  color = Color.White.copy(alpha = 0.9f),
                  fontWeight = FontWeight.Medium,
                  fontSize = 13.sp,
                )
              }
            }
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
          // The name, rating and distance now live on the hero — repeating them here is
          // what made the top of this page a wall of restated facts.
          //
          // What leads instead is the one question a player actually opens a venue page
          // to answer: when can I play, and what does it cost. See [TodaySlotRail].
          detail?.let { d ->
            TodaySlotRail(
              venue = d,
              onPickSlot = { slot, date ->
                // Carry the choice into the sheet — see BookingSheet's initialSlot.
                pickedSlot = slot
                pickedDate = date
                showBooking = true
              },
            )
          }

          // Courts, said concretely. "Available Sports → View pricing ›" was a chevron
          // hiding the answer; the venue's own court names and real rate span are the
          // answer, and no generic listing page can print them.
          detail?.let { d -> CourtsLine(d, onOpenPriceChart) }

          // Operating hours, verbatim from the venue. NOT parsed into "Open now": the
          // column is free text authored per venue, and a mis-parse would confidently
          // tell someone a closed court is open.
          (detail?.hours)?.takeIf { it.isNotBlank() }?.let { hours ->
            Spacer(Modifier.height(16.dp))
            Row(verticalAlignment = Alignment.Top) {
              Icon(
                Icons.Default.Schedule, null,
                tint = HaraanColors.TextMuted,
                modifier = Modifier.size(14.dp).padding(top = 2.dp),
              )
              Spacer(Modifier.width(6.dp))
              Text(
                hours,
                color = HaraanColors.TextSecondary,
                fontWeight = FontWeight.Medium,
                fontSize = 13.sp,
                lineHeight = 19.sp,
              )
            }
          }
          // Full address (falls back to the short area label), shown under the timing,
          // with the "Show in Map" pill pulled up beside it.
          Spacer(Modifier.height(10.dp))
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
              // Distance deliberately omitted — the hero already carries it. It used to
              // appear here, in the Location section AND on the card that got you here.
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
            // The rating summary card that used to sit here printed 4.9 a second time,
            // directly under a 4.9 — the score now lives once, on the hero, and rating
            // the venue belongs with the reviews it produces (see the Reviews section).

            // ── Amenities ───────────────────────────────────────────────────────
            if (d.amenities.isNotEmpty()) {
              SectionCard(title = "What's here") {
                AmenityPills(d.amenities)
              }
            }

            // ── 5. About ────────────────────────────────────────────────────────
            if (d.about.isNotBlank()) {
              SectionCard(title = "About this venue") {
                Text(d.about, color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 20.sp)
              }
            }

            // ── 5b. Rules & policies (cancellation policy + admin-authored checklist) ──
            val goodToKnow = remember(d) {
              buildList {
                if (d.cancellation.isNotBlank()) add(d.cancellation)
                addAll(d.rules)
              }
            }
            if (goodToKnow.isNotEmpty()) {
              SectionCard(title = "Good to know") {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                  goodToKnow.forEach { rule ->
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
            // Always rendered, even with no reviews: this is where "Rate venue" lives
            // now, and a venue nobody has reviewed is exactly the one that needs the ask.
            SectionCard(title = if (d.reviewsCount > 0) "Reviews (${d.reviewsCount})" else "Reviews") {
              Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                if (d.reviews.isEmpty()) {
                  Text(
                    "No reviews yet — if you've played here, you'd be the first.",
                    color = HaraanColors.TextMuted, fontSize = 13.sp, lineHeight = 19.sp,
                  )
                } else {
                  d.reviews.forEach { ReviewRow(it) }
                }
                Row(
                  verticalAlignment = Alignment.CenterVertically,
                  modifier = Modifier
                    .clip(RoundedCornerShape(50))
                    .border(BorderStroke(1.dp, HaraanColors.EventsBlue.copy(alpha = 0.45f)), RoundedCornerShape(50))
                    .pressable { showRating = true }
                    .padding(horizontal = 16.dp, vertical = 10.dp),
                ) {
                  Icon(Icons.Default.Star, null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(15.dp))
                  Spacer(Modifier.width(7.dp))
                  Text(
                    "Rate this venue",
                    color = HaraanColors.EventsBlue,
                    fontWeight = FontWeight.Bold,
                    fontSize = 13.sp,
                  )
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
    BookingSheet(
      venue = detail!!,
      onDismiss = {
        showBooking = false
        // Clear the pre-fill so the next Book Now opens a clean browse, not the last
        // chip the user happened to tap.
        pickedSlot = null
        pickedDate = null
        pendingBooking = null
      },
      initialSlot = pendingBooking?.slot ?: pickedSlot,
      initialDate = pendingBooking?.date ?: pickedDate,
      initialCourt = pendingBooking?.court,
      initialDuration = pendingBooking?.duration,
      onNeedsLogin = { pending ->
        // Park the selection, drop the sheet, put the sign-in screen up.
        pendingBooking = pending
        showBooking = false
        showLoginGate = true
      },
    )
  }

  // ── Login gate ────────────────────────────────────────────────────────────────
  // Rendered OVER the venue page so the venue is still behind them the whole time; the
  // booking sheet comes straight back either way, so backing out of the sign-in never
  // costs the user the slot, court and duration they already chose.
  if (showLoginGate) {
    com.haraan.app.ui.LoginRoute(
      onSkipClick = {
        showLoginGate = false
        showBooking = true
      },
      onLoginSuccess = { token ->
        TokenStore.saveToken(ctx, token)
        com.haraan.app.push.PushRegistrar.syncToken(ctx)
        showLoginGate = false
        // Back to the form, filled in, one tap from booked. Deliberately NOT
        // auto-submitted: committing a booking the instant a login lands would charge
        // the user for a button they never pressed.
        showBooking = true
      },
    )
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
 * Today's slots, on the page instead of behind the Book Now modal.
 *
 * This is the whole point of the redesign. Every fact the old page led with — name,
 * stars, address, amenities — is something a directory listing has too, which is why it
 * read as a clone. The venue's own start times and per-slot prices are the thing this
 * app holds and a listing doesn't, and they answer the only question a player came with:
 * can I play tonight, and what does it cost.
 *
 * **Honesty constraint, do not "improve" this into a live counter.** The API's
 * `available` flag is `VenueSlot.is_available` — the venue's own open/closed switch on a
 * slot *template*. It does NOT subtract existing bookings. So these chips may say a slot
 * is open when it has already been taken, and the labels stay deliberately vague
 * ("Open" / "Closed", never "3 left" or "free now"). Making this real means computing
 * availability against bookings server-side first.
 */
@Composable
private fun TodaySlotRail(venue: VenueDetailData, onPickSlot: (VenueSlotItem, LocalDate) -> Unit) {
  // Slot rows are keyed by a free-text day label, so match the day's, then fall back to
  // the venue's everyday template rather than showing nothing.
  fun slotsFor(date: LocalDate): List<VenueSlotItem> {
    val label = dayLabelFor(date)
    return venue.slots.filter { it.day.equals(label, ignoreCase = true) }
      .ifEmpty { venue.slots.filter { it.day.equals("Every day", ignoreCase = true) } }
  }

  // Drop the hours that have already gone. A rail headed "today" that opens on 6:00 AM
  // at midday reads as canned sample data — the single fastest way to lose the trust
  // this whole section exists to earn. Slots whose time can't be parsed are kept:
  // hiding a real bookable slot is worse than showing one that may have passed.
  // Keyed on the venue so the on-resume refresh (AutoRefresh) re-reads the clock; an
  // unkeyed remember would freeze the cutoff at whenever the page first opened.
  val nowMinutes = remember(venue) { LocalTime.now().let { it.hour * 60 + it.minute } }
  val todayRemaining = remember(venue, nowMinutes) {
    slotsFor(LocalDate.now()).filter { (timeToMinutes(it.time) ?: Int.MAX_VALUE) >= nowMinutes }
  }
  // Everything today is behind us — roll the rail forward rather than showing an empty
  // shelf or, worse, this morning's times.
  val showingTomorrow = todayRemaining.isEmpty()
  val slots = if (showingTomorrow) slotsFor(LocalDate.now().plusDays(1)) else todayRemaining
  if (slots.isEmpty()) return

  val open = slots.count { it.available }

  Spacer(Modifier.height(18.dp))
  Row(verticalAlignment = Alignment.CenterVertically) {
    Text(
      if (showingTomorrow) "Playing tomorrow" else "Playing today",
      color = HaraanColors.TextPrimary,
      fontWeight = FontWeight.Bold,
      fontSize = 16.sp,
    )
    Spacer(Modifier.width(8.dp))
    Text(
      // A fact about the clock and the venue's published list — never about bookings.
      when {
        open == 0 -> "none open"
        showingTomorrow -> "$open slot${if (open == 1) "" else "s"} listed"
        else -> "$open still to come"
      },
      color = HaraanColors.TextMuted,
      fontSize = 12.sp,
    )
  }
  Spacer(Modifier.height(10.dp))
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .horizontalScroll(rememberScrollState()),
    horizontalArrangement = Arrangement.spacedBy(9.dp),
  ) {
    val railDate = if (showingTomorrow) LocalDate.now().plusDays(1) else LocalDate.now()
    slots.forEach { slot -> SlotChip(slot, venue.price) { onPickSlot(slot, railDate) } }
  }
}

/** One start time: the hour, what it costs, and the venue's own "filling fast" flag. */
@Composable
private fun SlotChip(slot: VenueSlotItem, venuePrice: Int, onClick: () -> Unit) {
  val closed = !slot.available
  val rate = slot.price.takeIf { it > 0 } ?: venuePrice
  Column(
    modifier = Modifier
      .then(if (closed) Modifier else Modifier.pressable { onClick() })
      .clip(RoundedCornerShape(14.dp))
      .background(if (closed) Color(0xFFF3F5F8) else Color.White)
      .border(
        1.dp,
        when {
          closed -> HaraanColors.BorderLight
          slot.fillingFast -> HaraanColors.RatingGold.copy(alpha = 0.55f)
          else -> HaraanColors.EventsBlue.copy(alpha = 0.35f)
        },
        RoundedCornerShape(14.dp),
      )
      .padding(horizontal = 14.dp, vertical = 10.dp),
    horizontalAlignment = Alignment.Start,
  ) {
    Text(
      slot.time,
      color = if (closed) HaraanColors.TextMuted else HaraanColors.TextPrimary,
      fontWeight = FontWeight.Bold,
      fontSize = 14.sp,
      maxLines = 1,
    )
    Spacer(Modifier.height(3.dp))
    Text(
      when {
        closed -> "Closed"
        rate > 0 -> "₹$rate"
        else -> "Tap to book"
      },
      color = if (closed) HaraanColors.TextMuted else HaraanColors.TextSecondary,
      fontSize = 12.sp,
      fontWeight = FontWeight.Medium,
      maxLines = 1,
    )
    // The venue's own flag, printed as the venue set it — not a computed urgency.
    if (!closed && slot.fillingFast) {
      Spacer(Modifier.height(5.dp))
      Text(
        "FILLING FAST",
        color = Color(0xFF9A6700),
        fontSize = 8.5.sp,
        fontWeight = FontWeight.ExtraBold,
        letterSpacing = 0.5.sp,
        maxLines = 1,
      )
    }
  }
}

/**
 * The courts line — replaces the "Available Sports ▸ View pricing" row.
 *
 * A chevron promising to reveal a price is worse than the price. This prints what the
 * venue actually has (its real court names and count) and the true rate span across
 * them, and still opens the full chart for peak/off-peak detail.
 */
@Composable
private fun CourtsLine(d: VenueDetailData, onOpenPriceChart: () -> Unit) {
  if (d.courts.isEmpty() && d.sports.isEmpty()) return

  val rates = d.courts.flatMap { listOfNotNull(it.price.takeIf { p -> p > 0 }, it.peakPrice?.takeIf { p -> p > 0 }) }
  val span = when {
    rates.isEmpty() -> d.price.takeIf { it > 0 }?.let { "₹$it/hr" }
    rates.min() == rates.max() -> "₹${rates.min()}/hr"
    else -> "₹${rates.min()}–${rates.max()}/hr"
  }
  val courtNames = d.courts.map { it.name }.filter { it.isNotBlank() }
  val sportsLine = d.sports.filter { it.isNotBlank() }.ifEmpty { listOf(d.category) }.joinToString(" · ")

  Spacer(Modifier.height(18.dp))
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .pressable { onOpenPriceChart() }
      .clip(RoundedCornerShape(14.dp))
      .background(HaraanColors.EventsBlue.copy(alpha = 0.05f))
      .padding(horizontal = 14.dp, vertical = 12.dp),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    Icon(sportIcon(d.category), null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(22.dp))
    Spacer(Modifier.width(12.dp))
    Column(Modifier.weight(1f)) {
      Text(
        buildString {
          if (courtNames.isNotEmpty()) {
            append(courtNames.size)
            append(if (courtNames.size == 1) " court" else " courts")
          } else {
            append(sportsLine)
          }
          span?.let { append("  ·  ").append(it) }
        },
        color = HaraanColors.TextPrimary,
        fontWeight = FontWeight.Bold,
        fontSize = 14.sp,
      )
      Spacer(Modifier.height(2.dp))
      Text(
        // Name the actual courts. "Court 1 · Court 2" is dull; "Wooden A · Synthetic B"
        // is the kind of detail a player chooses on, and only the venue can supply it.
        courtNames.take(3).joinToString(" · ").ifBlank { sportsLine }
          .let { if (courtNames.size > 3) "$it +${courtNames.size - 3}" else it },
        color = HaraanColors.TextSecondary,
        fontSize = 12.sp,
        maxLines = 1,
        overflow = TextOverflow.Ellipsis,
      )
    }
    Spacer(Modifier.width(10.dp))
    Text(
      "Rates",
      color = HaraanColors.EventsBlue,
      fontWeight = FontWeight.Bold,
      fontSize = 13.sp,
    )
    Icon(Icons.Default.KeyboardArrowRight, null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(20.dp))
  }
}

/** Sport-appropriate icon for the Available Sports tile. */
private fun sportIcon(category: String): androidx.compose.ui.graphics.vector.ImageVector = when {
  category.contains("Cricket", true) -> Icons.Default.SportsCricket
  category.contains("Football", true) || category.contains("Soccer", true) -> Icons.Default.SportsSoccer
  category.contains("Basketball", true) -> Icons.Default.SportsBasketball
  else -> Icons.Default.SportsTennis // badminton / pickleball / racquet sports
}

// ── Per-court peak pricing (mirrors the backend VenueCourt::isPeak / rateFor) ─────────────

/** Minutes-from-midnight for a time label like "7:00 PM" or "19:00", or null if unparseable. */
private fun timeToMinutes(label: String?): Int? {
  if (label.isNullOrBlank()) return null
  val m = Regex("""(\d{1,2}):(\d{2})\s*([AaPp][Mm])?""").find(label.trim()) ?: return null
  var h = m.groupValues[1].toIntOrNull() ?: return null
  val min = m.groupValues[2].toIntOrNull() ?: return null
  when (m.groupValues[3].uppercase()) {
    "PM" -> if (h != 12) h += 12
    "AM" -> if (h == 12) h = 0
  }
  return h * 60 + min
}

/** Whether a court's peak pricing applies for the given date + start time. */
private fun isCourtPeak(court: VenueCourt, date: LocalDate, time: String?): Boolean {
  court.peakPrice ?: return false
  val hasWindow = court.peakStart != null && court.peakEnd != null
  if (court.peakDays.isEmpty() && !hasWindow) return false
  if (court.peakDays.isNotEmpty()) {
    val day = date.dayOfWeek.getDisplayName(TextStyle.SHORT, Locale.ENGLISH) // "Sat"
    if (court.peakDays.none { it.equals(day, ignoreCase = true) }) return false
  }
  if (hasWindow) {
    val t = timeToMinutes(time) ?: return false
    val s = timeToMinutes(court.peakStart) ?: return false
    val e = timeToMinutes(court.peakEnd) ?: return false
    if (t < s || t >= e) return false
  }
  return true
}

/** Effective hourly rate for a court at a date/time: peak when it applies, else base/venue price. */
private fun courtRate(court: VenueCourt, date: LocalDate, time: String?, venuePrice: Int): Int {
  if (isCourtPeak(court, date, time)) return court.peakPrice ?: venuePrice
  return court.price.takeIf { it > 0 } ?: venuePrice
}

/** Human "when" for a court's peak pricing, e.g. "Sat, Sun · 6:00 PM–11:00 PM". */
private fun courtPeakWhen(court: VenueCourt): String {
  val days = if (court.peakDays.isNotEmpty()) court.peakDays.joinToString(", ") else "Every day"
  val window = if (court.peakStart != null && court.peakEnd != null) {
    " · ${to12h(court.peakStart)}–${to12h(court.peakEnd)}"
  } else ""
  return days + window
}

/** "18:00" → "6:00 PM"; passes through anything it can't parse. */
private fun to12h(hhmm: String): String {
  val mins = timeToMinutes(hhmm) ?: return hhmm
  val h = mins / 60; val m = mins % 60
  val ap = if (h < 12) "AM" else "PM"
  val h12 = when { h == 0 -> 12; h > 12 -> h - 12; else -> h }
  return "%d:%02d %s".format(h12, m, ap)
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
          verticalAlignment = Alignment.Top
        ) {
          Text(court.name, color = HaraanColors.TextPrimary, fontSize = 14.sp, modifier = Modifier.weight(1f))
          Column(horizontalAlignment = Alignment.End) {
            Text("INR ${court.price.takeIf { it > 0 } ?: d.price} / hour", color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
            if (court.peakPrice != null) {
              Spacer(Modifier.height(2.dp))
              Text("Peak INR ${court.peakPrice} / hour", color = HaraanColors.GameHubDeep, fontWeight = FontWeight.SemiBold, fontSize = 12.sp)
              Text(courtPeakWhen(court), color = HaraanColors.TextMuted, fontSize = 11.sp)
            }
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
              // getSignedInToken, not getToken: a guest holds the non-blank
              // "skipped_guest" token and would otherwise sail past this check.
              val token = TokenStore.getSignedInToken(ctx)
              if (token == null) {
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

/**
 * Amenities as pills that wrap, not as equal boxed tiles in a rigid grid.
 *
 * The grid version forced every amenity into the same 140dp cell with its own tinted
 * icon plate, so "Parking" and "Floodlights" got the visual weight of a feature card and
 * the block ended up looking generated. A facility list is a list of small facts — let
 * each one take exactly the width of its own name.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun AmenityPills(amenities: List<String>) {
  FlowRow(
    horizontalArrangement = Arrangement.spacedBy(8.dp),
    verticalArrangement = Arrangement.spacedBy(8.dp),
  ) {
    amenities.forEach { a ->
      Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
          .clip(RoundedCornerShape(50))
          .background(Color(0xFFF4F7FB))
          .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(50))
          .padding(start = 10.dp, end = 14.dp, top = 8.dp, bottom = 8.dp),
      ) {
        Icon(
          amenityIcon(a), null,
          tint = HaraanColors.EventsBlue,
          modifier = Modifier.size(15.dp),
        )
        Spacer(Modifier.width(6.dp))
        Text(
          a,
          color = HaraanColors.TextPrimary,
          fontSize = 12.5.sp,
          fontWeight = FontWeight.Medium,
          maxLines = 1,
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
/**
 * Everything the user chose before we discovered they weren't signed in. Carried across
 * the login gate so they come back to a filled-in form instead of an empty one — losing
 * a four-field selection to an auth check is how a booking gets abandoned.
 */
internal data class PendingBooking(
  val slot: VenueSlotItem,
  val date: LocalDate,
  val court: VenueCourt?,
  val duration: Int,
)

@Composable
internal fun BookingSheet(
  venue: VenueDetailData,
  onDismiss: () -> Unit,
  /**
   * The slot the user tapped on the venue page's "Playing today" rail, and the day it
   * belonged to. Without these the rail is decoration: tapping 12:00 PM would open this
   * sheet with Start Time empty, so the choice just made is thrown away and the user
   * makes it again. Null when the sheet is opened from Book Now, which is a browse.
   */
  initialSlot: VenueSlotItem? = null,
  initialDate: LocalDate? = null,
  /** Court + duration restored after a login gate; see [PendingBooking]. */
  initialCourt: VenueCourt? = null,
  initialDuration: Int? = null,
  /**
   * Booking needs a real account. Rather than printing "Please log in" under a form the
   * user has already filled in — a dead end that asks them to find the login themselves —
   * hand the whole selection up so the caller can gate on login and hand it straight back.
   */
  onNeedsLogin: (PendingBooking) -> Unit = {},
) {
  val ctx = LocalContext.current
  val scope = rememberCoroutineScope()

  val sports = remember(venue) { venue.sports.ifEmpty { listOf(venue.category) }.filter { it.isNotBlank() } }

  var selectedSport by remember { mutableStateOf(sports.firstOrNull() ?: venue.category) }
  var selectedDate by remember { mutableStateOf(initialDate ?: LocalDate.now()) }
  var selectedSlot by remember { mutableStateOf(initialSlot) }
  var duration by remember { mutableStateOf(initialDuration ?: 1) }
  // One court means there is nothing to choose — picking it for them turns a tap from
  // the slot rail into a single confirm. With several, the user must still choose,
  // because the court decides the price.
  var selectedCourt by remember {
    mutableStateOf(
      initialCourt ?: venue.courts.takeIf { it.size == 1 }?.firstOrNull()
    )
  }
  var submitting by remember { mutableStateOf(false) }
  var result by remember { mutableStateOf<String?>(null) }
  var success by remember { mutableStateOf(false) }
  // Booking used to fire straight off the form's button, so the first time the user saw
  // what they'd agreed to was after it was done. The form now hands off to a summary
  // they confirm from. FORM → SUMMARY → (booked) slip.
  var reviewing by remember { mutableStateOf(false) }
  // Set from the booking response so the slip can print a real, scannable code.
  var bookedCode by remember { mutableStateOf<String?>(null) }
  // Coupon state. `appliedCode`/`discount`/`fee` are whatever the SERVER returned for
  // this subtotal — never computed here, so the summary can't promise a price the
  // charge won't match.
  var couponInput by remember { mutableStateOf("") }
  var couponBusy by remember { mutableStateOf(false) }
  var couponMessage by remember { mutableStateOf<String?>(null) }
  var appliedCode by remember { mutableStateOf<String?>(null) }
  var discountRs by remember { mutableStateOf(0) }
  var feeRs by remember { mutableStateOf(0) }

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
  // Per-court price wins over the slot/venue price when a court is chosen, and peak pricing
  // wins over that when the picked day/time falls in the court's peak window.
  val perHour = selectedCourt?.let { courtRate(it, selectedDate, selectedSlot?.time, venue.price) }
    ?: selectedSlot?.price?.takeIf { it > 0 }
    ?: venue.price
  val isPeakNow = selectedCourt?.let { isCourtPeak(it, selectedDate, selectedSlot?.time) } == true
  val subtotalRs = perHour * duration
  // Fee is added and the discount taken off, mirroring reserveVenue's order exactly.
  val total = (subtotalRs + feeRs - discountRs).coerceAtLeast(0)
  val canBook = selectedSlot != null && (!courtNeeded || selectedCourt != null) && !submitting
  // The chosen window as "7:00 PM – 8:00 PM" (null when the time string can't be parsed).
  val endLabel = selectedSlot?.let { slotWindowLabel(it.time, duration) }
  // What the primary button should say — a disabled button always names the next step
  // instead of leaving the user guessing why it's greyed out.
  val bookLabel = when {
    selectedSlot == null -> "Select a start time"
    courtNeeded && selectedCourt == null -> "Select a court"
    // The form's button reviews; only the summary's button commits, and it says so.
    !reviewing -> "Review booking  ·  ₹$total"
    else -> "Confirm booking  ·  ₹$total"
  }

  // The commit itself, hoisted out of the form's button so the order-summary PAGE can
  // own it. `slot` is captured at call time, not at composition.
  val submitBooking: () -> Unit = {
    val slot = selectedSlot
    if (slot != null) {
    submitting = true
    result = null
    scope.launch {
      // getSignedInToken — see the rating check above.
      val token = TokenStore.getSignedInToken(ctx)
      if (token == null) {
        // Send them to sign in with the selection in hand, instead of printing
        // a refusal under a form they just filled in.
        submitting = false
        onNeedsLogin(PendingBooking(slot, selectedDate, selectedCourt, duration))
        return@launch
      }
      val date = selectedDate.toString()
      when (val r = BookingRepository().bookVenueSlot(
        token, venue.id.toIntOrNull() ?: 0, slot.id, date,
        courtId = selectedCourt?.id, duration = duration,
      )) {
        is BookingResult.Success -> {
          // Free slot, or a server that still confirms without payment.
          success = true
          submitting = false
          bookedCode = r.ticketCode
          val court = selectedCourt?.let { " · ${it.name}" } ?: ""
          result = "${slot.time} · $duration hr$court — ${r.message}"
        }
        is BookingResult.Error -> {
          result = r.message
          submitting = false
        }

        // The slot is now held PENDING for 15 minutes. Take the money, then
        // confirm — the same reserve → pay → confirm handshake the ticket flow
        // uses, down to the shared /api/bookings/confirm endpoint.
        is BookingResult.PaymentRequired -> {
          val activity = ctx as? android.app.Activity
          if (activity == null) {
            // No Activity to host the sheet: drop the hold rather than leave the
            // court blocked for 15 minutes on a checkout that can't open.
            BookingRepository().releaseOrder(token, r.orderId)
            result = "Couldn't open payment."
            submitting = false
          } else {
            // Arm the one-shot handler BEFORE opening the sheet — the result
            // arrives via MainActivity → PaymentBridge.
            com.haraan.app.data.PaymentBridge.await { outcome ->
              scope.launch {
                when (outcome) {
                  is com.haraan.app.data.PaymentBridge.Outcome.Success -> {
                    val confirmed = BookingRepository().confirmOrder(
                      token = token,
                      orderId = r.orderId,
                      paymentId = outcome.paymentId,
                      signature = outcome.signature,
                    )
                    submitting = false
                    when (confirmed) {
                      is BookingResult.Success -> {
                        // Paid and verified server-side — print the slip.
                        bookedCode = confirmed.ticketCode
                        result = confirmed.message
                        success = true
                      }
                      else -> result = (confirmed as? BookingResult.Error)?.message
                        ?: "Payment could not be verified."
                    }
                  }
                  is com.haraan.app.data.PaymentBridge.Outcome.Cancelled -> {
                    BookingRepository().releaseOrder(token, r.orderId)
                    submitting = false
                    result = "Payment cancelled — the slot has been released."
                  }
                  is com.haraan.app.data.PaymentBridge.Outcome.Failed -> {
                    BookingRepository().releaseOrder(token, r.orderId)
                    submitting = false
                    result = outcome.message
                  }
                }
              }
            }
            openRazorpayCheckout(
              activity = activity,
              pr = r,
              name = "",
              email = "",
              phone = "",
              description = "${venue.name} · ${selectedSlot?.time.orEmpty()}",
            )
          }
        }
      }
      // No blanket reset here: while Razorpay is open the booking is still in
      // flight, and clearing `submitting` would re-enable Confirm behind the
      // sheet. Every branch above owns its own reset.
    }
    }
  }

  Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
    // Booked: the sheet gets out of the way entirely and the slip prints on a dark
    // stage. A receipt sliding out of a white form card would read as another dialog.
    if (success) {
      Box(
        modifier = Modifier
          .fillMaxSize()
          .background(Color(0xFF0B0F14).copy(alpha = 0.97f))
          .navigationBarsPadding()
          .verticalScroll(rememberScrollState()),
        contentAlignment = Alignment.Center,
      ) {
        PrintedBookingSlip(
          venueName = venue.name,
          sport = selectedSport,
          dateLabel = "${dayLabelFor(selectedDate)} · ${selectedDate.dayOfMonth} ${
            selectedDate.month.getDisplayName(TextStyle.SHORT, Locale.getDefault())
          }",
          timeLabel = endLabel ?: (selectedSlot?.time ?: ""),
          courtName = selectedCourt?.name,
          durationHours = duration,
          totalLabel = "₹$total",
          ticketCode = bookedCode,
          // Nothing was charged in-app: venue slots confirm without a payment step, so
          // the money changes hands at the desk. Said on the slip rather than implied.
          payAtVenue = true,
          onDone = onDismiss,
        )
      }
      return@Dialog
    }

    // ── Order summary: a PAGE, not a step ────────────────────────────────────────
    // As a section inside the bottom sheet it still read as a dialog — the venue page
    // showed through behind it and it inherited the sheet's rounded lip. A checkout that
    // decides money should own the screen: opaque, full height, its own app bar, its own
    // back. Same shape as the event order summary.
    if (reviewing) {
      BackHandler(enabled = true) { reviewing = false }
      VenueOrderSummaryPage(
        venue = venue,
        sport = selectedSport,
        dateLabel = "${dayLabelFor(selectedDate)} · ${selectedDate.dayOfMonth} ${
          selectedDate.month.getDisplayName(TextStyle.SHORT, Locale.getDefault())
        }",
        timeLabel = endLabel ?: (selectedSlot?.time ?: ""),
        courtName = selectedCourt?.name,
        duration = duration,
        perHour = perHour,
        subtotal = subtotalRs,
        fee = feeRs,
        discount = discountRs,
        appliedCode = appliedCode,
        total = total,
        isPeak = isPeakNow,
        couponInput = couponInput,
        couponBusy = couponBusy,
        couponMessage = couponMessage,
        onCouponChange = { couponInput = it; couponMessage = null },
        onApplyCoupon = {
          val code = couponInput.trim()
          if (code.isNotBlank() && !couponBusy) {
            couponBusy = true
            scope.launch {
              val token = TokenStore.getSignedInToken(ctx)
              if (token == null) {
                couponBusy = false
                couponMessage = "Sign in to use a coupon."
              } else {
                val r = BookingRepository().validateCoupon(
                  token = token,
                  code = code,
                  subtotal = subtotalRs.toDouble(),
                  venueId = venue.id.toIntOrNull() ?: 0,
                )
                couponBusy = false
                couponMessage = r.message
                if (r.valid) {
                  appliedCode = r.code ?: code
                  discountRs = r.discount.toInt()
                  feeRs = r.fee.toInt()
                }
              }
            }
          }
        },
        onRemoveCoupon = {
          appliedCode = null
          discountRs = 0
          couponInput = ""
          couponMessage = null
        },
        submitting = submitting,
        error = result,
        onBack = { reviewing = false },
        onConfirm = submitBooking,
      )
      return@Dialog
    }

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
          "Book a slot",
          color = HaraanColors.TextPrimary, fontWeight = FontWeight.ExtraBold, fontSize = 18.sp
        )
        Spacer(Modifier.height(4.dp))
        Text(venue.name, color = HaraanColors.TextSecondary, fontSize = 13.sp)
        Spacer(Modifier.height(18.dp))

        Text(
          "Pick a time and how long you'll play — we'll reserve it for you.",
          color = HaraanColors.TextMuted, fontSize = 12.sp, lineHeight = 16.sp
        )
        Spacer(Modifier.height(14.dp))

        run {
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
              Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Total", color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
                if (isPeakNow) {
                  Spacer(Modifier.width(8.dp))
                  Text(
                    "PEAK",
                    color = HaraanColors.GameHubDeep,
                    fontWeight = FontWeight.Bold,
                    fontSize = 10.sp,
                    modifier = Modifier
                      .clip(RoundedCornerShape(4.dp))
                      .background(HaraanColors.GameHubGreen.copy(alpha = 0.15f))
                      .padding(horizontal = 6.dp, vertical = 2.dp)
                  )
                }
              }
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
              // The form only ever advances. Committing is the summary page's job.
              if (selectedSlot != null) reviewing = true
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

/**
 * The order summary — what you're about to book, priced, before anything is committed.
 *
 * Deliberately NOT a second copy of the form: no dropdowns, no steppers, nothing to
 * fiddle with. It reads as a statement with one way back (Edit) and one way on. The
 * price is broken down rather than asserted, because "₹500" with no arithmetic is the
 * thing people distrust — and when a court's peak rate is what's driving it, that's
 * named too.
 */
/**
 * The order summary as its own screen: opaque, full height, app bar on top, the commit
 * pinned to the bottom. Nothing of the venue page shows through, because a screen that
 * decides money should not look like something floating over what you were just doing.
 */
@Composable
private fun VenueOrderSummaryPage(
  venue: VenueDetailData,
  sport: String,
  dateLabel: String,
  timeLabel: String,
  courtName: String?,
  duration: Int,
  perHour: Int,
  subtotal: Int,
  fee: Int,
  discount: Int,
  appliedCode: String?,
  total: Int,
  isPeak: Boolean,
  couponInput: String,
  couponBusy: Boolean,
  couponMessage: String?,
  onCouponChange: (String) -> Unit,
  onApplyCoupon: () -> Unit,
  onRemoveCoupon: () -> Unit,
  submitting: Boolean,
  error: String?,
  onBack: () -> Unit,
  onConfirm: () -> Unit,
) {
  Column(
    modifier = Modifier
      .fillMaxSize()
      .background(HaraanColors.Background),
  ) {
    // App bar
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .background(Color.White)
        .statusBarsPadding()
        .padding(horizontal = 8.dp, vertical = 10.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Box(
        modifier = Modifier
          .pressable(haptic = Feel.SELECT, onClick = onBack)
          .size(40.dp)
          .clip(CircleShape),
        contentAlignment = Alignment.Center,
      ) {
        Icon(Icons.Default.ArrowBack, "Back", tint = HaraanColors.TextPrimary, modifier = Modifier.size(22.dp))
      }
      Spacer(Modifier.width(4.dp))
      Text(
        "Order summary",
        color = HaraanColors.TextPrimary,
        fontWeight = FontWeight.ExtraBold,
        fontSize = 19.sp,
      )
    }

    Column(
      modifier = Modifier
        .weight(1f)
        .verticalScroll(rememberScrollState())
        .padding(horizontal = 16.dp),
    ) {
      Spacer(Modifier.height(16.dp))

      // The venue, with its photo. A checkout that names a place but doesn't show it
      // reads as a form; the thumbnail is what makes this feel like buying a real thing.
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .premiumCardShadow(radius = 18.dp, ambient = 14.dp, contact = 2.dp)
          .clip(RoundedCornerShape(18.dp))
          .background(Color.White)
          .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
      ) {
        Box(
          modifier = Modifier
            .size(60.dp)
            .clip(RoundedCornerShape(14.dp))
            .background(Color(0xFFEFF3F8)),
          contentAlignment = Alignment.Center,
        ) {
          venue.images.firstOrNull()?.takeIf { it.isNotBlank() }?.let { img ->
            AsyncImage(
              model = img,
              contentDescription = venue.name,
              contentScale = ContentScale.Crop,
              modifier = Modifier.fillMaxSize(),
            )
          } ?: Icon(
            sportIcon(venue.category), null,
            tint = HaraanColors.EventsBlue,
            modifier = Modifier.size(24.dp),
          )
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
          Text(
            venue.name,
            color = HaraanColors.TextPrimary,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 15.5.sp,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
          )
          Spacer(Modifier.height(3.dp))
          Text(
            venue.address.takeIf { it.isNotBlank() } ?: venue.location,
            color = HaraanColors.TextSecondary,
            fontSize = 12.sp,
            lineHeight = 16.sp,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
          )
        }
      }

      Spacer(Modifier.height(14.dp))

      OrderSummaryBody(
        venue = venue,
        sport = sport,
        dateLabel = dateLabel,
        timeLabel = timeLabel,
        courtName = courtName,
        duration = duration,
        perHour = perHour,
        subtotal = subtotal,
        fee = fee,
        discount = discount,
        appliedCode = appliedCode,
        total = total,
        isPeak = isPeak,
        couponInput = couponInput,
        couponBusy = couponBusy,
        couponMessage = couponMessage,
        onCouponChange = onCouponChange,
        onApplyCoupon = onApplyCoupon,
        onRemoveCoupon = onRemoveCoupon,
        onEdit = onBack,
      )

      if (!error.isNullOrBlank()) {
        Spacer(Modifier.height(14.dp))
        Text(error, color = HaraanColors.LiveRed, fontSize = 12.5.sp, lineHeight = 17.sp)
      }
      Spacer(Modifier.height(24.dp))
    }

    // Commit, pinned — the total stays in view next to the button that charges it.
    Column(
      modifier = Modifier
        .fillMaxWidth()
        .shadow(elevation = 14.dp, clip = false)
        .background(Color.White)
        .navigationBarsPadding()
        .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
      Row(verticalAlignment = Alignment.CenterVertically) {
        // The number stays beside the button that commits it — a full-width CTA with the
        // price buried in its label is the generic pattern this screen kept falling into.
        Column(Modifier.weight(1f)) {
          Text(
            "₹$total",
            color = HaraanColors.TextPrimary,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 22.sp,
          )
          Text(
            "Pay at the venue",
            color = HaraanColors.TextMuted,
            fontSize = 11.5.sp,
          )
        }
        Spacer(Modifier.width(14.dp))
        Box(
          modifier = Modifier
            .then(if (submitting) Modifier else Modifier.pressable(haptic = Feel.COMMIT, onClick = onConfirm))
            .clip(RoundedCornerShape(50))
            .background(if (submitting) Color(0xFFBFC8D2) else HaraanColors.GameHubGreen)
            .padding(horizontal = 28.dp, vertical = 15.dp),
          contentAlignment = Alignment.Center,
        ) {
          if (submitting) {
            CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
          } else {
            Text(
              "Confirm booking",
              color = Color.White,
              fontWeight = FontWeight.Bold,
              fontSize = 15.sp,
              maxLines = 1,
            )
          }
        }
      }
    }
  }
}

@Composable
private fun OrderSummaryBody(
  venue: VenueDetailData,
  sport: String,
  dateLabel: String,
  timeLabel: String,
  courtName: String?,
  duration: Int,
  perHour: Int,
  subtotal: Int,
  fee: Int,
  discount: Int,
  appliedCode: String?,
  total: Int,
  isPeak: Boolean,
  couponInput: String,
  couponBusy: Boolean,
  couponMessage: String?,
  onCouponChange: (String) -> Unit,
  onApplyCoupon: () -> Unit,
  onRemoveCoupon: () -> Unit,
  onEdit: () -> Unit,
) {
  Column(
    modifier = Modifier
      .fillMaxWidth()
      .premiumCardShadow(radius = 18.dp, ambient = 14.dp, contact = 2.dp)
      .clip(RoundedCornerShape(18.dp))
      .background(Color.White)
      .padding(16.dp),
  ) {
    Text(
      "YOUR SLOT",
      color = HaraanColors.TextMuted,
      fontSize = 10.sp,
      fontWeight = FontWeight.Bold,
      letterSpacing = 1.3.sp,
    )
    Spacer(Modifier.height(10.dp))
    SummaryLine("Sport", sport)
    SummaryLine("When", "$dateLabel  ·  $timeLabel")
    if (!courtName.isNullOrBlank()) SummaryLine("Court", courtName)
    SummaryLine("Duration", if (duration == 1) "1 hour" else "$duration hours")

    Spacer(Modifier.height(12.dp))
    Box(Modifier.fillMaxWidth().height(1.dp).background(HaraanColors.BorderLight))
    Spacer(Modifier.height(12.dp))

    // The arithmetic, shown — every line the server used to reach the charge.
    SummaryLine("₹$perHour × $duration hr", "₹$subtotal", muted = true)
    if (fee > 0) SummaryLine("Booking fee", "₹$fee", muted = true)
    if (discount > 0) {
      Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
      ) {
        Text(
          appliedCode?.let { "Coupon $it" } ?: "Discount",
          color = HaraanColors.Success,
          fontSize = 13.sp,
          fontWeight = FontWeight.Medium,
        )
        Text(
          "− ₹$discount",
          color = HaraanColors.Success,
          fontSize = 13.sp,
          fontWeight = FontWeight.Bold,
        )
      }
    }
    if (isPeak) {
      Spacer(Modifier.height(2.dp))
      Text(
        "Peak rate applies at this time",
        color = Color(0xFF9A6700),
        fontSize = 11.5.sp,
        fontWeight = FontWeight.Medium,
      )
    }

    Spacer(Modifier.height(12.dp))
    Box(Modifier.fillMaxWidth().height(1.dp).background(HaraanColors.BorderLight))
    Spacer(Modifier.height(12.dp))

    Row(
      modifier = Modifier.fillMaxWidth(),
      horizontalArrangement = Arrangement.SpaceBetween,
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Column {
        Text("Total", color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 15.sp)
        Text("Pay at the venue", color = HaraanColors.TextMuted, fontSize = 11.5.sp)
      }
      Text("₹$total", color = HaraanColors.TextPrimary, fontWeight = FontWeight.ExtraBold, fontSize = 22.sp)
    }
  }

  Spacer(Modifier.height(14.dp))

  // ── Coupon ─────────────────────────────────────────────────────────────────
  // The code is checked against the SERVER (venue rules, min-order, per-customer cap)
  // and the discount shown is the one it returned — the app never computes an offer it
  // then can't honour at the charge.
  Column(
    modifier = Modifier
      .fillMaxWidth()
      .premiumCardShadow(radius = 18.dp, ambient = 14.dp, contact = 2.dp)
      .clip(RoundedCornerShape(18.dp))
      .background(Color.White)
      .padding(14.dp),
  ) {
    if (appliedCode != null) {
      Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Default.Check, null, tint = HaraanColors.Success, modifier = Modifier.size(17.dp))
        Spacer(Modifier.width(8.dp))
        Column(Modifier.weight(1f)) {
          Text(
            appliedCode,
            color = HaraanColors.TextPrimary,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 14.sp,
            letterSpacing = 0.6.sp,
          )
          Text("₹$discount off", color = HaraanColors.Success, fontSize = 12.sp, fontWeight = FontWeight.Medium)
        }
        Text(
          "Remove",
          color = HaraanColors.TextSecondary,
          fontWeight = FontWeight.Bold,
          fontSize = 12.5.sp,
          modifier = Modifier.pressable(haptic = Feel.SELECT, onClick = onRemoveCoupon).padding(6.dp),
        )
      }
    } else {
      Text(
        "HAVE A COUPON?",
        color = HaraanColors.TextMuted,
        fontSize = 10.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = 1.3.sp,
      )
      Spacer(Modifier.height(9.dp))
      Row(verticalAlignment = Alignment.CenterVertically) {
        Row(
          modifier = Modifier
            .weight(1f)
            .height(44.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFFF4F7FB))
            .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(12.dp))
            .padding(horizontal = 12.dp),
          verticalAlignment = Alignment.CenterVertically,
        ) {
          BasicTextField(
            value = couponInput,
            onValueChange = { onCouponChange(it.uppercase()) },
            singleLine = true,
            cursorBrush = SolidColor(HaraanColors.EventsBlue),
            // Fully qualified: this file's `TextStyle` is java.time.format's, used for
            // the date labels.
            textStyle = androidx.compose.ui.text.TextStyle(
              fontSize = 14.sp,
              color = HaraanColors.TextPrimary,
              fontWeight = FontWeight.Bold,
              letterSpacing = 0.8.sp,
            ),
            modifier = Modifier.weight(1f),
            decorationBox = { inner ->
              if (couponInput.isEmpty()) {
                Text("Enter code", color = HaraanColors.TextMuted, fontSize = 14.sp)
              }
              inner()
            },
          )
        }
        Spacer(Modifier.width(10.dp))
        Box(
          modifier = Modifier
            .then(
              if (couponBusy || couponInput.isBlank()) Modifier
              else Modifier.pressable(haptic = Feel.SELECT, onClick = onApplyCoupon)
            )
            .clip(RoundedCornerShape(12.dp))
            .background(
              if (couponInput.isBlank()) Color(0xFFE2E8F0) else HaraanColors.EventsBlue
            )
            .padding(horizontal = 18.dp, vertical = 13.dp),
          contentAlignment = Alignment.Center,
        ) {
          if (couponBusy) {
            CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
          } else {
            Text(
              "Apply",
              color = if (couponInput.isBlank()) HaraanColors.TextMuted else Color.White,
              fontWeight = FontWeight.Bold,
              fontSize = 13.sp,
            )
          }
        }
      }
    }
    if (!couponMessage.isNullOrBlank()) {
      Spacer(Modifier.height(8.dp))
      Text(
        couponMessage,
        color = if (appliedCode != null) HaraanColors.Success else HaraanColors.LiveRed,
        fontSize = 12.sp,
        lineHeight = 16.sp,
      )
    }
  }

  Spacer(Modifier.height(14.dp))
  // A real control, not a naked blue word floating on the page.
  Row(
    modifier = Modifier
      .pressable(haptic = Feel.SELECT, onClick = onEdit)
      .clip(RoundedCornerShape(50))
      .background(Color.White)
      .border(BorderStroke(1.dp, HaraanColors.BorderLight), RoundedCornerShape(50))
      .padding(horizontal = 16.dp, vertical = 10.dp),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    Icon(
      Icons.Default.ArrowBack, null,
      tint = HaraanColors.EventsBlue,
      modifier = Modifier.size(15.dp),
    )
    Spacer(Modifier.width(7.dp))
    Text(
      "Change time or court",
      color = HaraanColors.EventsBlue,
      fontWeight = FontWeight.Bold,
      fontSize = 13.sp,
    )
  }
}

@Composable
private fun SummaryLine(label: String, value: String, muted: Boolean = false) {
  Row(
    modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
    horizontalArrangement = Arrangement.SpaceBetween,
    verticalAlignment = Alignment.Top,
  ) {
    Text(
      label,
      color = HaraanColors.TextSecondary,
      fontSize = 13.sp,
      modifier = Modifier.weight(1f),
    )
    Spacer(Modifier.width(12.dp))
    Text(
      value,
      color = if (muted) HaraanColors.TextSecondary else HaraanColors.TextPrimary,
      fontWeight = if (muted) FontWeight.Medium else FontWeight.SemiBold,
      fontSize = 13.sp,
    )
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
// Slots are generated per weekday (Monday…Sunday) from the venue's structured hours, so we
// always match a date to its full weekday name rather than a relative "Today"/"Tomorrow" label.
private fun dayLabelFor(date: LocalDate): String =
  date.dayOfWeek.getDisplayName(TextStyle.FULL, Locale.ENGLISH)

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
