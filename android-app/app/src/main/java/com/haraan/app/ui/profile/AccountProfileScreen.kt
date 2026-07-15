package com.haraan.app.ui.profile

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
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
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
import androidx.compose.material.icons.automirrored.filled.Article
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.PrivacyTip
import androidx.compose.material.icons.filled.Shield
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material.icons.filled.SupportAgent
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
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
import androidx.compose.ui.draw.drawWithCache
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
// Aliased: the file-private palette already owns the name `Stroke` (a Color).
import androidx.compose.ui.graphics.drawscope.Stroke as StrokeStyle
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.ui.components.AutoRefresh
import com.haraan.app.data.AccountInfo
import com.haraan.app.data.ApiConfig
import com.haraan.app.data.BookingLite
import com.haraan.app.data.BookingRepository
import com.haraan.app.data.BookingResult
import com.haraan.app.data.PlayerProfile
import com.haraan.app.data.PrivacyRepository
import com.haraan.app.data.PrivacySettings
import com.haraan.app.data.ProfileRepository
import com.haraan.app.data.TokenStore
import com.haraan.app.data.VenueApiItem
import com.haraan.app.data.VenueRepository
import com.haraan.app.data.VenueSlotItem
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

// The hero — the screen's ONE saturated moment. Navy → blue → green, exactly as the
// brand reads. Nothing else on the page is allowed a full-bleed gradient; a second
// one halves the impact of both.
private val HeroGradient   = Brush.linearGradient(listOf(Navy, NavyMid, Color(0xFF0A3D2A)))
private val AvatarGradient = Brush.linearGradient(listOf(Color(0xFF2563EB), Green))

/**
 * The hero is a membership credential, not a banner, so it is printed like one:
 * a glow lifting the avatar off the navy, and guilloché arcs — the concentric
 * line-work of banknotes and passports — struck from a point past the
 * bottom-right corner so only their shoulders cross the card.
 *
 * Arc alpha stays under 0.06: the member ID sits on top of these, and monospaced
 * zeros are the first glyphs to go muddy against a moving line.
 */
private fun Modifier.identityCardSurface(): Modifier = this
    .clip(RoundedCornerShape(24.dp))
    .background(HeroGradient)
    .drawWithCache {
        val glow = Brush.radialGradient(
            colors = listOf(Color(0xFF2563EB).copy(alpha = 0.30f), Color.Transparent),
            center = Offset(size.width * 0.16f, size.height * 0.18f),
            radius = size.minDimension * 1.15f,
        )
        val origin = Offset(size.width * 1.05f, size.height * 1.35f)
        val arc = StrokeStyle(width = 1.dp.toPx())
        onDrawBehind {
            drawRect(glow)
            repeat(7) { i ->
                drawCircle(
                    color = Color.White.copy(alpha = 0.055f - i * 0.006f),
                    radius = size.height * (0.55f + i * 0.28f),
                    center = origin,
                    style = arc,
                )
            }
        }
    }

// One spacing scale instead of ad-hoc 14/18/24/28dp per item: air between
// groups, a tighter beat from a heading to the card it labels.
private val GroupGap = 24.dp
private val HeadingGap = 12.dp

private sealed interface AccountState {
    data object Loading : AccountState
    data class Error(val message: String) : AccountState

