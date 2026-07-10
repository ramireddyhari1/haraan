package com.example.thanna.ui.profile

import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Shield
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.example.thanna.ui.components.AutoRefresh
import com.example.thanna.data.AccountInfo
import com.example.thanna.data.ApiConfig
import com.example.thanna.data.BookingLite
import com.example.thanna.data.BookingRepository
import com.example.thanna.data.BookingResult
import com.example.thanna.data.ProfileRepository
import com.example.thanna.data.TokenStore
import com.example.thanna.data.VenueApiItem
import com.example.thanna.data.VenueRepository
import com.example.thanna.data.VenueSlotItem
import kotlinx.coroutines.launch

// ─── Palette: blue + green, light/business feel (file-private) ───────────────────
private val Bg        = Color(0xFFF4F7FB)
private val Surface   = Color(0xFFFFFFFF)
private val Navy      = Color(0xFF07111F)
private val NavyMid   = Color(0xFF102B52)
private val Blue      = Color(0xFF1E3A8A)
private val BlueBright= Color(0xFF2563EB)
private val Green     = Color(0xFF00B140)
private val GreenTint = Color(0xFFE7F7EE)
private val BlueTint  = Color(0xFFEAF1FE)
private val Text1     = Color(0xFF0F172A)
private val Text2     = Color(0xFF5A6473)
private val Text3     = Color(0xFF9AA3B2)
private val Stroke    = Color(0xFFE5E9F0)
private val DangerBg  = Color(0xFFFDECEF)
private val Danger    = Color(0xFFD23F57)

// The hero — the screen's one "moment". Navy → blue → green, exactly as the brand reads.
private val HeroGradient   = Brush.linearGradient(listOf(Navy, NavyMid, Color(0xFF0A3D2A)))
private val AvatarGradient = Brush.linearGradient(listOf(Color(0xFF2563EB), Green))
// ActionBoard gets its own deep, confident surface so it never blends with white cards.
private val ActionGradient = Brush.linearGradient(listOf(Navy, NavyMid))
// Featured banner — bright, marketing-forward.
private val BannerGradient = Brush.linearGradient(listOf(Color(0xFF0B7A3E), Green))

private sealed interface AccountState {
    data object Loading : AccountState
    data class Error(val message: String) : AccountState
    data class Loaded(val account: AccountInfo, val bookings: List<BookingLite>) : AccountState
}

private val CANCELLED_STATUSES = setOf("CANCELLED", "REFUNDED", "FAILED")

/** Today as `yyyy-MM-dd`. SimpleDateFormat keeps this safe on minSdk 24 (no java.time). */
private fun todayIso(): String =
    java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).format(java.util.Date())

private fun BookingLite.isCancelled(): Boolean = status.uppercase() in CANCELLED_STATUSES

/**
 * Upcoming vs past is a question about *time*, not payment. The old summary
 * counted CONFIRMED as "Active" and PENDING as "Upcoming", so a confirmed gig
 * that happened last month read as Active forever and Upcoming only ever counted
 * tickets you hadn't paid for. Dates are stored `yyyy-MM-dd…`, so a lexicographic
 * compare against today is both correct and cheap. A booking with no date is
 * treated as upcoming — better to over-show a live ticket than to bury it.
 */
private fun BookingLite.isPast(today: String): Boolean {
    val day = eventDate?.take(10)?.takeIf { it.length == 10 } ?: return false
    return day < today
}

/**
 * Bookings for the same event on the same day, collapsed into one entry. Seven
 * tickets to one concert is one row that says "7 tickets", not seven rows.
 */
private data class BookingGroup(val bookings: List<BookingLite>) {
    val head: BookingLite get() = bookings.first()
    val totalTickets: Int get() = bookings.sumOf { it.quantity }
    val isGrouped: Boolean get() = bookings.size > 1
}

private fun List<BookingLite>.grouped(): List<BookingGroup> =
    groupBy { Triple(it.type, it.eventTitle, it.eventDate?.take(10)) }
        .values.map { BookingGroup(it) }

/**
 * Unified Haraan account — shared by Events + GameHub. Built to read like a
 * dashboard for the user's relationship with the platform, not a settings page:
 * a gradient identity hero, a quick-stats strip, a bookings module, a featured
 * banner, and a doorway into the ActionBoard player profile. The gamified cricket
 * dashboard itself lives in [PlayerProfileScreen].
 */
