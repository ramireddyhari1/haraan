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
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.LocalOffer
import androidx.compose.ui.draw.drawBehind
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.fillMaxHeight
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
import androidx.compose.material.icons.filled.FitnessCenter
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
import androidx.compose.runtime.produceState
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
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
import androidx.compose.animation.animateContentSize
import com.haraan.app.ui.util.VenueMap
import com.haraan.app.ui.Feel
import com.haraan.app.ui.components.AutoRefresh
import com.haraan.app.ui.pressable
import com.haraan.app.ui.util.openMap
import com.haraan.app.VenueDetail
import com.haraan.app.data.ApiConfig
import com.haraan.app.data.BookingRepository
import com.haraan.app.data.BookingResult
import com.haraan.app.data.FavoritesStore
import com.haraan.app.ui.components.HeroActionButton
import com.haraan.app.ui.components.HeroActionDefaults
import com.haraan.app.ui.components.HeroActionIcons
import com.haraan.app.ui.components.HeroToggleActionButton
import com.haraan.app.data.LocationRepository
import com.haraan.app.data.LocationState
import com.haraan.app.data.TokenStore
import com.haraan.app.data.ReviewResult
import com.haraan.app.data.VenueCourt
import com.haraan.app.data.VenueDetailData
import com.haraan.app.data.VenueFeeLine
import com.haraan.app.data.VenueRepository
import com.haraan.app.data.VenueReviewItem
import com.haraan.app.data.VenueSlotItem
import com.haraan.app.data.SlotAvailability
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
    ?: listOfNotNull(venue.imageUrl.takeIf { it.isNotBlank() })

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
        // Same layout as the website's phone page (gamehub-detail.blade.php, `.mven`):
        // the photo stays clean — just the controls and the page dots — and the venue's
        // identity opens the white sheet below it.
        // Parallax: the hero rides up at ~0.6× the scroll speed so the content sheet
        // laps over a still-visible image instead of a hard cut.
        Box(
          modifier = Modifier
            .fillMaxWidth()
            .height(280.dp)
            .background(Color(0xFFE2E8F0))
            .graphicsLayer { translationY = scroll.value * 0.4f }
        ) {
          // One pager for every venue, even a single-photo one: swapping a plain image for a
          // pager when the detail (and its full gallery) lands would recreate the hero and
          // flash it. With one page, swiping is simply off. The pager only claims horizontal
          // drags, so a vertical drag on the photo still scrolls the page.
          val heroPager = rememberPagerState(pageCount = { images.size })
          HorizontalPager(
            state = heroPager,
            userScrollEnabled = images.size > 1,
            key = { page -> images.getOrElse(page) { page } },
            modifier = Modifier.fillMaxSize(),
          ) { page ->
            val url = images.getOrNull(page)
            AsyncImage(
              model = remember(url) {
                coil.request.ImageRequest.Builder(ctx).data(url).crossfade(200).build()
              },
              contentDescription = name,
              contentScale = ContentScale.Crop,
              modifier = Modifier.fillMaxSize()
            )
          }
          // Top + bottom scrims so the controls and dots stay legible on any photo.
          Box(
            modifier = Modifier
              .fillMaxSize()
              .background(
                Brush.verticalGradient(
                  colorStops = arrayOf(
                    0f to Color.Black.copy(alpha = 0.35f),
                    0.35f to Color.Transparent,
                    0.65f to Color.Transparent,
                    1f to Color.Black.copy(alpha = 0.25f),
                  )
                )
              )
          )
          // Centred dots, clear of the sheet that laps 24dp up over the hero.
          if (images.size > 1) {
            HeroPhotoDots(
              count = images.size,
              current = heroPager.currentPage,
              modifier = Modifier
                .align(Alignment.BottomCenter)
                .padding(bottom = 38.dp),
            )
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
          // Name + score.
          Row(verticalAlignment = Alignment.Top) {
            Text(
              text = name,
              color = HaraanColors.TextPrimary,
              fontWeight = FontWeight.ExtraBold,
              fontSize = 22.sp,
              lineHeight = 27.sp,
              letterSpacing = (-0.4).sp,
              modifier = Modifier.weight(1f),
            )
            rating.toFloatOrNull()?.takeIf { it > 0f }?.let { score ->
              Spacer(Modifier.width(12.dp))
              Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.padding(top = 4.dp),
              ) {
                Icon(Icons.Default.Star, null, tint = HaraanColors.RatingGold, modifier = Modifier.size(15.dp))
                Spacer(Modifier.width(4.dp))
                Text("%.1f".format(score), color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                (detail?.ratingsCount)?.takeIf { it > 0 }?.let { c ->
                  Spacer(Modifier.width(4.dp))
                  Text("($c)", color = HaraanColors.TextSecondary, fontSize = 12.sp)
                }
              }
            }
          }

          // Operating hours, verbatim from the venue. NOT parsed into "Open now": the
          // column is free text authored per venue, and a mis-parse would confidently
          // tell someone a closed court is open.
          (detail?.hours)?.takeIf { it.isNotBlank() }?.let { hours ->
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.Top) {
              Icon(
                Icons.Default.Schedule, null,
                tint = HaraanColors.TextSecondary,
                modifier = Modifier.size(14.dp).padding(top = 2.dp),
              )
              Spacer(Modifier.width(4.dp))
              Text(
                hours,
                color = HaraanColors.TextPrimary,
                fontWeight = FontWeight.Medium,
                fontSize = 13.sp,
                lineHeight = 18.sp,
              )
            }
          }

          // Full address (falls back to the short area label) with the "Show in Map" pill.
          val address = detail?.address?.takeIf { it.isNotBlank() } ?: detail?.location ?: venue.location
          val openVenueMap = {
            val lat = detail?.latitude; val lng = detail?.longitude
            val q = if (lat != null && lng != null) "$lat,$lng($name)" else "$name $address"
            openMap(ctx, detail?.mapLink, q)
          }
          Spacer(Modifier.height(6.dp))
          Row(verticalAlignment = Alignment.Top) {
            Icon(
              Icons.Default.LocationOn, null,
              tint = HaraanColors.TextSecondary,
              modifier = Modifier.size(14.dp).padding(top = 2.dp)
            )
            Spacer(Modifier.width(4.dp))
            Text(
              address,
              color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 18.sp,
              modifier = Modifier.weight(1f),
            )
            Spacer(Modifier.width(10.dp))
            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clip(RoundedCornerShape(50))
                .border(BorderStroke(1.dp, HaraanColors.BorderLight), RoundedCornerShape(50))
                .clickable { openVenueMap() }
                .padding(horizontal = 12.dp, vertical = 8.dp)
            ) {
              Icon(Icons.Default.Place, null, tint = HaraanColors.EventsBlue, modifier = Modifier.size(16.dp))
              Spacer(Modifier.width(6.dp))
              Text("Show in Map", color = HaraanColors.TextPrimary, fontWeight = FontWeight.SemiBold, fontSize = 13.sp, maxLines = 1, softWrap = false)
            }
          }

          // The website's embedded map, as a Static Maps image (no Maps SDK). Only with
          // real coordinates — the web embeds one only when the venue has them too.
          val mapCoords = detail?.let { d ->
            if (d.latitude != null && d.longitude != null) d.latitude to d.longitude
            else VenueMap.coordsFromMapLink(d.mapLink)
          }
          if (mapCoords != null && VenueMap.hasKey) {
            Spacer(Modifier.height(12.dp))
            AsyncImage(
              model = VenueMap.staticMapUrl(mapCoords.first, mapCoords.second, heightPx = 300),
              contentDescription = "$name location map",
              contentScale = ContentScale.Crop,
              modifier = Modifier
                .fillMaxWidth()
                .height(200.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(Color(0xFFF1F5F9))
                .border(1.dp, Color(0xFFECECF1), RoundedCornerShape(16.dp))
                .clickable { openVenueMap() },
            )
          }

          if (loading) {
            Box(Modifier.fillMaxWidth().padding(40.dp), contentAlignment = Alignment.Center) {
              CircularProgressIndicator(color = HaraanColors.EventsBlue)
            }
          }

          detail?.let { d ->
            // ── 3. Rating summary + RATE VENUE ──────────────────────────────────
            RatingCard(d, onRate = { showRating = true })

            // ── 4. Available Sports → price chart ───────────────────────────────
            val sports = d.sports.filter { it.isNotBlank() }.ifEmpty { listOfNotNull(d.category.takeIf { it.isNotBlank() }) }
            if (sports.isNotEmpty()) {
              SectionCard(title = "Available Sports") {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                  sports.forEach { sport -> SportTile(sport, onClick = onOpenPriceChart) }
                }
              }
            }

            // ── 5. Amenities ────────────────────────────────────────────────────
            if (d.amenities.isNotEmpty()) {
              SectionCard(title = "Amenities") {
                AmenityGrid(d.amenities)
              }
            }

            // ── 6. About ────────────────────────────────────────────────────────
            if (d.about.isNotBlank()) {
              SectionCard(title = "About this venue") {
                Text(d.about, color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 20.sp)
              }
            }

            // ── 7. Good to know (cancellation policy + admin-authored checklist) ──
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
                        tint = HaraanColors.EventsBlue,
                        modifier = Modifier.size(16.dp).padding(top = 2.dp)
                      )
                      Spacer(Modifier.width(8.dp))
                      Text(rule, color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 19.sp)
                    }
                  }
                }
              }
            }

            // ── 8. Location ─────────────────────────────────────────────────────
            SectionCard(title = "Location") {
              Column {
                Text(
                  buildString {
                    append(d.address.takeIf { it.isNotBlank() } ?: d.location)
                    // The web can't show this (no GPS fix); the app has a real one.
                    val dist = liveDistance ?: d.distance.takeIf { it.isNotBlank() }
                    if (!dist.isNullOrBlank()) append("  ·  $dist away")
                  },
                  color = HaraanColors.TextSecondary, fontSize = 13.sp, lineHeight = 20.sp
                )
                Spacer(Modifier.height(10.dp))
                Text(
                  "Get directions",
                  color = HaraanColors.EventsBlue,
                  fontWeight = FontWeight.Bold,
                  fontSize = 13.sp,
                  modifier = Modifier.clickable { openVenueMap() }
                )
              }
            }

            // ── 9. Reviews ──────────────────────────────────────────────────────
            if (d.reviews.isNotEmpty()) {
              SectionCard(title = "Reviews (${d.reviewsCount.coerceAtLeast(d.reviews.size)})") {
                Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                  d.reviews.forEach { ReviewRow(it) }
                }
              }
            }

            // ── 9b. FAQ — answered from this venue's own data, same as the web ──
            val faqs = remember(d) { venueFaqs(d) }
            if (faqs.isNotEmpty()) {
              SectionCard(title = "Questions about ${d.name}") {
                VenueFaqList(faqs)
              }
            }
          }

          Spacer(Modifier.height(96.dp)) // room for the sticky bar
        }
      }

      // ── Fixed overlays: back + save + share ──────────────────────────────────────
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .statusBarsPadding()
          .padding(horizontal = HeroActionDefaults.EdgePadding, vertical = 4.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
      ) {
        HeroActionButton(
          icon = HeroActionIcons.Back,
          contentDescription = "Back",
          onClick = onBack,
          iconSize = HeroActionDefaults.BackIconSize,
        )
        Row(
          horizontalArrangement = Arrangement.spacedBy(HeroActionDefaults.Spacing),
          verticalAlignment = Alignment.CenterVertically,
        ) {
          HeroToggleActionButton(
            checked = isFavorite,
            onToggle = {
              isFavorite = FavoritesStore.toggle(ctx, venue.id)
              Toast.makeText(ctx, if (isFavorite) "Saved" else "Removed", Toast.LENGTH_SHORT).show()
            },
            iconOff = HeroActionIcons.HeartOutline,
            iconOn = HeroActionIcons.HeartFilled,
            activeColor = HaraanColors.LiveRed,
            contentDescriptionOff = "Save venue",
            contentDescriptionOn = "Remove from saved",
          )
          HeroActionButton(
            icon = HeroActionIcons.Share,
            contentDescription = "Share",
            onClick = {
              runCatching {
                val share = Intent(Intent.ACTION_SEND).apply {
                  type = "text/plain"
                  putExtra(Intent.EXTRA_TEXT, "Check out $name on Haraan — ${ApiConfig.BASE_URL}")
                }
                ctx.startActivity(Intent.createChooser(share, "Share venue"))
              }
            },
          )
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
        val hasSlotsOrCourts = (detail?.courts?.isNotEmpty() == true) || (detail?.slots?.isNotEmpty() == true)
        val canBook = (detail?.isBookable ?: false) && hasSlotsOrCourts
        Button(
          onClick = { showBooking = true },
          enabled = canBook,
          colors = ButtonDefaults.buttonColors(
            containerColor = HaraanColors.EventsBlue,
            disabledContainerColor = Color(0xFFCBD5E1),
          ),
          shape = RoundedCornerShape(50),
          modifier = Modifier.height(48.dp)
        ) {
          Text(
            when {
              detail == null -> "Book Now"
              detail?.isBookable != true -> "Not bookable"
              !hasSlotsOrCourts -> "No slots available"
              else -> "Book Now"
            },
            color = Color.White,
            fontWeight = FontWeight.Bold,
            fontSize = 15.sp,
            modifier = Modifier.padding(horizontal = 20.dp)
          )
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

/** Hero page indicator — the web's dots: 6dp each, the current photo's a solid 8dp. */
@Composable
private fun HeroPhotoDots(count: Int, current: Int, modifier: Modifier = Modifier) {
  // A 12-photo gallery shouldn't grow a bar across the photo; the last dot stands for the rest.
  val shown = count.coerceAtMost(6)
  val active = current.coerceAtMost(shown - 1)
  Row(
    modifier = modifier,
    horizontalArrangement = Arrangement.spacedBy(6.dp),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    repeat(shown) { i ->
      val selected = i == active
      val size by androidx.compose.animation.core.animateDpAsState(
        targetValue = if (selected) 8.dp else 6.dp,
        animationSpec = androidx.compose.animation.core.spring(dampingRatio = 0.8f, stiffness = 500f),
        label = "heroDotSize",
      )
      Box(
        modifier = Modifier
          .size(size)
          .clip(CircleShape)
          .background(Color.White.copy(alpha = if (selected) 1f else 0.5f))
      )
    }
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

/** One "Available Sports" tile — the web's `.mven__sport`: glyph, sport, "View pricing", chevron. */
@Composable
private fun SportTile(sport: String, onClick: () -> Unit) {
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .clip(RoundedCornerShape(14.dp))
      .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(14.dp))
      .pressable { onClick() }
      .padding(16.dp),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    Icon(sportIcon(sport), null, tint = HaraanColors.GameHubDeep, modifier = Modifier.size(28.dp))
    Spacer(Modifier.width(14.dp))
    Column(Modifier.weight(1f)) {
      Text(sport, color = HaraanColors.TextPrimary, fontWeight = FontWeight.Bold, fontSize = 15.sp)
      Spacer(Modifier.height(2.dp))
      Text("View pricing", color = HaraanColors.TextSecondary, fontSize = 12.sp)
    }
    Icon(Icons.Default.KeyboardArrowRight, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(22.dp))
  }
}

/**
 * Amenities two per row, each with its own tinted icon plate — the web's `.mven__amenities`
 * grid, so the app and the site read as one product.
 */
@Composable
private fun AmenityGrid(amenities: List<String>) {
  Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
    amenities.chunked(2).forEach { pair ->
      Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        pair.forEach { a ->
          Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.weight(1f),
          ) {
            Box(
              modifier = Modifier
                .size(34.dp)
                .clip(RoundedCornerShape(10.dp))
                .background(HaraanColors.EventsBlue.copy(alpha = 0.10f)),
              contentAlignment = Alignment.Center,
            ) {
              Icon(amenityIcon(a), null, tint = HaraanColors.GameHubDeep, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(10.dp))
            Text(
              a,
              color = HaraanColors.TextPrimary,
              fontSize = 13.sp,
              maxLines = 2,
              overflow = TextOverflow.Ellipsis,
            )
          }
        }
        if (pair.size == 1) Spacer(Modifier.weight(1f))
      }
    }
  }
}

private data class VenueFaq(val question: String, val answer: String)

/**
 * The venue's FAQ, answered only from its own data — a port of the website's
 * PublicWebController::venueFaqs so both surfaces ask and answer the same things.
 */
private fun venueFaqs(d: VenueDetailData): List<VenueFaq> {
  val name = d.name
  val faqs = mutableListOf<VenueFaq>()

  val sports = d.sports.filter { it.isNotBlank() }
  if (sports.isNotEmpty()) {
    val list = if (sports.size == 1) sports[0]
      else sports.dropLast(1).joinToString(", ") + " and " + sports.last()
    val courts = if (d.courts.size > 1) " across ${d.courts.size} courts" else ""
    faqs += VenueFaq(
      "Which sports can I play at $name?",
      "$name hosts $list$courts. Pick the sport when you book a slot on Haraan.",
    )
  }

  val from = d.courts.map { it.price }.filter { it > 0 }.minOrNull() ?: d.price
  if (from > 0) {
    val peak = if (d.courts.any { it.peakPrice != null }) "; peak hours cost more" else ""
    faqs += VenueFaq(
      "How much does it cost to book $name?",
      "Slots at $name start from ₹${"%,d".format(from)} per hour$peak. Haraan shows the exact price for your court and time before you pay.",
    )
  }

  if (d.hours.isNotBlank()) {
    faqs += VenueFaq(
      "What are the timings of $name?",
      "$name is open ${d.hours}. Tap Book Now to see open slots for the next 7 days.",
    )
  }

  val address = d.address.ifBlank { d.location }.trim()
  if (address.isNotEmpty()) {
    faqs += VenueFaq(
      "Where is $name located?",
      "$name is at $address. Use \"Get directions\" on this page to open it in Google Maps.",
    )
  }

  if (d.isBookable) {
    faqs += VenueFaq(
      "How do I book $name online?",
      "Tap Book Now, choose a date, court and slot, then pay online. Your booking is confirmed instantly on Haraan.",
    )
  }
  return faqs
}

/** Expandable FAQ rows — first one open, blue +/– — like the web's `<details>` list. */
@Composable
private fun VenueFaqList(faqs: List<VenueFaq>) {
  var open by remember(faqs) { mutableStateOf(setOf(0)) }
  Column {
    faqs.forEachIndexed { i, faq ->
      val expanded = i in open
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .clickable { open = if (expanded) open - i else open + i }
          .padding(vertical = 12.dp)
          .animateContentSize()
      ) {
        Row(verticalAlignment = Alignment.Top) {
          Text(
            faq.question,
            color = HaraanColors.TextPrimary,
            fontWeight = FontWeight.Bold,
            fontSize = 14.sp,
            lineHeight = 19.sp,
            modifier = Modifier.weight(1f),
          )
          Spacer(Modifier.width(12.dp))
          Text(
            if (expanded) "–" else "+",
            color = HaraanColors.EventsBlue,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 15.sp,
          )
        }
        if (expanded) {
          Spacer(Modifier.height(8.dp))
          Text(faq.answer, color = Color(0xFF475569), fontSize = 14.sp, lineHeight = 21.sp)
        }
      }
      SectionDivider()
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

  // How far ahead this person may book — the server's answer, including their plan's priority days.
  val bookingWindow by produceState<com.haraan.app.data.BookingWindow?>(initialValue = null, venue.id) {
    value = VenueRepository().getBookingWindow(venue.id, TokenStore.getSignedInToken(ctx))
  }

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
  var isPaidOnline by remember { mutableStateOf(false) }
  val venueRepo = remember { VenueRepository() }
  var sheetAvailability by remember { mutableStateOf<Map<Int, SlotAvailability>?>(null) }
  // True until the first live read for the picked day lands: the grid shows a skeleton,
  // not the previous day's states.
  var availLoading by remember { mutableStateOf(true) }

  // The live read, then a quiet re-check every 15s while the sheet is open — the same
  // cadence as the website's slot board — so a slot someone else takes turns "Booked".
  LaunchedEffect(venue.id, selectedDate) {
    sheetAvailability = null
    availLoading = true
    while (true) {
      venueRepo.getSlotAvailability(venue.id, selectedDate, TokenStore.getSignedInToken(ctx))?.let {
        sheetAvailability = it
      }
      availLoading = false
      kotlinx.coroutines.delay(15_000)
    }
  }

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
  // The fee the server quoted for this order, once it has quoted one. Until then the
  // venue's own rule stands in — see [feeRs] below. It used to be a plain 0 that only
  // filled in if the customer happened to try a coupon, so a fee-charging venue showed
  // a total on the summary that wasn't the total Razorpay then asked for.
  var quotedFeeRs by remember { mutableStateOf<Int?>(null) }

  // Only the courts that can host the chosen sport. A court with no sports listed hosts any.
  // One physical court shared by two sports appears under both — booking it blocks the other.
  val courtsForSport = remember(venue, selectedSport) {
    venue.courts.filter { it.sports.isEmpty() || it.sports.any { s -> s.equals(selectedSport, ignoreCase = true) } }
  }
  val courtNeeded = courtsForSport.isNotEmpty()

  // The base rate the rate line states once: the court's own, else the venue's.
  val baseRate = selectedCourt?.price?.takeIf { it > 0 } ?: venue.price
  // Every time card for the chosen day — open, booked and closed alike, like the website's
  // grid — so a taken hour reads as taken instead of silently missing. Once a court is
  // chosen the cards narrow to the times that run its sport; either side listing no sports
  // means no restriction. Today, the hours already gone are dropped (checkout refuses them).
  val dayTiles: List<SlotTile> = remember(venue, selectedDate, selectedSport, selectedCourt, sheetAvailability) {
    val courtSports = selectedCourt?.sports.orEmpty()
    val nowMin = LocalTime.now().let { it.hour * 60 + it.minute }
    val isToday = selectedDate == LocalDate.now()
    // Same day rule as the slot rail and the website: that weekday's rows, else the
    // every-day rows. Matching the weekday alone made "Every day" slots unbookable here.
    slotsForDay(venue.slots, selectedDate).mapNotNull { slot ->
      val start = timeToMinutes(slot.time) ?: return@mapNotNull null
      if (isToday && start <= nowMin) return@mapNotNull null
      val forSport = slot.sports.isEmpty() || slot.sports.any { it.equals(selectedSport, ignoreCase = true) }
      val forCourt = slot.sports.isEmpty() || courtSports.isEmpty() || slot.sports.any { it in courtSports }
      if (!forSport || !forCourt) return@mapNotNull null
      val live = sheetAvailability?.get(slot.id)
      val state = when {
        live == null -> if (slot.available) SlotTileState.OPEN else SlotTileState.CLOSED
        live.state == SlotAvailability.State.CLOSED -> SlotTileState.CLOSED
        live.state == SlotAvailability.State.OPEN && live.courtsFree > 0 -> SlotTileState.OPEN
        else -> SlotTileState.BOOKED
      }
      val rate = selectedCourt?.let { courtRate(it, selectedDate, slot.time, venue.price) }
        ?: slot.price.takeIf { it > 0 } ?: venue.price
      SlotTile(slot, start, state, rate, isPeak = rate > baseRate)
    }
  }
  // The venue's slot length (30 or 60 min): the smallest gap between two cards of the day.
  val slotLenMin = remember(dayTiles) {
    dayTiles.zipWithNext { a, b -> b.startMin - a.startMin }.filter { it > 0 }.minOrNull()?.coerceAtMost(60) ?: 60
  }
  // The booking is ONE run of back-to-back open slots: [selectedSlot] for [duration] slots
  // (what the server's `duration` means — VenueSlot lengths, not hours).
  val pickedTiles: List<SlotTile> = run {
    val si = dayTiles.indexOfFirst { it.slot.id == selectedSlot?.id }
    if (si < 0) emptyList() else dayTiles.subList(si, (si + duration).coerceAtMost(dayTiles.size))
  }
  // Court can be chosen after the time, and the grid re-checks live. If that drops the time
  // already picked, let it go; if it breaks the run, keep the part that still holds — never
  // submit a pair the server will refuse.
  LaunchedEffect(dayTiles) {
    // On the review page — and while our own checkout hold is open — the live read shows
    // OUR hold as booked. Dropping the pick then would blank the order being paid for.
    if (reviewing || submitting) return@LaunchedEffect
    val si = dayTiles.indexOfFirst { it.slot.id == selectedSlot?.id }
    if (selectedSlot != null && (si < 0 || dayTiles[si].state != SlotTileState.OPEN)) {
      selectedSlot = null; duration = 1
    } else if (si >= 0) {
      var run = 1
      while (run < duration && si + run < dayTiles.size &&
        dayTiles[si + run].state == SlotTileState.OPEN &&
        dayTiles[si + run].startMin - dayTiles[si + run - 1].startMin == slotLenMin
      ) run++
      if (run != duration) duration = run
    }
  }
  // Tap rules for the grid, so a range is built the way people expect: the first tap picks
  // a start, the card right after the run (or right before it) grows it, tapping either end
  // shrinks it, and any other card starts again from there. Twelve slots at most.
  val onTileTap: (SlotTile) -> Unit = tap@{ t ->
    val ti = dayTiles.indexOf(t)
    val si = dayTiles.indexOfFirst { it.slot.id == selectedSlot?.id }
    fun joined(a: Int, b: Int) = a in dayTiles.indices && b in dayTiles.indices &&
      dayTiles[a].state == SlotTileState.OPEN && dayTiles[b].state == SlotTileState.OPEN &&
      dayTiles[b].startMin - dayTiles[a].startMin == slotLenMin
    if (si < 0) { selectedSlot = t.slot; duration = 1; return@tap }
    val ei = si + duration - 1
    when {
      ti == ei + 1 && joined(ei, ti) && duration < 12 -> duration += 1
      ti == si - 1 && joined(ti, si) && duration < 12 -> { selectedSlot = t.slot; duration += 1 }
      ti == si && duration == 1 -> { selectedSlot = null; duration = 1 }
      ti == si -> { selectedSlot = dayTiles[si + 1].slot; duration -= 1 }
      ti == ei -> duration -= 1
      else -> { selectedSlot = t.slot; duration = 1 }
    }
  }
  // Which part of the day the grid shows. Follows the pick; else the first part with an
  // open card. Reset when the day or court changes.
  var activePart by remember { mutableStateOf<DayPart?>(null) }
  LaunchedEffect(selectedDate, selectedCourt, selectedSport, dayTiles.isEmpty()) {
    activePart = pickedTiles.firstOrNull()?.let { partOf(it.startMin) }
      ?: dayTiles.firstOrNull { it.state == SlotTileState.OPEN }?.let { partOf(it.startMin) }
      ?: dayTiles.firstOrNull()?.let { partOf(it.startMin) }
  }
  var showCalendar by remember { mutableStateOf(false) }
  // Per-court price wins over the slot/venue price when a court is chosen, and peak pricing
  // wins over that when the picked day/time falls in the court's peak window.
  val perHour = selectedCourt?.let { courtRate(it, selectedDate, selectedSlot?.time, venue.price) }
    ?: selectedSlot?.price?.takeIf { it > 0 }
    ?: venue.price
  val isPeakNow = selectedCourt?.let { isCourtPeak(it, selectedDate, selectedSlot?.time) } == true
  val subtotalRs = perHour * duration
  // The venue's fee rule until the server quotes its own for this order. Both are the
  // same arithmetic on the same two columns, so the summary reads the charge either way.
  val feeRs = quotedFeeRs ?: venue.convenienceFeeOn(subtotalRs)
  // The same fee, itemised: "Convenience fee", "Floodlight charge"… (Venue::feeLinesFor).
  val feeLines = venue.feeLinesOn(subtotalRs)
  // Pulse tax on the post-discount subtotal (/control → Fees), as reserveVenue charges it.
  val taxRs = venue.taxOn(subtotalRs, discountRs)
  // Fee added, discount taken off, tax on top — mirroring reserveVenue's order exactly.
  val total = (subtotalRs + feeRs - discountRs).coerceAtLeast(0) + taxRs

  // A coupon is quoted against ONE subtotal. Change the duration, court or slot and that
  // quote is about a different order — and since the code now travels with the booking,
  // the server would resolve a discount the summary never showed. Drop it and say so,
  // rather than let the screen and the charge tell two stories.
  LaunchedEffect(subtotalRs) {
    if (appliedCode != null) {
      appliedCode = null
      discountRs = 0
      quotedFeeRs = null
      couponMessage = "Re-apply your coupon for the new total."
    }
  }
  val canBook = selectedSlot != null && (!courtNeeded || selectedCourt != null) && !submitting
  // The picked run in minutes: start, end, length.
  val runStart = pickedTiles.firstOrNull()?.startMin ?: selectedSlot?.let { timeToMinutes(it.time) }
  val runLen = duration * slotLenMin
  // The chosen window as "7:00 PM – 8:00 PM" (null when the time string can't be parsed).
  val endLabel = runStart?.let { s ->
    val (a, ap) = clockParts(s); val (b, bp) = clockParts(s + runLen)
    "$a $ap – $b $bp"
  }
  val signedIn = remember(success, reviewing) { TokenStore.getSignedInToken(ctx) != null }
  // What the Checkout key says — a disabled key always names the next step instead of
  // leaving the user guessing why it's greyed out.
  val bookLabel = when {
    courtNeeded && selectedCourt == null -> "Choose a court"
    selectedSlot == null -> "Pick a time"
    !signedIn -> "Sign in to checkout"
    else -> "Checkout"
  }

  // How the buyer chose to pay on the review page: UPI (a Razorpay UPI QR any UPI app can
  // pay, shown even when Razorpay's checkout lists no UPI app on this phone) or Razorpay's
  // checkout for cards / netbanking / wallets.
  var payByUpiQr by remember { mutableStateOf(true) }
  // The UPI QR page's state: the held order it pays and the QR itself.
  var upiQr by remember { mutableStateOf<BookingRepository.UpiQr?>(null) }
  var qrOrder by remember { mutableStateOf<BookingResult.PaymentRequired?>(null) }
  var qrToken by remember { mutableStateOf<String?>(null) }

  // Paid and confirmed server-side, by any route — print the slip.
  val showPaid: (BookingResult.Success) -> Unit = { done ->
    upiQr = null
    submitting = false
    isPaidOnline = true
    bookedCode = done.ticketCode
    result = done.message
    success = true
  }

  // Razorpay's own checkout for a held order. Every outcome owns its reset.
  val openRazorpay: (BookingResult.PaymentRequired, String) -> Unit = { r, token ->
    val activity = ctx as? android.app.Activity
    if (activity == null) {
      // No Activity to host the sheet: drop the hold rather than leave the
      // court blocked for 15 minutes on a checkout that can't open.
      scope.launch { BookingRepository().releaseOrder(token, r.orderId) }
      result = "Couldn't open payment."
      submitting = false
    } else {
      // Arm the one-shot handler BEFORE opening the sheet — the result
      // arrives via HomeActivity → PaymentBridge.
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
                is BookingResult.Success -> showPaid(confirmed)
                else -> {
                  val status = BookingRepository().checkOrderStatus(token, r.orderId)
                  if (status is BookingResult.Success) {
                    showPaid(status)
                  } else {
                    result = (confirmed as? BookingResult.Error)?.message
                      ?: "Payment could not be verified."
                  }
                }
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
        // The code the summary discounted by. Without it the server priced the order
        // at full rate: the screen said one number and Razorpay asked for another.
        couponCode = appliedCode,
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
        // uses. UPI goes by the order's UPI QR; anything else by Razorpay's checkout.
        is BookingResult.PaymentRequired -> {
          if (!payByUpiQr) {
            openRazorpay(r, token)
          } else {
            when (val q = BookingRepository().createUpiQr(token, r.orderId)) {
              is BookingRepository.UpiQrResult.Ready -> {
                qrOrder = r
                qrToken = token
                upiQr = q.qr
              }
              is BookingRepository.UpiQrResult.Confirmed -> showPaid(q.booking)
              // No QR (Razorpay's QR product off, or a network blip): don't strand the
              // hold — say so and open Razorpay's checkout on the same order.
              is BookingRepository.UpiQrResult.Failed -> {
                result = q.message
                openRazorpay(r, token)
              }
            }
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
          // A soft key light on the stage, so the machine sits in a pool rather than a void.
          .background(Brush.radialGradient(listOf(Color(0xFF2A3644).copy(alpha = 0.55f), Color.Transparent)))
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
          // Only show 'Pay at the venue' if not paid online and amount > 0.
          payAtVenue = !isPaidOnline && total > 0,
          onDone = onDismiss,
          venueAddress = venue.address.ifBlank { venue.location }.trim().ifBlank { null },
          onOpenMap = {
            val lat = venue.latitude; val lng = venue.longitude
            openMap(ctx, venue.mapLink, if (lat != null && lng != null) "$lat,$lng(${venue.name})" else "${venue.name} ${venue.address}")
          },
        )
      }
      return@Dialog
    }

    // ── Order summary: a PAGE, not a step ────────────────────────────────────────
    // As a section inside the bottom sheet it still read as a dialog — the venue page
    // showed through behind it and it inherited the sheet's rounded lip. A checkout that
    // decides money should own the screen: opaque, full height, its own app bar, its own
    // back. Same shape as the event order summary.
    // ── Pay by UPI: the order's UPI QR, over the review page ──
    val qr = upiQr
    if (qr != null) {
      // Leaving drops the hold (and closes the QR server-side) so the court isn't blocked.
      val leave: () -> Unit = {
        val r = qrOrder; val token = qrToken
        upiQr = null
        submitting = false
        result = "Payment cancelled — the slot has been released."
        if (r != null && token != null) scope.launch { BookingRepository().releaseOrder(token, r.orderId) }
      }
      BackHandler(enabled = true, onBack = leave)
      UpiQrPayPage(
        qr = qr,
        venueName = venue.name,
        checkPaid = {
          val token = qrToken; val r = qrOrder
          if (token == null || r == null) null
          else BookingRepository().upiQrStatus(token, r.orderId) as? BookingResult.Success
        },
        onPaid = showPaid,
        onUseRazorpay = {
          val r = qrOrder; val token = qrToken
          upiQr = null
          if (r != null && token != null) openRazorpay(r, token) else submitting = false
        },
        onBack = leave,
      )
      return@Dialog
    }

    if (reviewing) {
      BackHandler(enabled = true) { reviewing = false }
      VenueReviewPage(
        payByUpi = payByUpiQr,
        onPayByUpi = { payByUpiQr = it },
        venue = venue,
        sport = selectedSport,
        date = selectedDate,
        courtName = selectedCourt?.name,
        windowLabel = endLabel ?: (selectedSlot?.time ?: ""),
        lengthMin = runLen,
        slotCount = duration,
        subtotal = subtotalRs,
        fee = feeRs,
        feeLines = feeLines,
        discount = discountRs,
        tax = taxRs,
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
                  quotedFeeRs = r.fee.toInt()
                }
              }
            }
          }
        },
        onRemoveCoupon = {
          appliedCode = null
          discountRs = 0
          // Back to the venue's own fee rule — the quote came with the coupon.
          quotedFeeRs = null
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

    // ── The slot sheet — the website's phone sheet, drawn natively ─────────────
    // Date strip → court cards → one Morning/Afternoon/Evening control over one grid of
    // time cards; the foot is a ticket stub of what you picked and the Checkout key. No
    // money in the foot (the bill is the review page's job), the rate said once above
    // the grid, and a card only carries a price when it differs (peak).
    val isToday = selectedDate == LocalDate.now()
    val nowMin = LocalTime.now().let { it.hour * 60 + it.minute }
    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.BottomCenter) {
      // Tap above the sheet to close it, like the site's backdrop.
      Box(
        Modifier
          .fillMaxSize()
          .clickable(
            interactionSource = remember { androidx.compose.foundation.interaction.MutableInteractionSource() },
            indication = null,
            onClick = onDismiss,
          ),
      )
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .fillMaxHeight(0.9f)
          .shadow(24.dp, RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp))
          .clip(RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp))
          .background(Color.White)
          // Swallow taps on the sheet so they don't reach the backdrop.
          .clickable(
            interactionSource = remember { androidx.compose.foundation.interaction.MutableInteractionSource() },
            indication = null,
            onClick = {},
          ),
      ) {
        BookSheetHead(
          title = "Book a slot",
          sub = listOf(venue.name, venue.location).filter { it.isNotBlank() }.joinToString(" · "),
          onClose = onDismiss,
        )

        Column(
          modifier = Modifier
            .weight(1f)
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp),
        ) {
          Spacer(Modifier.height(10.dp))
          BookDateStrip(
            selected = selectedDate,
            lastDate = bookingWindow?.lastDate,
            onPick = { selectedDate = it; selectedSlot = null; duration = 1 },
            onOpenCalendar = { showCalendar = true },
          )
          bookingWindow?.takeIf { it.priorityDays > 0 }?.let { w ->
            Text(
              "Book up to ${w.days + w.priorityDays} days ahead, including ${w.priorityDays} priority ${if (w.priorityDays == 1) "day" else "days"} from your plan.",
              color = BookInk.Muted, fontSize = 12.sp, lineHeight = 16.sp,
              modifier = Modifier.padding(top = 8.dp),
            )
          }

          // Sport — only when there's a choice to make. Changing it re-picks the court,
          // since courts are filtered to the chosen sport.
          if (sports.size > 1) {
            Spacer(Modifier.height(18.dp))
            BookLabel("Sport")
            Spacer(Modifier.height(8.dp))
            BookSportChips(sports, selectedSport, icon = { sportIcon(it) }) { picked ->
              selectedSport = picked
              selectedCourt = venue.courts
                .filter { it.sports.isEmpty() || it.sports.any { s -> s.equals(picked, ignoreCase = true) } }
                .singleOrNull()
            }
          }

          // Court — a card per court with its drawing and its own rate, because the
          // court decides the price.
          if (courtNeeded) {
            Spacer(Modifier.height(18.dp))
            BookLabel("Choose a court", if (courtsForSport.size > 1) "${courtsForSport.size} courts" else null)
            Spacer(Modifier.height(2.dp))
            Row(
              modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(bottom = 8.dp),
              horizontalArrangement = Arrangement.spacedBy(4.dp),
            ) {
              courtsForSport.forEach { c ->
                val rate = c.price.takeIf { it > 0 } ?: venue.price
                CourtCard(
                  name = c.name,
                  rate = rate,
                  peakRate = c.peakPrice?.takeIf { it > rate },
                  active = c.id == selectedCourt?.id,
                ) { selectedCourt = c }
              }
            }
          }

          Spacer(Modifier.height(14.dp))
          val note: @Composable (String) -> Unit = { text ->
            Text(text, color = BookInk.Muted, fontSize = 13.5.sp, lineHeight = 19.sp, modifier = Modifier.padding(vertical = 8.dp))
          }
          when {
            courtNeeded && selectedCourt == null -> note("Choose a court to see its open times.")
            availLoading && sheetAvailability == null -> SlotTileSkeleton()
            dayTiles.isEmpty() -> note(if (isToday) "No more slots today. Pick another day." else "No slots on this day. Pick another day.")
            else -> {
              val parts = DayPart.values().map { p ->
                val ts = dayTiles.filter { partOf(it.startMin) == p }
                PartSummary(
                  part = p,
                  total = ts.size,
                  open = ts.count { it.state == SlotTileState.OPEN },
                  booked = ts.count { it.state == SlotTileState.BOOKED },
                  hasPick = pickedTiles.any { partOf(it.startMin) == p },
                )
              }
              DayPartControl(parts, activePart) { activePart = it }
              Spacer(Modifier.height(12.dp))
              // The price once, and the peak rate only where this court has one.
              val peakCourt = selectedCourt?.takeIf { (it.peakPrice ?: 0) > baseRate }
              Row(
                modifier = Modifier.fillMaxWidth().padding(horizontal = 2.dp),
                verticalAlignment = Alignment.Bottom,
              ) {
                Text("₹${inr(baseRate)}", color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 14.sp)
                Text(" / hour", color = BookInk.Muted, fontSize = 12.5.sp, modifier = Modifier.weight(1f))
                if (peakCourt != null) {
                  Text(
                    "Peak ₹${inr(peakCourt.peakPrice ?: 0)} · ${courtPeakWhen(peakCourt)}",
                    color = BookInk.Amber, fontWeight = FontWeight.SemiBold, fontSize = 12.sp,
                    maxLines = 1, overflow = TextOverflow.Ellipsis,
                  )
                }
              }
              Spacer(Modifier.height(10.dp))
              // Today only: how soon the first open slot starts — a real clock.
              val soon = if (isToday) {
                dayTiles.firstOrNull { it.state == SlotTileState.OPEN }?.takeIf { it.startMin - nowMin <= 240 }
              } else null
              val shown = dayTiles.filter { partOf(it.startMin) == activePart }
              if (shown.isEmpty()) {
                note("Nothing on in the ${activePart?.label?.lowercase() ?: "day"}. Try another part of the day.")
              } else {
                SlotTileGrid(
                  tiles = shown,
                  picked = pickedTiles.map { it.slot.id }.toSet(),
                  soonId = soon?.slot?.id,
                  soonText = soon?.let { startsIn(it.startMin - nowMin) },
                  onTap = onTileTap,
                )
              }
            }
          }
          Spacer(Modifier.height(20.dp))
        }

        // ── Foot: the stub and the key, outside the scroll, under the thumb ──
        Column(
          modifier = Modifier
            .fillMaxWidth()
            .shadow(elevation = 14.dp, clip = false)
            .background(Color.White)
            .navigationBarsPadding()
            .padding(horizontal = 16.dp, vertical = 12.dp),
        ) {
          val start = runStart?.takeIf { selectedSlot != null }
          val stubRange = start?.let { s ->
            val (a, ap) = clockParts(s); val (b, bp) = clockParts(s + runLen)
            (if (ap == bp) "$a – $b" else "$a $ap – $b") to bp
          }
          TicketStub(
            date = selectedDate,
            what = listOfNotNull(selectedSport.takeIf { it.isNotBlank() }, selectedCourt?.name).joinToString(" · ").ifBlank { "Your slot" },
            range = stubRange?.first,
            ampm = stubRange?.second,
            facts = start?.let { s ->
              "${lengthLabel(runLen)} of play" + if (isToday && s > nowMin) " · starts ${startsIn(s - nowMin)}" else ""
            },
            courtName = selectedCourt?.name,
            onClear = { selectedSlot = null; duration = 1 },
          )
          Spacer(Modifier.height(12.dp))
          result?.let {
            Text(it, color = BookInk.Red, fontSize = 12.5.sp, lineHeight = 17.sp)
            Spacer(Modifier.height(8.dp))
          }
          CheckoutKey(label = bookLabel, enabled = canBook, busy = submitting) {
            val slot = selectedSlot
            if (slot != null) {
              // Sign in first, with the selection in hand; the sheet comes straight back.
              if (TokenStore.getSignedInToken(ctx) == null) {
                onNeedsLogin(PendingBooking(slot, selectedDate, selectedCourt, duration))
              } else {
                result = null
                reviewing = true
              }
            }
          }
        }
      }
    }

    if (showCalendar) {
      BookCalendarDialog(
        selected = selectedDate,
        lastDate = bookingWindow?.lastDate,
        onDismiss = { showCalendar = false },
      ) { selectedDate = it; selectedSlot = null; duration = 1 }
    }
  }
}