    /**
     * [player] is optional on purpose: the ActionBoard profile is a second endpoint,
     * and a user who has never played should still get a complete account screen.
     * Null simply means the Play lane invites instead of reporting.
     */
    data class Loaded(
        val account: AccountInfo,
        val bookings: List<BookingLite>,
        val player: PlayerProfile?,
    ) : AccountState
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
 * Unified Haraan account — shared by Events + GameHub. It reads as a membership
 * dashboard rather than a settings page: an identity hero carrying the member ID,
 * the two lanes the platform actually offers (Tickets and Play), a standing strip
 * of earned numbers, and a quiet utility list. The gamified cricket dashboard
 * itself lives in [PlayerProfileScreen].
 */
@Composable
fun AccountProfileScreen(
    onClose: () -> Unit,
    fetchAccount: suspend () -> AccountInfo,
    fetchBookings: suspend () -> List<BookingLite>,
    fetchPlayer: suspend () -> PlayerProfile?,
    onOpenPlayerProfile: () -> Unit,
    onOpenPass: (BookingLite) -> Unit,
    onOpenSupport: () -> Unit,
    onSignOut: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var state by remember { mutableStateOf<AccountState>(AccountState.Loading) }
    var reloadKey by remember { mutableStateOf(0) }

    // Bookings is a full page, not a section: it owns its own header. Both it and
    // the venue sheet are hoisted above the account chrome so the page replaces the
    // "Account" bar instead of stacking a second header beneath it.
    var showBookings by remember { mutableStateOf(false) }
    var showVenueSheet by remember { mutableStateOf(false) }
    var showPrivacy by remember { mutableStateOf(false) }
    var legalSlug by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(reloadKey) {
        state = AccountState.Loading
        state = try {
            val account = fetchAccount()
            val bookings = runCatching { fetchBookings() }.getOrDefault(emptyList())
            val player = runCatching { fetchPlayer() }.getOrNull()
            AccountState.Loaded(account, bookings, player)
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
        val player = runCatching { fetchPlayer() }.getOrNull()
        state = AccountState.Loaded(account, bookings, player)
    }

    if (showVenueSheet) {
        VenueBookingSheet(
            onDismiss = { showVenueSheet = false },
            onBooked = { showVenueSheet = false; reloadKey++ },
        )
    }

    // Full pages, each carrying its own header — never nested inside the account's.
    legalSlug?.let { slug ->
        LegalScreen(slug = slug, onClose = { legalSlug = null }, modifier = modifier)
        return
    }

    if (showPrivacy) {
        PrivacySettingsScreen(onClose = { showPrivacy = false }, modifier = modifier)
        return
    }

    val loaded = state as? AccountState.Loaded
    if (loaded != null && showBookings) {
        MyBookingsScreen(
            bookings = loaded.bookings,
            onClose = { showBookings = false },
            onOpenPass = onOpenPass,
            onBookVenue = { showVenueSheet = true },
            modifier = modifier,
        )
        return
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
            is AccountState.Loaded -> Content(
                account = s.account,
                bookings = s.bookings,
                player = s.player,
                onOpenBookings = { showBookings = true },
                onOpenPlayerProfile = onOpenPlayerProfile,
                onOpenPrivacy = { showPrivacy = true },
                onOpenLegal = { legalSlug = it },
                onOpenSupport = onOpenSupport,
                onSignOut = onSignOut,
                onReload = { reloadKey++ },
            )
        }
    }
}

@Composable
private fun Content(
    account: AccountInfo,
    bookings: List<BookingLite>,
    player: PlayerProfile?,
    onOpenBookings: () -> Unit,
    onOpenPlayerProfile: () -> Unit,
    onOpenPrivacy: () -> Unit,
    onOpenLegal: (String) -> Unit,
    onOpenSupport: () -> Unit,
    onSignOut: () -> Unit,
    onReload: () -> Unit,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

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
    val upcomingCount = bookings.filterNot { it.isCancelled() }.count { !it.isPast(today) }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
    ) {
        // ── Identity hero — the one moment that dominates the screen ──
        item { IdentityHero(account, uploadingPhoto, onEditPhoto = editPhoto) }

        // ── The two lanes. This is the screen's whole proposition: one identity,
        // two things to do with it. Each lane owns its accent and its own number,
        // so neither repeats what the hero already said.
        item {
            Spacer(Modifier.height(14.dp))
            Row(
                Modifier.fillMaxWidth().height(IntrinsicSize.Min),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                // A lane never buries what the user has. With nothing upcoming we
                // still count the bookings they've made — the old screen's "7" badge
                // must not vanish just because those events have happened.
                LaneCard(
                    modifier = Modifier.weight(1f),
                    icon = Icons.Default.ConfirmationNumber,
                    accent = BlueBright,
                    tint = BlueTint,
                    label = "Tickets",
                    value = when {
                        upcomingCount > 0 -> upcomingCount.toString()
                        bookings.isNotEmpty() -> bookings.size.toString()
                        else -> null
                    },
                    caption = when {
                        upcomingCount > 0 -> "upcoming"
                        bookings.isNotEmpty() -> "past bookings"
                        else -> "Book your first event"
                    },
                    onClick = onOpenBookings,
                )
                LaneCard(
                    modifier = Modifier.weight(1f),
                    icon = Icons.Default.SportsCricket,
                    accent = Green,
                    tint = GreenTint,
                    label = "Play",
                    value = player?.careerMatches?.takeIf { it > 0 }?.toString(),
                    caption = if (player != null && player.careerMatches > 0) "matches played" else "Join a local match",
                    onClick = onOpenPlayerProfile,
                )
            }
        }

        // ── Standing — only earned numbers, and only once there are any. An
        // all-zero strip would be decoration; absence is the honest state.
        if (player != null && (player.rankedXp > 0 || player.careerMatches > 0)) {
            item { Spacer(Modifier.height(12.dp)); StandingStrip(player) }
        }

        item { Spacer(Modifier.height(GroupGap)); SectionTitle("Account") }
        item {
            Spacer(Modifier.height(HeadingGap))
            SettingsCard {
                SettingRow(Icons.Default.Shield, "Privacy", onClick = onOpenPrivacy)
                ThinDivider()
                SettingRow(Icons.Default.SupportAgent, "Support", onClick = onOpenSupport)
            }
        }

        // Legal is its own group: these are documents you read, not settings you
        // change, and they're the two links an app store review looks for.
        item { Spacer(Modifier.height(GroupGap)); SectionTitle("Legal") }
        item {
            Spacer(Modifier.height(HeadingGap))
            SettingsCard {
                SettingRow(Icons.AutoMirrored.Filled.Article, "Terms & Conditions") { onOpenLegal("terms") }
                ThinDivider()
                SettingRow(Icons.Default.PrivacyTip, "Privacy Policy") { onOpenLegal("privacy") }
            }
        }

        // The destructive action gets its own container. It shouldn't share a card
        // edge with a help link.
        item {
            Spacer(Modifier.height(GroupGap))
            SettingsCard { SignOutRow(onSignOut) }
        }

        item {
            Spacer(Modifier.height(20.dp))
            Text(
                "Haraan v${com.haraan.app.BuildConfig.VERSION_NAME}",
                color = Text3,
                fontSize = 11.sp,
                textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(24.dp))
        }
    }
}