@Composable
fun AccountProfileScreen(
    onClose: () -> Unit,
    fetchAccount: suspend () -> AccountInfo,
    fetchBookings: suspend () -> List<BookingLite>,
    onOpenPlayerProfile: () -> Unit,
    onOpenPass: (BookingLite) -> Unit,
    onSignOut: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var state by remember { mutableStateOf<AccountState>(AccountState.Loading) }
    var reloadKey by remember { mutableStateOf(0) }

    LaunchedEffect(reloadKey) {
        state = AccountState.Loading
        state = try {
            val account = fetchAccount()
            val bookings = runCatching { fetchBookings() }.getOrDefault(emptyList())
            AccountState.Loaded(account, bookings)
        } catch (e: Exception) {
            AccountState.Error(e.message ?: "Unable to load account.")
        }
    }

    // Keep bookings current without a manual refresh: silently re-fetch on screen
    // re-focus / app foreground and every 30s while open. Unlike the reloadKey path
    // above this never flips back to Loading — on success it swaps in fresh data, on
    // failure it keeps what's on screen — so there's no spinner flash mid-view.
    AutoRefresh(intervalMs = 30_000L) {
        val account = runCatching { fetchAccount() }.getOrNull() ?: return@AutoRefresh
        val bookings = runCatching { fetchBookings() }.getOrDefault(emptyList())
        state = AccountState.Loaded(account, bookings)
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Bg)
    ) {
        Row(
            Modifier.fillMaxWidth().background(Surface).padding(horizontal = 16.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier.size(36.dp).clip(CircleShape).background(Color(0xFFEFF2F7)).clickable(onClick = onClose),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Default.Close, "Close", tint = Text1, modifier = Modifier.size(18.dp)) }
            Spacer(Modifier.width(12.dp))
            Text("Account", color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold)
        }

        when (val s = state) {
            is AccountState.Loading -> Box(Modifier.fillMaxSize(), Alignment.Center) { CircularProgressIndicator(color = BlueBright) }
            is AccountState.Error -> Box(Modifier.fillMaxSize().padding(24.dp), Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(s.message, color = Text2, fontSize = 14.sp)
                    Spacer(Modifier.height(12.dp))
                    Box(
                        Modifier.clip(RoundedCornerShape(10.dp)).background(BlueBright).clickable { reloadKey++ }
                            .padding(horizontal = 20.dp, vertical = 10.dp),
                    ) { Text("Retry", color = Color.White, fontWeight = FontWeight.Bold) }
                    Spacer(Modifier.height(10.dp))
                    Box(
                        Modifier.clickable(onClick = onSignOut).padding(horizontal = 20.dp, vertical = 10.dp),
                    ) { Text("Sign out", color = Danger, fontWeight = FontWeight.Bold, fontSize = 14.sp) }
                }
            }
            is AccountState.Loaded -> Content(s.account, s.bookings, onOpenPlayerProfile, onOpenPass, onSignOut, onReload = { reloadKey++ })
        }
    }
}

@Composable
private fun Content(
    account: AccountInfo,
    bookings: List<BookingLite>,
    onOpenPlayerProfile: () -> Unit,
    onOpenPass: (BookingLite) -> Unit,
    onSignOut: () -> Unit,
    onReload: () -> Unit,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val soon: (String) -> Unit = { Toast.makeText(context, "$it coming soon.", Toast.LENGTH_SHORT).show() }

    // The only thing "edit" does here: pick a photo and upload it as the profile avatar.
    var uploadingPhoto by remember { mutableStateOf(false) }
    val photoLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia(),
    ) { uri ->
        if (uri == null) return@rememberLauncherForActivityResult
        uploadingPhoto = true
        scope.launch {
            val ok = try {
                val token = TokenStore.getToken(context) ?: ""
                val resolver = context.contentResolver
                val mime = resolver.getType(uri) ?: "image/jpeg"
                val bytes = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
                    resolver.openInputStream(uri)?.use { it.readBytes() }
                }
                if (bytes != null) {
                    ProfileRepository().uploadAvatar(token, bytes, mime); true
                } else false
            } catch (_: Exception) {
                false
            }
            uploadingPhoto = false
            if (ok) {
                Toast.makeText(context, "Photo updated.", Toast.LENGTH_SHORT).show()
                onReload()
            } else {
                Toast.makeText(context, "Couldn't upload photo. Try again.", Toast.LENGTH_SHORT).show()
            }
        }
    }
    val editPhoto: () -> Unit = {
        photoLauncher.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly))
    }

    val today = remember { todayIso() }
    val cancelled = bookings.filter { it.isCancelled() }
    val live = bookings.filterNot { it.isCancelled() }
    val upcoming = live.filterNot { it.isPast(today) }
    val past = live.filter { it.isPast(today) }

    // One identity, two lanes: "tickets" (what you've booked) vs "play" (your game).
    var lane by remember { mutableStateOf("tickets") }
    var showVenueSheet by remember { mutableStateOf(false) }
    var showBookings by remember { mutableStateOf(false) }

    // Declared before the early return so the sheet still composes when the venue
    // booking is started from the bookings page.
    if (showVenueSheet) {
        VenueBookingSheet(
            onDismiss = { showVenueSheet = false },
            onBooked = { showVenueSheet = false; onReload() },
        )
    }

    if (showBookings) {
        MyBookingsScreen(
            bookings = bookings,
            onClose = { showBookings = false },
            onOpenPass = onOpenPass,
            onBookVenue = { showVenueSheet = true },
        )
        return
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
    ) {
        // ── Identity hero — the one moment that dominates the screen ──
        item { IdentityHero(account, uploadingPhoto, onEditPhoto = editPhoto) }

        // ── Quick-stats strip — makes the account feel substantial ──
        item { Spacer(Modifier.height(14.dp)); QuickStats(account, bookings.size) }

        // ── Lane switch — pick a world: Tickets (what you've booked) or Play (your game) ──
        item { Spacer(Modifier.height(18.dp)); ProfileLaneSwitch(lane) { lane = it } }

        if (lane == "tickets") {
            // An at-a-glance count, not a second copy of the list — the bookings
            // themselves live on one screen, reached from here or the Account row.
            item { Spacer(Modifier.height(18.dp)); SectionTitle("My bookings") }
            item {
                Spacer(Modifier.height(12.dp))
                BookingsSummary(
                    upcoming = upcoming.size,
                    past = past.size,
                    cancelled = cancelled.size,
                    selected = null,
                    onSelect = { showBookings = true },
                )
            }
            item {
                Spacer(Modifier.height(10.dp))
                ViewAllBookingsRow(count = bookings.size, onClick = { showBookings = true })
            }
        } else {
            // Play = your competitive identity — the door into the ActionBoard player profile.
            item { Spacer(Modifier.height(18.dp)); SectionTitle("Your game") }
            item { Spacer(Modifier.height(12.dp)); ActionBoardEntryCard(onOpenPlayerProfile) }
            item { Spacer(Modifier.height(12.dp)); FeaturedBanner(onOpenPlayerProfile) }
        }

        // ── Account settings ──
        item { Spacer(Modifier.height(24.dp)); SectionTitle("Account") }
        item {
            Spacer(Modifier.height(12.dp))
            SettingsList(
                onTap = soon,
                onEditPhoto = editPhoto,
                bookingCount = bookings.size,
                onOpenBookings = { showBookings = true },
            )
        }

        item {
            Spacer(Modifier.height(28.dp))
            Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                Text(
                    "Sign out",
                    color = Danger.copy(alpha = 0.7f),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable(onClick = onSignOut)
                        .padding(horizontal = 24.dp, vertical = 10.dp),
                )
            }
            Spacer(Modifier.height(20.dp))
        }
    }
}