/**
 * "Review booking" — what you're about to book, priced, before anything is committed.
 * The website's review page (`.vco`) drawn natively: a grey page, a white bar with a round
 * back key, then three cards — the booking, the price details, the venue's own policies —
 * and the pay key pinned to the bottom. Every amount is the one the charge will use: the
 * venue's fee rules, the server's coupon quote, Pulse tax on top, as reserveVenue does.
 */
@Composable
private fun VenueReviewPage(
  payByUpi: Boolean,
  onPayByUpi: (Boolean) -> Unit,
  venue: VenueDetailData,
  sport: String,
  date: LocalDate,
  courtName: String?,
  windowLabel: String,
  lengthMin: Int,
  slotCount: Int,
  subtotal: Int,
  fee: Int,
  feeLines: List<VenueFeeLine> = emptyList(),
  discount: Int,
  tax: Int,
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
  var couponOpen by remember { mutableStateOf(couponInput.isNotBlank()) }
  var breakupOpen by remember { mutableStateOf(false) }

  Column(
    modifier = Modifier
      .fillMaxSize()
      .background(BookInk.Page),
  ) {
    // Bar
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .background(Color.White)
        .statusBarsPadding()
        .padding(horizontal = 12.dp, vertical = 10.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Box(
        modifier = Modifier
          .pressable(haptic = Feel.SELECT, onClick = onBack)
          .size(38.dp)
          .clip(CircleShape)
          .background(BookInk.Chip),
        contentAlignment = Alignment.Center,
      ) {
        Icon(Icons.Default.ArrowBack, "Back to slots", tint = BookInk.Ink, modifier = Modifier.size(20.dp))
      }
      Spacer(Modifier.width(10.dp))
      Text("Review booking", color = BookInk.Ink, fontWeight = FontWeight.Bold, fontSize = 18.sp, letterSpacing = (-0.36).sp)
    }

    Column(
      modifier = Modifier
        .weight(1f)
        .verticalScroll(rememberScrollState())
        .padding(12.dp),
      verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
      // ── What you're booking ──
      ReviewCard {
        Row(verticalAlignment = Alignment.Top) {
          Column(Modifier.weight(1f)) {
            Text(venue.name, color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 17.sp, lineHeight = 21.sp)
            venue.location.takeIf { it.isNotBlank() }?.let {
              Text(it, color = BookInk.Muted, fontSize = 13.sp, modifier = Modifier.padding(top = 2.dp))
            }
          }
          if (sport.isNotBlank()) {
            Spacer(Modifier.width(12.dp))
            Text(
              sport,
              color = BookInk.BlueDeep, fontWeight = FontWeight.Bold, fontSize = 13.sp, maxLines = 1,
              modifier = Modifier
                .clip(RoundedCornerShape(99.dp))
                .background(Color(0xFFEFF6FF))
                .padding(horizontal = 10.dp, vertical = 5.dp),
            )
          }
        }
        Spacer(Modifier.height(14.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(BookInk.Hair))
        Column(
          modifier = Modifier.padding(top = 14.dp, bottom = 6.dp),
          verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
          Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(18.dp), contentAlignment = Alignment.Center) {
              CourtDrawing(color = BookInk.Muted, modifier = Modifier.size(width = 13.dp, height = 17.dp))
            }
            Spacer(Modifier.width(10.dp))
            Text(courtName ?: "Any court", color = BookInk.Ink2, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
          }
          Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Default.CalendarMonth, null, tint = BookInk.Muted, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(10.dp))
            Text(
              date.format(DateTimeFormatter.ofPattern("d MMMM yyyy, EEEE", Locale.ENGLISH)),
              color = BookInk.Ink2, fontWeight = FontWeight.SemiBold, fontSize = 14.sp,
            )
          }
        }
        Row(
          modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp).padding(vertical = 8.dp),
          verticalAlignment = Alignment.CenterVertically,
        ) {
          Icon(Icons.Default.Schedule, null, tint = BookInk.Muted, modifier = Modifier.size(18.dp))
          Spacer(Modifier.width(10.dp))
          Column(Modifier.weight(1f)) {
            Text(windowLabel, color = BookInk.Ink, fontWeight = FontWeight.Bold, fontSize = 14.sp)
            Text(
              lengthLabel(lengthMin) + if (slotCount > 1) " · $slotCount slots" else "",
              color = BookInk.Muted, fontSize = 12.sp, modifier = Modifier.padding(top = 1.dp),
            )
          }
          Text("₹${inr(subtotal)}", color = BookInk.Ink2, fontWeight = FontWeight.Bold, fontSize = 14.sp)
        }
      }

      // ── Price details ──
      ReviewCard {
        Text("Price details", color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 17.sp, letterSpacing = (-0.34).sp)
        Spacer(Modifier.height(8.dp))
        if (!error.isNullOrBlank()) {
          Text(
            error, color = Color(0xFFB91C1C), fontSize = 13.sp, lineHeight = 18.sp,
            modifier = Modifier
              .padding(top = 4.dp, bottom = 4.dp)
              .fillMaxWidth()
              .clip(RoundedCornerShape(12.dp))
              .background(Color(0xFFFEF2F2))
              .padding(horizontal = 12.dp, vertical = 10.dp),
          )
        }
        ReviewRow("Court price ($slotCount slot${if (slotCount == 1) "" else "s"})", "₹${inr(subtotal)}")

        // Coupon — checked against the SERVER, and the discount shown is the one it
        // returned; the app never computes an offer it then can't honour at the charge.
        when {
          appliedCode != null -> Row(
            modifier = Modifier
              .padding(vertical = 4.dp)
              .fillMaxWidth()
              .clip(RoundedCornerShape(12.dp))
              .background(Color(0xFFF0FDF4))
              .border(1.dp, Color(0xFFBBF7D0), RoundedCornerShape(12.dp))
              .padding(horizontal = 12.dp, vertical = 11.dp),
            verticalAlignment = Alignment.CenterVertically,
          ) {
            Icon(Icons.Default.Check, null, tint = BookInk.Save, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) {
              Row {
                Text(appliedCode, color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 14.sp, letterSpacing = 0.5.sp)
                Text(" applied", color = BookInk.Ink2, fontSize = 14.sp)
              }
              Text("You save ₹${inr(discount)}", color = BookInk.Save, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
            }
            Text(
              "Remove", color = BookInk.Blue, fontWeight = FontWeight.Bold, fontSize = 13.sp,
              modifier = Modifier.pressable(haptic = Feel.SELECT, onClick = onRemoveCoupon).padding(6.dp),
            )
          }
          !couponOpen -> Row(
            modifier = Modifier
              .padding(vertical = 4.dp)
              .pressable(haptic = Feel.SELECT) { couponOpen = true }
              .fillMaxWidth()
              .height(50.dp)
              .clip(RoundedCornerShape(12.dp))
              .background(Color(0xFFF8FBFF))
              .drawBehind {
                drawRoundRect(
                  Color(0xFFBFDBFE), cornerRadius = androidx.compose.ui.geometry.CornerRadius(12.dp.toPx()),
                  style = androidx.compose.ui.graphics.drawscope.Stroke(
                    1.dp.toPx(),
                    pathEffect = androidx.compose.ui.graphics.PathEffect.dashPathEffect(floatArrayOf(8f, 6f)),
                  ),
                )
              }
              .padding(horizontal = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
          ) {
            Icon(Icons.Default.LocalOffer, null, tint = BookInk.BlueDeep, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(10.dp))
            Text("Apply coupon", color = BookInk.BlueDeep, fontWeight = FontWeight.Bold, fontSize = 14.5.sp, modifier = Modifier.weight(1f))
            Icon(Icons.Default.KeyboardArrowRight, null, tint = BookInk.BlueDeep, modifier = Modifier.size(20.dp))
          }
          else -> Row(
            modifier = Modifier.padding(vertical = 4.dp).fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
          ) {
            Row(
              modifier = Modifier
                .weight(1f)
                .height(46.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(Color.White)
                .border(1.3.dp, Color(0xFFDBE3EE), RoundedCornerShape(12.dp))
                .padding(horizontal = 14.dp),
              verticalAlignment = Alignment.CenterVertically,
            ) {
              BasicTextField(
                value = couponInput,
                onValueChange = { onCouponChange(it.uppercase()) },
                singleLine = true,
                cursorBrush = SolidColor(BookInk.Blue),
                // Fully qualified: this file's `TextStyle` is java.time.format's, used for
                // the date labels.
                textStyle = androidx.compose.ui.text.TextStyle(
                  fontSize = 16.sp,
                  color = BookInk.Ink,
                  fontWeight = FontWeight.Bold,
                  letterSpacing = 0.64.sp,
                ),
                modifier = Modifier.weight(1f),
                decorationBox = { inner ->
                  if (couponInput.isEmpty()) {
                    Text("Enter coupon code", color = BookInk.Faint, fontSize = 15.sp)
                  }
                  inner()
                },
              )
            }
            Spacer(Modifier.width(8.dp))
            Box(
              modifier = Modifier
                .then(
                  if (couponBusy || couponInput.isBlank()) Modifier
                  else Modifier.pressable(haptic = Feel.SELECT, onClick = onApplyCoupon)
                )
                .height(46.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(if (couponInput.isBlank()) Color(0xFFCBD5E1) else BookInk.Blue)
                .padding(horizontal = 18.dp),
              contentAlignment = Alignment.Center,
            ) {
              if (couponBusy) {
                CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
              } else {
                Text("Apply", color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 14.5.sp)
              }
            }
          }
        }
        if (!couponMessage.isNullOrBlank()) {
          Text(
            couponMessage,
            color = if (appliedCode != null) BookInk.Save else BookInk.Red,
            fontSize = 12.5.sp, lineHeight = 17.sp,
            modifier = Modifier.padding(top = 2.dp, bottom = 4.dp),
          )
        }

        // Fees and tax as one line, with the breakup a tap away — the site's shape.
        // One line per fee while they add up to what the server quoted; if a coupon quote
        // came back with a different fee, show that one number rather than lines that disagree.
        val lines = if (feeLines.isNotEmpty() && feeLines.sumOf { it.amount } == fee) feeLines.map { it.label to it.amount }
        else if (fee > 0) listOf("Booking fee" to fee) else emptyList()
        if (fee + tax > 0) {
          val title = when {
            tax > 0 -> if (lines.isNotEmpty()) "Fees & taxes" else "Taxes"
            lines.size == 1 -> lines.first().first
            else -> "Fees"
          }
          ReviewRow(title, "₹${inr(fee + tax)}", sub = {
            Text(
              if (breakupOpen) "Hide breakup" else "See breakup",
              color = BookInk.Blue, fontWeight = FontWeight.Bold, fontSize = 13.sp,
              modifier = Modifier.padding(top = 2.dp).pressable(haptic = Feel.SELECT) { breakupOpen = !breakupOpen },
            )
          })
          if (breakupOpen) {
            Row(Modifier.fillMaxWidth().padding(bottom = 6.dp)) {
              Box(Modifier.width(2.dp).height((((lines.size + if (tax > 0) 1 else 0)) * 26).dp).background(BookInk.Line))
              Column(Modifier.padding(start = 12.dp)) {
                lines.forEach { (label, amount) -> BreakupLine(label, "₹${inr(amount)}") }
                if (tax > 0) BreakupLine(venue.taxLabel, "₹${inr(tax)}")
              }
            }
          }
        }
        if (discount > 0) {
          ReviewRow(
            appliedCode?.let { "Coupon $it" } ?: "Discount", "−₹${inr(discount)}",
            labelColor = BookInk.Save, valueColor = BookInk.Save,
          )
        }
        if (isPeak) {
          Text(
            "Peak rate applies at this time", color = BookInk.Amber, fontSize = 12.sp, fontWeight = FontWeight.SemiBold,
            modifier = Modifier.padding(bottom = 4.dp),
          )
        }

        Box(Modifier.padding(top = 6.dp).fillMaxWidth().height(1.dp).background(BookInk.Line))
        Row(
          Modifier.fillMaxWidth().padding(top = 12.dp, bottom = 10.dp),
          horizontalArrangement = Arrangement.SpaceBetween,
          verticalAlignment = Alignment.CenterVertically,
        ) {
          Text("Total amount", color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 17.sp)
          Text("₹${inr(total)}", color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 18.sp)
        }
        Row(
          Modifier.fillMaxWidth().padding(top = 2.dp, bottom = 10.dp),
          horizontalArrangement = Arrangement.SpaceBetween,
        ) {
          Text("Payable now", color = BookInk.Muted, fontSize = 13.5.sp)
          Text("₹${inr(total)}", color = BookInk.BlueDeep, fontWeight = FontWeight.Bold, fontSize = 13.5.sp)
        }
        Row(Modifier.padding(top = 2.dp), verticalAlignment = Alignment.CenterVertically) {
          Icon(Icons.Default.Lock, null, tint = BookInk.Faint, modifier = Modifier.size(13.dp))
          Spacer(Modifier.width(4.dp))
          Text("Secure payment via Razorpay", color = BookInk.Faint, fontSize = 12.sp)
        }
      }

      // ── Pay with — UPI always on the list, whatever this phone has installed ──
      if (total > 0) {
        ReviewCard {
          Text("Pay with", color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 17.sp, letterSpacing = (-0.34).sp)
          Spacer(Modifier.height(10.dp))
          PayMethodRow(
            title = "UPI",
            sub = "Scan a QR or open any UPI app — GPay, PhonePe, Paytm, BHIM",
            selected = payByUpi,
          ) { onPayByUpi(true) }
          Spacer(Modifier.height(8.dp))
          PayMethodRow(
            title = "Cards, netbanking & wallets",
            sub = "Razorpay secure checkout",
            selected = !payByUpi,
          ) { onPayByUpi(false) }
        }
      }

      // ── The venue's own policies — nothing invented ──
      val rules = venue.rules.filter { it.isNotBlank() }
      if (venue.cancellation.isNotBlank() || rules.isNotEmpty()) {
        ReviewCard {
          if (venue.cancellation.isNotBlank()) {
            Text("Cancellation policy", color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 14.sp)
            Spacer(Modifier.height(6.dp))
            Text(venue.cancellation, color = BookInk.Slate, fontSize = 13.5.sp, lineHeight = 20.sp)
          }
          if (rules.isNotEmpty()) {
            if (venue.cancellation.isNotBlank()) Spacer(Modifier.height(14.dp))
            Text("Venue rules", color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 14.sp)
            Spacer(Modifier.height(6.dp))
            rules.forEach { rule ->
              Row(Modifier.padding(vertical = 2.dp)) {
                Text("•", color = BookInk.Faint, fontSize = 13.5.sp, modifier = Modifier.padding(end = 8.dp))
                Text(rule, color = BookInk.Slate, fontSize = 13.5.sp, lineHeight = 20.sp)
              }
            }
          }
        }
      }
      Spacer(Modifier.height(8.dp))
    }

    // Pay, pinned — the total stays in the key that charges it.
    Column(
      modifier = Modifier
        .fillMaxWidth()
        .shadow(elevation = 14.dp, clip = false)
        .background(Color.White)
        .navigationBarsPadding(),
    ) {
      Box(Modifier.fillMaxWidth().height(1.dp).background(BookInk.Line))
      Box(
        modifier = Modifier
          .padding(horizontal = 16.dp, vertical = 12.dp)
          .then(if (submitting) Modifier else Modifier.pressable(haptic = Feel.COMMIT, onClick = onConfirm))
          .fillMaxWidth()
          .height(52.dp)
          .then(if (submitting) Modifier else Modifier.shadow(10.dp, RoundedCornerShape(14.dp), ambientColor = BookInk.Blue, spotColor = BookInk.Blue.copy(alpha = 0.5f)))
          .clip(RoundedCornerShape(14.dp))
          .background(if (submitting) Color(0xFFCBD5E1) else BookInk.Blue),
        contentAlignment = Alignment.Center,
      ) {
        if (submitting) {
          CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(20.dp))
        } else {
          Text(
            when {
              total <= 0 -> "Confirm booking"
              payByUpi -> "Pay ₹${inr(total)} by UPI"
              else -> "Proceed ₹${inr(total)}"
            },
            color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 16.sp, maxLines = 1,
          )
        }
      }
    }
  }
}