// ─────────────────────────────────────────────── Identity hero (gradient) ───────
@Composable
private fun IdentityHero(a: AccountInfo, uploadingPhoto: Boolean, onEditPhoto: () -> Unit) {
    Column(
        Modifier
            .fillMaxWidth()
            .identityCardSurface()
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
                Text(a.name.ifBlank { "Haraan user" }, color = Color.White, fontSize = 22.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                a.email?.let {
                    Spacer(Modifier.height(5.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Email, null, tint = Color.White.copy(alpha = 0.65f), modifier = Modifier.size(13.dp))
                        Spacer(Modifier.width(6.dp))
                        Text(
                            it, color = Color.White.copy(alpha = 0.8f), fontSize = 13.sp,
                            maxLines = 1, overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
                // Where you play and how long you've been here.
                val since = memberSinceYear(a.memberSince)?.let { "Member since $it" }
                val place = a.district?.takeIf { it.isNotBlank() }
                val subtitle = listOfNotNull(place, since).joinToString(" · ")
                if (subtitle.isNotBlank()) {
                    Spacer(Modifier.height(3.dp))
                    Text(
                        subtitle, color = Color.White.copy(alpha = 0.6f), fontSize = 12.sp,
                        maxLines = 1, overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }

        // The member ID is the one genuinely distinctive thing on this screen, so
        // it gets its own band rather than an 11sp chip: labelled, monospaced, and
        // tappable to copy — the way an airline treats a frequent-flyer number.
        a.playerId?.let { id ->
            Spacer(Modifier.height(18.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.12f)))
            Spacer(Modifier.height(14.dp))
            MemberIdBand(id)
        }
    }
}

@Composable
private fun MemberIdBand(id: String) {
    val clipboard = LocalClipboardManager.current
    val context = LocalContext.current
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .clickable {
                clipboard.setText(AnnotatedString(id))
                Toast.makeText(context, "Member ID copied.", Toast.LENGTH_SHORT).show()
            }
            .padding(vertical = 2.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(
                "MEMBER ID",
                color = Color.White.copy(alpha = 0.55f),
                fontSize = 9.5.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 1.2.sp,
            )
            Spacer(Modifier.height(3.dp))
            Text(
                id,
                color = Color.White,
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                fontFamily = FontFamily.Monospace,
                letterSpacing = 1.sp,
                maxLines = 1,
                softWrap = false,
            )
        }
        Icon(Icons.Default.ContentCopy, "Copy member ID", tint = Color.White.copy(alpha = 0.6f), modifier = Modifier.size(17.dp))
    }
}

// ───────────────────────────────────────────────────────────── Lanes ────────────
/**
 * One identity, two lanes. A lane either reports a number it has earned or invites
 * you in. A null [value] means there is nothing to count yet, and the caption is
 * promoted to the headline: an empty lane should read as a door, not as a zero
 * (or worse, a dash, which looks like a redaction).
 */
@Composable
private fun RowScope.LaneCard(
    modifier: Modifier = Modifier,
    icon: ImageVector,
    accent: Color,
    tint: Color,
    label: String,
    value: String?,
    caption: String,
    onClick: () -> Unit,
) {
    Column(
        modifier
            .fillMaxHeight()
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(18.dp))
            .clickable(onClick = onClick)
            .padding(16.dp),
    ) {
        Box(
            Modifier.size(36.dp).clip(RoundedCornerShape(11.dp)).background(tint),
            contentAlignment = Alignment.Center,
        ) { Icon(icon, null, tint = accent, modifier = Modifier.size(19.dp)) }
        Spacer(Modifier.height(14.dp))
        Text(label, color = Text2, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold)
        // Push the payload to the bottom so a counting lane and an inviting lane
        // still share a baseline when they sit side by side.
        Spacer(Modifier.weight(1f).height(6.dp))
        if (value != null) {
            Text(value, color = Text1, fontSize = 26.sp, fontWeight = FontWeight.Bold, maxLines = 1)
            Spacer(Modifier.height(3.dp))
            Text(caption, color = Text3, fontSize = 11.5.sp, maxLines = 2)
        } else {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    caption, color = accent, fontSize = 13.sp, fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f),
                )
                Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = accent, modifier = Modifier.size(16.dp))
            }
        }
    }
}