// ─────────────────────────────────────────────── Identity hero (gradient) ───────
@Composable
private fun IdentityHero(a: AccountInfo, uploadingPhoto: Boolean, onEditPhoto: () -> Unit) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(24.dp))
            .background(HeroGradient)
            .padding(20.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            // Tappable avatar — the one "edit" action on this screen: upload a photo.
            Box(contentAlignment = Alignment.BottomEnd) {
                Box(
                    Modifier
                        .size(68.dp)
                        .clip(CircleShape)
                        .background(AvatarGradient)
                        .border(2.dp, Color.White.copy(alpha = 0.25f), CircleShape)
                        .clickable(onClick = onEditPhoto),
                    contentAlignment = Alignment.Center,
                ) {
                    val photo = avatarUrl(a.avatar)
                    when {
                        uploadingPhoto -> CircularProgressIndicator(color = Color.White, modifier = Modifier.size(24.dp))
                        photo != null -> AsyncImage(
                            model = photo,
                            contentDescription = "Profile photo",
                            contentScale = ContentScale.Crop,
                            modifier = Modifier.fillMaxSize().clip(CircleShape),
                        )
                        else -> Text(
                            a.name.take(1).uppercase().ifBlank { "?" },
                            color = Color.White, fontSize = 28.sp, fontWeight = FontWeight.Bold,
                        )
                    }
                }
                // Camera badge signals the avatar is editable.
                Box(
                    Modifier
                        .size(24.dp)
                        .clip(CircleShape)
                        .background(BlueBright)
                        .border(2.dp, Navy, CircleShape)
                        .clickable(onClick = onEditPhoto),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.Default.PhotoCamera, "Upload photo", tint = Color.White, modifier = Modifier.size(12.dp)) }
            }
            Spacer(Modifier.width(16.dp))
            Column(Modifier.weight(1f)) {
                Text(a.name.ifBlank { "Haraan user" }, color = Color.White, fontSize = 21.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                // Always show the email the user logged in with.
                a.email?.let {
                    Spacer(Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Email, null, tint = Color.White.copy(alpha = 0.7f), modifier = Modifier.size(13.dp))
                        Spacer(Modifier.width(5.dp))
                        Text(it, color = Color.White.copy(alpha = 0.8f), fontSize = 13.sp, maxLines = 1)
                    }
                }
                memberSinceYear(a.memberSince)?.let {
                    Spacer(Modifier.height(3.dp))
                    Text("Member since $it", color = Color.White.copy(alpha = 0.6f), fontSize = 12.sp)
                }
                a.playerId?.let {
                    Spacer(Modifier.height(8.dp))
                    Box(
                        Modifier.clip(RoundedCornerShape(7.dp)).background(Color.White.copy(alpha = 0.15f))
                            .padding(horizontal = 9.dp, vertical = 4.dp),
                    ) {
                        Text(
                            "ID  $it",
                            color = Color.White, fontSize = 11.sp, fontWeight = FontWeight.Bold,
                            maxLines = 1, softWrap = false,
                        )
                    }
                }
            }
        }

        Spacer(Modifier.height(16.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.12f)))
        Spacer(Modifier.height(14.dp))

        // Ecosystem chips — make it unmistakable this is the Haraan platform. Sized so all
        // three sit on one line (was overflowing → "ActionBoard" char-wrapped).
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            EcosystemChip(Icons.Default.ConfirmationNumber, "Events")
            EcosystemChip(Icons.Default.SportsCricket, "GameHub")
            EcosystemChip(Icons.Default.EmojiEvents, "ActionBoard")
        }
    }
}