/** One way to pay: a radio, its name, one line on what it covers. */
@Composable
private fun PayMethodRow(title: String, sub: String, selected: Boolean, onClick: () -> Unit) {
  Row(
    modifier = Modifier
      .pressable(haptic = Feel.SELECT, onClick = onClick)
      .fillMaxWidth()
      .clip(RoundedCornerShape(12.dp))
      .background(if (selected) BookInk.BlueTint else Color.White)
      .border(1.dp, if (selected) BookInk.Blue else BookInk.Line, RoundedCornerShape(12.dp))
      .padding(horizontal = 12.dp, vertical = 11.dp),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    Box(
      Modifier
        .size(20.dp)
        .clip(CircleShape)
        .border(2.dp, if (selected) BookInk.Blue else Color(0xFFCBD5E1), CircleShape),
      contentAlignment = Alignment.Center,
    ) {
      if (selected) Box(Modifier.size(10.dp).clip(CircleShape).background(BookInk.Blue))
    }
    Spacer(Modifier.width(12.dp))
    Column(Modifier.weight(1f)) {
      Text(title, color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 14.5.sp)
      Text(sub, color = BookInk.Muted, fontSize = 12.sp, lineHeight = 16.sp, modifier = Modifier.padding(top = 1.dp))
    }
  }
}