// ───────────────────────────────────────────────── Standing (earned stats) ──────
/** XP, district rank and trust — three numbers you can only get by playing. */
@Composable
private fun StandingStrip(p: PlayerProfile) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp))
            .padding(vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        StatCell(Modifier.weight(1f), Icons.AutoMirrored.Filled.TrendingUp, BlueBright, p.rankedXp.formatted(), "Match XP")
        StatDivider()
        StatCell(Modifier.weight(1f), Icons.Default.EmojiEvents, Green, p.rankDistrict?.let { "#$it" } ?: "—", "In district")
        StatDivider()
        StatCell(Modifier.weight(1f), Icons.Default.Shield, Text2, p.trustScore.toString(), "Trust")
    }
}

/** 1240 → "1,240". Big numbers should not be read digit by digit. */
private fun Int.formatted(): String = java.text.NumberFormat.getInstance(java.util.Locale.US).format(this)

@Composable
private fun StatCell(modifier: Modifier, icon: ImageVector, accent: Color, value: String, label: String) {
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Icon(icon, null, tint = accent, modifier = Modifier.size(15.dp))
        Spacer(Modifier.height(6.dp))
        Text(value, color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold, maxLines = 1)
        Spacer(Modifier.height(2.dp))
        Text(label, color = Text3, fontSize = 11.sp, fontWeight = FontWeight.Medium)
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

// ─────────────────────────────────────────────────────────── Settings ───────────
@Composable
private fun SettingsCard(content: @Composable ColumnScope.() -> Unit) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(18.dp)),
        content = content,
    )
}

@Composable
private fun SignOutRow(onSignOut: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().clickable(onClick = onSignOut).padding(horizontal = 16.dp, vertical = 15.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.AutoMirrored.Filled.Logout, null, tint = Danger, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(14.dp))
        Text("Sign out", color = Danger, fontSize = 15.sp, fontWeight = FontWeight.Medium)
    }
}

/**
 * Utility rows are deliberately quiet: grey icons, no accent. Colour on this screen
 * means "this is a lane" — spending it on Support too would flatten the hierarchy
 * back into an undifferentiated list.
 */