@Composable
private fun EcosystemChip(icon: ImageVector, label: String) {
    Row(
        Modifier.clip(RoundedCornerShape(20.dp)).background(Color.White.copy(alpha = 0.12f))
            .padding(horizontal = 8.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = Color.White, modifier = Modifier.size(12.dp))
        Spacer(Modifier.width(4.dp))
        Text(
            label, color = Color.White, fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold,
            maxLines = 1, softWrap = false,
        )
    }
}

// ─────────────────────────────────────────────────── Quick-stats strip ──────────
@Composable
private fun QuickStats(a: AccountInfo, bookingCount: Int) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp))
            .padding(vertical = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        StatCell(Modifier.weight(1f), bookingCount.toString(), "Bookings")
        StatDivider()
        StatCell(Modifier.weight(1f), a.district?.takeIf { it.isNotBlank() } ?: "—", "District")
        StatDivider()
        StatCell(Modifier.weight(1f), memberSinceYear(a.memberSince) ?: "2025", "Since")
    }
}

@Composable
private fun StatCell(modifier: Modifier, value: String, label: String) {
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Text(value, color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold, maxLines = 1)
        Spacer(Modifier.height(3.dp))
        Text(label, color = Text3, fontSize = 11.5.sp, fontWeight = FontWeight.Medium)
    }
}

@Composable
private fun StatDivider() {
    Box(Modifier.width(1.dp).height(30.dp).background(Stroke))
}

// ─────────────────────────────────────────────── Bookings summary ───────────────
/**
 * The three counters are also the filter. Buckets are time-based (and cancellation),
 * never payment status — see [BookingLite.isPast]. A null [selected] means nothing is
 * filtered (the profile's read-only glance); tapping a cell there opens the bookings
 * page instead.
 */
@Composable
private fun BookingsSummary(
    upcoming: Int,
    past: Int,
    cancelled: Int,
    selected: String?,
    onSelect: (String) -> Unit,
) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp))
            .padding(vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // With no filter applied, every cell reads at full strength; the underline
        // only appears once a cell is actually the active filter.
        val filtering = selected != null
        SummaryCell(Modifier.weight(1f), upcoming, "Upcoming", Green, !filtering || selected == "upcoming", filtering && selected == "upcoming") { onSelect("upcoming") }
        StatDivider()
        SummaryCell(Modifier.weight(1f), past, "Past", Text2, !filtering || selected == "past", filtering && selected == "past") { onSelect("past") }
        StatDivider()
        SummaryCell(Modifier.weight(1f), cancelled, "Cancelled", Danger, !filtering || selected == "cancelled", filtering && selected == "cancelled") { onSelect("cancelled") }
    }
}

@Composable
private fun SummaryCell(
    modifier: Modifier,
    value: Int,
    label: String,
    accent: Color,
    active: Boolean,
    indicator: Boolean,
    onClick: () -> Unit,
) {
    Column(
        modifier
            .clip(RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
            .padding(vertical = 10.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(
            value.toString(),
            color = if (active) accent else Text3,
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
        )
        Spacer(Modifier.height(2.dp))
        Text(
            label,
            color = if (active) Text1 else Text3,
            fontSize = 11.5.sp,
            fontWeight = if (active) FontWeight.Bold else FontWeight.Medium,
        )
        Spacer(Modifier.height(6.dp))
        Box(
            Modifier
                .height(2.dp)
                .width(if (indicator) 22.dp else 0.dp)
                .clip(RoundedCornerShape(1.dp))
                .background(if (indicator) accent else Color.Transparent),
        )
    }
}

/** The profile's doorway into the bookings page. */
@Composable
private fun ViewAllBookingsRow(count: Int, onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(14.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 15.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Default.ConfirmationNumber, null, tint = BlueBright, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text("View all bookings", color = Text1, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
            Text(
                if (count == 0) "Nothing booked yet" else "$count booking${if (count == 1) "" else "s"} · tap for your passes",
                color = Text3, fontSize = 12.sp,
            )
        }
        Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = Text3, modifier = Modifier.size(20.dp))
    }
}

/** Booking a venue is a different job from reviewing what you've booked. */
@Composable
private fun BookVenueButton(onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(14.dp))
            .clickable(onClick = onClick)
            .padding(vertical = 14.dp),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Default.SportsCricket, null, tint = BlueBright, modifier = Modifier.size(17.dp))
        Spacer(Modifier.width(8.dp))
        Text("Book a venue slot", color = BlueBright, fontSize = 13.5.sp, fontWeight = FontWeight.Bold)
    }
}

// ─────────────────────────────────────────────── Booking group ──────────────────
/**
 * One entry per event-day. A single booking is just a tappable row that opens its
 * pass; several bookings to the same event collapse into one row that expands to
 * reveal a pass per booking — each has its own QR, so they can't be merged.
 */