@Composable
private fun BreakupLine(label: String, value: String) {
  Row(
    Modifier.fillMaxWidth().height(26.dp),
    horizontalArrangement = Arrangement.SpaceBetween,
    verticalAlignment = Alignment.CenterVertically,
  ) {
    Text(label, color = BookInk.Muted, fontSize = 13.sp)
    Text(value, color = BookInk.Slate, fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
  }
}

/**
 * The slot rows that run on [date] — the server's rule (VenueSlot::forDate), so the app
 * offers exactly the times the website and checkout sell: the every-day rows plus rows
 * labelled with its weekday, a weekday row replacing an every-day row at the same time.
 * The server saves a slot's day as a weekday name or "Every day"; "Daily"/"Today" are
 * older spellings of every day.
 */
private fun slotsForDay(slots: List<VenueSlotItem>, date: LocalDate): List<VenueSlotItem> {
  val label = dayLabelFor(date)
  val mine = slots.filter { it.day.trim().equals(label, ignoreCase = true) }
  val taken = mine.mapNotNull { timeToMinutes(it.time) }.toSet()
  val everyDay = slots.filter {
    it.day.trim().lowercase() in EVERY_DAY_LABELS && timeToMinutes(it.time) !in taken
  }
  return (mine + everyDay).sortedBy { timeToMinutes(it.time) ?: Int.MAX_VALUE }
}

private val EVERY_DAY_LABELS = setOf("every day", "everyday", "daily", "today", "")

/** Human label for a date relative to today, matched against the slot rows' free-text "day". */
// Slots are generated per weekday (Monday…Sunday) from the venue's structured hours, so we
// always match a date to its full weekday name rather than a relative "Today"/"Tomorrow" label.
private fun dayLabelFor(date: LocalDate): String =
  date.dayOfWeek.getDisplayName(TextStyle.FULL, Locale.ENGLISH)

/**
 * The "Pick a date" calendar. Past dates are disabled, and so is anything past the
 * last day the server will take a booking from this person.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun BookCalendarDialog(
  selected: LocalDate,
  lastDate: LocalDate?,
  onDismiss: () -> Unit,
  onPick: (LocalDate) -> Unit,
) {
  val todayUtc = LocalDate.now().atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli()
  // The last day the server will take a booking from this person; null = no known limit.
  val lastUtc = lastDate?.atStartOfDay(ZoneOffset.UTC)?.toInstant()?.toEpochMilli()
  val state = rememberDatePickerState(
    initialSelectedDateMillis = selected.atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli(),
    selectableDates = object : SelectableDates {
      override fun isSelectableDate(utcTimeMillis: Long): Boolean =
        utcTimeMillis >= todayUtc && (lastUtc == null || utcTimeMillis <= lastUtc)
    },
  )
  DatePickerDialog(
    onDismissRequest = onDismiss,
    confirmButton = {
      TextButton(onClick = {
        state.selectedDateMillis?.let { onPick(Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate()) }
        onDismiss()
      }) { Text("OK", color = BookInk.Blue, fontWeight = FontWeight.Bold) }
    },
    dismissButton = {
      TextButton(onClick = onDismiss) { Text("Cancel", color = HaraanColors.TextSecondary) }
    },
  ) {
    DatePicker(state = state, showModeToggle = false)
  }
}