@Composable
private fun SettingRow(icon: ImageVector, title: String, onClick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().clickable(onClick = onClick).padding(horizontal = 16.dp, vertical = 15.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = Text2, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(14.dp))
        Text(title, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
        Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, null, tint = Text3, modifier = Modifier.size(20.dp))
    }
}

// ──────────────────────────────────────────────────── Sub-pages (full screen) ───
/** Shared chrome for the account's child pages: a back arrow and a title. */
@Composable
private fun PageHeader(title: String, onClose: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().background(Surface).padding(horizontal = 16.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(36.dp).clip(CircleShape).background(Color(0xFFEFF2F7)).clickable(onClick = onClose),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back", tint = Text1, modifier = Modifier.size(18.dp)) }
        Spacer(Modifier.width(12.dp))
        Text(title, color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

/**
 * Terms & Conditions / Privacy Policy, fetched from `/api/legal/{slug}` so an
 * admin can republish the wording without an app release.
 */
@Composable
private fun LegalScreen(slug: String, onClose: () -> Unit, modifier: Modifier = Modifier) {
    var doc by remember(slug) { mutableStateOf<com.haraan.app.data.LegalDocument?>(null) }
    var error by remember(slug) { mutableStateOf<String?>(null) }
    var attempt by remember(slug) { mutableStateOf(0) }

    LaunchedEffect(slug, attempt) {
        error = null
        doc = null
        runCatching { com.haraan.app.data.LegalRepository().fetch(slug) }
            .onSuccess { doc = it }
            .onFailure { error = it.message ?: "Couldn't load this document." }
    }

    // The title comes from the server, but the header must say something while the
    // fetch is in flight — so fall back to the slug's known name.
    val fallbackTitle = if (slug == "terms") "Terms & Conditions" else "Privacy Policy"

    Column(modifier.fillMaxSize().background(Bg)) {
        PageHeader(doc?.title?.takeIf { it.isNotBlank() } ?: fallbackTitle, onClose)
        when {
            error != null -> Box(Modifier.fillMaxSize().padding(24.dp), Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(error!!, color = Text2, fontSize = 14.sp)
                    Spacer(Modifier.height(12.dp))
                    Box(
                        Modifier.clip(RoundedCornerShape(10.dp)).background(BlueBright).clickable { attempt++ }
                            .padding(horizontal = 20.dp, vertical = 10.dp),
                    ) { Text("Retry", color = Color.White, fontWeight = FontWeight.Bold) }
                }
            }
            doc == null -> Box(Modifier.fillMaxSize(), Alignment.Center) { CircularProgressIndicator(color = BlueBright) }
            else -> LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp)) {
                item {
                    Column(
                        Modifier.fillMaxWidth().clip(RoundedCornerShape(18.dp)).background(Surface)
                            .border(1.dp, Stroke, RoundedCornerShape(18.dp)).padding(20.dp),
                    ) {
                        // Blank lines separate paragraphs; a short line ending in ":"
                        // is a heading. That's the whole format — enough structure for
                        // legal copy without shipping a markdown renderer.
                        doc!!.body.split("\n\n").map { it.trim() }.filter { it.isNotEmpty() }.forEachIndexed { i, para ->
                            if (i > 0) Spacer(Modifier.height(14.dp))
                            val isHeading = para.endsWith(":") && para.length < 60 && !para.contains("\n")
                            Text(
                                para,
                                color = if (isHeading) Text1 else Text2,
                                fontSize = if (isHeading) 14.5.sp else 13.5.sp,
                                fontWeight = if (isHeading) FontWeight.Bold else FontWeight.Normal,
                                lineHeight = 21.sp,
                            )
                        }
                    }
                    Spacer(Modifier.height(24.dp))
                }
            }
        }
    }
}

/**
 * Account → Privacy. Each toggle writes immediately (there is no Save button), so
 * the switch flips optimistically and rolls back if the server refuses — a toggle
 * that lies about what the server stored is worse than a slow one.
 */