@Composable
private fun BookingGroupCard(group: BookingGroup, onOpenPass: (BookingLite) -> Unit) {
    var expanded by remember(group.head.id) { mutableStateOf(false) }

    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(14.dp)),
    ) {
        BookingRow(
            b = group.head,
            ticketsOverride = if (group.isGrouped) group.totalTickets else null,
            trailing = if (group.isGrouped) "${group.bookings.size} passes" else null,
            onClick = { if (group.isGrouped) expanded = !expanded else onOpenPass(group.head) },
        )
        if (expanded) {
            group.bookings.forEach { b ->
                ThinDivider()
                Row(
                    Modifier
                        .fillMaxWidth()
                        .clickable { onOpenPass(b) }
                        .padding(horizontal = 14.dp, vertical = 12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(Icons.Default.ConfirmationNumber, null, tint = BlueBright, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(10.dp))
                    Column(Modifier.weight(1f)) {
                        Text(
                            "${b.quantity} ticket${if (b.quantity == 1) "" else "s"}",
                            color = Text1, fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
                        )
                        b.ticketCode?.takeIf { it.isNotBlank() }?.let {
                            Text(it.take(8), color = Text3, fontSize = 11.sp)
                        }
                    }
                    Text("View pass", color = BlueBright, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

// ─────────────────────────────────────────────── Lane switch (Tickets/Play) ──────
@Composable
private fun ProfileLaneSwitch(selected: String, onSelect: (String) -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(14.dp))
            .padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        LaneTab(Modifier.weight(1f), Icons.Default.ConfirmationNumber, "Tickets", selected == "tickets") { onSelect("tickets") }
        LaneTab(Modifier.weight(1f), Icons.Default.SportsCricket, "Play", selected == "play") { onSelect("play") }
    }
}

@Composable
private fun LaneTab(modifier: Modifier, icon: ImageVector, label: String, active: Boolean, onClick: () -> Unit) {
    val fg = if (active) Color.White else Text2
    Row(
        modifier
            .clip(RoundedCornerShape(11.dp))
            .background(if (active) Text1 else Color.Transparent)
            .clickable(onClick = onClick)
            .padding(vertical = 11.dp),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = fg, modifier = Modifier.size(16.dp))
        Spacer(Modifier.width(7.dp))
        Text(label, color = fg, fontSize = 13.5.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun EmptyLaneNote(title: String, sub: String) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp))
            .padding(28.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(title, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(5.dp))
        Text(
            sub, color = Text3, fontSize = 12.5.sp,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
        )
    }
}

/** 44dp gradient icon container — gives the rows personality instead of flat Material chips. */
@Composable
private fun GradientIcon(icon: ImageVector) {
    Box(
        Modifier
            .size(44.dp)
            .clip(RoundedCornerShape(13.dp))
            .background(Brush.linearGradient(listOf(Color(0xFFEAF1FE), Color(0xFFE7F7EE)))),
        contentAlignment = Alignment.Center,
    ) { Icon(icon, null, tint = BlueBright, modifier = Modifier.size(22.dp)) }
}

@Composable
private fun BookingRow(
    b: BookingLite,
    ticketsOverride: Int? = null,
    trailing: String? = null,
    onClick: () -> Unit,
) {
    val isVenue = b.type == "venue"
    Row(
        Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // The real poster makes a row recognisable at a glance; the tinted icon
        // tile is the fallback when there's no creative.
        Box(
            Modifier.size(40.dp).clip(RoundedCornerShape(11.dp)).background(if (isVenue) GreenTint else BlueTint),
            contentAlignment = Alignment.Center,
        ) {
            if (!b.imageUrl.isNullOrBlank()) {
                AsyncImage(
                    model = b.imageUrl,
                    contentDescription = b.eventTitle,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize().clip(RoundedCornerShape(11.dp)),
                )
            } else {
                Icon(
                    if (isVenue) Icons.Default.SportsCricket else Icons.Default.ConfirmationNumber,
                    null, tint = if (isVenue) Green else BlueBright, modifier = Modifier.size(20.dp),
                )
            }
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(b.eventTitle, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
            val meta = listOfNotNull(b.eventVenue, b.eventDate?.take(10)).joinToString(" · ")
            if (meta.isNotBlank()) {
                Text(meta, color = Text3, fontSize = 12.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            val tickets = ticketsOverride ?: b.quantity
            val line = if (isVenue) (b.slotLabel ?: "Venue slot") else "$tickets ticket${if (tickets == 1) "" else "s"}"
            Text(line, color = Text2, fontSize = 12.sp, maxLines = 1)
        }
        Spacer(Modifier.width(8.dp))
        if (trailing != null) {
            Text(trailing, color = Text3, fontSize = 11.5.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.width(4.dp))
            Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = Text3, modifier = Modifier.size(18.dp))
        } else if (b.isCancelled()) {
            // Only alarm when there's something to alarm about; an upcoming ticket's
            // "CONFIRMED" pill was noise on every single row.
            StatusPill(b.status)
        } else {
            Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = Text3, modifier = Modifier.size(18.dp))
        }
    }
}

@Composable
private fun StatusPill(status: String) {
    val (c, bg) = when (status.uppercase()) {
        "CONFIRMED" -> Green to GreenTint
        "CANCELLED" -> Danger to DangerBg
        else -> BlueBright to BlueTint
    }
    Box(
        Modifier.clip(RoundedCornerShape(6.dp)).background(bg).padding(horizontal = 8.dp, vertical = 4.dp),
    ) { Text(status.uppercase(), color = c, fontSize = 10.sp, fontWeight = FontWeight.Bold) }
}

// ─────────────────────────────────────────────── Featured banner ────────────────
@Composable
private fun FeaturedBanner(onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .background(BannerGradient)
            .clickable(onClick = onClick)
            .padding(20.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text("PLAY CRICKET NEAR YOU", color = Color.White.copy(alpha = 0.85f), fontSize = 10.5.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(6.dp))
            Text("Join local matches", color = Color.White, fontSize = 18.sp, fontWeight = FontWeight.Bold)
            Text("Track stats · climb the rankings", color = Color.White.copy(alpha = 0.85f), fontSize = 12.5.sp)
            Spacer(Modifier.height(12.dp))
            Row(
                Modifier.clip(RoundedCornerShape(10.dp)).background(Color.White).padding(horizontal = 14.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("Explore", color = Color(0xFF0B7A3E), fontSize = 13.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.width(5.dp))
                Icon(Icons.AutoMirrored.Filled.ArrowForward, null, tint = Color(0xFF0B7A3E), modifier = Modifier.size(15.dp))
            }
        }
        Spacer(Modifier.width(12.dp))
        Box(
            Modifier.size(56.dp).clip(CircleShape).background(Color.White.copy(alpha = 0.18f)),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.Default.SportsCricket, null, tint = Color.White, modifier = Modifier.size(30.dp)) }
    }
}

// ─────────────────────────────────────────────── ActionBoard entry ──────────────
@Composable
private fun ActionBoardEntryCard(onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .background(ActionGradient)
            .clickable(onClick = onClick)
            .height(IntrinsicSize.Min)
    ) {
        // Green accent rail — signals "this is the live, gamified product".
        Box(Modifier.width(4.dp).fillMaxHeight().background(Green))
        Column(Modifier.weight(1f).padding(18.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(44.dp).clip(RoundedCornerShape(13.dp)).background(Color.White.copy(alpha = 0.12f)),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.Default.EmojiEvents, null, tint = Green, modifier = Modifier.size(24.dp)) }
                Spacer(Modifier.width(14.dp))
                Column(Modifier.weight(1f)) {
                    Text("ACTIONBOARD", color = Green, fontSize = 10.5.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(2.dp))
                    Text("Cricket Profile", color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
                Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = Color.White.copy(alpha = 0.6f), modifier = Modifier.size(22.dp))
            }
            Spacer(Modifier.height(16.dp))
            ActionPoint(Icons.AutoMirrored.Filled.TrendingUp, "Track every match stat")
            Spacer(Modifier.height(8.dp))
            ActionPoint(Icons.Default.Shield, "Build a verified reputation")
            Spacer(Modifier.height(8.dp))
            ActionPoint(Icons.Default.EmojiEvents, "Climb the local rankings")
            Spacer(Modifier.height(16.dp))
            Text("View Player Profile →", color = Green, fontSize = 14.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun ActionPoint(icon: ImageVector, text: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(icon, null, tint = Color.White.copy(alpha = 0.7f), modifier = Modifier.size(16.dp))
        Spacer(Modifier.width(10.dp))
        Text(text, color = Color.White.copy(alpha = 0.85f), fontSize = 13.sp)
    }
}

// ─────────────────────────────────────────────────────────── Settings ───────────
@Composable
private fun SettingsList(
    onTap: (String) -> Unit,
    onEditPhoto: () -> Unit,
    bookingCount: Int,
    onOpenBookings: () -> Unit,
) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(18.dp)),
    ) {
        // Bookings are the thing people come back for, so they lead the list.
        SettingRow(
            Icons.Default.ConfirmationNumber,
            "My bookings",
            badge = bookingCount.takeIf { it > 0 }?.toString(),
            onClick = onOpenBookings,
        )
        ThinDivider()
        // The only editable field on this account: the profile photo.
        SettingRow(Icons.Default.PhotoCamera, "Upload profile photo") { onEditPhoto() }
        ThinDivider()
        SettingRow(Icons.Default.Notifications, "Notifications") { onTap("Notifications") }
        ThinDivider()
        SettingRow(Icons.Default.Shield, "Privacy") { onTap("Privacy") }
        ThinDivider()
        SettingRow(Icons.Default.CalendarMonth, "Support") { onTap("Support") }
    }
}

@Composable
private fun SettingRow(icon: ImageVector, title: String, badge: String? = null, onClick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().clickable(onClick = onClick).padding(horizontal = 16.dp, vertical = 15.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = BlueBright, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(14.dp))
        Text(title, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
        if (badge != null) {
            Box(
                Modifier.clip(RoundedCornerShape(7.dp)).background(BlueTint).padding(horizontal = 8.dp, vertical = 3.dp),
            ) { Text(badge, color = BlueBright, fontSize = 11.5.sp, fontWeight = FontWeight.Bold) }
            Spacer(Modifier.width(8.dp))
        }
        Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = Text3, modifier = Modifier.size(20.dp))
    }
}

/**
 * The one place bookings live. Reached from the Account list; the header calendar
 * icon lands here too. Kept in this file so it shares the profile's colour tokens
 * and the booking row/group composables rather than duplicating them.
 */
@Composable
private fun MyBookingsScreen(
    bookings: List<BookingLite>,
    onClose: () -> Unit,
    onOpenPass: (BookingLite) -> Unit,
    onBookVenue: () -> Unit,
) {
    val today = remember { todayIso() }
    val cancelled = bookings.filter { it.isCancelled() }
    val live = bookings.filterNot { it.isCancelled() }
    val upcoming = live.filterNot { it.isPast(today) }
    val past = live.filter { it.isPast(today) }

    // Land on whichever bucket actually has something in it, so a user whose
    // events have all happened doesn't open to an empty screen.
    var bucket by remember { mutableStateOf(if (upcoming.isEmpty() && past.isNotEmpty()) "past" else "upcoming") }

    val shown = when (bucket) {
        "past" -> past
        "cancelled" -> cancelled
        else -> upcoming
    }

    Column(Modifier.fillMaxSize().background(Bg)) {
        Row(
            Modifier.fillMaxWidth().background(Surface).padding(horizontal = 16.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier.size(36.dp).clip(CircleShape).background(Color(0xFFEFF2F7)).clickable(onClick = onClose),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Text1, modifier = Modifier.size(18.dp)) }
            Spacer(Modifier.width(12.dp))
            Text("My bookings", color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold)
        }

        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp)) {
            item {
                BookingsSummary(
                    upcoming = upcoming.size,
                    past = past.size,
                    cancelled = cancelled.size,
                    selected = bucket,
                    onSelect = { bucket = it },
                )
            }

            if (shown.isNotEmpty()) {
                val groups = shown.grouped()
                item { Spacer(Modifier.height(12.dp)) }
                items(groups.size) { i ->
                    BookingGroupCard(groups[i], onOpenPass = onOpenPass)
                    Spacer(Modifier.height(8.dp))
                }
            } else {
                item {
                    Spacer(Modifier.height(12.dp))
                    when (bucket) {
                        "past" -> EmptyLaneNote("Nothing here yet", "Events and slots you've already attended will move here.")
                        "cancelled" -> EmptyLaneNote("No cancellations", "Bookings you cancel or that get refunded show up here.")
                        else -> EmptyLaneNote("Nothing coming up", "Book an event or a venue slot and your entry pass lands here.")
                    }
                }
            }

            item { Spacer(Modifier.height(12.dp)); BookVenueButton(onClick = onBookVenue) }
            item { Spacer(Modifier.height(24.dp)) }
        }
    }
}