@Composable
private fun PrivacySettingsScreen(onClose: () -> Unit, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    var settings by remember { mutableStateOf<PrivacySettings?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var attempt by remember { mutableStateOf(0) }

    LaunchedEffect(attempt) {
        error = null
        settings = null
        val token = TokenStore.getToken(context) ?: ""
        runCatching { PrivacyRepository().fetch(token) }
            .onSuccess { settings = it }
            .onFailure { error = it.message ?: "Couldn't load your privacy settings." }
    }

    fun save(field: String, value: Boolean, apply: (PrivacySettings, Boolean) -> PrivacySettings) {
        val before = settings ?: return
        settings = apply(before, value)          // optimistic
        scope.launch {
            val token = TokenStore.getToken(context) ?: ""
            runCatching { PrivacyRepository().update(token, field, value) }
                .onSuccess { settings = it }     // trust the server's echo
                .onFailure {
                    settings = before            // roll back
                    Toast.makeText(context, "Couldn't save that. Try again.", Toast.LENGTH_SHORT).show()
                }
        }
    }

    Column(modifier.fillMaxSize().background(Bg)) {
        PageHeader("Privacy", onClose)
        when {
            error != null -> Box(Modifier.fillMaxSize().padding(24.dp), Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(error!!, color = Text2, fontSize = 14.sp)
                    Spacer(Modifier.height(12.dp))
                    Box(
                        Modifier.clip(RoundedCornerShape(10.dp)).background(BlueBright).clickable { attempt++ }
                            .padding(horizontal = 20.dp, vertical = 10.dp),
                    ) { Text("Retry", color = Color.White, fontWeight = FontWeight.Bold) }
                }
            }
            settings == null -> Box(Modifier.fillMaxSize(), Alignment.Center) { CircularProgressIndicator(color = BlueBright) }
            else -> {
                val s = settings!!
                LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp)) {
                    item {
                        Text(
                            "Haraan is a public leaderboard. These controls decide how much of your play other people can see.",
                            color = Text2, fontSize = 13.sp, lineHeight = 19.sp,
                        )
                        Spacer(Modifier.height(GroupGap))
                    }
                    item { SectionTitle("Your profile") }
                    item {
                        Spacer(Modifier.height(HeadingGap))
                        SettingsCard {
                            ToggleRow(
                                "Public profile",
                                "Anyone can open your player profile from a match or a leaderboard.",
                                s.publicProfile,
                            ) { save("publicProfile", it) { p, v -> p.copy(publicProfile = v) } }
                            ThinDivider()
                            ToggleRow(
                                "Show career stats",
                                "Your runs, wickets and matches appear on your profile.",
                                s.showStats,
                            ) { save("showStats", it) { p, v -> p.copy(showStats = v) } }
                            ThinDivider()
                            ToggleRow(
                                "Show my district",
                                "Your district and state are shown next to your name.",
                                s.showDistrict,
                            ) { save("showDistrict", it) { p, v -> p.copy(showDistrict = v) } }
                        }
                    }
                    item { Spacer(Modifier.height(GroupGap)); SectionTitle("Discovery") }
                    item {
                        Spacer(Modifier.height(HeadingGap))
                        SettingsCard {
                            ToggleRow(
                                "Findable in search",
                                "Other players can find you by name or Member ID to add you to a squad.",
                                s.discoverable,
                            ) { save("discoverable", it) { p, v -> p.copy(discoverable = v) } }
                        }
                    }
                    item { Spacer(Modifier.height(24.dp)) }
                }
            }
        }
    }
}

/** A switch row. The whole row is the target — a 32dp switch is a poor one. */
@Composable
private fun ToggleRow(title: String, description: String, checked: Boolean, onChange: (Boolean) -> Unit) {
    Row(
        Modifier.fillMaxWidth().clickable { onChange(!checked) }.padding(horizontal = 16.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(title, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(3.dp))
            Text(description, color = Text3, fontSize = 12.sp, lineHeight = 17.sp)
        }
        Spacer(Modifier.width(14.dp))
        Switch(
            checked = checked,
            onCheckedChange = onChange,
            colors = SwitchDefaults.colors(
                checkedThumbColor = Color.White,
                checkedTrackColor = Green,
                checkedBorderColor = Green,
            ),
        )
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
    modifier: Modifier = Modifier,
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

    Column(modifier.fillMaxSize().background(Bg)) {
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
private fun avatarUrl(raw: String?): String? = ApiConfig.mediaUrl(raw)

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