@Composable
private fun ThinDivider() {
    Box(Modifier.fillMaxWidth().padding(start = 50.dp).height(1.dp).background(Stroke))
}

// ─────────────────────────────────────────────────────────── Shared ─────────────
@Composable
private fun SectionTitle(title: String, trailing: String? = null) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Text(title, color = Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
        if (trailing != null) {
            Text(trailing, color = Text3, fontSize = 12.5.sp, fontWeight = FontWeight.Medium)
        }
    }
}

/** Absolute avatar URL (backend may hand back a relative /storage path). */
private fun avatarUrl(raw: String?): String? {
    val s = raw?.trim().orEmpty()
    if (s.isBlank() || s == "null") return null
    return if (s.startsWith("http")) s else ApiConfig.BASE_URL.trimEnd('/') + "/" + s.trimStart('/')
}

/** Pull the year out of an ISO-ish createdAt string ("2025-06-01T…" → "2025"). */
private fun memberSinceYear(raw: String?): String? {
    val s = raw?.trim().orEmpty()
    if (s.length < 4) return null
    val year = s.take(4)
    return year.takeIf { it.all(Char::isDigit) }
}

// ─────────────────────────────────────────── Venue-slot booking sheet ───────────
// Pick a bookable venue → a date (next 7 days) → an available slot → confirm.
// POSTs /api/bookings/venue; on success the caller reloads so the booking shows in Tickets.
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VenueBookingSheet(onDismiss: () -> Unit, onBooked: () -> Unit) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    var venues by remember { mutableStateOf<List<VenueApiItem>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var venue by remember { mutableStateOf<VenueApiItem?>(null) }
    var slots by remember { mutableStateOf<List<VenueSlotItem>>(emptyList()) }
    var slot by remember { mutableStateOf<VenueSlotItem?>(null) }
    var booking by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    // Next 7 days as (isoDate, label, offset) — Calendar/SimpleDateFormat to stay below API 26.
    val days = remember {
        val iso = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US)
        val lbl = java.text.SimpleDateFormat("EEE d MMM", java.util.Locale.US)
        (0..6).map { off ->
            val c = java.util.Calendar.getInstance(); c.add(java.util.Calendar.DAY_OF_YEAR, off)
            Triple(iso.format(c.time), lbl.format(c.time), off)
        }
    }
    var date by remember { mutableStateOf(days.first().first) }

    LaunchedEffect(Unit) {
        venues = runCatching { VenueRepository().getVenues().filter { it.isBookable } }.getOrDefault(emptyList())
        loading = false
    }
    LaunchedEffect(venue?.id) {
        val v = venue ?: return@LaunchedEffect
        slot = null
        slots = runCatching { VenueRepository().getVenueSlots(v.id) }.getOrDefault(emptyList())
    }

    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheetState, containerColor = Surface) {
        Column(Modifier.fillMaxWidth().padding(start = 20.dp, end = 20.dp, bottom = 28.dp)) {
            Text(
                if (venue == null) "Book a venue slot" else venue!!.name,
                color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold, maxLines = 1,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                if (venue == null) "Choose a venue, then a date & slot" else "₹${venue!!.price} · ${venue!!.location}",
                color = Text3, fontSize = 12.5.sp,
            )
            Spacer(Modifier.height(16.dp))

            when {
                loading -> Box(Modifier.fillMaxWidth().padding(24.dp), Alignment.Center) {
                    CircularProgressIndicator(color = BlueBright)
                }
                venue == null -> {
                    if (venues.isEmpty()) {
                        Text("No bookable venues right now.", color = Text3, fontSize = 13.sp)
                    } else {
                        venues.forEach { v ->
                            Row(
                                Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp))
                                    .border(1.dp, Stroke, RoundedCornerShape(12.dp))
                                    .clickable { venue = v }.padding(14.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                GradientIcon(Icons.Default.Place)
                                Spacer(Modifier.width(12.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(v.name, color = Text1, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold, maxLines = 1)
                                    Text("${v.category} · ${v.location}", color = Text3, fontSize = 12.sp, maxLines = 1)
                                }
                                Text("₹${v.price}", color = Text2, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                            }
                            Spacer(Modifier.height(8.dp))
                        }
                    }
                }
                else -> {
                    Text("Date", color = Text2, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(8.dp))
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(days) { d ->
                            val sel = d.first == date
                            Box(
                                Modifier.clip(RoundedCornerShape(12.dp)).background(if (sel) Text1 else Bg)
                                    .border(1.dp, if (sel) Color.Transparent else Stroke, RoundedCornerShape(12.dp))
                                    .clickable { date = d.first }.padding(horizontal = 14.dp, vertical = 10.dp),
                            ) {
                                Text(
                                    if (d.third == 0) "Today" else d.second,
                                    color = if (sel) Color.White else Text2, fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold, maxLines = 1,
                                )
                            }
                        }
                    }
                    Spacer(Modifier.height(16.dp))
                    Text("Slot", color = Text2, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(8.dp))
                    if (slots.isEmpty()) {
                        Text("No slots listed for this venue.", color = Text3, fontSize = 13.sp)
                    } else {
                        LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            items(slots) { s ->
                                val sel = slot?.id == s.id
                                val enabled = s.available
                                Column(
                                    Modifier.clip(RoundedCornerShape(12.dp))
                                        .background(if (sel) BlueBright else if (enabled) Bg else Color(0xFFF1F1F4))
                                        .border(1.dp, if (sel) Color.Transparent else Stroke, RoundedCornerShape(12.dp))
                                        .then(if (enabled) Modifier.clickable { slot = s } else Modifier)
                                        .padding(horizontal = 14.dp, vertical = 10.dp),
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                ) {
                                    Text(s.time, color = if (sel) Color.White else if (enabled) Text1 else Text3, fontSize = 13.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                                    Text(
                                        if (!enabled) "Booked" else if (s.fillingFast) "Filling fast" else s.day,
                                        color = if (sel) Color.White.copy(alpha = 0.85f) else Text3, fontSize = 10.5.sp, maxLines = 1,
                                    )
                                }
                            }
                        }
                    }
                    error?.let { Spacer(Modifier.height(10.dp)); Text(it, color = Danger, fontSize = 12.5.sp) }
                    Spacer(Modifier.height(18.dp))
                    val canBook = slot != null && !booking
                    Box(
                        Modifier.fillMaxWidth().clip(RoundedCornerShape(13.dp))
                            .background(if (canBook) Green else Color(0xFFBFC8D2))
                            .then(
                                if (canBook) Modifier.clickable {
                                    booking = true; error = null
                                    scope.launch {
                                        val token = TokenStore.getToken(ctx) ?: ""
                                        val res = BookingRepository().bookVenueSlot(token, venue!!.id.toIntOrNull() ?: 0, slot!!.id, date)
                                        booking = false
                                        when (res) {
                                            is BookingResult.Success -> {
                                                Toast.makeText(ctx, "Slot booked!", Toast.LENGTH_SHORT).show(); onBooked()
                                            }
                                            is BookingResult.Error -> error = res.message
                                        }
                                    }
                                } else Modifier,
                            )
                            .padding(vertical = 15.dp),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(
                            if (booking) "Booking…" else "Confirm booking · ₹${venue!!.price}",
                            color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold,
                        )
                    }
                    Spacer(Modifier.height(6.dp))
                    Box(
                        Modifier.fillMaxWidth().clickable { venue = null; slot = null; error = null }.padding(vertical = 8.dp),
                        contentAlignment = Alignment.Center,
                    ) { Text("← Choose a different venue", color = Text3, fontSize = 12.5.sp, fontWeight = FontWeight.Medium) }
                }
            }
        }
    }
}
