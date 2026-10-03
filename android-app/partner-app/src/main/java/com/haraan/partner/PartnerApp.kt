package com.haraan.partner

import androidx.lifecycle.repeatOnLifecycle
import android.content.Context
import android.content.Intent
import com.haraan.partner.daybookings.ui.DayBookingsScreen
import com.haraan.partner.register.ui.ShiftRegisterScreen
import com.haraan.partner.register.data.ShiftLocalDataSource
import com.haraan.partner.register.data.ShiftRepository
import com.haraan.partner.register.viewmodel.ShiftViewModel
import androidx.core.content.FileProvider
import java.io.File
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Box as LayoutBox
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.DisposableEffect
import androidx.compose.ui.layout.layout
import androidx.compose.ui.graphics.RectangleShape
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.material.icons.filled.PersonAdd
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Share
import androidx.compose.material.icons.filled.Forum
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.material.icons.filled.AccountBalance
import androidx.compose.foundation.layout.heightIn
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.automirrored.filled.TrendingDown
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.Badge
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.ChevronLeft
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.CurrencyRupee
import androidx.compose.material.icons.filled.Chat
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.Flag
import androidx.compose.material.icons.filled.Tune
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Insights
import androidx.compose.material.icons.filled.Menu
import androidx.compose.material.icons.automirrored.filled.HelpOutline
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.School
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Sms
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Today
import androidx.compose.material3.Button
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DrawerValue
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalDrawerSheet
import androidx.compose.material3.ModalNavigationDrawer
import androidx.compose.material3.rememberDrawerState
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.material3.OutlinedButton
import androidx.compose.ui.zIndex
import androidx.compose.runtime.collectAsState
import androidx.compose.foundation.layout.windowInsetsTopHeight
import androidx.compose.foundation.layout.statusBars
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.Lock
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.Spring
import com.haraan.partner.ui.pricing.generateArt
import com.haraan.partner.ui.pricing.peakArt
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.launch
import kotlin.math.roundToInt
import android.net.Uri
import android.view.HapticFeedbackConstants
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.activity.compose.BackHandler
import coil.compose.AsyncImage
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.drawer.HaraanPartnerDrawer
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.home.CellState
import com.haraan.partner.ui.home.CourtHour
import com.haraan.partner.ui.home.CourtTag
import com.haraan.partner.ui.home.CourtsDaySection
import com.haraan.partner.ui.home.courtKindFor
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import com.haraan.partner.ui.components.pressableTile
import com.haraan.partner.ui.components.rememberMoneyMotion

private sealed interface UiState<out T> {
    data object Loading : UiState<Nothing>
    data class Error(val message: String) : UiState<Nothing>
    data class Data<T>(val value: T) : UiState<T>
}

@Composable
fun PartnerApp() {
    com.haraan.partner.ui.theme.HaraanTheme {
        val context = LocalContext.current
        val session = remember { Session(context) }
        val api = remember { PartnerApi() }
        var signedIn by remember { mutableStateOf(session.isSignedIn) }

        if (!signedIn) {
            LoginScreen(api = api, session = session, onSignedIn = { signedIn = true })
        } else {
            HomeScaffold(api = api, session = session, onSignedOut = { signedIn = false })
        }
    }
}

// ---- Login --------------------------------------------------------------

// Partner auth palette — a native port of the web partner "blue-aurora" sign-in
// (resources/views/filament/partner/auth-brand.blade.php). Keep in step with it.
private val AuthInkTop = Color(0xFF0A1738)
private val AuthInkMid = Color(0xFF0B1C46)
private val AuthInkBot = Color(0xFF0A1230)
private val AuthAccent = Color(0xFF2F6BFF)
private val AuthAccentDeep = Color(0xFF1E50E6)
private val AuthPageBg = Color(0xFFF7F8FB)
private val AuthInk = Color(0xFF0F172A)
private val AuthMuted = Color(0xFF64748B)
private val CardBorder = Color(0x0F0F172A)
private val Hairline = Color(0x140F172A)
private val NavIdle = Color(0xFF94A3B8)

/** The one premium card surface used across every screen: soft lifted shadow,
 *  white fill, hairline border, consistent radius. Keeps the whole app coherent. */
internal fun Modifier.premiumSurface(radius: Dp = 18.dp): Modifier = this
    .shadow(10.dp, RoundedCornerShape(radius), clip = false, spotColor = Color(0x1A0F172A))
    .clip(RoundedCornerShape(radius))
    .background(Color.White)
    .border(1.dp, CardBorder, RoundedCornerShape(radius))

/** A muted, uniform top-bar action icon (no stray tonal circle). */
@Composable
private fun HeaderIcon(icon: ImageVector, desc: String, onClick: () -> Unit) {
    IconButton(onClick = onClick) {
        Icon(icon, contentDescription = desc, tint = AuthMuted, modifier = Modifier.size(22.dp))
    }
}

/** Header bell with an unread-count badge for live booking alerts. */
@Composable
private fun BellIcon(count: Int, onClick: () -> Unit) {
    LayoutBox {
        IconButton(onClick = onClick) {
            Icon(Icons.Filled.Notifications, contentDescription = "Bookings", tint = AuthMuted, modifier = Modifier.size(22.dp))
        }
        if (count > 0) {
            LayoutBox(
                Modifier.align(Alignment.TopEnd).padding(top = 7.dp, end = 5.dp)
                    .size(16.dp).clip(RoundedCornerShape(99.dp)).background(RED)
                    .border(1.5.dp, Color.White, RoundedCornerShape(99.dp)),
                contentAlignment = Alignment.Center,
            ) { Text(if (count > 9) "9+" else "$count", fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = Color.White) }
        }
    }
}

/**
 * The venue's own photo in the top bar — whose app this is, at a glance. Falls
 * back to a navy monogram before a photo exists. Opens the drawer, where the
 * account and branches live.
 */
@Composable
private fun VenueAvatar(photo: String?, initial: String, onClick: () -> Unit) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    LayoutBox(
        Modifier
            .pressScale(interaction, pressedScale = 0.9f)
            .size(36.dp)
            .clip(RoundedCornerShape(99.dp))
            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid)))
            .border(1.5.dp, Color.White, RoundedCornerShape(99.dp))
            .clickable(interactionSource = interaction, indication = null) { Haptics.tick(view); onClick() },
        contentAlignment = Alignment.Center,
    ) {
        Text(initial, color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
        if (photo != null) {
            AsyncImage(
                model = photo,
                contentDescription = "Venue",
                contentScale = ContentScale.Crop,
                modifier = Modifier.matchParentSize(),
            )
        }
    }
}

/** The navy live-booking banner that drops in when a new booking arrives. */
@Composable
private fun BookingBanner(message: String, onClick: () -> Unit) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 8.dp)
            .shadow(12.dp, RoundedCornerShape(14.dp), clip = false, spotColor = AuthInkTop)
            .clip(RoundedCornerShape(14.dp))
            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid)))
            .clickable { onClick() }
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        LayoutBox(
            Modifier.size(30.dp).clip(RoundedCornerShape(99.dp)).background(Color(0x333B82F6)),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.Filled.Notifications, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp)) }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text("NEW BOOKING", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = Color(0xB3CFE0FF), letterSpacing = 1.sp)
            Spacer(Modifier.height(1.dp))
            Text(message, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Color.White, maxLines = 1)
        }
        Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0x99FFFFFF), modifier = Modifier.size(20.dp))
    }
}

@Composable
private fun LoginScreen(api: PartnerApi, session: Session, onSignedIn: () -> Unit) {
    val view = LocalView.current
    // White status-bar icons over the navy band.
    LightStatusBar(light = true)
    // Every sign-in path funnels here: confirm it's really a partner (overview 403s
    // otherwise), persist the session, then enter.
    com.haraan.partner.ui.auth.PartnerSignIn(api = api) { result ->
        api.overview(result.token)
        session.token = result.token
        session.name = result.name
        session.partnerType = result.partnerType
        session.isDesk = result.isDesk
        session.permissionsCsv = result.permissions.joinToString(",")
        Haptics.confirm(view)
        onSignedIn()
    }
}

/** Outlined white "continue with…" button — leading mark + label, with a press dip. */
@Composable
private fun SocialButton(
    text: String,
    leading: @Composable () -> Unit,
    enabled: Boolean = true,
    onClick: () -> Unit,
) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(if (pressed && enabled) 0.98f else 1f, label = "social-scale")
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.Center,
        modifier = Modifier
            .fillMaxWidth()
            .height(50.dp)
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White)
            .border(1.dp, Color(0x220F172A), RoundedCornerShape(14.dp))
            .clickable(interactionSource = interaction, indication = null, enabled = enabled) {
                view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                onClick()
            },
    ) {
        leading()
        Spacer(Modifier.width(10.dp))
        Text(text, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold, color = AuthInk)
    }
}

/** Full-width gradient sign-in button with a confident press state (scale + lift). */
@Composable
private fun GradientCta(
    text: String,
    enabled: Boolean,
    loading: Boolean,
    onClick: () -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(if (pressed && enabled) 0.975f else 1f, label = "cta-scale")

    LayoutBox(
        modifier = Modifier
            .fillMaxWidth()
            .height(52.dp)
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .shadow(
                elevation = if (enabled) 16.dp else 0.dp,
                shape = RoundedCornerShape(14.dp),
                clip = false,
                spotColor = AuthAccent,
                ambientColor = AuthAccent,
            )
            .clip(RoundedCornerShape(14.dp))
            .background(
                if (enabled) Brush.verticalGradient(listOf(AuthAccent, AuthAccentDeep))
                else Brush.verticalGradient(listOf(Color(0xFFE8EEF9), Color(0xFFDFE7F4)))
            )
            .clickable(
                interactionSource = interaction,
                indication = null,
                enabled = enabled,
                onClick = onClick,
            ),
        contentAlignment = Alignment.Center,
    ) {
        val contentColor = if (enabled) Color.White else Color(0xFF9AA7BD)
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            if (loading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(18.dp),
                    color = contentColor,
                    strokeWidth = 2.dp,
                )
            }
            Text(text, color = contentColor, fontSize = 15.sp, fontWeight = FontWeight.Bold)
        }
    }
}

// ---- Home scaffold + tabs ----------------------------------------------

internal enum class Tab(val label: String) { Home("Home"), Events("Events"), Venues("Venues"), Matches("Matches"), Sales("Sales"), Payments("Payments"), Scan("Scan") }

/**
 * Which console the signed-in partner belongs to. Drives the whole shell.
 *
 * CAFE is its own lane, not a flavour of VENUE. A café owner reading "Courts"
 * and turf language is looking at somebody else's business — the two share their
 * booking arithmetic, but not their vocabulary and not their tabs.
 */
internal enum class Lane { EVENT, VENUE, CAFE, BOTH }

internal fun laneOf(partnerType: String?): Lane = when (partnerType?.lowercase()) {
    "event", "host", "organiser", "organizer" -> Lane.EVENT
    "venue" -> Lane.VENUE
    "cafe", "café" -> Lane.CAFE
    else -> Lane.BOTH // legacy / no type / admin → combined
}

/** What a bookable unit is called here: a turf has courts, a café has tables. */
private fun resourceNoun(lane: Lane, plural: Boolean = false): String = when (lane) {
    Lane.CAFE -> if (plural) "Tables" else "Table"
    else -> if (plural) "Courts" else "Court"
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HomeScaffold(api: PartnerApi, session: Session, onSignedOut: () -> Unit) {
    var detail by remember { mutableStateOf<AnalyticsTarget?>(null) }
    var manageVenue by remember { mutableStateOf<Pair<Long, String>?>(null) }
    // Home's "Add time slots" opens the venue straight on its slot editor.
    var manageStartsInSlots by remember { mutableStateOf(false) }
    var showReports by remember { mutableStateOf(false) }
    var showPayouts by remember { mutableStateOf(false) }
    var showCustomers by remember { mutableStateOf(false) }
    var showPackages by remember { mutableStateOf(false) }
    var showAcademy by remember { mutableStateOf(false) }
    var showNotifications by remember { mutableStateOf(false) }
    var showSupport by remember { mutableStateOf(false) }
    var showStaff by remember { mutableStateOf(false) }
    var showShiftRegister by remember { mutableStateOf(false) }
    var showStandingSlots by remember { mutableStateOf(false) }
    var showPricingMatrix by remember { mutableStateOf(false) }
    var showWhatsAppDesk by remember { mutableStateOf(false) }
    var showOperationsCenter by remember { mutableStateOf(false) }
    val token = session.token ?: return

    // Everything the console keeps across screens lives above the early returns
    // below: state remembered after a `return` left composition whenever a drawer
    // screen opened, so coming back reset the tab to Home, zeroed the bell and
    // stopped new-booking alerts for as long as Reports or Payouts was open.
    val screenOpen = showNotifications || showSupport || showAcademy || showPackages ||
        showCustomers || showPayouts || showReports || showStaff || showShiftRegister ||
        showStandingSlots || showPricingMatrix || showWhatsAppDesk || showOperationsCenter ||
        detail != null || manageVenue != null
    // Build the visible tabs from the partner's lane so an event host never sees
    // venue tabs and vice-versa. Sales is shared but relabelled per lane. The lane
    // seeds from the cached type and is corrected once the server confirms it.
    var lane by remember { mutableStateOf(laneOf(session.partnerType)) }

    // The shell: which branches this account may act on, and at what altitude.
    // Loaded once; a failure leaves ctx null, which renders exactly the
    // single-branch console that shipped before branches existed — the switcher
    // never becomes a way to lock someone out of their own app.
    var ctx by remember { mutableStateOf<PartnerContext?>(null) }
    var branchId by remember { mutableStateOf(session.branchId) }
    LaunchedEffect(token) { ctx = runCatching { api.context(token) }.getOrNull() }
    // The partner's venues, for the header avatar and Home's setup path. A failed
    // load stays null, which Home treats as "not known yet" rather than "none".
    var venues by remember { mutableStateOf<List<VenueSummary>?>(null) }
    LaunchedEffect(token) { venues = runCatching { api.venues(token) }.getOrNull() }

    // A remembered branch the server no longer offers (reassigned, deactivated)
    // must fall back to "all branches" rather than silently filtering everything
    // to an outlet this person can't see.
    LaunchedEffect(ctx) {
        val known = ctx ?: return@LaunchedEffect
        if (branchId != null && known.branches.none { it.id == branchId }) {
            branchId = null
            session.branchId = null
        }
    }

    val tabs = remember(lane) {
        buildList {
            add(Tab.Home)
            if (lane != Lane.VENUE) add(Tab.Events)
            if (lane != Lane.EVENT) add(Tab.Venues)
            // Matches is off the bar (the user asked, 2026-10-01): it lives in the
            // drawer for the venue lanes instead. A café has no pitch and an event
            // host has no venue, so neither lane is offered it at all.
            add(Tab.Sales)
            // Every booking's money in one history (the user asked, 2026-10-01). The
            // combined lane's bar is full, so it reaches Payments from the drawer.
            if ((lane == Lane.VENUE || lane == Lane.CAFE) && session.can("reports")) add(Tab.Payments)
            add(Tab.Scan)
        }
    }
    // What the drawer lists. The drawer is the index, so a destination the bar
    // couldn't fit still has to appear here.
    val navTabs = remember(tabs, lane) {
        if (lane != Lane.VENUE && lane != Lane.BOTH) tabs
        else tabs.toMutableList().apply {
            add(indexOf(Tab.Venues) + 1, Tab.Matches)
            if (lane == Lane.BOTH && session.can("reports")) add(indexOf(Tab.Sales) + 1, Tab.Payments)
        }
    }
    var tab by remember { mutableStateOf(Tab.Home) }
    // Back from any other tab returns Home first; only Back on Home leaves the app.
    // Not while a venue desk or analytics screen is on top: this handler is composed
    // after the screen handler above, so it won — Back switched the tab hidden behind
    // the open venue, and it took a second Back to close the venue and land on Home.
    BackHandler(enabled = tab != Tab.Home && !screenOpen) { tab = Tab.Home }
    // If the lane resolves and the current tab is no longer valid, fall back Home.
    LaunchedEffect(navTabs) { if (tab !in navTabs) tab = Tab.Home }

    // Live booking alerts (Part A — works with no push infra): while the app is
    // open, poll for bookings and surface anything newer than we've already shown
    // as a top banner + a bell badge. FCM (Part B) covers the closed-app case.
    val view = LocalView.current
    var unseenBookings by remember { mutableStateOf(0) }
    var bookingBanner by remember { mutableStateOf<String?>(null) }
    // The same card the on-duty service draws over other apps, shown inside Haraan.
    var inAppAlert by remember { mutableStateOf<com.haraan.partner.alerts.BookingAlert?>(null) }
    // On duty: bookings reach the partner with the app closed.
    val appContext = LocalContext.current
    var onDuty by remember { mutableStateOf(session.onDuty) }
    var dutySetup by remember { mutableStateOf(false) }
    fun goOnDuty() {
        session.onDuty = true; onDuty = true
        com.haraan.partner.alerts.BookingWatchService.start(appContext)
    }
    val notifPermission = androidx.activity.compose.rememberLauncherForActivityResult(
        androidx.activity.result.contract.ActivityResultContracts.RequestPermission(),
    ) { _ -> if (com.haraan.partner.alerts.AlertOverlay.allowed(appContext)) goOnDuty() else dutySetup = true }
    val toggleDuty: () -> Unit = {
        if (onDuty) {
            session.onDuty = false; onDuty = false
            com.haraan.partner.alerts.BookingWatchService.stop(appContext)
        } else if (android.os.Build.VERSION.SDK_INT >= 33 &&
            androidx.core.content.ContextCompat.checkSelfPermission(appContext, android.Manifest.permission.POST_NOTIFICATIONS) != android.content.pm.PackageManager.PERMISSION_GRANTED
        ) {
            notifPermission.launch(android.Manifest.permission.POST_NOTIFICATIONS)
        } else if (!com.haraan.partner.alerts.AlertOverlay.allowed(appContext)) {
            dutySetup = true
        } else goOnDuty()
    }
    if (dutySetup) {
        DutySetupDialog(
            onAllow = {
                dutySetup = false
                goOnDuty()
                runCatching {
                    appContext.startActivity(
                        android.content.Intent(
                            android.provider.Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                            android.net.Uri.parse("package:" + appContext.packageName),
                        ).addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK),
                    )
                }
            },
            onNotificationsOnly = { dutySetup = false; goOnDuty() },
            onDismiss = { dutySetup = false },
        )
    }
    // Bumped whenever a new booking lands, so an open Home refetches quietly and
    // its money figures count up to the new total instead of waiting for a pull.
    var moneyLanded by remember { mutableStateOf(0) }
    val lifecycle = androidx.lifecycle.compose.LocalLifecycleOwner.current.lifecycle
    var seenHere by remember { mutableStateOf(0L) }
    LaunchedEffect(token) {
        // Only while the app is on screen: in the background this pulled 100
        // bookings every 20 seconds for nobody, and drained the desk phone's battery.
        lifecycle.repeatOnLifecycle(androidx.lifecycle.Lifecycle.State.STARTED) {
        while (true) {
            runCatching {
                val list = api.bookings(token)
                val maxId = list.maxOfOrNull { it.id } ?: 0L
                val last = session.lastNotifiedBookingId
                if (session.onDuty) {
                    // The service announces bookings (over any app, with its chime);
                    // here we only refresh Home when one has landed.
                    if (maxId > seenHere && seenHere != 0L) { moneyLanded++; unseenBookings += 1 }
                    seenHere = maxId
                } else when {
                    last == 0L -> session.lastNotifiedBookingId = maxId // baseline; don't alert on first load
                    maxId > last -> {
                        // Holds still waiting for their money are left out until paid.
                        val (fresh, mark) = com.haraan.partner.alerts.BookingAlert.pickFresh(list, last)
                        session.lastNotifiedBookingId = mark
                        if (fresh.isNotEmpty()) {
                            unseenBookings += fresh.size
                            // Deliberately NOT branch-filtered: an owner wants to know
                            // a booking landed anywhere, not only at the outlet they
                            // happen to be looking at — so the alert names the branch.
                            inAppAlert = com.haraan.partner.alerts.BookingAlert.from(fresh.first(), more = fresh.size - 1)
                            moneyLanded++
                            com.haraan.partner.alerts.AlertChime.play(appContext)
                        }
                    }
                }
            }
            kotlinx.coroutines.delay(20_000)
        }
        }
    }
    val openFromAlert by com.haraan.partner.alerts.AlertRouter.openBookings.collectAsState()
    LaunchedEffect(openFromAlert) {
        if (openFromAlert) { tab = Tab.Sales; unseenBookings = 0; com.haraan.partner.alerts.AlertRouter.consumed() }
    }
    LaunchedEffect(bookingBanner) {
        if (bookingBanner != null) { kotlinx.coroutines.delay(6_000); bookingBanner = null }
    }

    // The phone's Back closes whichever console screen is open, in the same order
    // they take the screen below. There was no handler at all, so Back from
    // Payouts, Reports, the venue desk — any of them — closed the whole app.
    // Screens with their own inner steps register their own handler later in
    // composition, which wins over this one.
    BackHandler(enabled = screenOpen) {
        when {
            showNotifications -> showNotifications = false
            showSupport -> showSupport = false
            showAcademy -> showAcademy = false
            showPackages -> showPackages = false
            showCustomers -> showCustomers = false
            showPayouts -> showPayouts = false
            showReports -> showReports = false
            showStaff -> showStaff = false
            showShiftRegister -> showShiftRegister = false
            showStandingSlots -> showStandingSlots = false
            showPricingMatrix -> showPricingMatrix = false
            showWhatsAppDesk -> showWhatsAppDesk = false
            showOperationsCenter -> showOperationsCenter = false
            detail != null -> detail = null
            manageVenue != null -> { manageVenue = null; manageStartsInSlots = false }
        }
    }

    if (showNotifications) {
        NotificationsScreen(api, token, onBack = { showNotifications = false })
        return
    }
    if (showSupport) {
        SupportScreen(api, token, onBack = { showSupport = false })
        return
    }
    if (showAcademy) {
        AcademyScreen(api, token, onBack = { showAcademy = false })
        return
    }
    if (showPackages) {
        PackagesScreen(api, token, session.branchId, onBack = { showPackages = false })
        return
    }
    if (showCustomers) {
        CustomersScreen(api, token, session.branchId, onBack = { showCustomers = false })
        return
    }
    if (showPayouts) {
        PayoutsScreen(api, token, onBack = { showPayouts = false })
        return
    }
    if (showReports) {
        com.haraan.partner.reports.ReportsScreen(api, token, onBack = { showReports = false })
        return
    }
    if (showStaff) {
        StaffScreen(api, token, onBack = { showStaff = false })
        return
    }

    if (showShiftRegister) {
        val targetVenue = ctx?.branches?.firstOrNull { it.id == branchId }
            ?: ctx?.branches?.firstOrNull()
        // No fallback id: guessing venue 1 opened some other business's data.
        val venueId = targetVenue?.id
        if (venueId == null) {
            VenueToolNotice("Shift register", "Shift register needs a venue", NO_VENUE_NOTE, onBack = { showShiftRegister = false })
            return
        }
        val context = LocalContext.current
        val localDs = remember { ShiftLocalDataSource(context) }
        val repo = remember { ShiftRepository(localDs) }
        val shiftVm = remember { ShiftViewModel(repo, token) }
        ShiftRegisterScreen(
            viewModel = shiftVm,
            venueId = venueId,
            onNavigateBack = { showShiftRegister = false }
        )
        return
    }

    if (showStandingSlots) {
        val targetVenue = ctx?.branches?.firstOrNull { it.id == branchId }
            ?: ctx?.branches?.firstOrNull()
        val context = LocalContext.current
        val localDs = remember { com.haraan.partner.recurring.data.RecurringLocalDataSource(context) }
        val repo = remember { com.haraan.partner.recurring.data.RecurringRepository(localDs) }
        val recurringVm = remember { com.haraan.partner.recurring.viewmodel.StandingContractViewModel(repo, token) }
        // The venue's real courts. This was a hard-coded "Court 1/2/3", so contracts were
        // written against court ids that belong to other venues or don't exist.
        WithVenueCourts(api, token, targetVenue?.id, "Standing slots", onBack = { showStandingSlots = false }) { venueId, courts ->
            com.haraan.partner.recurring.ui.StandingSlotsMasterScreen(
                viewModel = recurringVm,
                venueId = venueId,
                availableCourts = courts,
                onNavigateBack = { showStandingSlots = false }
            )
        }
        return
    }

    if (showPricingMatrix) {
        val targetVenue = ctx?.branches?.firstOrNull { it.id == branchId }
            ?: ctx?.branches?.firstOrNull()
        val context = LocalContext.current
        val localDs = remember { com.haraan.partner.pricing.data.PricingLocalDataSource(context) }
        val repo = remember { com.haraan.partner.pricing.data.PricingRepository(localDs) }
        val pricingVm = remember { com.haraan.partner.pricing.viewmodel.PricingMatrixViewModel(repo, token) }
        // Real courts, not a hard-coded "Court 1/2/3" (see Standing slots above).
        WithVenueCourts(api, token, targetVenue?.id, "Pricing", onBack = { showPricingMatrix = false }) { venueId, courts ->
            com.haraan.partner.pricing.ui.PricingMatrixDashboard(
                viewModel = pricingVm,
                venueId = venueId,
                availableCourts = courts,
                onNavigateBack = { showPricingMatrix = false }
            )
        }
        return
    }

    if (showWhatsAppDesk) {
        val targetVenue = ctx?.branches?.firstOrNull { it.id == branchId }
            ?: ctx?.branches?.firstOrNull()
        // No fallback id: guessing venue 1 would open some other business's inbox (404).
        val venueId = targetVenue?.id
        val context = LocalContext.current
        val localDs = remember { com.haraan.partner.whatsapp.data.WhatsAppLocalDataSource(context) }
        val remoteDs = remember { com.haraan.partner.whatsapp.data.WhatsAppRemoteDataSource() }
        val repo = remember { com.haraan.partner.whatsapp.data.WhatsAppRepository(remoteDs, localDs) }
        val whatsappVm = remember { com.haraan.partner.whatsapp.viewmodel.WhatsAppDeskViewModel(repo, token) }
        com.haraan.partner.whatsapp.ui.WhatsAppDeskDashboard(
            viewModel = whatsappVm,
            venueId = venueId,
            onNavigateBack = { showWhatsAppDesk = false }
        )
        return
    }

    if (showOperationsCenter) {
        val targetVenue = ctx?.branches?.firstOrNull { it.id == branchId }
            ?: ctx?.branches?.firstOrNull()
        val venueId = targetVenue?.id
        if (venueId == null) {
            VenueToolNotice("Operations", "Operations needs a venue", NO_VENUE_NOTE, onBack = { showOperationsCenter = false })
            return
        }
        val context = LocalContext.current
        val localDs = remember { com.haraan.partner.operations.data.OperationsLocalDataSource(context) }
        val remoteDs = remember { com.haraan.partner.operations.data.OperationsRemoteDataSource() }
        val repo = remember { com.haraan.partner.operations.data.OperationsRepository(remoteDs, localDs) }
        val opsVm = remember { com.haraan.partner.operations.viewmodel.OwnerOperationsViewModel(repo, token) }
        com.haraan.partner.operations.ui.OwnerOperationsDashboard(
            viewModel = opsVm,
            venueId = venueId,
            onNavigateBack = { showOperationsCenter = false }
        )
        return
    }
    detail?.let { target ->
        AnalyticsScreen(api, token, target, onBack = { detail = null })
        return
    }

    manageVenue?.let { (id, name) ->
        VenueDayScreen(
            api, token, id, name,
            onBack = { manageVenue = null; manageStartsInSlots = false },
            startInSlots = manageStartsInSlots,
            onAnalytics = { detail = AnalyticsTarget(AnalyticsKind.Venue, id, name) },
            canPricing = session.can("pricing"),
            canBookings = session.can("bookings"),
            lane = lane,
        )
        return
    }

    // The drawer is the app's one index: every destination and every tool lives
    // in it, so nothing is reachable only by remembering which header glyph it
    // hid behind. The topbar keeps just the bell, which is a live alert rather
    // than a destination.
    val drawerState = rememberDrawerState(DrawerValue.Closed)
    val drawerScope = rememberCoroutineScope()
    fun closeDrawer() { drawerScope.launch { drawerState.close() } }

    ModalNavigationDrawer(
        drawerState = drawerState,
        drawerContent = {
            PartnerDrawer(
                session = session,
                ctx = ctx,
                lane = lane,
                tabs = navTabs,
                current = tab,
                branchId = branchId,
                onTab = { picked -> tab = picked; closeDrawer() },
                onBranch = { picked ->
                    branchId = picked
                    session.branchId = picked
                    closeDrawer()
                },
                onCustomers = { showCustomers = true; closeDrawer() },
                onPackages = if (session.can("pricing")) ({ showPackages = true; closeDrawer() }) else null,
                onStaff = if (!session.isDesk) ({ showStaff = true; closeDrawer() }) else null,
                onPayouts = if (session.can("reports")) ({ showPayouts = true; closeDrawer() }) else null,
                onReports = if (session.can("reports")) ({ showReports = true; closeDrawer() }) else null,
                onAcademy = if (session.can("pricing")) ({ showAcademy = true; closeDrawer() }) else null,
                onShiftRegister = if (lane == Lane.VENUE || lane == Lane.CAFE) ({ showShiftRegister = true; closeDrawer() }) else null,
                onStandingSlots = if (lane == Lane.VENUE || lane == Lane.BOTH) ({ showStandingSlots = true; closeDrawer() }) else null,
                onPricingMatrix = if ((lane == Lane.VENUE || lane == Lane.BOTH) && session.can("pricing")) ({ showPricingMatrix = true; closeDrawer() }) else null,
                onWhatsAppDesk = if (!WHATSAPP_DESK_LOCKED && (lane == Lane.VENUE || lane == Lane.BOTH)) ({ showWhatsAppDesk = true; closeDrawer() }) else null,
                onOperationsCenter = if (lane == Lane.VENUE || lane == Lane.BOTH) ({ showOperationsCenter = true; closeDrawer() }) else null,
                onNotifications = { showNotifications = true; closeDrawer() },
                onSupport = { showSupport = true; closeDrawer() },
                onSignOut = { session.clear(); onSignedOut() },
            )
        },
    ) {
    // Bookings on a venue is the day desk, which has its own header (back, venue name,
    // view switches); the white app bar on top of it was a second header.
    val deskTab = tab == Tab.Sales && (lane == Lane.VENUE || lane == Lane.CAFE) &&
        ctx?.branches?.isNotEmpty() == true
    Scaffold(
        topBar = {
            // Scan is full-screen camera, and Home carries its own chrome inside
            // its hero: neither gets the white header. The floating bar below is
            // still there to leave them.
            if (tab != Tab.Scan && tab != Tab.Home && !deskTab) TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color.White,
                    scrolledContainerColor = Color.White,
                ),
                navigationIcon = {
                    IconButton(onClick = { drawerScope.launch { drawerState.open() } }) {
                        Icon(
                            Icons.Filled.Menu,
                            contentDescription = "Menu",
                            tint = AuthInk,
                            modifier = Modifier.size(22.dp),
                        )
                    }
                },
                title = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        // The wordmark opens the drawer too: the brand is the one
                        // thing always in the same place, so it is the most
                        // findable target on the screen.
                        Image(
                            painter = painterResource(R.drawable.haraan_logo),
                            contentDescription = "Haraan",
                            contentScale = ContentScale.Fit,
                            modifier = Modifier
                                .height(22.dp)
                                .clickable { drawerScope.launch { drawerState.open() } },
                        )
                        ctx?.takeIf { it.isMultiBranch }?.let { known ->
                            Spacer(Modifier.width(8.dp))
                            BranchSwitcher(known, branchId) { picked ->
                                branchId = picked
                                session.branchId = picked
                            }
                        }
                    }
                },
                actions = {
                    BellIcon(unseenBookings) { tab = Tab.Sales; unseenBookings = 0 }
                    val focus = venues?.let { list -> list.firstOrNull { it.id == branchId } ?: list.firstOrNull() }
                    VenueAvatar(
                        photo = focus?.image,
                        initial = (focus?.name ?: session.name ?: "H").trim().take(1).uppercase(),
                    ) { drawerScope.launch { drawerState.open() } }
                    Spacer(Modifier.width(10.dp))
                },
            )
        },
        bottomBar = {
            HaraanBottomBar(
                items = tabs.map { navItemFor(it, lane) },
                selectedKey = tab.name,
                onSelect = { key -> tab = Tab.valueOf(key) },
                accent = AuthAccentDeep,
                idle = NavIdle,
                // The page colour, so the floating capsule sits on the screen
                // itself rather than on a white strip.
                container = AuthPageBg,
                hairline = Hairline,
                overMedia = tab == Tab.Scan,
            )
        },
        // Scan is a camera: black behind it, and the picture runs under the
        // floating bar instead of stopping at a pale band above it.
        containerColor = if (tab == Tab.Scan) Color.Black else AuthPageBg,
    ) { padding ->
        Column(
            Modifier.fillMaxSize().padding(
                // Scan and Home draw their own status-bar space: the camera and the
                // navy hero run to the top of the screen.
                top = if (tab == Tab.Scan || tab == Tab.Home) 0.dp else padding.calculateTopPadding(),
                bottom = if (tab == Tab.Scan) 0.dp else padding.calculateBottomPadding(),
            ),
        ) {
            bookingBanner?.let { msg ->
                LayoutBox(if (tab == Tab.Home || tab == Tab.Scan) Modifier.statusBarsPadding() else Modifier) {
                    BookingBanner(msg) { bookingBanner = null; tab = Tab.Sales; unseenBookings = 0 }
                }
            }
            inAppAlert?.let { a ->
                LayoutBox(Modifier.fillMaxWidth().zIndex(10f).statusBarsPadding().padding(top = 8.dp)) {
                    androidx.compose.runtime.key(a.id) {
                        com.haraan.partner.alerts.BookingAlertCard(
                            alert = a,
                            onOpen = { inAppAlert = null; tab = Tab.Sales; unseenBookings = 0 },
                            onDismiss = { inAppAlert = null },
                            formatInr = ::formatInr,
                        )
                    }
                }
            }
            when (tab) {
                // Customers, Packages, Academy and Payouts are reached from the
                // drawer, which already lists every one of them — Home no longer
                // repeats that list as four cards.
                Tab.Home -> HomeTab(
                    api, token, session.name ?: "Partner", lane, branchId,
                    onDuty = onDuty,
                    onToggleDuty = toggleDuty,
                    venues = venues,
                    reloadSignal = moneyLanded,
                    unseen = unseenBookings,
                    onBookings = { tab = Tab.Sales; unseenBookings = 0 },
                    onSetUpSlots = { id, name -> manageStartsInSlots = true; manageVenue = id to name },
                    onOpenDesk = { id, name -> manageStartsInSlots = false; manageVenue = id to name },
                    onPricing = if (session.can("pricing")) ({ id, name -> manageStartsInSlots = true; manageVenue = id to name }) else null,
                    onScan = { tab = Tab.Scan },
                    onSupport = { showSupport = true },
                    onReports = if (session.can("reports")) ({ showReports = true }) else null,
                    onWhatsApp = if (!WHATSAPP_DESK_LOCKED && (lane == Lane.VENUE || lane == Lane.BOTH)) ({ showWhatsAppDesk = true }) else null,
                    onSettlement = if (session.can("reports")) ({ showPayouts = true }) else null,
                    onMenu = { drawerScope.launch { drawerState.open() } },
                    onBell = { tab = Tab.Sales; unseenBookings = 0 },
                    branchSwitcher = ctx?.takeIf { it.isMultiBranch }?.let { known ->
                        {
                            BranchSwitcher(known, branchId, onDark = false) { picked ->
                                branchId = picked
                                session.branchId = picked
                            }
                        }
                    },
                ) { serverType ->
                    if (serverType != null) {
                        session.partnerType = serverType
                        lane = laneOf(serverType)
                    }
                }
                Tab.Events -> EventsTab(api, token) { id, name ->
                    detail = AnalyticsTarget(AnalyticsKind.Event, id, name)
                }
                Tab.Venues -> com.haraan.partner.ui.venues.VenuesScreen(
                    api = api,
                    token = token,
                    canPricing = session.can("pricing"),
                    canReports = session.can("reports"),
                    onDesk = { id, name -> manageStartsInSlots = false; manageVenue = id to name },
                    onPricing = { id, name -> manageStartsInSlots = true; manageVenue = id to name },
                    onAnalytics = { id, name -> detail = AnalyticsTarget(AnalyticsKind.Venue, id, name) },
                    onSupport = { showSupport = true },
                )
                Tab.Matches -> MatchesScreen(api, token, branchId)
                Tab.Payments -> com.haraan.partner.payments.PaymentsScreen(api, token, branchId, canManage = session.can("bookings"))
                Tab.Sales -> {
                    if (lane == Lane.VENUE || lane == Lane.CAFE) {
                        val targetVenue = ctx?.branches?.firstOrNull { it.id == branchId }
                            ?: ctx?.branches?.firstOrNull()
                        if (targetVenue != null) {
                            DayBookingsScreen(
                                api = api,
                                token = token,
                                venueId = targetVenue.id,
                                venueName = targetVenue.name.ifBlank { targetVenue.branch },
                                onBack = { tab = Tab.Home },
                                embedded = true,
                                canPricing = session.can("pricing"),
                                canBookings = session.can("bookings"),
                                onPricing = {
                                    manageVenue = targetVenue.id to (targetVenue.name.ifBlank { targetVenue.branch })
                                },
                                onAnalytics = {
                                    detail = AnalyticsTarget(AnalyticsKind.Venue, targetVenue.id, targetVenue.name.ifBlank { targetVenue.branch })
                                },
                            )
                        } else {
                            SalesTab(api, token, branchId)
                        }
                    } else {
                        SalesTab(api, token, branchId)
                    }
                }
                Tab.Scan -> ScanTab(api, token, bottomInset = padding.calculateBottomPadding())
            }
        }
    }
    }
}

/**
 * The slide-out index of the whole console.
 *
 * Three bands, in the order a partner actually thinks: where am I going
 * (the tabs), what do I want to run (the tools that used to hide as topbar
 * glyphs), and which outlet am I looking at. Sign out sits alone at the
 * bottom so nobody hits it reaching for anything else.
 *
 * Every tool is passed as a nullable lambda: a null means this account can't
 * do that, and the row simply isn't drawn — the drawer never shows a partner
 * a door that would only tell them no.
 */
@Composable
private fun PartnerDrawer(
    session: Session,
    ctx: PartnerContext?,
    lane: Lane,
    tabs: List<Tab>,
    current: Tab,
    branchId: Long?,
    onTab: (Tab) -> Unit,
    onBranch: (Long?) -> Unit,
    onCustomers: () -> Unit,
    onPackages: (() -> Unit)?,
    onStaff: (() -> Unit)?,
    onPayouts: (() -> Unit)?,
    onReports: (() -> Unit)?,
    onAcademy: (() -> Unit)?,
    onShiftRegister: (() -> Unit)? = null,
    onStandingSlots: (() -> Unit)? = null,
    onPricingMatrix: (() -> Unit)? = null,
    onWhatsAppDesk: (() -> Unit)? = null,
    onOperationsCenter: (() -> Unit)? = null,
    onNotifications: () -> Unit,
    onSupport: () -> Unit,
    onSignOut: () -> Unit,
) {
    HaraanPartnerDrawer(
        session = session,
        ctx = ctx,
        lane = lane,
        tabs = tabs,
        current = current,
        branchId = branchId,
        onTab = onTab,
        onBranch = onBranch,
        onCustomers = onCustomers,
        onPackages = onPackages,
        onStaff = onStaff,
        onPayouts = onPayouts,
        onReports = onReports,
        onAcademy = onAcademy,
        onShiftRegister = onShiftRegister,
        onStandingSlots = onStandingSlots,
        onPricingMatrix = onPricingMatrix,
        onWhatsAppDesk = onWhatsAppDesk,
        onOperationsCenter = onOperationsCenter,
        onNotifications = onNotifications,
        onSupport = onSupport,
        onSettings = onNotifications,
        onSignOut = onSignOut,
    )
}

/** Navy identity block: who is signed in, and at what altitude. */
@Composable
private fun DrawerHeader(session: Session, ctx: PartnerContext?) {
    val name = ctx?.businessName ?: session.name ?: "Partner"
    // Same aurora as the login band and the revenue hero, so the drawer reads as
    // part of the app rather than a stock Material panel.
    LayoutBox(
        Modifier
            .fillMaxWidth()
            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid, AuthInkBot))),
    ) {
        LayoutBox(
            Modifier.matchParentSize().background(
                Brush.radialGradient(
                    listOf(Color(0x553B82F6), Color(0x00000000)),
                    center = Offset(90f, 40f), radius = 460f,
                )
            )
        )
        Column(
            Modifier.statusBarsPadding().padding(start = 20.dp, end = 20.dp, top = 14.dp, bottom = 15.dp),
        ) {
            Image(
                painter = painterResource(R.drawable.haraan_logo_white),
                contentDescription = "Haraan",
                contentScale = ContentScale.Fit,
                colorFilter = ColorFilter.tint(Color.White),
                modifier = Modifier.height(18.dp),
            )
            Spacer(Modifier.height(16.dp))
            // Monogram beside the name (not centred on the whole block, which left
            // it hanging below the text), chips on their own line underneath.
            Row(verticalAlignment = Alignment.CenterVertically) {
                LayoutBox(
                    Modifier.size(38.dp).clip(RoundedCornerShape(12.dp))
                        .background(Color(0x2E7DA9FF))
                        .border(1.dp, Color(0x3DFFFFFF), RoundedCornerShape(12.dp)),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        name.trim().take(1).uppercase(),
                        fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = Color.White,
                    )
                }
                Spacer(Modifier.width(11.dp))
                Text(
                    name,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.ExtraBold,
                    color = Color.White,
                    letterSpacing = (-0.2).sp,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f),
                )
            }
            Spacer(Modifier.height(11.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                DrawerChip(altitudeLabel(session, ctx))
                ctx?.typeLabel?.let {
                    Spacer(Modifier.width(6.dp))
                    DrawerChip(it)
                }
            }
        }
    }
}

/** Reads the partner's altitude for the header chip. Owners see "Owner". */
internal fun altitudeLabel(session: Session, ctx: PartnerContext?): String = when {
    ctx?.altitude == "desk" || session.isDesk -> "Desk"
    ctx?.altitude == "manager" -> "Manager"
    else -> "Owner"
}

@Composable
private fun DrawerChip(text: String) {
    Text(
        text,
        fontSize = 11.5.sp,
        fontWeight = FontWeight.SemiBold,
        color = Color(0xFFBFD0FF),
        modifier = Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(Color(0x2E4C7DFF))
            .padding(horizontal = 10.dp, vertical = 4.dp),
    )
}

@Composable
private fun DrawerSection(label: String) {
    // Label + hairline: the rule is what makes these read as groups rather than
    // stray small text floating above a flat list.
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.fillMaxWidth().padding(start = 21.dp, end = 20.dp, top = 8.dp, bottom = 4.dp),
    ) {
        Text(
            label.uppercase(),
            fontSize = 10.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.2.sp,
            color = Color(0xFF94A3B8),
        )
        Spacer(Modifier.width(10.dp))
        LayoutBox(Modifier.weight(1f).height(1.dp).background(Hairline))
    }
}

@Composable
private fun DrawerTick() {
    Icon(Icons.Filled.Check, contentDescription = null, tint = AuthAccentDeep, modifier = Modifier.size(17.dp))
}

/**
 * One drawer line. Selection is carried by a tinted pill rather than a side
 * rail so it reads the same as the branch chip in the topbar.
 */
@Composable
private fun DrawerRow(
    icon: ImageVector,
    label: String,
    selected: Boolean,
    onClick: () -> Unit,
    tint: Color? = null,
    trailing: @Composable (() -> Unit)? = null,
) {
    val fg = tint ?: if (selected) AuthAccentDeep else AuthInk
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(if (pressed) 0.975f else 1f, label = "drawer-row")

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 10.dp, vertical = 1.dp)
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .clip(RoundedCornerShape(13.dp))
            .background(if (selected) Color(0x142F6BFF) else Color.Transparent)
            .clickable(interactionSource = interaction, indication = null) {
                view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                onClick()
            }
            .padding(horizontal = 9.dp, vertical = 5.dp),
    ) {
        // A tonal container instead of a bare grey glyph — the same device the
        // dashboard entry cards use, so the drawer belongs to the same app.
        LayoutBox(
            Modifier.size(32.dp).clip(RoundedCornerShape(10.dp))
                .background(
                    when {
                        tint != null -> tint.copy(alpha = 0.10f)
                        selected -> Color(0x1F2F6BFF)
                        else -> Color(0x0A0F172A)
                    }
                ),
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                icon,
                contentDescription = null,
                tint = if (selected || tint != null) fg else Color(0xFF64748B),
                modifier = Modifier.size(17.dp),
            )
        }
        Spacer(Modifier.width(12.dp))
        Text(
            label,
            fontSize = 14.5.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = fg,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )
        trailing?.invoke()
    }
}

/**
 * The branch switcher — a chip in the topbar that opens the outlet list.
 *
 * Lives beside the wordmark rather than in the actions row because it answers
 * "where am I", which is orientation, not an action. Switching is instant and
 * global: no partner should walk a settings tree to change what they're looking
 * at.
 *
 * Rendered only when there is more than one branch, so today's single-venue
 * partners see no change at all.
 */
@Composable
private fun BranchSwitcher(ctx: PartnerContext, selected: Long?, onDark: Boolean = false, onSelect: (Long?) -> Unit) {
    val tint = if (onDark) Color.White else AuthAccentDeep
    var open by remember { mutableStateOf(false) }

    // LayoutBox, not Box: see CenteredPane — a bare `Box` here binds to a
    // fillMaxSize() helper and takes the whole top bar with it.
    LayoutBox {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .clip(RoundedCornerShape(999.dp))
                .background(if (onDark) Color(0x1FFFFFFF) else Color(0x142F6BFF))
                .clickable { open = true }
                .padding(start = 9.dp, end = 6.dp, top = 5.dp, bottom = 5.dp),
        ) {
            Icon(Icons.Filled.Place, null, tint = tint, modifier = Modifier.size(13.dp))
            Spacer(Modifier.width(4.dp))
            Text(
                ctx.branchName(selected),
                fontSize = 12.5.sp,
                fontWeight = FontWeight.SemiBold,
                color = tint,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.widthIn(max = 104.dp),
            )
            Icon(Icons.Filled.ExpandMore, null, tint = tint, modifier = Modifier.size(15.dp))
        }

        DropdownMenu(expanded = open, onDismissRequest = { open = false }) {
            DropdownMenuItem(
                text = { BranchMenuRow("All branches", "${ctx.branches.size} outlets", selected == null) },
                onClick = { onSelect(null); open = false },
            )
            ctx.branches.forEach { b ->
                val sub = listOfNotNull(
                    b.code ?: b.city,
                    if (b.isActive) null else "inactive",
                ).joinToString(" · ")
                DropdownMenuItem(
                    text = { BranchMenuRow(b.branch, sub, selected == b.id) },
                    onClick = { onSelect(b.id); open = false },
                )
            }
        }
    }
}

@Composable
private fun BranchMenuRow(name: String, sub: String, isOn: Boolean) {
    Column {
        Text(
            name,
            fontSize = 13.5.sp,
            fontWeight = if (isOn) FontWeight.Bold else FontWeight.SemiBold,
            color = if (isOn) AuthAccentDeep else AuthInk,
            maxLines = 1,
        )
        if (sub.isNotBlank()) Text(sub, fontSize = 11.sp, color = AuthMuted, maxLines = 1)
    }
}

internal fun iconFor(tab: Tab) = when (tab) {
    Tab.Home -> Icons.Filled.Home
    Tab.Matches -> Icons.Filled.Flag
    Tab.Events -> Icons.Filled.CalendarMonth
    Tab.Venues -> Icons.Filled.Place
    Tab.Sales -> Icons.Filled.Payments
    Tab.Payments -> Icons.Filled.AccountBalanceWallet
    Tab.Scan -> Icons.Filled.QrCodeScanner
}

/**
 * The bottom bar's version of a tab: both glyph states plus the lane's word for
 * it. Kept apart from [iconFor], which still feeds the drawer's Material rows.
 */
private fun navItemFor(tab: Tab, lane: Lane): HaraanNavItem {
    val (outline, active) = when (tab) {
        Tab.Home -> HaraanNavIcons.HomeOutline to HaraanNavIcons.HomeActive
        Tab.Events -> HaraanNavIcons.CalendarOutline to HaraanNavIcons.CalendarActive
        Tab.Venues -> HaraanNavIcons.PinOutline to HaraanNavIcons.PinActive
        Tab.Matches -> HaraanNavIcons.FlagOutline to HaraanNavIcons.FlagActive
        Tab.Sales -> HaraanNavIcons.ReceiptOutline to HaraanNavIcons.ReceiptActive
        Tab.Payments -> HaraanNavIcons.WalletOutline to HaraanNavIcons.WalletActive
        Tab.Scan -> HaraanNavIcons.ScanOutline to HaraanNavIcons.ScanActive
    }
    return HaraanNavItem(tab.name, labelFor(tab, lane), outline, active)
}

/**
 * Tab labels follow the lane's vocabulary.
 *
 * A venue takes "Bookings" where a host makes "Sales"; a café runs "Outlets"
 * where a turf lists "Venues". The tab SET is already right for a café — it
 * keeps Events, which a sports venue doesn't get — so only the words change.
 */
internal fun labelFor(tab: Tab, lane: Lane): String = when {
    tab == Tab.Sales && (lane == Lane.VENUE || lane == Lane.CAFE) -> "Bookings"
    tab == Tab.Venues && lane == Lane.CAFE -> "Outlets"
    else -> tab.label
}

/**
 * Loads data once, shows it, and lets the user swipe down to reload. Keeps the
 * current content on screen while a refresh spins, so it never flashes empty.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun <T> RefreshableContent(
    key: Any?,
    load: suspend () -> T,
    /** Bump to refetch in place: no skeleton, and a failed refetch keeps what's shown. */
    reloadSignal: Int = 0,
    content: @Composable (T) -> Unit,
) {
    var data by remember(key) { mutableStateOf<UiState<T>>(UiState.Loading) }
    var refreshing by remember(key) { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    LaunchedEffect(key) { data = runCatchingUi { load() } }
    LaunchedEffect(key, reloadSignal) {
        if (reloadSignal > 0 && data is UiState.Data) {
            val fresh = runCatchingUi { load() }
            if (fresh is UiState.Data) data = fresh
        }
    }
    PullToRefreshBox(
        isRefreshing = refreshing,
        onRefresh = {
            Haptics.tick(view)
            scope.launch {
                refreshing = true
                data = runCatchingUi { load() }
                refreshing = false
            }
        },
        modifier = Modifier.fillMaxSize(),
    ) {
        Loaded(data) { content(it) }
    }
}

private data class Tile(val icon: ImageVector, val label: String, val value: String, val hint: String)

@Composable
private fun HomeTab(
    api: PartnerApi,
    token: String,
    name: String,
    lane: Lane,
    venueId: Long? = null,
    onDuty: Boolean = false,
    onToggleDuty: () -> Unit = {},
    /** The partner's venues, loaded once by the scaffold. Null while that load is in flight. */
    venues: List<VenueSummary>? = null,
    reloadSignal: Int = 0,
    /** New bookings since the partner last looked; drives the bell's badge. */
    unseen: Int = 0,
    onBookings: () -> Unit,
    /** Opens a venue's slot editor: the one place a court becomes bookable. */
    onSetUpSlots: (Long, String) -> Unit = { _, _ -> },
    /** Opens a venue's day desk (grid, walk-ins). */
    onOpenDesk: (Long, String) -> Unit = { _, _ -> },
    /** Opens a venue's courts & pricing; null when this partner may not change prices. */
    onPricing: ((Long, String) -> Unit)? = null,
    onScan: () -> Unit = {},
    onSupport: () -> Unit = {},
    /** Each null when this partner may not open that screen; its door isn't drawn. */
    onReports: (() -> Unit)? = null,
    onWhatsApp: (() -> Unit)? = null,
    onSettlement: (() -> Unit)? = null,
    onMenu: () -> Unit = {},
    onBell: () -> Unit = {},
    /** The outlet picker for multi-branch partners; null when there is one venue. */
    branchSwitcher: (@Composable () -> Unit)? = null,
    onLane: (String?) -> Unit,
) {
    // Home is the venue's day, told top to bottom: whose venue this is, what the
    // day is worth, the four things the desk does most, the courts hour by hour,
    // and who is next. Every line is real data or a real action — an empty
    // account gets a setup path, not cards reading ₹0.
    val branch = venueId
    // The venue this screen is about: the picked branch, else the first one. Its
    // photo and name are what make the app feel like theirs, not a template's.
    val focus = venues?.let { list -> list.firstOrNull { it.id == branch } ?: list.firstOrNull() }
    val courtsLane = lane == Lane.VENUE || lane == Lane.CAFE || lane == Lane.BOTH
    // Three calls, fetched together: the day's sheet, the long view, and the
    // venue's court grid for today's slot strip. The grid is optional: a failure
    // there loses the strip, never the screen.
    RefreshableContent(
        Triple(token, branch, focus?.id),
        reloadSignal = reloadSignal,
        load = {
            coroutineScope {
                val overview = async { api.overview(token, branch) }
                val sheet = async { api.today(token, branch) }
                val grid = async {
                    if (focus != null && courtsLane) {
                        runCatching { api.venueDay(token, focus.id, apiDate(todayMillis())) }.getOrNull()
                    } else null
                }
                val payouts = async {
                    if (onSettlement != null) runCatching { api.payouts(token) }.getOrNull() else null
                }
                val whatsapp = async {
                    if (onWhatsApp != null && focus != null && courtsLane) {
                        runCatching {
                            com.haraan.partner.whatsapp.data.WhatsAppRemoteDataSource().fetchDashboard(token, focus.id)
                        }.getOrNull()
                    } else null
                }
                // The latest bookings with their money, for the payments feed.
                val recent = async {
                    if (courtsLane) runCatching { api.bookings(token, branch) }.getOrNull() else null
                }
                // Tomorrow's courts, for the top card once today's hours are over.
                val tomorrowGrid = async {
                    if (focus != null && courtsLane) {
                        runCatching { api.venueDay(token, focus.id, apiDate(todayMillis() + DAY_MS)) }.getOrNull()
                    } else null
                }
                // The charts are extra: a failure loses them, never Home.
                val insights = async {
                    if (courtsLane) runCatching { api.insights(token, branch) }.getOrNull() else null
                }
                HomeData(overview.await(), sheet.await(), grid.await(), payouts.await(), whatsapp.await(), insights.await(), tomorrowGrid.await(), recent.await())
            }
        },
    ) { data ->
        val o = data.overview
        val day = data.day
        LaunchedEffect(o.type) { onLane(o.type) }
        // Set-up means the venue has no slots at all. Today's capacity is 0 on a closed
        // day too, and an established venue was told it "isn't bookable yet".
        val settingUp = courtsLane && !day.hasAnySlots
        // The customers screen opens over Home from the new vs returning card.
        var customersOpen by remember { mutableStateOf<Pair<String, String>?>(null) }
        customersOpen?.let { (period, type) ->
            CustomersScreen(api, token, branch, period, type) { customersOpen = null }
        }
        // The week pager lives out here: LazyColumn items can't remember across pages.
        val insights = data.insights?.takeIf { it.enabled && !settingUp }?.let { initial ->
            rememberInsightsState(initial) { week -> api.insights(token, branch, week) }
        }
        val listState = rememberLazyListState()
        val density = LocalDensity.current
        // Past the hero the page grows a slim white bar, so the venue's name and
        // the menu never scroll out of reach.
        val collapsed by remember {
            derivedStateOf {
                listState.firstVisibleItemIndex > 0 ||
                    listState.firstVisibleItemScrollOffset > with(density) { 190.dp.toPx() }
            }
        }
        LightStatusBar(light = false)
        // The booking the desk tapped on the board: who, their number, and one tap to call.
        var counter by remember { mutableStateOf<CounterPick?>(null) }
        counter?.let { pick -> CounterSheet(pick, onDismiss = { counter = null }, onBookings = { counter = null; onBookings() }) }

        LayoutBox(Modifier.fillMaxSize().background(AuthPageBg)) {
            LazyColumn(
                state = listState,
                modifier = Modifier.fillMaxSize(),
                verticalArrangement = Arrangement.spacedBy(16.dp),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 32.dp),
            ) {
                item(key = "hero") {
                    Rise(0) {
                        val todayHours = data.grid?.let { courtHours(it) }?.takeIf { it.isNotEmpty() }
                        val tomorrowHoursList = data.tomorrowGrid?.let { courtHours(it) }?.takeIf { it.isNotEmpty() }
                        val slotLen = slotLengthOf(todayHours ?: tomorrowHoursList)
                        HomeTop(
                            status = if (settingUp) null else venueOpenStatus(todayHours, tomorrowHoursList, slotLen),
                            onDuty = onDuty,
                            onToggleDuty = onToggleDuty,
                            name = focus?.name ?: name,
                            place = if (focus != null) focus.location?.takeIf { it.isNotBlank() } else placeLine(venues, focus, branch),
                            photo = focus?.image,
                            initial = (focus?.name ?: name).trim().take(1).uppercase(),
                            unseen = unseen,
                            onMenu = onMenu,
                            onBell = onBell,
                            branchSwitcher = branchSwitcher,
                        ) {
                            if (settingUp) {
                                // Setup still reads best white-on-navy: a card, not the whole top.
                                LayoutBox(
                                    Modifier.fillMaxWidth().clip(RoundedCornerShape(22.dp))
                                        .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid))).padding(18.dp),
                                ) { HeroSetup(venues, focus, o.bookingsTotal > 0, onSetUpSlots, onSupport) }
                            } else {
                                val tomorrowOpen = data.insights?.tomorrow
                                TodayStatus(
                                    day = day,
                                    memoryKey = "home.today.$branch",
                                    clockHours = data.grid?.let { courtHoursByClock(it) },
                                    tomorrow = tomorrowOpen?.venues?.let { vs -> vs.firstOrNull { it.id == focus?.id } ?: vs.singleOrNull() },
                                    tomorrowLabel = tomorrowOpen?.label,
                                    shareUrl = focus?.let { "${ApiConfig.BASE_URL}/gamehub/${it.id}" },
                                    tomorrowHours = tomorrowHoursList,
                                    todayHours = todayHours,
                                    slotMinutes = slotLen,
                                    onBooked = { b, time, court -> counter = CounterPick(b, time, court) },
                                    onOpenDesk = { focus?.let { f -> onOpenDesk(f.id, f.name) } },
                                    onDue = onBookings,
                                    onBookings = onBookings,
                                )
                            }
                        }
                    }
                }
                item(key = "actions") {
                    Rise(1) {
                        DeskActions(
                            doors = deskDoors(
                                focus = focus,
                                courtsLane = courtsLane,
                                pips = data.grid?.let { slotPips(it) }.orEmpty(),
                                bookingsTotal = o.bookingsTotal,
                                payouts = data.payouts,
                                whatsapp = data.whatsapp,
                                onWalkIn = { v -> onOpenDesk(v.id, v.name) },
                                onReports = onReports,
                                onWhatsApp = onWhatsApp,
                                onSettlement = onSettlement,
                                onScan = onScan,
                                onSupport = onSupport,
                            ),
                            modifier = Modifier.padding(horizontal = 16.dp),
                        )
                    }
                }
                data.recent?.let { list ->
                    item(key = "payments") {
                        Rise(2) {
                            com.haraan.partner.ui.home.PaymentsCard(
                                entries = paymentEntries(list),
                                toBank = data.payouts?.let { it.inFlight + it.available }?.takeIf { it > 0 },
                                formatInr = ::formatInr,
                                onViewAll = onBookings,
                                onSettlement = onSettlement,
                                modifier = Modifier.padding(horizontal = 16.dp),
                            )
                        }
                    }
                }
                if (day.chaseCount > 0) {
                    item(key = "chase") { Rise(2) { LayoutBox(Modifier.padding(horizontal = 16.dp)) { ChaseStrip(day, onBookings) } } }
                }
                day.closed.takeIf { it.isNotEmpty() }?.let { shut ->
                    item(key = "closed") { Rise(2) { LayoutBox(Modifier.padding(horizontal = 16.dp)) { ClosedNotice(shut) } } }
                }
                val pips = data.grid?.let { slotPips(it) }.orEmpty()
                val todayOver = data.grid?.let { courtHoursByClock(it).first == 0 } == true
                // The board at the top now carries today's courts; the strip only stays for a
                // day the board can't draw (no rows came back for it).
                if (!settingUp && pips.isNotEmpty() && focus != null && data.grid?.let { courtHours(it) }.isNullOrEmpty()) {
                    item(key = "courts") {
                        Rise(3) {
                            val grid = data.grid!!
                            CourtsDaySection(
                                hours = courtHours(grid),
                                kind = courtKindFor(grid.courts.flatMap { it.sports } + grid.slots.flatMap { it.sports }),
                                formatPrice = ::formatInr,
                                onOpenDesk = { onOpenDesk(focus.id, focus.name) },
                            )
                        }
                    }
                }
                if (!settingUp) {
                    if (day.next.size <= 1) {
                        // After hours the top already says so; the line would repeat it.
                        // The board at the top already shows the day; this line only speaks
                        // when there's no board to read it from.
                        if (day.next.isEmpty() && !todayOver && data.grid?.let { courtHours(it) }.isNullOrEmpty()) item(key = "quiet") {
                            Rise(5) {
                                QuietLine(
                                    text = if (day.slotsBooked > 0) "All of today's bookings are done" else "No bookings left today",
                                    action = "All bookings",
                                    onAction = onBookings,
                                    modifier = Modifier.padding(horizontal = 16.dp),
                                )
                            }
                        }
                    } else {
                        item(key = "next-head") {
                            Rise(5) {
                                LayoutBox(Modifier.padding(horizontal = 16.dp)) {
                                    HomeSectionHeader(icon = Icons.Filled.Today, title = "Later today", action = "All bookings", onAction = onBookings)
                                }
                            }
                        }
                        val later = day.next.drop(1)
                        itemsIndexed(later, key = { i, _ -> "next-$i" }) { i, booking ->
                            Rise(6 + i.coerceAtMost(4)) {
                                LayoutBox(Modifier.padding(horizontal = 16.dp)) {
                                    TimelineRow(booking, first = i == 0, last = i == later.lastIndex)
                                }
                            }
                        }
                    }
                }
                insights?.let { (state, go) ->
                    insightItems(
                        state = state,
                        showTomorrow = settingUp,
                        onWeek = go,
                        memoryKey = "home.week.$branch",
                        onPricing = onPricing?.let { open -> focus?.let { f -> { open(f.id, f.name) } } },
                        onCustomers = { period, type -> customersOpen = period to type },
                    )
                }
                // All-time money only earns its place once there is some. A ₹0 card on
                // the first screen reads as "this app doesn't work", not as a fact.
                // With insights on, "Since you joined" tells the all-time story as a line
                // that climbs; the 14 near-empty daily bars here read as "nobody comes".
                if (o.revenue > 0.0 && insights == null) {
                    item(key = "revenue") {
                        Rise(8) { LayoutBox(Modifier.padding(horizontal = 16.dp)) { RevenueCard(o, memoryKey = "home.revenue.$branch") } }
                    }
                }
            }

            // The top is white now, so the status bar needs a white band of its own:
            // without it, the first lines slid up under the clock and battery icons.
            LayoutBox(
                Modifier.fillMaxWidth()
                    .windowInsetsTopHeight(androidx.compose.foundation.layout.WindowInsets.statusBars)
                    .background(Color.White),
            )
            CompactHomeBar(
                visible = collapsed,
                title = focus?.name ?: name,
                unseen = unseen,
                onMenu = onMenu,
                onBell = onBell,
            )
        }
    }
}

/** Everything Home loads in one refresh. */
private data class HomeData(
    val overview: Overview,
    val day: ShiftBoard,
    val grid: DayGrid?,
    /** Settlement balance for the Settlement door; null when not allowed or not loaded. */
    val payouts: PayoutsPage? = null,
    /** WhatsApp desk summary for its door; null when not a venue or not loaded. */
    val whatsapp: com.haraan.partner.whatsapp.model.WhatsAppMetrics? = null,
    /** The week's bars, channel split, tomorrow and busy hours; null when not a venue or off. */
    val insights: HomeInsights? = null,
    val tomorrowGrid: DayGrid? = null,
    val recent: List<BookingSummary>? = null,
)

/**
 * Pulls the next item up under the one before it by [by], without leaving the
 * gap an offset would: the quick-actions card sits half on the hero's curve.
 */
private fun Modifier.overlapUp(by: Dp): Modifier = layout { measurable, constraints ->
    val placeable = measurable.measure(constraints)
    val lift = by.roundToPx()
    layout(placeable.width, (placeable.height - lift).coerceAtLeast(0)) { placeable.place(0, -lift) }
}

/**
 * Status-bar icons light over the navy hero, dark once the white bar takes the
 * top — and back to dark when Home leaves, since every other tab is white.
 */
@Composable
private fun LightStatusBar(light: Boolean) {
    val view = LocalView.current
    val controller = remember(view) {
        (view.context as? android.app.Activity)?.window?.let { androidx.core.view.WindowCompat.getInsetsController(it, view) }
    }
    LaunchedEffect(light, controller) { controller?.isAppearanceLightStatusBars = !light }
    DisposableEffect(controller) { onDispose { controller?.isAppearanceLightStatusBars = true } }
}

/**
 * The top of Home, plainly: the venue, one or two sentences that are true right now, and
 * a single card for what comes next — the next booking, the hours still open, or
 * tomorrow once today is over. No scenery and no gauges: everything here is something
 * only this venue's own data can say.
 */
@Composable
private fun HomeTop(
    /** "Open now · till 12 PM" and whether it's open; null while setting up. */
    status: Pair<String, Boolean>?,
    onDuty: Boolean,
    onToggleDuty: () -> Unit,
    name: String,
    place: String?,
    photo: String?,
    initial: String,
    unseen: Int,
    onMenu: () -> Unit,
    onBell: () -> Unit,
    branchSwitcher: (@Composable () -> Unit)?,
    content: @Composable () -> Unit,
) {
    val view = LocalView.current
    Column(
        Modifier
            .fillMaxWidth()
            .background(Color.White)
            .statusBarsPadding()
            .padding(bottom = 18.dp),
    ) {
        Row(
            Modifier.fillMaxWidth().padding(start = 4.dp, end = 12.dp, top = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onMenu) {
                Icon(Icons.Filled.Menu, contentDescription = "Menu", tint = AuthInk, modifier = Modifier.size(23.dp))
            }
            Column(Modifier.weight(1f)) {
                Text(
                    name, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk,
                    maxLines = 1, overflow = TextOverflow.Ellipsis, letterSpacing = (-0.3).sp,
                )
                Row(verticalAlignment = Alignment.CenterVertically) {
                    if (status != null) {
                        LayoutBox(
                            Modifier.size(7.dp).clip(RoundedCornerShape(99.dp))
                                .background(if (status.second) Color(0xFF16A34A) else Color(0xFF94A3B8)),
                        )
                        Spacer(Modifier.width(6.dp))
                        Text(
                            status.first, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold,
                            color = if (status.second) Color(0xFF15803D) else AuthMuted, maxLines = 1,
                        )
                    }
                    place?.let {
                        Text(
                            (if (status != null) "  ·  " else "") + it,
                            fontSize = 12.5.sp, color = AuthMuted, maxLines = 1, overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
            }
            branchSwitcher?.let { it(); Spacer(Modifier.width(2.dp)) }
            DutySwitch(onDuty, onToggleDuty)
            LayoutBox {
                IconButton(onClick = { Haptics.tick(view); onBell() }) {
                    Icon(Icons.Filled.Notifications, contentDescription = "New bookings", tint = AuthInk, modifier = Modifier.size(22.dp))
                }
                if (unseen > 0) {
                    LayoutBox(
                        Modifier.align(Alignment.TopEnd).padding(top = 7.dp, end = 5.dp)
                            .size(17.dp).clip(RoundedCornerShape(99.dp)).background(RED)
                            .border(1.5.dp, Color.White, RoundedCornerShape(99.dp)),
                        contentAlignment = Alignment.Center,
                    ) { Text(if (unseen > 9) "9+" else "$unseen", fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = Color.White) }
                }
            }
            Spacer(Modifier.width(4.dp))
            VenueAvatar(photo = photo, initial = initial, onClick = onMenu)
        }
        Column(Modifier.padding(start = 20.dp, end = 20.dp, top = 18.dp)) { content() }
    }
}

private fun minutesNow(): Int = java.util.Calendar.getInstance().let { it.get(java.util.Calendar.HOUR_OF_DAY) * 60 + it.get(java.util.Calendar.MINUTE) }

/** Minutes in one slot, read off the gaps between start times (30 or 60). */
private fun slotLengthOf(hours: List<CourtHour>?): Int =
    hours.orEmpty().map { it.start }.filter { it != Int.MAX_VALUE }.distinct().sorted()
        .zipWithNext { a, b -> b - a }.filter { it > 0 }.minOrNull()?.coerceIn(30, 60) ?: 60

/** "Open now · till 12 PM", "Opens at 6 AM", "Closed · opens 6 AM tomorrow" — off the real slots. */
private fun venueOpenStatus(today: List<CourtHour>?, tomorrow: List<CourtHour>?, len: Int): Pair<String, Boolean> {
    val now = minutesNow()
    val starts = today.orEmpty().map { it.start }.filter { it != Int.MAX_VALUE }
    if (starts.isNotEmpty()) {
        val first = starts.min()
        val end = starts.max() + len
        if (now < first) return "Opens at ${com.haraan.partner.ui.pricing.clock(first)}" to false
        if (now < end) return "Open now · till ${com.haraan.partner.ui.pricing.clock(end)}" to true
    }
    val next = tomorrow.orEmpty().map { it.start }.filter { it != Int.MAX_VALUE }.minOrNull()
    return (if (next != null) "Closed · opens ${com.haraan.partner.ui.pricing.clock(next)} tomorrow" else "Closed today") to false
}

private data class CounterPick(val b: com.haraan.partner.ui.home.CellBooking, val time: String, val court: String?)

/**
 * A booking, at the counter: the full name and the full number (Playo's own owners ask
 * for this — a masked number is no use when someone hasn't turned up), what's owed, and
 * Call / WhatsApp one tap away.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CounterSheet(p: CounterPick, onDismiss: () -> Unit, onBookings: () -> Unit) {
    val context = LocalContext.current
    val view = LocalView.current
    val b = p.b
    val digits = b.phone.orEmpty().filter { it.isDigit() }
    val local = if (digits.length > 10) digits.takeLast(10) else digits
    val pretty = if (local.length == 10) "+91 " + local.take(5) + " " + local.drop(5) else b.phone.orEmpty()
    val owed = (b.amount - b.paid).coerceAtLeast(0.0)
    androidx.compose.material3.ModalBottomSheet(onDismissRequest = onDismiss, containerColor = Color.White) {
        Column(Modifier.fillMaxWidth().padding(start = 22.dp, end = 22.dp, bottom = 28.dp)) {
            Text(b.customer, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(2.dp))
            Text(
                listOfNotNull(p.court, p.time, if (b.walkIn) "Walk-in" else "Online").joinToString(" · "),
                fontSize = 14.sp, color = AuthMuted,
            )
            Spacer(Modifier.height(16.dp))
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(AuthPageBg).border(1.dp, Hairline, RoundedCornerShape(14.dp)).padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text("₹" + formatInr(b.amount), fontSize = 20.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                    Text(
                        when {
                            owed <= 0.0 -> "Paid in full"
                            b.paid > 0.0 -> "₹" + formatInr(b.paid) + " paid · ₹" + formatInr(owed) + " to collect"
                            else -> "Not paid yet"
                        },
                        fontSize = 13.sp, color = if (owed > 0.0) RED else Color(0xFF15803D), fontWeight = FontWeight.SemiBold,
                    )
                }
                if (pretty.isNotBlank()) {
                    Text(pretty, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = AuthInk, style = TextStyle(fontFeatureSettings = "tnum"))
                }
            }
            if (local.length == 10) {
                Spacer(Modifier.height(14.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    Button(
                        onClick = {
                            Haptics.tick(view)
                            runCatching { context.startActivity(android.content.Intent(android.content.Intent.ACTION_DIAL, android.net.Uri.parse("tel:+91$local"))) }
                        },
                        modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp),
                        colors = androidx.compose.material3.ButtonDefaults.buttonColors(containerColor = AuthAccent),
                    ) {
                        Icon(Icons.Filled.Phone, contentDescription = null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Call", fontWeight = FontWeight.Bold)
                    }
                    OutlinedButton(
                        onClick = {
                            Haptics.tick(view)
                            runCatching { context.startActivity(android.content.Intent(android.content.Intent.ACTION_VIEW, android.net.Uri.parse("https://wa.me/91$local"))) }
                        },
                        modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp),
                    ) {
                        Icon(Icons.Filled.Forum, contentDescription = null, modifier = Modifier.size(18.dp), tint = AuthAccentDeep)
                        Spacer(Modifier.width(8.dp))
                        Text("WhatsApp", fontWeight = FontWeight.Bold, color = AuthAccentDeep)
                    }
                }
            }
            Spacer(Modifier.height(6.dp))
            TextButton(onClick = onBookings) { Text("Open in Bookings", color = AuthAccentDeep, fontWeight = FontWeight.SemiBold) }
        }
    }
}

/**
 * A gate pulled shut with a padlock — single-weight line work in the app's ink, drawn
 * only after hours. It's the one picture on Home, and it says the one true thing.
 */
@Composable
private fun ClosedGate(modifier: Modifier) {
    Canvas(modifier) {
        val ink = Color(0xFF94A3B8)
        val sw = 1.8.dp.toPx()
        val w = size.width
        val h = size.height
        val ground = h * 0.92f
        // Posts with caps.
        listOf(w * 0.12f, w * 0.88f).forEach { x ->
            drawLine(ink, Offset(x, h * 0.12f), Offset(x, ground), sw * 1.4f, StrokeCap.Round)
            drawCircle(ink, sw * 1.3f, Offset(x, h * 0.1f))
        }
        // Two leaves meeting in the middle: top rail, bottom rail, bars.
        val top = h * 0.3f
        val bottom = ground - h * 0.06f
        drawLine(ink, Offset(w * 0.12f, top), Offset(w * 0.88f, top), sw, StrokeCap.Round)
        drawLine(ink, Offset(w * 0.12f, bottom), Offset(w * 0.88f, bottom), sw, StrokeCap.Round)
        var x = w * 0.2f
        while (x < w * 0.84f) {
            if (kotlin.math.abs(x - w * 0.5f) > w * 0.05f) drawLine(ink, Offset(x, top), Offset(x, bottom), sw * 0.8f, StrokeCap.Round)
            x += w * 0.075f
        }
        // Padlock where the leaves meet, in the accent — the only colour in it.
        val lock = Offset(w * 0.5f, (top + bottom) / 2 + h * 0.06f)
        val lw = w * 0.13f
        val lh = h * 0.2f
        drawArc(AuthAccent, 180f, 180f, false, Offset(lock.x - lw * 0.32f, lock.y - lh * 0.95f), androidx.compose.ui.geometry.Size(lw * 0.64f, lh * 0.9f),
            style = androidx.compose.ui.graphics.drawscope.Stroke(sw * 0.9f, cap = StrokeCap.Round))
        drawRoundRect(AuthAccent, Offset(lock.x - lw / 2, lock.y - lh * 0.5f), androidx.compose.ui.geometry.Size(lw, lh), androidx.compose.ui.geometry.CornerRadius(2.dp.toPx()))
        drawLine(ink, Offset(0f, ground), Offset(w, ground), sw, StrokeCap.Round)
    }
}

/**
 * Bookings as a payments feed, newest first: venue bookings only (event tickets have
 * their own screen), skipping cancelled ones and anything with nothing to collect.
 */
private fun paymentEntries(list: List<BookingSummary>): List<com.haraan.partner.ui.home.PaymentEntry> {
    val fmtIn = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", java.util.Locale.US)
    val today = java.util.Calendar.getInstance()
    fun sameDay(a: java.util.Calendar, b: java.util.Calendar) = a.get(java.util.Calendar.YEAR) == b.get(java.util.Calendar.YEAR) && a.get(java.util.Calendar.DAY_OF_YEAR) == b.get(java.util.Calendar.DAY_OF_YEAR)
    return list.asSequence()
        .filter { it.amount > 0 && !(it.status ?: "").lowercase().let { s -> s.startsWith("cancel") || s == "expired" || s == "refunded" } }
        .mapNotNull { b ->
            val at = b.createdAt?.let { runCatching { fmtIn.parse(it) }.getOrNull() } ?: return@mapNotNull null
            val cal = java.util.Calendar.getInstance().apply { time = at }
            val yesterday = (today.clone() as java.util.Calendar).apply { add(java.util.Calendar.DAY_OF_YEAR, -1) }
            val label = when {
                sameDay(cal, today) -> java.text.SimpleDateFormat("h:mm a", java.util.Locale.ENGLISH).format(at)
                sameDay(cal, yesterday) -> "Yesterday"
                else -> java.text.SimpleDateFormat("d MMM", java.util.Locale.ENGLISH).format(at)
            }
            val way = when ((b.paymentMethod ?: "").lowercase()) {
                "cash" -> com.haraan.partner.ui.home.PayWay.Cash
                "upi", "upi_qr" -> com.haraan.partner.ui.home.PayWay.Upi
                "card" -> com.haraan.partner.ui.home.PayWay.Card
                "" -> if (b.amountPaid > 0) com.haraan.partner.ui.home.PayWay.Online else null
                else -> com.haraan.partner.ui.home.PayWay.Online
            }
            com.haraan.partner.ui.home.PaymentEntry(
                name = b.customer, amount = b.amount, paid = b.amountPaid, way = way,
                walkIn = b.channel.equals("offline", true), whenLabel = label, today = sameDay(cal, today),
            ) to at.time
        }
        .sortedByDescending { it.second }
        .map { it.first }
        .toList()
}

/**
 * On duty / off duty, like a driver going online: a pill with a thumb that slides
 * across, green when Haraan is listening for bookings with the app closed.
 */
@Composable
private fun DutySwitch(on: Boolean, onToggle: () -> Unit) {
    val view = LocalView.current
    val slide by animateFloatAsState(if (on) 1f else 0f, spring(dampingRatio = 0.6f, stiffness = Spring.StiffnessMedium), label = "duty")
    val interaction = remember { MutableInteractionSource() }
    Row(
        Modifier
            .pressScale(interaction, pressedScale = 0.93f)
            .clip(RoundedCornerShape(99.dp))
            .background(lerp(Color(0xFFF1F5F9), Color(0xFFDCFCE7), slide))
            .border(1.dp, lerp(Color(0x1F0F172A), Color(0x6616A34A), slide), RoundedCornerShape(99.dp))
            .clickable(interactionSource = interaction, indication = null) {
                if (on) Haptics.tick(view) else Haptics.confirm(view)
                onToggle()
            }
            .semantics { contentDescription = if (on) "On duty. Tap to go off duty." else "Off duty. Tap to go on duty." }
            .padding(start = 4.dp, end = 10.dp, top = 4.dp, bottom = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        // The track and its thumb.
        LayoutBox(
            Modifier.size(width = 30.dp, height = 18.dp).clip(RoundedCornerShape(99.dp))
                .background(lerp(Color(0xFFCBD5E1), Color(0xFF16A34A), slide)),
        ) {
            LayoutBox(
                Modifier.padding(2.dp).size(14.dp)
                    .graphicsLayer { translationX = 12.dp.toPx() * slide }
                    .shadow(2.dp, RoundedCornerShape(99.dp))
                    .clip(RoundedCornerShape(99.dp)).background(Color.White),
            )
        }
        Spacer(Modifier.width(7.dp))
        Text(
            if (on) "On duty" else "Off duty",
            fontSize = 12.5.sp, fontWeight = FontWeight.Bold,
            color = lerp(AuthMuted, Color(0xFF15803D), slide), maxLines = 1,
        )
    }
}

/**
 * Asking for "Display over other apps" the way a person would: what it does (with the
 * card drawn small), why, and a way to say no that still works.
 */
@Composable
private fun DutySetupDialog(onAllow: () -> Unit, onNotificationsOnly: () -> Unit, onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Color.White,
        title = { Text("Never miss a booking", fontWeight = FontWeight.Bold, color = AuthInk) },
        text = {
            Column {
                // A phone with the booking card dropping in at the top.
                Canvas(Modifier.fillMaxWidth().height(120.dp)) {
                    val w = size.height * 0.56f
                    val left = (size.width - w) / 2
                    drawRoundRect(Color(0xFFE2E8F0), Offset(left, 0f), androidx.compose.ui.geometry.Size(w, size.height), androidx.compose.ui.geometry.CornerRadius(14.dp.toPx()))
                    drawRoundRect(Color.White, Offset(left + 4.dp.toPx(), 4.dp.toPx()), androidx.compose.ui.geometry.Size(w - 8.dp.toPx(), size.height - 8.dp.toPx()), androidx.compose.ui.geometry.CornerRadius(11.dp.toPx()))
                    // Some other app underneath: grey lines.
                    for (i in 0 until 5) {
                        val y = size.height * (0.45f + i * 0.1f)
                        drawLine(Color(0xFFE2E8F0), Offset(left + 12.dp.toPx(), y), Offset(left + w - 12.dp.toPx() - (i % 2) * 18.dp.toPx(), y), 5.dp.toPx(), StrokeCap.Round)
                    }
                    // The Haraan card over it.
                    drawRoundRect(
                        Brush.linearGradient(listOf(Color(0xFF4D8BFF), Color(0xFF1E40AF))),
                        Offset(left - 10.dp.toPx(), 14.dp.toPx()),
                        androidx.compose.ui.geometry.Size(w + 20.dp.toPx(), size.height * 0.3f),
                        androidx.compose.ui.geometry.CornerRadius(9.dp.toPx()),
                    )
                    drawLine(Color.White, Offset(left + 2.dp.toPx(), 26.dp.toPx()), Offset(left + w * 0.45f, 26.dp.toPx()), 4.dp.toPx(), StrokeCap.Round)
                    drawLine(Color(0xB3FFFFFF), Offset(left + 2.dp.toPx(), 36.dp.toPx()), Offset(left + w * 0.7f, 36.dp.toPx()), 3.dp.toPx(), StrokeCap.Round)
                    drawRoundRect(Color.White, Offset(left + w - 22.dp.toPx(), 22.dp.toPx()), androidx.compose.ui.geometry.Size(26.dp.toPx(), 12.dp.toPx()), androidx.compose.ui.geometry.CornerRadius(4.dp.toPx()))
                }
                Spacer(Modifier.height(12.dp))
                Text(
                    "While you're on duty, a new booking pops up over whatever app is open — with the amount, the customer and the court — and plays Haraan's chime.",
                    fontSize = 14.sp, color = AuthInk, lineHeight = 20.sp,
                )
                Spacer(Modifier.height(8.dp))
                Text(
                    "Android calls this \"Display over other apps\". Turn it on for Haraan Partner on the next screen.",
                    fontSize = 13.sp, color = AuthMuted, lineHeight = 18.sp,
                )
            }
        },
        confirmButton = {
            Button(onClick = onAllow, colors = androidx.compose.material3.ButtonDefaults.buttonColors(containerColor = AuthAccent), shape = RoundedCornerShape(12.dp)) {
                Text("Turn it on", fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = {
            TextButton(onClick = onNotificationsOnly) { Text("Notifications only", color = AuthMuted) }
        },
    )
}

/** Today in words, then the one card for what's next. */
@Composable
private fun TodayStatus(
    day: ShiftBoard,
    memoryKey: String,
    clockHours: Pair<Int, Int>?,
    tomorrow: TomorrowVenue?,
    tomorrowLabel: String?,
    shareUrl: String?,
    tomorrowHours: List<CourtHour>?,
    todayHours: List<CourtHour>?,
    slotMinutes: Int,
    onBooked: (com.haraan.partner.ui.home.CellBooking, String, String?) -> Unit,
    onOpenDesk: () -> Unit,
    onDue: () -> Unit,
    onBookings: () -> Unit,
) {
    // The board's "now" line moves with the clock while Home sits open.
    var nowMin by remember { mutableStateOf(minutesNow()) }
    LaunchedEffect(Unit) { while (true) { kotlinx.coroutines.delay(30_000); nowMin = minutesNow() } }
    val context = LocalContext.current
    val view = LocalView.current
    val money = rememberMoneyMotion(day.expected, memoryKey)
    val left = clockHours?.first ?: (day.slotsTotal - day.slotsDone).coerceAtLeast(0)
    val over = day.slotsTotal > 0 && left == 0
    val next = day.next.firstOrNull()
    val running = next?.running == true

    val headline = when {
        running -> "On court now: ${next!!.court.ifBlank { next.venue }}"
        over -> "Today's hours are over."
        day.slotsBooked > 0 -> "${day.slotsBooked} of ${day.slotsTotal} court-hours booked"
        day.slotsTotal == 0 -> "No courts running today."
        else -> "Nothing booked yet today."
    }
    val subline = when {
        day.expected > 0 && over -> "₹" + formatInr(kotlin.math.round(money.shown)) + " earned · ₹" + formatInr(day.collected) + " collected"
        day.expected > 0 -> "₹" + formatInr(kotlin.math.round(money.shown)) + " expected · ₹" + formatInr(day.collected) + " in so far"
        over -> "No bookings came in."
        left == 1 -> "1 court-hour still open."
        left > 0 -> "$left court-hours still open."
        else -> null
    }
    Row(verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            Text(headline, fontSize = 20.sp, lineHeight = 25.sp, fontWeight = FontWeight.Bold, color = AuthInk, letterSpacing = (-0.3).sp)
            subline?.let {
                Spacer(Modifier.height(3.dp))
                Text(
                    it, fontSize = 14.sp, color = lerp(AuthMuted, AuthAccentDeep, money.pulse),
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
            }
        }
        // The one drawing on Home: the gate shut for the night, only when it is.
        if (over) {
            Spacer(Modifier.width(12.dp))
            ClosedGate(Modifier.size(width = 64.dp, height = 46.dp))
        }
    }
    if (day.due > 0) {
        Spacer(Modifier.height(8.dp))
        Row(
            Modifier.clip(RoundedCornerShape(10.dp)).clickable { Haptics.tick(view); onDue() }.padding(vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            LayoutBox(Modifier.size(7.dp).clip(RoundedCornerShape(99.dp)).background(RED))
            Spacer(Modifier.width(7.dp))
            Text("₹" + formatInr(day.due) + " still to collect today", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = RED)
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = RED, modifier = Modifier.size(18.dp))
        }
    }

    // The one card for what comes next.
    val card: (@Composable () -> Unit)? = when {
        // During hours: the next customer, then the whole of today, live.
        !over && todayHours != null -> {
            {
                Column {
                    if (next != null) {
                        NextBookingCard(next, onBookings)
                        Spacer(Modifier.height(12.dp))
                    }
                    com.haraan.partner.ui.home.DayGridCard(
                        label = "Today · " + day.dayLabel,
                        hours = todayHours,
                        nowMinutes = nowMin,
                        slotMinutes = slotMinutes,
                        onBooked = onBooked,
                        onOpen = { _, _ -> onOpenDesk() },
                        // No button on the board (removed at the user's ask, 2026-10-01).
                        action = null,
                        onAction = {},
                    )
                }
            }
        }
        next != null -> { { NextBookingCard(next, onBookings) } }
        !over && left > 0 && shareUrl != null -> {
            {
                NextCard(
                    label = "Still open today",
                    title = if (left == 1) "1 court-hour to sell" else "$left court-hours to sell",
                    detail = "Send your booking link to regulars.",
                    action = "Share booking link",
                    onAction = { Haptics.tick(view); shareText(context, "Book a court with us on Haraan: $shareUrl") },
                )
            }
        }
        over && tomorrowHours != null -> {
            {
                com.haraan.partner.ui.home.DayGridCard(
                    label = "Tomorrow" + (tomorrowLabel?.let { " · $it" } ?: ""),
                    hours = tomorrowHours,
                    nowMinutes = null,
                    slotMinutes = slotMinutes,
                    onBooked = onBooked,
                    onOpen = { _, _ -> onOpenDesk() },
                    action = null,
                    onAction = {},
                )
            }
        }
        tomorrow != null -> {
            {
                NextCard(
                    label = "Tomorrow" + (tomorrowLabel?.let { " · $it" } ?: ""),
                    title = when {
                        tomorrow.closed -> "Closed tomorrow"
                        tomorrow.openHours <= 0.0 -> "Fully booked"
                        else -> formatHours(tomorrow.openHours) + " open"
                    },
                    detail = tomorrow.windows.takeIf { !tomorrow.closed && it.isNotEmpty() }?.joinToString(" · ") { it.first },
                    action = if (!tomorrow.closed && tomorrow.openHours > 0.0) "Share on WhatsApp" else null,
                    onAction = { Haptics.tick(view); shareOpenSlots(context, tomorrow) },
                )
            }
        }
        else -> null
    }
    card?.let {
        Spacer(Modifier.height(16.dp))
        it()
    }
}

private fun formatHours(h: Double): String {
    val whole = h == kotlin.math.floor(h)
    val n = if (whole) h.toLong().toString() else "%.1f".format(h)
    return "$n court-hour" + if (h == 1.0) "" else "s"
}

private fun shareText(context: Context, text: String) {
    val send = android.content.Intent(android.content.Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(android.content.Intent.EXTRA_TEXT, text)
    }
    runCatching { context.startActivity(android.content.Intent.createChooser(send, "Share booking link")) }
}

/** The next booking, as the desk would say it: when, who, which court, and whether it's paid. */
@Composable
private fun NextBookingCard(b: ShiftBooking, onOpen: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val view = LocalView.current
    Row(
        Modifier
            .fillMaxWidth()
            .pressScale(interaction, pressedScale = 0.97f)
            .clip(RoundedCornerShape(18.dp))
            .background(if (b.running) Color(0xFFEFF5FF) else AuthPageBg)
            .border(1.dp, if (b.running) Color(0x332F6BFF) else Hairline, RoundedCornerShape(18.dp))
            .pressShade(interaction, amount = 0.04f)
            .clickable(interactionSource = interaction, indication = null) { Haptics.tick(view); onOpen() }
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.width(72.dp)) {
            Text(if (b.running) "Now" else "Next", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = if (b.running) AuthAccentDeep else AuthMuted)
            Text(b.time.replace(":00", ""), fontSize = 19.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk, maxLines = 1)
        }
        Column(Modifier.weight(1f)) {
            Text(b.customer, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(
                listOf(b.court.ifBlank { b.venue }, if (b.walkIn) "Walk-in" else "Online").joinToString(" · "),
                fontSize = 12.5.sp, color = AuthMuted, maxLines = 1, overflow = TextOverflow.Ellipsis,
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            Text("₹" + formatInr(b.amount), fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Text(if (b.paid) "Paid" else "Unpaid", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = if (b.paid) Color(0xFF15803D) else RED)
        }
    }
}

/** What comes next when it isn't a booking: a plain card with one real action. */
@Composable
private fun NextCard(label: String, title: String, detail: String?, action: String?, onAction: () -> Unit) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(AuthPageBg)
            .border(1.dp, Hairline, RoundedCornerShape(18.dp))
            .padding(16.dp),
    ) {
        Text(label, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = AuthMuted)
        Spacer(Modifier.height(2.dp))
        Text(title, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
        detail?.let {
            Spacer(Modifier.height(2.dp))
            Text(it, fontSize = 13.sp, color = AuthMuted, maxLines = 2, overflow = TextOverflow.Ellipsis)
        }
        if (action != null) {
            Spacer(Modifier.height(12.dp))
            val interaction = remember { MutableInteractionSource() }
            Text(
                action, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color.White,
                modifier = Modifier
                    .pressScale(interaction, pressedScale = 0.95f)
                    .clip(RoundedCornerShape(12.dp))
                    .background(AuthAccent)
                    .clickable(interactionSource = interaction, indication = null) { onAction() }
                    .padding(horizontal = 16.dp, vertical = 10.dp),
            )
        }
    }
}

/**
 * What the hero says before a single court is bookable: what's missing, how far
 * along they are, and the one action that moves them forward. The steps read
 * the account; none is decoration.
 */
@Composable
private fun HeroSetup(
    venues: List<VenueSummary>?,
    focus: VenueSummary?,
    firstBookingDone: Boolean,
    onSetUpSlots: (Long, String) -> Unit,
    onSupport: () -> Unit,
) {
    val listed = !venues.isNullOrEmpty()
    val stepsDone = listOf(listed, false, firstBookingDone).count { it }
    Column {
        Text("GET SET UP · $stepsDone OF 3", color = Color(0xB3CFE0FF), fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.5.sp)
        Spacer(Modifier.height(8.dp))
        Text(
            when {
                venues == null -> "Getting your venue…"
                !listed -> "Your venue isn't on Haraan yet"
                else -> "${focus?.name ?: "Your venue"} isn't bookable yet"
            },
            color = Color.White, fontSize = 22.sp, lineHeight = 27.sp,
            fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.4).sp,
        )
        Spacer(Modifier.height(6.dp))
        Text(
            if (!listed) "Haraan lists venues for their owners. Message us and we'll put yours on the app."
            else "Publish your time slots and players can book your courts straight away.",
            color = Color(0xCCE0E8FF), fontSize = 13.sp, lineHeight = 19.sp,
        )
        Spacer(Modifier.height(18.dp))
        SetupSteps(listed = listed, slots = false, firstBooking = firstBookingDone)
        if (venues != null) {
            Spacer(Modifier.height(18.dp))
            if (listed && focus != null) {
                NavyCta(text = "Add time slots") { onSetUpSlots(focus.id, focus.name) }
            } else {
                NavyCta(text = "Message Haraan") { onSupport() }
            }
        }
    }
}

/**
 * The desk's four doors — Walk-in, Reports, WhatsApp, Settlement — as a row of
 * tiles on a card that sits half on the hero's curve. Each tile sinks under the
 * thumb with a tick; WhatsApp carries a live count when chats are waiting.
 *
 * A door the partner can't use (no permission, no venue yet) isn't drawn, and
 * the row spreads the rest evenly.
 */
@Composable
private fun DeskActions(
    doors: List<DeskDoor>,
    modifier: Modifier,
) {
    if (doors.isEmpty()) return
    // What a locked door says when it's tapped; clears itself.
    var notice by remember { mutableStateOf<String?>(null) }
    LaunchedEffect(notice) { if (notice != null) { kotlinx.coroutines.delay(2600); notice = null } }
    Column(
        modifier
            .fillMaxWidth()
            .shadow(14.dp, RoundedCornerShape(24.dp), clip = false, spotColor = Color(0x262563EB))
            .clip(RoundedCornerShape(24.dp))
            .background(Color.White)
            .border(1.dp, Color(0x0F0F172A), RoundedCornerShape(24.dp))
            .padding(horizontal = 8.dp, vertical = 16.dp),
    ) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceEvenly) {
            doors.forEach { door -> DoorTile(door, Modifier.weight(1f), onLocked = { notice = it }) }
        }
        androidx.compose.animation.AnimatedVisibility(
            visible = notice != null,
            enter = androidx.compose.animation.fadeIn() + androidx.compose.animation.expandVertically(),
            exit = androidx.compose.animation.fadeOut() + androidx.compose.animation.shrinkVertically(),
        ) {
            Row(
                Modifier.fillMaxWidth().padding(start = 12.dp, end = 12.dp, top = 14.dp)
                    .clip(RoundedCornerShape(12.dp)).background(Color(0xFFF1F5FB)).padding(horizontal = 12.dp, vertical = 9.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Filled.Lock, contentDescription = null, tint = AuthMuted, modifier = Modifier.size(14.dp))
                Spacer(Modifier.width(8.dp))
                Text(notice.orEmpty(), fontSize = 12.5.sp, color = AuthInk)
            }
        }
    }
}

/**
 * One door: a lit tile that presses in under the thumb (it sinks, its shadow goes),
 * the glyph, the word. A locked door looks reachable but asleep — greyed mark,
 * padlock, a "Coming soon" tag — and answers a tap with a short shake and a line
 * saying when, instead of opening.
 */
@Composable
private fun DoorTile(door: DeskDoor, modifier: Modifier, onLocked: (String) -> Unit) {
    val view = LocalView.current
    val scope = rememberCoroutineScope()
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val sink by animateFloatAsState(if (pressed) 1f else 0f, spring(dampingRatio = 0.7f, stiffness = Spring.StiffnessMedium), label = "door-sink")
    val shake = remember { Animatable(0f) }
    Column(
        modifier
            .graphicsLayer { translationX = shake.value }
            .clickable(interactionSource = interaction, indication = null) {
                if (door.locked) {
                    Haptics.warn(view)
                    onLocked(door.lockedNote ?: "${door.label} is coming soon.")
                    scope.launch {
                        for (x in listOf(7f, -6f, 4f, -2f, 0f)) shake.animateTo(x, tween(55))
                    }
                } else {
                    Haptics.tick(view); door.onClick()
                }
            }
            // The live fact ("₹15 ready", "2 chats waiting") is what a screen
            // reader says for the tile, so it isn't lost off the visible label.
            .semantics(mergeDescendants = true) {
                contentDescription = if (door.locked) "${door.label}. Coming soon." else "${door.label}. ${door.fact}"
            }
            .padding(vertical = 4.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        LayoutBox {
            LayoutBox(
                Modifier
                    .graphicsLayer {
                        val sc = 1f - 0.07f * sink
                        scaleX = sc; scaleY = sc
                        translationY = 1.5.dp.toPx() * sink
                    }
                    .shadow((5f * (1f - sink)).dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x402563EB))
                    .size(56.dp)
                    .clip(RoundedCornerShape(18.dp))
                    .background(
                        if (door.locked) Brush.verticalGradient(listOf(Color(0xFFF8FAFC), Color(0xFFEFF2F6)))
                        else Brush.verticalGradient(listOf(Color.White, Color(0xFFEAF1FF)))
                    )
                    // Lit top edge, darker foot: a key, not a sticker.
                    .border(
                        1.dp,
                        Brush.verticalGradient(listOf(Color(0x332563EB), Color(0x142563EB))),
                        RoundedCornerShape(18.dp),
                    )
                    .pressShade(interaction, amount = 0.05f),
                contentAlignment = Alignment.Center,
            ) {
                when {
                    door.whatsapp -> com.haraan.partner.ui.home.WhatsAppMark(locked = door.locked, modifier = Modifier.size(30.dp))
                    else -> Icon(door.icon, contentDescription = null, tint = Color(0xFF1D4ED8), modifier = Modifier.size(26.dp))
                }
            }
            if (door.locked) {
                // Padlock, sitting on the tile's corner.
                LayoutBox(
                    Modifier.align(Alignment.BottomEnd).offset(x = 4.dp, y = 4.dp)
                        .size(20.dp).clip(RoundedCornerShape(99.dp)).background(Color.White)
                        .border(1.dp, Color(0x1F0F172A), RoundedCornerShape(99.dp)),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.Filled.Lock, contentDescription = null, tint = Color(0xFF64748B), modifier = Modifier.size(11.dp)) }
                // The tag rides the tile's top edge.
                Text(
                    "Coming soon",
                    fontSize = 7.5.sp, lineHeight = 9.sp, fontWeight = FontWeight.Bold, color = Color.White, maxLines = 1, softWrap = false,
                    letterSpacing = 0.2.sp,
                    // Sits on the tile's top edge, half above it, clear of the mark.
                    modifier = Modifier.align(Alignment.TopCenter).offset(y = (-7).dp)
                        .clip(RoundedCornerShape(99.dp)).background(AuthInk)
                        .border(1.5.dp, Color.White, RoundedCornerShape(99.dp))
                        .padding(horizontal = 5.dp, vertical = 1.5.dp),
                )
            } else if (door.badge > 0) {
                LayoutBox(
                    Modifier.align(Alignment.TopEnd).offset(x = 5.dp, y = (-5).dp)
                        .size(19.dp).clip(RoundedCornerShape(99.dp)).background(RED)
                        .border(2.dp, Color.White, RoundedCornerShape(99.dp)),
                    contentAlignment = Alignment.Center,
                ) { Text(if (door.badge > 9) "9+" else "${door.badge}", fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = Color.White) }
            }
        }
        Spacer(Modifier.height(9.dp))
        Text(
            door.label, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, maxLines = 1,
            color = if (door.locked) AuthMuted else AuthInk,
        )
    }
}

/**
 * WhatsApp Desk is held back in this version: its door shows locked with a "Coming
 * soon" tag and the drawer entry is hidden. Flip to false to open it again.
 */
private const val WHATSAPP_DESK_LOCKED = true

/** One door: what it is, and one live fact about it. */
private class DeskDoor(
    val icon: ImageVector,
    val label: String,
    val fact: String,
    /** The fact is good news worth colour (money ready) or needs attention (chats waiting). */
    val factTone: DoorTone = DoorTone.Quiet,
    val badge: Int = 0,
    /** Drawn with WhatsApp's own mark instead of a glyph. */
    val whatsapp: Boolean = false,
    /** Shown but not open in this version. */
    val locked: Boolean = false,
    val lockedNote: String? = null,
    val onClick: () -> Unit,
)

private enum class DoorTone { Quiet, Good, Attention }

/**
 * The doors Home offers this partner, each with its live fact. Real numbers or
 * plain words — never a placeholder count.
 */
private fun deskDoors(
    focus: VenueSummary?,
    courtsLane: Boolean,
    pips: List<SlotPip>,
    bookingsTotal: Int,
    payouts: PayoutsPage?,
    whatsapp: com.haraan.partner.whatsapp.model.WhatsAppMetrics?,
    onWalkIn: ((VenueSummary) -> Unit)?,
    onReports: (() -> Unit)?,
    onWhatsApp: (() -> Unit)?,
    onSettlement: (() -> Unit)?,
    onScan: () -> Unit,
    onSupport: () -> Unit,
): List<DeskDoor> {
    val doors = mutableListOf<DeskDoor>()
    if (focus != null && courtsLane && onWalkIn != null) {
        val now = java.util.Calendar.getInstance().let { it.get(java.util.Calendar.HOUR_OF_DAY) * 60 + it.get(java.util.Calendar.MINUTE) }
        val open = pips.count { (it.start == Int.MAX_VALUE || it.start + 60 > now) && it.booked < it.total }
        doors += DeskDoor(
            com.haraan.partner.ui.home.DoorGlyphs.WalkIn, "Walk-in",
            when {
                pips.isEmpty() -> "Book a court at the desk"
                open == 0 -> "No slots left today"
                open == 1 -> "1 slot open today"
                else -> "$open slots open today"
            },
            factTone = if (open > 0) DoorTone.Attention else DoorTone.Quiet,
        ) { onWalkIn(focus) }
    }
    if (onReports != null) {
        doors += DeskDoor(
            com.haraan.partner.ui.home.DoorGlyphs.Reports, "Reports",
            when (bookingsTotal) {
                0 -> "Export any date range"
                1 -> "1 booking · CSV"
                else -> "$bookingsTotal bookings · CSV"
            },
        ) { onReports() }
    }
    if (focus != null && courtsLane) {
        // Locked in this version: shown so partners know it's coming, never opened.
        doors += DeskDoor(
            Icons.Filled.Forum, "WhatsApp", "Coming soon",
            whatsapp = true,
            locked = WHATSAPP_DESK_LOCKED,
            lockedNote = "WhatsApp bookings are coming in the next update.",
            badge = if (WHATSAPP_DESK_LOCKED) 0 else (whatsapp?.needsActionCount ?: 0),
        ) { onWhatsApp?.invoke() }
    }
    if (onSettlement != null) {
        doors += DeskDoor(
            com.haraan.partner.ui.home.DoorGlyphs.Settlement, "Settlement",
            when {
                payouts == null -> "Payouts to your bank"
                payouts.available > 0 -> "₹" + formatInr(payouts.available) + " ready"
                payouts.inFlight > 0 -> "₹" + formatInr(payouts.inFlight) + " on the way"
                else -> "All settled"
            },
            factTone = if ((payouts?.available ?: 0.0) > 0) DoorTone.Good else DoorTone.Quiet,
        ) { onSettlement() }
    }
    // A partner who can't use the venue doors still gets a useful card.
    if (doors.size < 2) {
        doors += DeskDoor(Icons.Filled.QrCodeScanner, "Scan", "Check guests in") { onScan() }
        if (doors.size < 2) doors += DeskDoor(Icons.AutoMirrored.Filled.HelpOutline, "Help", "Talk to Haraan") { onSupport() }
    }
    return doors
}

/** One hour of the day at the venue, summed across its courts. */
private data class SlotPip(val time: String, val start: Int, val booked: Int, val total: Int, val price: Double)

/**
 * (court-hours still ahead, court-hours already gone by) for today, off the device clock —
 * the same rows and the same "past" test as the courts strip, so the two never disagree.
 */
private fun courtHoursByClock(grid: DayGrid): Pair<Int, Int> {
    val hours = courtHours(grid)
    val starts = hours.map { it.start }.filter { it != Int.MAX_VALUE }.distinct().sorted()
    val length = starts.zipWithNext { a, b -> b - a }.filter { it > 0 }.minOrNull()?.coerceIn(30, 60) ?: 60
    val now = java.util.Calendar.getInstance().let { it.get(java.util.Calendar.HOUR_OF_DAY) * 60 + it.get(java.util.Calendar.MINUTE) }
    val past = hours.filter { it.start != Int.MAX_VALUE && now >= it.start + length }
    val played = past.sumOf { it.total }
    return (hours.sumOf { it.total } - played) to played
}

/** Today's hours court by court, for the drawn strip on Home. */
private fun courtHours(grid: DayGrid): List<CourtHour> = grid.slots.map { s ->
    val cells = s.courts.filter { it.allowed }
    val states = if (cells.isNotEmpty()) {
        cells.map { c -> when { c.isBooked -> CellState.Booked; c.isHeld -> CellState.Held; else -> CellState.Open } }
    } else {
        // A venue without courts still sells the slot [capacity] times.
        val cap = s.capacity.coerceAtLeast(1)
        List(cap) { i -> if (i < s.booked.coerceAtMost(cap)) CellState.Booked else CellState.Open }
    }
    val byId = grid.courts.associateBy { it.id }
    CourtHour(
        time = (s.time ?: s.label).trim(),
        start = slotStartMinutes(s.time ?: s.label),
        cells = states,
        price = cells.minOfOrNull { it.price } ?: s.price,
        // Each drawn court is that court: its name, and its own sport's markings.
        courts = cells.map { c ->
            val col = byId[c.courtId]
            CourtTag(col?.name ?: "Court", courtKindFor(col?.sports.orEmpty()))
        },
        cellPrices = cells.map { it.price },
        bookings = cells.map { c ->
            c.bookings.firstOrNull()?.let { b ->
                com.haraan.partner.ui.home.CellBooking(
                    id = b.id, customer = b.customer, phone = b.phone, amount = b.amount, paid = b.amountPaid,
                    paymentStatus = b.paymentStatus, walkIn = b.channel.equals("offline", true) || b.channel.equals("walk-in", true) || b.channel.equals("desk", true),
                )
            }
        },
    )
}

private fun slotPips(grid: DayGrid): List<SlotPip> = grid.slots.map { s ->
    val cells = s.courts.filter { it.allowed }
    val total = if (cells.isNotEmpty()) cells.size else s.capacity
    val booked = if (cells.isNotEmpty()) cells.count { it.isBooked || it.isHeld } else s.booked
    SlotPip(
        time = (s.time ?: s.label).trim(),
        start = slotStartMinutes(s.time ?: s.label),
        booked = booked,
        total = total,
        price = cells.minOfOrNull { it.price } ?: s.price,
    )
}

/** One quiet line where an empty card used to stand. */
@Composable
private fun QuietLine(text: String, action: String, onAction: () -> Unit, modifier: Modifier = Modifier) {
    val view = LocalView.current
    Row(
        modifier.fillMaxWidth().padding(vertical = 2.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Filled.Check, null, tint = AuthMuted, modifier = Modifier.size(16.dp))
        Spacer(Modifier.width(8.dp))
        Text(text, fontSize = 13.sp, color = AuthMuted, modifier = Modifier.weight(1f))
        Text(
            action, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep,
            modifier = Modifier.clip(RoundedCornerShape(999.dp)).clickable { Haptics.tick(view); onAction() }
                .padding(horizontal = 8.dp, vertical = 6.dp),
        )
    }
}

/**
 * The slim white bar that takes the top once the hero scrolls away, so the
 * venue's name, the menu and the bell stay in reach. Slides and fades, never pops.
 */
@Composable
private fun CompactHomeBar(visible: Boolean, title: String, unseen: Int, onMenu: () -> Unit, onBell: () -> Unit) {
    val shown by animateFloatAsState(if (visible) 1f else 0f, tween(220), label = "compact-bar")
    if (shown <= 0.01f) return
    Row(
        Modifier
            .fillMaxWidth()
            .graphicsLayer { alpha = shown; translationY = (1f - shown) * -24.dp.toPx() }
            .shadow(10.dp * shown, RectangleShape, clip = false, spotColor = Color(0x1A0F172A))
            .background(Color.White)
            .statusBarsPadding()
            .height(56.dp)
            .padding(horizontal = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        IconButton(onClick = onMenu) { Icon(Icons.Filled.Menu, contentDescription = "Menu", tint = AuthInk, modifier = Modifier.size(22.dp)) }
        Text(title, fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
        BellIcon(unseen, onBell)
    }
}

/**
 * The one line under the name that only this partner's app could print: which
 * venue this is and where. Generic greeting copy ("Here's what's happening…")
 * is the thing that makes a dashboard look generated.
 */
private fun placeLine(venues: List<VenueSummary>?, focus: VenueSummary?, branch: Long?): String? = when {
    venues == null -> null
    venues.isEmpty() -> "No venue on Haraan yet"
    branch == null && venues.size > 1 -> "All ${venues.size} venues"
    focus != null -> listOfNotNull(focus.name, focus.location?.takeIf { it.isNotBlank() }).joinToString(" · ")
    else -> null
}

/** Home's cards arrive in order, each a beat after the last, instead of all at once. */
@Composable
internal fun Rise(order: Int, content: @Composable () -> Unit) {
    val shown = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        kotlinx.coroutines.delay(order * 45L)
        shown.animateTo(1f, tween(durationMillis = 340, easing = FastOutSlowInEasing))
    }
    LayoutBox(
        Modifier.graphicsLayer {
            alpha = shown.value
            translationY = (1f - shown.value) * 14.dp.toPx()
        },
    ) { content() }
}

/** Venue → slots → first booking, as a connected track rather than three tiles. */
@Composable
private fun SetupSteps(listed: Boolean, slots: Boolean, firstBooking: Boolean) {
    val steps = listOf("Venue" to listed, "Time slots" to slots, "First booking" to firstBooking)
    // The step to do next is the first one not done; it is the only one lit.
    val nextIndex = steps.indexOfFirst { !it.second }
    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
        steps.forEachIndexed { i, (label, done) ->
            val current = i == nextIndex
            Row(verticalAlignment = Alignment.CenterVertically) {
                LayoutBox(
                    Modifier.size(20.dp).clip(RoundedCornerShape(99.dp))
                        .background(
                            when {
                                done -> Color(0xFF7DA9FF)
                                current -> Color.White
                                else -> Color(0x26FFFFFF)
                            }
                        ),
                    contentAlignment = Alignment.Center,
                ) {
                    if (done) {
                        Icon(Icons.Filled.Check, contentDescription = null, tint = AuthInkTop, modifier = Modifier.size(13.dp))
                    } else {
                        Text(
                            "${i + 1}",
                            fontSize = 10.5.sp, fontWeight = FontWeight.ExtraBold,
                            color = if (current) AuthInkTop else Color(0x99FFFFFF),
                        )
                    }
                }
                Spacer(Modifier.width(6.dp))
                Text(
                    label,
                    fontSize = 11.5.sp,
                    fontWeight = if (current) FontWeight.Bold else FontWeight.SemiBold,
                    color = when {
                        current -> Color.White
                        done -> Color(0xCCE0E8FF)
                        else -> Color(0x80E0E8FF)
                    },
                    maxLines = 1,
                )
            }
            if (i < steps.lastIndex) {
                LayoutBox(
                    Modifier.weight(1f).padding(horizontal = 7.dp).height(1.dp)
                        .background(if (done) Color(0x997DA9FF) else Color(0x26FFFFFF)),
                )
            }
        }
    }
}

/** The one action on a navy card: brand blue, sized to its words, pressable. */
@Composable
private fun NavyCta(text: String, onClick: () -> Unit) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .pressScale(interaction, pressedScale = 0.95f)
            .shadow(14.dp, RoundedCornerShape(999.dp), clip = false, spotColor = AuthAccent)
            .clip(RoundedCornerShape(999.dp))
            .background(Brush.horizontalGradient(listOf(AuthAccent, AuthAccentDeep)))
            .pressShade(interaction, amount = 0.12f)
            .clickable(interactionSource = interaction, indication = null) {
                Haptics.tick(view)
                onClick()
            }
            .heightIn(min = 48.dp)
            .padding(horizontal = 20.dp, vertical = 13.dp),
    ) {
        Text(text, color = Color.White, fontSize = 14.5.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.width(8.dp))
        Icon(Icons.AutoMirrored.Filled.ArrowForward, contentDescription = null, tint = Color.White, modifier = Modifier.size(17.dp))
    }
}

/** A section title with its icon and, when there is somewhere real to go, one link. */
@Composable
internal fun HomeSectionHeader(icon: ImageVector, title: String, action: String? = null, onAction: () -> Unit = {}) {
    val view = LocalView.current
    Row(
        Modifier.fillMaxWidth().padding(top = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        LayoutBox(
            Modifier.size(28.dp).clip(RoundedCornerShape(9.dp)).background(Color(0xFFEAF1FF)),
            contentAlignment = Alignment.Center,
        ) { Icon(icon, contentDescription = null, tint = AuthAccentDeep, modifier = Modifier.size(16.dp)) }
        Spacer(Modifier.width(10.dp))
        Text(title, fontSize = 16.5.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk, letterSpacing = (-0.2).sp, modifier = Modifier.weight(1f))
        if (action != null) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .clickable { Haptics.tick(view); onAction() }
                    .padding(horizontal = 8.dp, vertical = 6.dp),
            ) {
                Text(action, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep)
                Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = AuthAccentDeep, modifier = Modifier.size(17.dp))
            }
        }
    }
}

/** How full today is, at a glance. Empty reads as empty — no cheerful full bar. */
@Composable
private fun OccupancyBar(fraction: Float) {
    val filled = fraction.coerceIn(0f, 1f)
    LayoutBox(
        Modifier.fillMaxWidth().height(6.dp)
            .clip(RoundedCornerShape(999.dp))
            .background(Color(0x33FFFFFF)),
    ) {
        if (filled > 0f) {
            LayoutBox(
                Modifier.fillMaxWidth(filled).fillMaxHeight()
                    .clip(RoundedCornerShape(999.dp))
                    .background(Color(0xFF7DA9FF)),
            )
        }
    }
}

/**
 * Money owed, across every date.
 *
 * The figure is what is OUTSTANDING — total minus whatever has already been
 * collected — so a booking half-settled in cash stops being chased in full.
 */
@Composable
private fun ChaseStrip(day: ShiftBoard, onOpen: () -> Unit) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    Row(
        Modifier.fillMaxWidth()
            .pressScale(interaction)
            .clip(RoundedCornerShape(14.dp))
            .background(Color(0x14DC2626))
            .border(1.dp, Color(0x33DC2626), RoundedCornerShape(14.dp))
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null) {
                Haptics.tick(view)
                onOpen()
            }
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        LayoutBox(Modifier.size(7.dp).clip(RoundedCornerShape(99.dp)).background(RED))
        Spacer(Modifier.width(9.dp))
        Text(
            "${day.chaseCount} unpaid · ₹" + formatInr(day.chaseAmount) + " to collect",
            fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = RED,
            modifier = Modifier.weight(1f),
        )
        Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = RED, modifier = Modifier.size(18.dp))
    }
}

/** A venue shut for the day can't be filled, and the owner should be told why it's quiet. */
@Composable
private fun ClosedNotice(names: List<String>) {
    Row(
        Modifier.fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Color(0x14F59E0B))
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        LayoutBox(Modifier.size(7.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFFF59E0B)))
        Spacer(Modifier.width(9.dp))
        Text(
            names.joinToString(" · ") + if (names.size == 1) " is closed today" else " are closed today",
            fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFFB45309),
        )
    }
}

/**
 * One booking on today's sheet, hung on a time rail.
 *
 * The rail is what makes a list of rows read as a day: the eye runs down the
 * times, and the booking on court now is the one whose dot is alive.
 */
@Composable
private fun TimelineRow(b: ShiftBooking, first: Boolean, last: Boolean) {
    Row(Modifier.fillMaxWidth().height(androidx.compose.foundation.layout.IntrinsicSize.Min)) {
        Column(Modifier.width(56.dp).padding(top = 16.dp)) {
            Text(
                b.time.ifBlank { "—" },
                fontSize = 13.sp, fontWeight = FontWeight.ExtraBold,
                color = if (b.running) AuthAccentDeep else AuthInk,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }
        // The rail: a hairline through every row, broken above the first and
        // below the last, with this booking's dot on it.
        LayoutBox(Modifier.width(18.dp).fillMaxHeight(), contentAlignment = Alignment.TopCenter) {
            LayoutBox(
                Modifier.width(1.dp).fillMaxHeight()
                    .padding(top = if (first) 20.dp else 0.dp, bottom = if (last) 0.dp else 0.dp)
                    .background(if (last && !first) Brush.verticalGradient(listOf(Hairline, Color.Transparent)) else SolidColor(Hairline)),
            )
            LayoutBox(Modifier.padding(top = 18.dp)) { RailDot(live = b.running) }
        }
        Spacer(Modifier.width(8.dp))
        Row(
            Modifier.weight(1f).padding(bottom = 10.dp).premiumSurface(16.dp).padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        b.customer, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = AuthInk,
                        maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false),
                    )
                    if (b.running) {
                        Spacer(Modifier.width(7.dp))
                        Text(
                            "ON COURT", fontSize = 9.sp, fontWeight = FontWeight.ExtraBold,
                            color = AuthAccentDeep, letterSpacing = 0.8.sp,
                        )
                    }
                }
                Spacer(Modifier.height(5.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    b.court.takeIf { it.isNotBlank() }?.let { court ->
                        Text(
                            court, fontSize = 10.5.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep,
                            maxLines = 1,
                            modifier = Modifier.clip(RoundedCornerShape(6.dp)).background(Color(0xFFEAF1FF))
                                .padding(horizontal = 7.dp, vertical = 2.dp),
                        )
                        Spacer(Modifier.width(7.dp))
                    }
                    Text(
                        listOfNotNull(
                            b.venue.takeIf { it.isNotBlank() },
                            if (b.walkIn) "Walk-in" else null,
                        ).joinToString(" · "),
                        fontSize = 11.5.sp, color = AuthMuted, maxLines = 1, overflow = TextOverflow.Ellipsis,
                    )
                }
            }
            Spacer(Modifier.width(10.dp))
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    "₹" + formatInr(b.amount), fontSize = 14.5.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
                if (!b.paid) {
                    Spacer(Modifier.height(4.dp))
                    Text(
                        "UNPAID",
                        fontSize = 9.sp, fontWeight = FontWeight.Bold, color = RED, letterSpacing = 0.6.sp,
                        modifier = Modifier.clip(RoundedCornerShape(999.dp))
                            .background(Color(0x14DC2626)).padding(horizontal = 6.dp, vertical = 2.dp),
                    )
                }
            }
        }
    }
}

/** A booking's place on the rail. Live means on court now, and it breathes. */
@Composable
private fun RailDot(live: Boolean) {
    if (!live) {
        LayoutBox(
            Modifier.size(9.dp).clip(RoundedCornerShape(99.dp)).background(Color.White)
                .border(2.dp, Color(0xFFCBD5E1), RoundedCornerShape(99.dp)),
        )
        return
    }
    val t = rememberInfiniteTransition(label = "live-dot")
    val ring by t.animateFloat(
        initialValue = 0f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(1400, easing = LinearEasing), RepeatMode.Restart),
        label = "ring",
    )
    LayoutBox(Modifier.size(9.dp), contentAlignment = Alignment.Center) {
        LayoutBox(
            Modifier.size(9.dp)
                .graphicsLayer { scaleX = 1f + 1.6f * ring; scaleY = 1f + 1.6f * ring; alpha = 1f - ring }
                .clip(RoundedCornerShape(99.dp)).background(AuthAccent),
        )
        LayoutBox(Modifier.size(9.dp).clip(RoundedCornerShape(99.dp)).background(AuthAccent))
    }
}

/**
 * The long view, below today's sheet, and only once there is money in it.
 *
 * Both numbers say what period they cover: the total is all-time, the change is
 * the last seven days against the seven before them. The bars are the real
 * fourteen days — never a decorative chart behind a ₹0.
 */
@Composable
private fun RevenueCard(o: Overview, memoryKey: String) {
    val money = rememberMoneyMotion(o.revenue, memoryKey)
    val trendPct: Int? = run {
        if (o.trend.size < 14) null else {
            val last7 = o.trend.takeLast(7).sum()
            val prev7 = o.trend.dropLast(7).takeLast(7).sum()
            if (prev7 <= 0.0) null else (((last7 - prev7) / prev7) * 100).roundToInt()
        }
    }
    Column(Modifier.fillMaxWidth().premiumSurface(18.dp).padding(18.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                "REVENUE · ALL TIME", fontSize = 10.5.sp, fontWeight = FontWeight.Bold,
                color = AuthMuted, letterSpacing = 1.3.sp, modifier = Modifier.weight(1f),
            )
            if (trendPct != null) {
                val up = trendPct >= 0
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(if (up) Color(0x1416A34A) else Color(0x14DC2626))
                        .padding(horizontal = 8.dp, vertical = 3.dp),
                ) {
                    Icon(
                        if (up) Icons.AutoMirrored.Filled.TrendingUp else Icons.AutoMirrored.Filled.TrendingDown,
                        contentDescription = null,
                        tint = if (up) GREEN else RED,
                        modifier = Modifier.size(13.dp),
                    )
                    Spacer(Modifier.width(4.dp))
                    // The baseline is named. A bare "-37%" is a comparison with
                    // nothing to compare against.
                    Text(
                        "${if (up) "+" else ""}$trendPct% vs prev 7 days",
                        color = if (up) GREEN else RED, fontSize = 10.5.sp, fontWeight = FontWeight.Bold,
                    )
                }
            }
        }
        Spacer(Modifier.height(8.dp))
        Text(
            "₹" + formatInr(kotlin.math.round(money.shown)),
            fontSize = 28.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.8).sp,
            // Tabular digits, so the figure doesn't jitter sideways while it counts.
            style = TextStyle(fontFeatureSettings = "tnum, zero"),
            color = androidx.compose.ui.graphics.lerp(AuthInk, AuthAccentDeep, money.pulse),
        )
        Spacer(Modifier.height(2.dp))
        Text("${o.bookingsTotal} bookings all-time", fontSize = 12.sp, color = AuthMuted)
        if (o.trend.any { it > 0 }) {
            Spacer(Modifier.height(16.dp))
            TrendBars(o.trend, Modifier.fillMaxWidth().height(54.dp))
            Spacer(Modifier.height(7.dp))
            Row(Modifier.fillMaxWidth()) {
                Text("14 days ago", fontSize = 10.5.sp, color = AuthMuted, modifier = Modifier.weight(1f))
                Text("Today", fontSize = 10.5.sp, color = AuthMuted)
            }
        }
    }
}

/**
 * Fourteen rounded bars that grow in once. Today's bar is full brand blue; the
 * past is a tint of it, so the eye lands on now.
 */
@Composable
private fun TrendBars(values: List<Double>, modifier: Modifier) {
    val grow = remember { Animatable(0f) }
    LaunchedEffect(Unit) { grow.animateTo(1f, tween(700, easing = FastOutSlowInEasing)) }
    val max = (values.maxOrNull() ?: 0.0).coerceAtLeast(1.0)
    Canvas(modifier) {
        if (values.isEmpty()) return@Canvas
        val gap = 5.dp.toPx()
        val w = ((size.width - gap * (values.size - 1)) / values.size).coerceAtLeast(1f)
        val floor = 3.dp.toPx()
        values.forEachIndexed { i, v ->
            val h = if (v > 0) ((v / max).toFloat() * size.height * grow.value).coerceAtLeast(floor) else floor
            drawRoundRect(
                color = if (i == values.lastIndex) AuthAccent else if (v > 0) Color(0x592F6BFF) else Color(0x140F172A),
                topLeft = Offset(i * (w + gap), size.height - h),
                size = androidx.compose.ui.geometry.Size(w, h),
                cornerRadius = androidx.compose.ui.geometry.CornerRadius(w / 3f),
            )
        }
    }
}

/** Dashboard shortcut into Customers. */
@Composable
private fun CustomersEntryCard(onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(42.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFEAF1FF), Color(0xFFDCE8FF)))),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.People, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(21.dp)) }
            Spacer(Modifier.width(13.dp))
            Column(Modifier.weight(1f)) {
                Text("Customers", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                Spacer(Modifier.height(1.dp))
                Text("Who plays here, and how often", fontSize = 12.sp, color = AuthMuted)
            }
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
        }
    }
}

/** Dashboard shortcut into Academy. */
@Composable
private fun AcademyEntryCard(onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(42.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFE6F7EE), Color(0xFFD3F0E0)))),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.People, contentDescription = null, tint = Color(0xFF15803D), modifier = Modifier.size(21.dp)) }
            Spacer(Modifier.width(13.dp))
            Column(Modifier.weight(1f)) {
                Text("Academy", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                Spacer(Modifier.height(1.dp))
                Text("Coaching batches & attendance", fontSize = 12.sp, color = AuthMuted)
            }
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
        }
    }
}

/** Dashboard shortcut into Packages. */
@Composable
private fun PackagesEntryCard(onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(42.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFF3ECFF), Color(0xFFE6DBFF)))),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.ConfirmationNumber, contentDescription = null, tint = Color(0xFF6D28D9), modifier = Modifier.size(21.dp)) }
            Spacer(Modifier.width(13.dp))
            Column(Modifier.weight(1f)) {
                Text("Packages", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                Spacer(Modifier.height(1.dp))
                Text("Memberships & prepaid sessions", fontSize = 12.sp, color = AuthMuted)
            }
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
        }
    }
}

/** Dashboard shortcut into Payouts — money owed deserves a row, not just a header icon. */
@Composable
private fun PayoutsEntryCard(onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(42.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFE7F7EE), Color(0xFFD3F0E0)))),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.Payments, contentDescription = null, tint = Color(0xFF0F766E), modifier = Modifier.size(21.dp)) }
            Spacer(Modifier.width(13.dp))
            Column(Modifier.weight(1f)) {
                Text("Payouts", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                Spacer(Modifier.height(1.dp))
                Text("Balance, settlement account & history", fontSize = 12.sp, color = AuthMuted)
            }
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
        }
    }
}

@Composable
private fun BookingsMixCard(o: Overview) {
    val online = o.online.coerceAtLeast(0)
    val offline = o.offline.coerceAtLeast(0)
    val total = (online + offline).coerceAtLeast(1)
    val onlineColor = AuthAccent
    val offlineColor = Color(0xFF0EA5E9)

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(10.dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x1A0F172A))
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Color(0x0F0F172A), RoundedCornerShape(18.dp))
            .padding(18.dp),
    ) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text("Bookings mix", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
            Text("$total total", fontSize = 12.sp, color = AuthMuted)
        }
        Spacer(Modifier.height(14.dp))
        Row(Modifier.fillMaxWidth().height(12.dp), horizontalArrangement = Arrangement.spacedBy(3.dp)) {
            if (online > 0) LayoutBox(Modifier.weight(online.toFloat()).fillMaxHeight().clip(RoundedCornerShape(6.dp)).background(onlineColor))
            if (offline > 0) LayoutBox(Modifier.weight(offline.toFloat()).fillMaxHeight().clip(RoundedCornerShape(6.dp)).background(offlineColor))
            if (online == 0 && offline == 0) LayoutBox(Modifier.weight(1f).fillMaxHeight().clip(RoundedCornerShape(6.dp)).background(Color(0xFFE5E7EB)))
        }
        Spacer(Modifier.height(14.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(20.dp)) {
            MixLegend(onlineColor, "Online", online, total)
            MixLegend(offlineColor, "Walk-in", offline, total)
        }
        if (o.cancelled > 0) {
            Spacer(Modifier.height(14.dp))
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x14DC2626)).padding(horizontal = 10.dp, vertical = 5.dp),
            ) {
                LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(RED))
                Spacer(Modifier.width(6.dp))
                Text("${o.cancelled} cancelled / refunded", fontSize = 12.sp, fontWeight = FontWeight.Medium, color = RED)
            }
        }
    }
}

@Composable
private fun MixLegend(color: Color, label: String, count: Int, total: Int) {
    val pct = if (total > 0) (count * 100 / total) else 0
    Row(verticalAlignment = Alignment.CenterVertically) {
        LayoutBox(Modifier.size(9.dp).clip(RoundedCornerShape(99.dp)).background(color))
        Spacer(Modifier.width(7.dp))
        Text("$label ", fontSize = 13.sp, color = AuthInk, fontWeight = FontWeight.SemiBold)
        Text("· $count ($pct%)", fontSize = 13.sp, color = AuthMuted)
    }
}

private fun greeting(): String {
    val h = java.util.Calendar.getInstance().get(java.util.Calendar.HOUR_OF_DAY)
    return when {
        h < 12 -> "Good morning"
        h < 17 -> "Good afternoon"
        else -> "Good evening"
    }
}

@Composable
private fun GreetingHeader(name: String, subtitle: String) {
    Column(Modifier.padding(top = 4.dp)) {
        Text(greeting(), fontSize = 14.sp, color = AuthMuted)
        Spacer(Modifier.height(2.dp))
        Text(name, fontSize = 24.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk, letterSpacing = (-0.5).sp)
        Spacer(Modifier.height(8.dp))
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .clip(RoundedCornerShape(999.dp))
                .background(Color(0x142F6BFF))
                .padding(horizontal = 10.dp, vertical = 5.dp),
        ) {
            LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(AuthAccent))
            Spacer(Modifier.width(6.dp))
            Text(subtitle, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthAccentDeep)
        }
    }
}

@Composable
private fun RevenueHero(o: Overview) {
    // Real week-over-week trend from the 14-day series: last 7 vs the prior 7.
    val trendPct: Int? = run {
        if (o.trend.size < 14) null else {
            val last7 = o.trend.takeLast(7).sum()
            val prev7 = o.trend.dropLast(7).takeLast(7).sum()
            if (prev7 <= 0.0) null else (((last7 - prev7) / prev7) * 100).roundToInt()
        }
    }
    LayoutBox(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(18.dp, RoundedCornerShape(22.dp), clip = false, spotColor = AuthInkTop)
            .clip(RoundedCornerShape(22.dp))
            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid, AuthInkBot))),
    ) {
        LayoutBox(
            Modifier.matchParentSize().background(
                Brush.radialGradient(
                    listOf(Color(0x553B82F6), Color(0x00000000)),
                    center = Offset(120f, 40f), radius = 520f,
                )
            )
        )
        Column(Modifier.fillMaxWidth().padding(20.dp)) {
            Text("TOTAL REVENUE", color = Color(0xB3CFE0FF), fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.5.sp)
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.Bottom) {
                Text("₹" + formatInr(o.revenue), color = Color.White, fontSize = 34.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-1).sp)
                if (trendPct != null) {
                    Spacer(Modifier.width(10.dp))
                    val up = trendPct >= 0
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier
                            .padding(bottom = 6.dp)
                            .clip(RoundedCornerShape(999.dp))
                            .background(if (up) Color(0x3325D366) else Color(0x33F87171))
                            .padding(horizontal = 8.dp, vertical = 3.dp),
                    ) {
                        Icon(
                            if (up) Icons.AutoMirrored.Filled.TrendingUp else Icons.AutoMirrored.Filled.TrendingDown,
                            contentDescription = null,
                            tint = if (up) Color(0xFF6EE7A8) else Color(0xFFFCA5A5),
                            modifier = Modifier.size(13.dp),
                        )
                        Spacer(Modifier.width(3.dp))
                        Text("${if (up) "+" else ""}$trendPct%", color = if (up) Color(0xFF6EE7A8) else Color(0xFFFCA5A5), fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
            Spacer(Modifier.height(6.dp))
            Text("${o.ticketsSold} bookings · ${o.bookingsToday} today", color = Color(0xCCE0E8FF), fontSize = 13.sp)
            if (o.trend.any { it > 0 }) {
                Spacer(Modifier.height(18.dp))
                Sparkline(o.trend, Modifier.fillMaxWidth().height(52.dp), Color(0xFF7DA9FF))
                Spacer(Modifier.height(6.dp))
                Text("last 14 days", color = Color(0x99CFE0FF), fontSize = 11.sp)
            }
        }
    }
}

/** A single clean stat strip — replaces the boxed count-cards. Equal columns
 *  divided by hairlines, so 3 (or 4) metrics read as one considered unit. */
@Composable
private fun StatStrip(tiles: List<Tile>) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(10.dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x1A0F172A))
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Color(0x0F0F172A), RoundedCornerShape(18.dp))
            .padding(vertical = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        tiles.forEachIndexed { i, t ->
            StatColumn(Modifier.weight(1f), t)
            if (i < tiles.size - 1) {
                LayoutBox(Modifier.width(1.dp).height(46.dp).background(Color(0x140F172A)))
            }
        }
    }
}

@Composable
private fun StatColumn(modifier: Modifier, t: Tile) {
    Column(modifier.padding(horizontal = 6.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Icon(t.icon, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(18.dp))
        Spacer(Modifier.height(9.dp))
        Text(t.value, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
        Spacer(Modifier.height(1.dp))
        Text(t.label, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthMuted, maxLines = 1)
    }
}

@Composable
private fun Sparkline(values: List<Double>, modifier: Modifier, color: Color) {
    val max = (values.maxOrNull() ?: 0.0).coerceAtLeast(1.0)
    Canvas(modifier) {
        if (values.size < 2) return@Canvas
        val stepX = size.width / (values.size - 1)
        // Inset the top a little so the peak isn't clipped against the card edge.
        val top = size.height * 0.12f
        val h = size.height - top
        val pts = values.mapIndexed { i, v ->
            Offset(i * stepX, top + (h - (v / max * h).toFloat()))
        }
        // Soft gradient fill beneath the line.
        val fill = Path().apply {
            moveTo(0f, size.height)
            pts.forEach { lineTo(it.x, it.y) }
            lineTo(size.width, size.height)
            close()
        }
        drawPath(fill, Brush.verticalGradient(listOf(color.copy(alpha = 0.35f), color.copy(alpha = 0f))))
        for (i in 0 until pts.size - 1) {
            drawLine(color, pts[i], pts[i + 1], strokeWidth = 5f, cap = StrokeCap.Round)
        }
    }
}

@Composable
private fun EventsTab(api: PartnerApi, token: String, onOpen: (Long, String) -> Unit) {
    RefreshableContent(token, load = { api.events(token) }) { list ->
        if (list.isEmpty()) EmptyState("No events yet") else
            LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                items(list) { e -> EventCard(e) { onOpen(e.id, e.title) } }
            }
    }
}

/** Maps a raw event status to a semantic colour for the status dot. */
@Composable
private fun statusColor(status: String?): Color = when (status?.lowercase()) {
    "published", "live", "active", "ongoing" -> Color(0xFF16A34A) // green
    "draft", "pending", "scheduled" -> Color(0xFFF59E0B)          // amber
    "cancelled", "canceled" -> Color(0xFFDC2626)                  // red
    else -> MaterialTheme.colorScheme.onSurfaceVariant
}

@Composable
private fun EventCard(e: EventSummary, onClick: () -> Unit) {
    val total = e.totalSlots.coerceAtLeast(1)
    val sold = (e.totalSlots - e.seatsLeft).coerceIn(0, total)
    val fill = sold.toFloat() / total.toFloat()

    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    Card(
        Modifier.fillMaxWidth()
            .pressScale(interaction)
            .clickable(interactionSource = interaction, indication = null) {
                Haptics.tick(view)
                onClick()
            },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.pressShade(interaction).padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(
                    e.title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 2,
                    modifier = Modifier.weight(1f, false),
                )
                Text(
                    "₹" + formatInr(e.revenue),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                LayoutBox(Modifier.size(8.dp).clip(RoundedCornerShape(4.dp)).background(statusColor(e.status)))
                Spacer(Modifier.width(6.dp))
                Text(
                    listOfNotNull(e.status?.replaceFirstChar { it.uppercase() }, e.category, e.date).joinToString(" · "),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Spacer(Modifier.height(12.dp))
            LinearProgressIndicator(
                progress = { fill },
                modifier = Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(3.dp)),
                color = MaterialTheme.colorScheme.primary,
                trackColor = MaterialTheme.colorScheme.primaryContainer,
            )
            Spacer(Modifier.height(6.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("$sold sold", style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.Medium)
                Text("${e.seatsLeft} of ${e.totalSlots} left", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun VenuesTab(api: PartnerApi, token: String, onOpen: (Long, String) -> Unit) {
    RefreshableContent(token, load = { api.venues(token) }) { list ->
        if (list.isEmpty()) EmptyState("No venues yet") else
            // Two per row. A venue card is a glance target — name, today's take,
            // booking count — so a half-width card still says everything, and the
            // owner sees four venues without scrolling instead of two.
            LazyVerticalGrid(
                columns = GridCells.Fixed(2),
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(16.dp),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                items(list) { v -> VenueCard(v) { onOpen(v.id, v.name) } }
            }
    }
}

@Composable
/** Half-width card, sized for the two-column venues grid. */
private fun VenueCard(v: VenueSummary, onClick: () -> Unit) {
    PressableSurface(onClick = onClick, radius = 20.dp) {
        Column {
            // Photo band. No venue has an uploaded photo yet, so the fallback has to
            // carry the design rather than look like a broken image: the brand navy
            // with a soft glow and a large watermark glyph, which reads as a
            // deliberate cover until a real photo is added in /control.
            LayoutBox(Modifier.fillMaxWidth().height(112.dp)) {
                if (!v.image.isNullOrBlank()) {
                    AsyncImage(
                        model = v.image,
                        contentDescription = v.name,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.matchParentSize(),
                    )
                } else {
                    LayoutBox(
                        Modifier.matchParentSize()
                            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid, AuthInkBot))),
                    ) {
                        LayoutBox(
                            Modifier.matchParentSize().background(
                                Brush.radialGradient(
                                    listOf(Color(0x553B82F6), Color(0x00000000)),
                                    center = Offset(140f, 30f), radius = 420f,
                                )
                            )
                        )
                        // Bleeds off the right edge at low alpha so it reads as
                        // texture behind the title, not an icon sitting in a box.
                        Icon(
                            Icons.Filled.Place,
                            contentDescription = null,
                            tint = Color(0x14FFFFFF),
                            modifier = Modifier.align(Alignment.CenterEnd)
                                .offset(x = 26.dp, y = (-6).dp).size(150.dp),
                        )
                    }
                }
                // Scrim so the name stays readable over any photo, however bright.
                LayoutBox(
                    Modifier.matchParentSize().background(
                        Brush.verticalGradient(listOf(Color(0x00000000), Color(0x40000000), Color(0xB3000000)))
                    )
                )
                // Revenue rides top-right — the number the owner scans for.
                Text(
                    "₹" + formatInr(v.revenue),
                    fontSize = 11.5.sp,
                    fontWeight = FontWeight.ExtraBold, color = Color.White,
                    modifier = Modifier.align(Alignment.TopEnd).padding(9.dp)
                        .clip(RoundedCornerShape(999.dp)).background(Color(0x66000000))
                        .padding(horizontal = 8.dp, vertical = 4.dp),
                )
                Column(Modifier.align(Alignment.BottomStart).padding(11.dp)) {
                    Text(
                        v.name,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.ExtraBold, color = Color.White,
                        letterSpacing = (-0.3).sp,
                        // Half-width cards cut most venue names mid-word on one
                        // line, so the name gets two before it ellipses.
                        maxLines = 2, overflow = TextOverflow.Ellipsis,
                        lineHeight = 17.sp,
                    )
                    v.location?.takeIf { it.isNotBlank() }?.let {
                        Spacer(Modifier.height(2.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Filled.Place, contentDescription = null, tint = Color(0xCCFFFFFF), modifier = Modifier.size(12.dp))
                            Spacer(Modifier.width(4.dp))
                            Text(it, fontSize = 12.sp, color = Color(0xCCFFFFFF), maxLines = 1)
                        }
                    }
                }
            }
            // Footer: what the owner acts on. At half width a single row has
            // nowhere to put "Open desk" once the count chip and the sport have
            // taken their space, so it sits on its own line underneath rather
            // than shrinking type until nothing is legible.
            Column(Modifier.fillMaxWidth().padding(horizontal = 11.dp, vertical = 10.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x142F6BFF))
                            .padding(horizontal = 8.dp, vertical = 5.dp),
                    ) {
                        Icon(Icons.Filled.ConfirmationNumber, contentDescription = null, tint = AuthAccentDeep, modifier = Modifier.size(12.dp))
                        Spacer(Modifier.width(5.dp))
                        Text(
                            "${v.bookings}",
                            fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep,
                        )
                    }
                    if (v.sports.isNotEmpty()) {
                        Spacer(Modifier.width(6.dp))
                        Text(
                            v.sports.first() + if (v.sports.size > 1) "  +${v.sports.size - 1}" else "",
                            fontSize = 11.5.sp, color = AuthMuted, maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f, fill = false),
                        )
                    }
                }
                Spacer(Modifier.height(7.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Open desk", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep)
                    Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = AuthAccentDeep, modifier = Modifier.size(17.dp))
                }
            }
        }
    }
}

/** A premium card that sinks and darkens under the thumb, with a haptic tick. */
@Composable
private fun PressableSurface(
    onClick: () -> Unit,
    radius: Dp = 18.dp,
    content: @Composable () -> Unit,
) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    LayoutBox(
        modifier = Modifier
            .fillMaxWidth()
            .pressScale(interaction)
            .premiumSurface(radius)
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null) {
                Haptics.tick(view)
                onClick()
            },
    ) { content() }
}

// ---- Venue day / booking management ------------------------------------

private const val DAY_MS = 86_400_000L
private val RED = Color(0xFFDC2626)
private val GREEN = Color(0xFF16A34A)

private fun todayMillis(): Long {
    val c = java.util.Calendar.getInstance()
    c.set(java.util.Calendar.HOUR_OF_DAY, 12)
    c.set(java.util.Calendar.MINUTE, 0); c.set(java.util.Calendar.SECOND, 0); c.set(java.util.Calendar.MILLISECOND, 0)
    return c.timeInMillis
}

private fun apiDate(ms: Long): String =
    java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).format(java.util.Date(ms))

private fun prettyDate(ms: Long): String =
    java.text.SimpleDateFormat("EEE, dd MMM", java.util.Locale.getDefault()).format(java.util.Date(ms))

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VenueDayScreen(
    api: PartnerApi,
    token: String,
    venueId: Long,
    venueName: String,
    onBack: () -> Unit,
    onAnalytics: () -> Unit,
    canPricing: Boolean = true,
    canBookings: Boolean = true,
    /** Decides whether this desk books courts or tables. */
    lane: Lane = Lane.VENUE,
    /** Open on the slot editor, for a venue that has nothing bookable yet. */
    startInSlots: Boolean = false,
) {
    var showPricing by remember { mutableStateOf(startInSlots) }

    if (showPricing) {
        // Opened from Home's setup card, back goes all the way home: the day
        // grid behind it is empty until these slots exist.
        VenuePricingScreen(api, token, venueId, venueName, onBack = { if (startInSlots) onBack() else showPricing = false })
        return
    }

    DayBookingsScreen(
        api = api,
        token = token,
        venueId = venueId,
        venueName = venueName,
        onBack = onBack,
        canPricing = canPricing,
        canBookings = canBookings,
        onPricing = { showPricing = true },
        onAnalytics = onAnalytics,
    )
}

/** A tapped free cell in the court grid: which slot + which court. */
private data class CellTarget(val slot: DaySlot, val courtId: Long, val courtName: String, val price: Double = 0.0)

/** Playo-style day grid: courts as columns, time slots as rows. Free cells add a
 *  walk-in; booked cells open a cancel confirm. The courts scroll horizontally when
 *  there are many; the time column stays pinned beside them, because a scrolled-off
 *  clock left the desk reading "Open ₹800" with no idea which hour it belonged to. */
@Composable
private fun CourtGrid(
    grid: DayGrid,
    canBookings: Boolean,
    onAddCell: (DaySlot, Long, String) -> Unit,
    onCancel: (DayBooking) -> Unit,
    lane: Lane = Lane.VENUE,
) {
    val timeW = 58.dp
    val cellW = 96.dp
    // Fixed heights on both columns: they scroll independently, so the only thing
    // keeping a time label level with its row is that both use the same pitch.
    // Tall enough for a court name over its sport line — 34.dp clipped the sport.
    val headerH = 44.dp
    val rowH = 56.dp
    val rowGap = 6.dp
    val courtName = { id: Long -> grid.courts.firstOrNull { it.id == id }?.name ?: resourceNoun(lane) }

    // Every sport these courts actually serve. A three-sport venue otherwise puts
    // badminton courts, a turf and cricket nets in one sideways scroll, interleaved
    // by sort order, with nothing to narrow it down.
    val sports = remember(grid.courts) { grid.courts.flatMap { it.sports }.distinct() }
    var picked by remember(grid.venueName) { mutableStateOf<String?>(null) }
    // The chosen sport can vanish under us — a court edited, a branch switched — and a
    // filter matching nothing would draw an empty grid with no obvious way back.
    val sport = picked?.takeIf { it in sports }
    val courts = if (sport == null) grid.courts else grid.courts.filter { sport in it.sports }
    val shown = courts.map { it.id }.toSet()

    Column(Modifier.fillMaxWidth().premiumSurface().padding(vertical = 12.dp)) {
        Row(Modifier.padding(start = 14.dp, end = 14.dp, bottom = 10.dp), verticalAlignment = Alignment.CenterVertically) {
            LegendDot(Color(0xFF16A34A), "Open"); Spacer(Modifier.width(14.dp)); LegendDot(AuthAccent, "Booked")
            Spacer(Modifier.width(14.dp)); LegendDot(Color(0xFFB45309), "Holding")
        }
        // Single-sport venues get no filter — one chip reading "All" is just clutter.
        if (sports.size > 1) {
            Row(
                Modifier.fillMaxWidth().horizontalScroll(rememberScrollState())
                    .padding(start = 14.dp, end = 14.dp, bottom = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                SportChip("All", sport == null) { picked = null }
                sports.forEach { s ->
                    Spacer(Modifier.width(8.dp))
                    SportChip(s, sport == s) { picked = if (sport == s) null else s }
                }
            }
        }
        Row(Modifier.fillMaxWidth()) {
            Column(
                Modifier.width(timeW).padding(start = 14.dp),
                verticalArrangement = Arrangement.spacedBy(rowGap),
            ) {
                LayoutBox(Modifier.height(headerH), contentAlignment = Alignment.CenterStart) {
                    Text("Time", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = AuthMuted)
                }
                grid.slots.forEach { slot ->
                    LayoutBox(Modifier.height(rowH), contentAlignment = Alignment.CenterStart) {
                        Text(slot.time ?: slot.label, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthInk, maxLines = 2)
                    }
                }
            }
            Column(
                Modifier.horizontalScroll(rememberScrollState()).padding(end = 14.dp),
                verticalArrangement = Arrangement.spacedBy(rowGap),
            ) {
                Row(Modifier.height(headerH), verticalAlignment = Alignment.CenterVertically) {
                    courts.forEach { c ->
                        Column(Modifier.width(cellW).padding(horizontal = 4.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(c.name, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                            // Once a sport is picked every column shares it, so the
                            // per-column label stops earning its space.
                            if (sport == null && c.sports.isNotEmpty()) {
                                Text(
                                    c.sports.joinToString(" · "),
                                    fontSize = 10.sp, color = AuthMuted,
                                    maxLines = 1, overflow = TextOverflow.Ellipsis,
                                )
                            }
                        }
                    }
                }
                grid.slots.forEach { slot ->
                    Row(Modifier.height(rowH), verticalAlignment = Alignment.CenterVertically) {
                        slot.courts.filter { it.courtId in shown }.forEach { cell ->
                            GridCell(
                                cell = cell,
                                width = cellW,
                                canBook = canBookings && slot.isOpen,
                                onFree = { onAddCell(slot, cell.courtId, courtName(cell.courtId)) },
                                onBooked = { onCancel(it) },
                                runsFor = slot.sports.firstOrNull()
                                    ?.let { if (slot.sports.size > 1) slot.sports.joinToString(", ") else it },
                            )
                        }
                    }
                }
            }
        }
    }
}

/** One sport in the desk grid's filter row. Tapping the active one clears it. */
@Composable
private fun SportChip(label: String, on: Boolean, onPick: () -> Unit) {
    Text(
        label,
        fontSize = 12.sp,
        fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
        color = if (on) AuthAccentDeep else AuthInk,
        maxLines = 1,
        modifier = Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(if (on) Color(0x142F6BFF) else Color(0xFFF8FAFC))
            .border(if (on) 1.5.dp else 1.dp, if (on) AuthAccent else Color(0x1F0F172A), RoundedCornerShape(999.dp))
            .clickable { onPick() }
            .padding(horizontal = 13.dp, vertical = 7.dp),
    )
}

@Composable
private fun LegendDot(color: Color, label: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        LayoutBox(Modifier.size(9.dp).clip(RoundedCornerShape(99.dp)).background(color))
        Spacer(Modifier.width(6.dp))
        Text(label, fontSize = 11.5.sp, color = AuthMuted)
    }
}

@Composable
private fun GridCell(
    cell: CourtCell,
    width: Dp,
    canBook: Boolean,
    onFree: () -> Unit,
    onBooked: (DayBooking) -> Unit,
    /** What this time DOES run for, shown on a cell the court can't be sold at. */
    runsFor: String? = null,
) {
    val booked = cell.isBooked
    // This time doesn't run the sport this court hosts, so the server would refuse the
    // sale. An already-booked cell still wins: restricting a slot after the fact must
    // not strand a booking somewhere the desk can no longer see or cancel it.
    val blocked = !booked && !cell.allowed
    // A court-hour someone is paying for in the app right now. It reads amber and takes
    // no tap: the server won't sell it to the desk either, and a cell that looks Open
    // and answers with an error is worse than one that says what it is.
    val held = !booked && !blocked && cell.isHeld
    val fill = when {
        booked -> Color(0x142F6BFF)
        blocked -> Color(0x080F172A)
        held -> Color(0x14B45309)
        else -> Color(0x1416A34A)
    }
    val edge = when {
        booked -> Color(0x332F6BFF)
        blocked -> Color(0x140F172A)
        held -> Color(0x33B45309)
        else -> Color(0x3316A34A)
    }
    LayoutBox(
        modifier = Modifier
            .width(width).height(56.dp).padding(horizontal = 4.dp)
            .clip(RoundedCornerShape(11.dp))
            .background(fill)
            .border(1.dp, edge, RoundedCornerShape(11.dp))
            .clickable(enabled = booked || (canBook && !held && !blocked)) {
                if (booked) cell.bookings.firstOrNull()?.let(onBooked) else onFree()
            },
        contentAlignment = Alignment.Center,
    ) {
        if (blocked) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text("—", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = AuthMuted)
                if (!runsFor.isNullOrBlank()) {
                    Text(
                        "$runsFor only",
                        fontSize = 9.5.sp, color = AuthMuted,
                        maxLines = 1, overflow = TextOverflow.Ellipsis,
                    )
                }
            }
            return@LayoutBox
        }
        if (booked) {
            val b = cell.bookings.firstOrNull()
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text(b?.customer?.take(9) ?: "Booked", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep, maxLines = 1)
                if ((b?.checkedIn ?: 0) > 0) Text("✓ in", fontSize = 9.5.sp, fontWeight = FontWeight.SemiBold, color = GREEN)
            }
        } else if (held) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text("Holding", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFFB45309), maxLines = 1)
                Text("paying now", fontSize = 9.5.sp, color = AuthMuted, maxLines = 1)
            }
        } else {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text("Open", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = GREEN)
                if (cell.price > 0) {
                    // Peak hours read amber so the desk can see the higher rate at a glance.
                    Text(
                        "₹${cell.price.toInt()}" + if (cell.isPeak) " ▲" else "",
                        fontSize = 10.sp,
                        fontWeight = if (cell.isPeak) FontWeight.Bold else FontWeight.Normal,
                        color = if (cell.isPeak) Color(0xFFB45309) else AuthMuted,
                    )
                }
            }
        }
    }
}

@Composable
private fun SlotCard(slot: DaySlot, blocked: Boolean, canBookings: Boolean, onAdd: () -> Unit, onCancel: (Long) -> Unit) {
    val full = slot.available <= 0
    val cap = slot.capacity.coerceAtLeast(1)
    val fill = (slot.booked.toFloat() / cap).coerceIn(0f, 1f)
    val capColor = if (full) RED else GREEN
    Column(Modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Column {
                Text(slot.time ?: slot.label, fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                if (slot.price > 0) {
                    Spacer(Modifier.height(1.dp))
                    Text("₹" + formatInr(slot.price), fontSize = 12.5.sp, color = AuthMuted)
                }
            }
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(capColor.copy(alpha = 0.12f)).padding(horizontal = 10.dp, vertical = 5.dp),
            ) {
                Text("${slot.booked}/${slot.capacity}", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = capColor)
            }
        }
        Spacer(Modifier.height(11.dp))
        LayoutBox(Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFFEDF0F5))) {
            LayoutBox(Modifier.fillMaxWidth(fill).fillMaxHeight().clip(RoundedCornerShape(99.dp)).background(if (full) RED else AuthAccent))
        }
        slot.bookings.forEach { b ->
            Spacer(Modifier.height(12.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Row(Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically) {
                    LayoutBox(
                        Modifier.size(30.dp).clip(RoundedCornerShape(99.dp)).background(Color(0x142F6BFF)),
                        contentAlignment = Alignment.Center,
                    ) { Text(b.customer.take(1).uppercase(), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep) }
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Text(b.customer, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = AuthInk, maxLines = 1)
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(if (b.channel == "offline") "Walk-in" else "Online", fontSize = 11.5.sp, color = AuthMuted)
                            if (b.checkedIn > 0) {
                                Spacer(Modifier.width(6.dp))
                                Text("✓ checked in", fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = GREEN)
                            }
                        }
                    }
                }
                if (canBookings) {
                    IconButton(onClick = { onCancel(b.id) }) { Icon(Icons.Filled.Close, contentDescription = "Cancel", tint = Color(0xFFC0491F).copy(alpha = 0.8f), modifier = Modifier.size(18.dp)) }
                }
            }
        }
        if (canBookings && !blocked && !full && slot.isOpen) {
            Spacer(Modifier.height(14.dp))
            Row(
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp))
                    .background(Color(0x0F2F6BFF))
                    .border(1.dp, Color(0x242F6BFF), RoundedCornerShape(12.dp))
                    .clickable { onAdd() }
                    .padding(vertical = 11.dp),
            ) {
                Icon(Icons.Filled.Add, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(6.dp))
                Text("Add walk-in booking", fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = AuthAccentDeep)
            }
        }
    }
}

// ---- Venue pricing / slot editor ---------------------------------------

private val WEEK_DAYS = listOf("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun")

/**
 * Per-court base rate + peak pricing — the "charge more at busy hours" lever
 * Playo has and we didn't expose. The server already charges the peak rate
 * ({@link VenueCourt::rateFor}); this is the missing way to configure it.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CourtPricingScreen(api: PartnerApi, token: String, venueId: Long, venueName: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var editing by remember { mutableStateOf<CourtPricing?>(null) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<List<CourtPricing>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.venueCourts(token, venueId) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Courts & peak pricing", maxLines = 1, fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Text(venueName, Modifier.padding(horizontal = 16.dp, vertical = 10.dp), fontSize = 13.sp, color = AuthMuted)
            Loaded(state) { courts ->
                if (courts.isEmpty()) {
                    EmptyState("No courts on this venue yet.")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 24.dp),
                    ) {
                        items(courts) { c -> CourtPricingCard(c) { editing = c } }
                    }
                }
            }
        }
    }

    editing?.let { court ->
        PeakPricingDialog(
            court = court,
            onDismiss = { editing = null },
            onSave = { price, peak, days, start, end ->
                editing = null
                scope.launch {
                    runCatching { api.saveCourtPricing(token, venueId, court.id, price, peak, days, start, end) }
                    reload++
                }
            },
        )
    }
}

@Composable
private fun CourtPricingCard(c: CourtPricing, onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Column(Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(c.name, fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                    if (c.sports.isNotEmpty()) {
                        Spacer(Modifier.height(1.dp))
                        Text(c.sports.joinToString(" · "), fontSize = 12.sp, color = AuthMuted)
                    }
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text("₹${c.price}", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Text("per hour", fontSize = 11.sp, color = AuthMuted)
                }
            }
            Spacer(Modifier.height(12.dp))
            if (c.peakOn) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.clip(RoundedCornerShape(999.dp))
                        .background(Color(0x1AB45309)).padding(horizontal = 10.dp, vertical = 6.dp),
                ) {
                    Icon(Icons.AutoMirrored.Filled.TrendingUp, contentDescription = null, tint = Color(0xFFB45309), modifier = Modifier.size(13.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(
                        "₹${c.peakPrice} peak · " + peakWhenLabel(c),
                        fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFF92400E),
                    )
                }
            } else {
                Text("No peak pricing — tap to add", fontSize = 12.sp, color = AuthMuted)
            }
        }
    }
}

/** "Sat, Sun 6:00 PM–10:00 PM" / "every day 6:00 PM–10:00 PM" / "Sat, Sun". */
private fun peakWhenLabel(c: CourtPricing): String {
    val days = if (c.peakDays.isEmpty()) "every day" else c.peakDays.joinToString(", ")
    val window = if (c.peakStart != null && c.peakEnd != null) " ${c.peakStart}–${c.peakEnd}" else ""
    return days + window
}

/** Editor for one court's base rate and its peak rule. */
@Composable
private fun PeakPricingDialog(
    court: CourtPricing,
    onDismiss: () -> Unit,
    onSave: (price: Int, peakPrice: Int?, days: List<String>, start: String?, end: String?) -> Unit,
) {
    var base by remember { mutableStateOf(court.price.toString()) }
    var peakOn by remember { mutableStateOf(court.peakOn) }
    var peak by remember { mutableStateOf(court.peakPrice?.toString() ?: "") }
    var days by remember { mutableStateOf(court.peakDays.toSet()) }
    var start by remember { mutableStateOf(court.peakStart ?: "18:00") }
    var end by remember { mutableStateOf(court.peakEnd ?: "22:00") }
    val view = LocalView.current

    val fieldColors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    // Peak needs a price AND a schedule, or the server ignores it — mirror that here
    // so the button can't promise something the backend will drop.
    val peakValid = !peakOn || (peak.toIntOrNull()?.let { it > 0 } == true &&
        (days.isNotEmpty() || (start.isNotBlank() && end.isNotBlank())))

    Dialog(onDismissRequest = onDismiss) {
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White)
                .verticalScroll(rememberScrollState()).padding(22.dp),
        ) {
            Text(court.name, fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(2.dp))
            Text("Hourly rate for this court", fontSize = 12.sp, color = AuthMuted)

            Spacer(Modifier.height(16.dp))
            OutlinedTextField(
                value = base,
                onValueChange = { base = it.filter { ch -> ch.isDigit() }.take(6) },
                label = { Text("Base price (₹/hour)") },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                colors = fieldColors,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                modifier = Modifier.fillMaxWidth(),
            )

            Spacer(Modifier.height(18.dp))
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp))
                    .background(Color(0xFFFFF8EE)).border(1.dp, Color(0x33B45309), RoundedCornerShape(14.dp))
                    .padding(horizontal = 14.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text("Peak pricing", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color(0xFF92400E))
                    Text("Charge more at busy hours", fontSize = 11.5.sp, color = Color(0xFFB45309))
                }
                Switch(
                    checked = peakOn,
                    onCheckedChange = { peakOn = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) },
                )
            }

            if (peakOn) {
                Spacer(Modifier.height(14.dp))
                OutlinedTextField(
                    value = peak,
                    onValueChange = { peak = it.filter { ch -> ch.isDigit() }.take(6) },
                    label = { Text("Peak price (₹/hour)") },
                    singleLine = true,
                    shape = RoundedCornerShape(12.dp),
                    colors = fieldColors,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    modifier = Modifier.fillMaxWidth(),
                )

                Spacer(Modifier.height(16.dp))
                Text("PEAK DAYS", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp)
                Spacer(Modifier.height(4.dp))
                Text("None selected = every day", fontSize = 11.sp, color = AuthMuted)
                Spacer(Modifier.height(10.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(5.dp)) {
                    WEEK_DAYS.forEach { d ->
                        val on = d in days
                        LayoutBox(
                            Modifier.weight(1f).height(38.dp).clip(RoundedCornerShape(10.dp))
                                .background(if (on) AuthAccent else Color(0xFFF1F5F9))
                                .clickable {
                                    days = if (on) days - d else days + d
                                    view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                                },
                            contentAlignment = Alignment.Center,
                        ) {
                            Text(
                                d.take(1),
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (on) Color.White else AuthMuted,
                            )
                        }
                    }
                }

                Spacer(Modifier.height(16.dp))
                Text("PEAK HOURS", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp)
                Spacer(Modifier.height(10.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = start,
                        onValueChange = { start = it.take(5) },
                        label = { Text("From") },
                        placeholder = { Text("18:00") },
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp),
                        colors = fieldColors,
                        modifier = Modifier.weight(1f),
                    )
                    OutlinedTextField(
                        value = end,
                        onValueChange = { end = it.take(5) },
                        label = { Text("To") },
                        placeholder = { Text("22:00") },
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp),
                        colors = fieldColors,
                        modifier = Modifier.weight(1f),
                    )
                }
                if (!peakValid) {
                    Spacer(Modifier.height(10.dp))
                    Text(
                        "Set a peak price and at least a day or an hours window.",
                        fontSize = 11.5.sp, color = RED,
                    )
                }
            }

            Spacer(Modifier.height(20.dp))
            GradientCta(text = "Save pricing", enabled = peakValid && base.isNotBlank(), loading = false) {
                view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                onSave(
                    base.toIntOrNull() ?: court.price,
                    if (peakOn) peak.toIntOrNull() else null,
                    days.toList(),
                    if (peakOn && start.isNotBlank()) start else null,
                    if (peakOn && end.isNotBlank()) end else null,
                )
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) {
                Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VenuePricingScreen(api: PartnerApi, token: String, venueId: Long, venueName: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var editing by remember { mutableStateOf<SlotEdit?>(null) }
    var adding by remember { mutableStateOf(false) }
    var showCourts by remember { mutableStateOf(false) }
    var generating by remember { mutableStateOf(false) }
    // Delete asks first: one tap used to remove a slot at once, and a stray run of taps
    // emptied a venue's whole week.
    var confirmDelete by remember { mutableStateOf<SlotEdit?>(null) }
    var note by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    val state by produceState<UiState<List<SlotEdit>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.venueSlots(token, venueId) }
    }
    LaunchedEffect(note) { if (note != null) { kotlinx.coroutines.delay(3000); note = null } }
    // The sports a slot may be narrowed to are whatever this venue's courts host —
    // asking the courts keeps the picker honest instead of offering a sport nothing
    // can be booked for. A failure here just means no picker, never a broken screen.
    val courts by produceState<List<CourtPricing>?>(null, venueId) {
        value = runCatching { api.venueCourts(token, venueId) }.getOrDefault(emptyList())
    }
    val venueSports = courts.orEmpty().flatMap { it.sports }.distinct()
    // With courts, each court is sold once per slot — a slot "capacity" means nothing there.
    val hasCourts = courts.orEmpty().isNotEmpty()
    val courtRate = courts.orEmpty().minOfOrNull { it.price }?.takeIf { it > 0 }
    val todayName = remember { java.text.SimpleDateFormat("EEEE", java.util.Locale.ENGLISH).format(java.util.Date()) }
    // Courts cost different amounts, so the list shows one court's real prices at a time.
    var courtTab by remember { mutableStateOf<Long?>(null) }
    val activeCourts = courts.orEmpty()

    if (showCourts) {
        CourtPricingScreen(api, token, venueId, venueName, onBack = { showCourts = false })
        return
    }

    // The dialog stays open until the server says yes, and says why when it says no —
    // a refused save used to close the dialog and show nothing, as if it had worked.
    suspend fun trySave(id: Long?, day: String, time: String, price: Double, capacity: Int, isOpen: Boolean, sports: List<String>, courtPrices: Map<Long, Double>?): String? =
        runCatching { api.saveSlot(token, venueId, id, day, time, price, capacity, isOpen, sports, courtPrices) }
            .fold(
                onSuccess = { Haptics.confirm(view); reload++; null },
                onFailure = { e -> Haptics.reject(view); (e as? ApiException)?.message ?: "Couldn't save. Check your connection and try again." },
            )
    val weekdays = remember { listOf("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday") }
    var day by remember { mutableStateOf(if (todayName in weekdays) todayName else "Monday") }
    // Switched on/off in the list before the server answers, so the switch never lags the thumb.
    val openOverride = remember { mutableStateMapOf<Long, Boolean>() }
    var noteIsError by remember { mutableStateOf(false) }

    /** The rows the desk sells on [d]: that day's own slots, plus every-day slots at other times. */
    fun rowsFor(d: String, all: List<SlotEdit>): List<Pair<SlotEdit, Boolean>> {
        val own = all.filter { it.day == d }
        val taken = own.mapNotNull { com.haraan.partner.ui.pricing.startMinutes(it.time) }.toSet()
        val every = all.filter { (it.day ?: "Every day") !in weekdays && com.haraan.partner.ui.pricing.startMinutes(it.time) !in taken }
        return (own.map { it to true } + every.map { it to false })
            .sortedBy { com.haraan.partner.ui.pricing.startMinutes(it.first.time) ?: Int.MAX_VALUE }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = AuthPageBg, scrolledContainerColor = Color.White),
                title = {
                    Column {
                        Text("Pricing & slots", maxLines = 1, fontWeight = FontWeight.ExtraBold, color = AuthInk, fontSize = 18.sp)
                        Text(venueName, maxLines = 1, fontSize = 12.sp, color = AuthMuted)
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = {
                    HeaderIcon(Icons.Filled.Add, "Add slot") { Haptics.tick(view); adding = true }
                    Spacer(Modifier.width(4.dp))
                },
            )
        },
    ) { padding ->
        LayoutBox(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            val s = state
            if (s is UiState.Loading) {
                com.haraan.partner.ui.pricing.StudioSkeleton()
            } else Loaded(state) { slots ->
                val slotMinutes = remember(slots) {
                    slots.mapNotNull { com.haraan.partner.ui.pricing.startMinutes(it.time) }.distinct().sorted()
                        .zipWithNext { a, b -> b - a }.filter { it > 0 }.minOrNull()?.coerceIn(30, 60) ?: 60
                }
                val columns = weekdays.map { d ->
                    com.haraan.partner.ui.pricing.DayColumn(
                        d,
                        rowsFor(d, slots).mapNotNull { (slot, own) ->
                            com.haraan.partner.ui.pricing.startMinutes(slot.time)?.let {
                                com.haraan.partner.ui.pricing.DayMark(it, own, openOverride[slot.id] ?: slot.isOpen)
                            }
                        },
                    )
                }
                val rows = rowsFor(day, slots)
                val court = activeCourts.firstOrNull { it.id == courtTab } ?: activeCourts.singleOrNull()

                /** What a slot charges, as the list shows it for the chosen court (or all of them). */
                fun priceOf(slot: SlotEdit): Triple<String, String?, Boolean> {
                    if (court != null) {
                        val own = slot.priceFor(court.id)
                        return if (own != null) Triple("₹" + formatInr(own), if (slot.courtPrices[court.id] != null) "this court" else "all courts", true)
                        else Triple(if (court.price > 0) "₹" + court.price else "—", "court rate", false)
                    }
                    val each = activeCourts.map { c -> slot.priceFor(c.id) ?: c.price.toDouble() }.filter { it > 0 }
                    val lo = each.minOrNull()
                    val hi = each.maxOrNull()
                    val set = slot.price > 0 || slot.courtPrices.isNotEmpty()
                    return when {
                        lo == null -> Triple(if (slot.price > 0) "₹" + formatInr(slot.price) else "Court rate", null, slot.price > 0)
                        lo == hi -> Triple("₹" + formatInr(lo), if (set) "all courts" else "court rate", set)
                        else -> Triple("₹" + formatInr(lo) + "–" + formatInr(hi!!), "by court", set)
                    }
                }

                LazyColumn(
                    Modifier.fillMaxSize(),
                    contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, end = 16.dp, top = 6.dp, bottom = 32.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    item(key = "week") {
                        com.haraan.partner.ui.pricing.WeekRibbon(
                            days = columns, selected = day, today = todayName, slotMinutes = slotMinutes,
                            onPick = { day = it },
                        )
                    }
                    item(key = "tools") {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            com.haraan.partner.ui.pricing.ToolTile(
                                "Peak pricing", "Charge more at busy hours, per court",
                                art = { peakArt() },
                                onClick = { showCourts = true }, modifier = Modifier.weight(1f),
                            )
                            com.haraan.partner.ui.pricing.ToolTile(
                                "Generate slots", "Open to close in one tap, 30 or 60 min",
                                art = { generateArt() },
                                onClick = { generating = true }, modifier = Modifier.weight(1f),
                            )
                        }
                    }
                    if (activeCourts.size > 1) {
                        item(key = "courts") {
                            Row(
                                Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(vertical = 2.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                com.haraan.partner.ui.pricing.CourtPill("All courts", null, courtTab == null) { courtTab = null }
                                activeCourts.forEach { c ->
                                    Spacer(Modifier.width(8.dp))
                                    com.haraan.partner.ui.pricing.CourtPill(c.name, if (c.price > 0) "₹${c.price}" else null, courtTab == c.id) { courtTab = c.id }
                                }
                            }
                        }
                    }
                    item(key = "summary-$day") {
                        val starts = rows.mapNotNull { com.haraan.partner.ui.pricing.startMinutes(it.first.time) }
                        val openCount = rows.count { openOverride[it.first.id] ?: it.first.isOpen }
                        val cheapest = rows.filter { openOverride[it.first.id] ?: it.first.isOpen }.mapNotNull { (slot, _) ->
                            if (court != null) slot.priceFor(court.id) ?: court.price.toDouble()
                            else activeCourts.map { c -> slot.priceFor(c.id) ?: c.price.toDouble() }.minOrNull() ?: slot.price
                        }.filter { it > 0 }.minOrNull()
                        com.haraan.partner.ui.pricing.DaySummary(
                            day = day, isToday = day == todayName, slots = rows.size, open = openCount,
                            span = if (starts.isEmpty()) null else com.haraan.partner.ui.pricing.clock(starts.min()) + " – " + com.haraan.partner.ui.pricing.clock(starts.max() + slotMinutes),
                            from = cheapest?.let { "₹" + formatInr(it) },
                            modifier = Modifier.padding(top = 8.dp),
                        )
                    }
                    note?.let { n ->
                        item(key = "note") {
                            Text(
                                n, fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
                                color = if (noteIsError) RED else Color(0xFF15803D),
                                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp))
                                    .background(if (noteIsError) Color(0xFFFEF2F2) else Color(0xFFF0FDF4))
                                    .padding(horizontal = 12.dp, vertical = 9.dp),
                            )
                        }
                    }
                    if (rows.isEmpty()) {
                        item(key = "empty-$day") {
                            Column(Modifier.fillMaxWidth().padding(top = 8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                com.haraan.partner.ui.pricing.EmptyDayArt(Modifier.size(width = 180.dp, height = 130.dp))
                                Spacer(Modifier.height(10.dp))
                                Text("No slots on ${day}s", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                                Text("Nothing can be booked this day until you add times.", fontSize = 12.5.sp, color = AuthMuted)
                                Spacer(Modifier.height(12.dp))
                                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                    OutlinedButton(onClick = { Haptics.tick(view); adding = true }, shape = RoundedCornerShape(14.dp)) { Text("Add one") }
                                    Button(
                                        onClick = { Haptics.tick(view); generating = true }, shape = RoundedCornerShape(14.dp),
                                        colors = androidx.compose.material3.ButtonDefaults.buttonColors(containerColor = AuthAccent),
                                    ) { Text("Generate slots", fontWeight = FontWeight.Bold) }
                                }
                            }
                        }
                    } else {
                        var index = 0
                        rows.groupBy { com.haraan.partner.ui.pricing.dayPartOf(com.haraan.partner.ui.pricing.startMinutes(it.first.time) ?: 0) }
                            .forEach { (part, partRows) ->
                                item(key = "part-$day-${part.name}") {
                                    com.haraan.partner.ui.pricing.DayPartHeader(
                                        part, partRows.size, partRows.count { openOverride[it.first.id] ?: it.first.isOpen },
                                    )
                                }
                                partRows.forEach { (slot, own) ->
                                    val i = index++
                                    // Keyed by day, so switching day plays the entrance again.
                                    item(key = "slot-$day-${slot.id}") {
                                        val isOpen = openOverride[slot.id] ?: slot.isOpen
                                        val (price, pnote, priceIsOwn) = priceOf(slot)
                                        com.haraan.partner.ui.pricing.SlotLine(
                                            time = slot.time,
                                            source = if (own) null else "Every day",
                                            detail = listOfNotNull(
                                                slot.sports.takeIf { it.isNotEmpty() }?.joinToString(", ")?.let { "$it only" },
                                                if (!hasCourts) "${slot.capacity} at a time" else null,
                                                if (isOpen) null else "Off sale",
                                            ).joinToString(" · ").ifBlank { null },
                                            price = price, priceNote = pnote, priceIsOwn = priceIsOwn,
                                            open = isOpen, index = i,
                                            onToggle = { on ->
                                                openOverride[slot.id] = on
                                                if (on) Haptics.confirm(view) else Haptics.tick(view)
                                                scope.launch {
                                                    val err = trySave(slot.id, slot.day ?: "Every day", slot.time, slot.price, slot.capacity, on, slot.sports, null)
                                                    if (err != null) {
                                                        openOverride.remove(slot.id)
                                                        noteIsError = true; note = err
                                                    } else {
                                                        noteIsError = false
                                                        note = if (own) "${slot.time} ${if (on) "back on sale" else "off sale"} on ${day}s"
                                                        else "${slot.time} ${if (on) "back on sale" else "off sale"} on every day"
                                                    }
                                                }
                                            },
                                            onEdit = { editing = slot },
                                        )
                                    }
                                }
                            }
                        item(key = "rule") {
                            Text(
                                "Every-day slots run on all days. A day's own slot at the same time replaces it — that's what the desk sells.",
                                fontSize = 11.5.sp, color = AuthMuted, lineHeight = 16.sp,
                                modifier = Modifier.padding(top = 6.dp, start = 4.dp, end = 4.dp),
                            )
                        }
                    }
                }
            }
        }

    if (generating) {
        val count = (state as? UiState.Data)?.value?.size ?: 0
        com.haraan.partner.slots.GenerateSlotsSheet(
            api = api,
            token = token,
            venueId = venueId,
            existingCount = count,
            onDismiss = { generating = false },
            onGenerated = { r ->
                generating = false
                noteIsError = false
                note = buildString {
                    append("${r.created} slot${if (r.created == 1) "" else "s"} created")
                    if (r.removed > 0) append(" · ${r.removed} replaced")
                    if (r.kept > 0) append(" · ${r.kept} already there")
                }
                reload++
            },
        )
    }

    confirmDelete?.let { slot ->
        AlertDialog(
            onDismissRequest = { confirmDelete = null },
            title = { Text("Delete ${slot.time}?", fontWeight = FontWeight.Bold) },
            text = { Text("${slot.day ?: "Every day"} · this time stops being bookable. Bookings already taken stay.") },
            confirmButton = {
                TextButton(onClick = {
                    confirmDelete = null
                    scope.launch {
                        runCatching { api.deleteSlot(token, venueId, slot.id) }
                            .onSuccess { Haptics.confirm(view) }
                            .onFailure { Haptics.reject(view) }
                        reload++
                    }
                }) { Text("Delete", color = RED, fontWeight = FontWeight.Bold) }
            },
            dismissButton = { TextButton(onClick = { confirmDelete = null }) { Text("Keep") } },
        )
    }
    }

    if (adding) {
        SlotEditDialog(
            existing = null,
            initialDay = day,
            venueSports = venueSports,
            courts = activeCourts,
            courtRate = courtRate,
            onDismiss = { adding = false },
            onSave = { day, time, price, capacity, isOpen, sports, courtPrices ->
                trySave(null, day, time, price, capacity, isOpen, sports, courtPrices).also { if (it == null) { adding = false; noteIsError = false; note = "$time added to $day" } }
            },
        )
    }
    editing?.let { slot ->
        SlotEditDialog(
            existing = slot,
            venueSports = venueSports,
            courts = activeCourts,
            courtRate = courtRate,
            onDismiss = { editing = null },
            onDelete = { editing = null; confirmDelete = slot },
            onSave = { day, time, price, capacity, isOpen, sports, courtPrices ->
                trySave(slot.id, day, time, price, capacity, isOpen, sports, courtPrices).also { if (it == null) { editing = null; noteIsError = false; note = "$day $time saved" } }
            },
        )
    }
}

@Composable
private fun SlotEditDialog(
    existing: SlotEdit?,
    /** The day a new slot starts on — the one the partner is looking at. */
    initialDay: String? = null,
    /** Every sport this venue's courts host — the only sensible options to offer. */
    venueSports: List<String>,
    /** The venue's courts — each can carry its own price at this time. */
    courts: List<CourtPricing>,
    /** The lowest court rate, to say what "no price" will charge. */
    courtRate: Int?,
    onDismiss: () -> Unit,
    /** Shown only when editing: deleting lives here, not as a red bin on every row. */
    onDelete: (() -> Unit)? = null,
    /** Returns null when saved, else the reason it wasn't. */
    onSave: suspend (day: String, time: String, price: Double, capacity: Int, isOpen: Boolean, sports: List<String>, courtPrices: Map<Long, Double>?) -> String?,
) {
    val hasCourts = courts.isNotEmpty()
    // One box per court; empty = that court's own rate (or the all-courts price).
    val courtPriceText = remember {
        mutableStateMapOf<Long, String>().apply {
            courts.forEach { c -> put(c.id, existing?.courtPrices?.get(c.id)?.let { if (it == it.toLong().toDouble()) it.toLong().toString() else it.toString() } ?: "") }
        }
    }
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val days = listOf("Every day", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday")
    // Picked, never typed: "Mon-Fri" used to be saved as every day, weekends included.
    var day by remember { mutableStateOf(existing?.day?.takeIf { it in days } ?: initialDay?.takeIf { it in days } ?: "Every day") }
    // A start time off a clock, never free text: "06:00 AM - 07:00 AM" couldn't be sold online.
    var minutes by remember { mutableStateOf(existing?.time?.let { slotStartMinutes(it) }?.takeIf { it != Int.MAX_VALUE }) }
    var price by remember { mutableStateOf(existing?.price?.takeIf { it > 0 }?.let { if (it == it.toLong().toDouble()) it.toLong().toString() else it.toString() } ?: "") }
    var capacity by remember { mutableStateOf((existing?.capacity ?: 1).toString()) }
    var isOpen by remember { mutableStateOf(existing?.isOpen ?: true) }
    // Nothing selected means "every sport", which is what a slot has always meant.
    val picked = remember { mutableStateListOf<String>().apply { addAll(existing?.sports.orEmpty()) } }
    var saving by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    fun label(m: Int) = "%d:%02d %s".format(if (m / 60 % 12 == 0) 12 else m / 60 % 12, m % 60, if (m < 720) "AM" else "PM")

    AlertDialog(
        onDismissRequest = { if (!saving) onDismiss() },
        title = { Text(if (existing == null) "Add slot" else "Edit slot", fontWeight = FontWeight.Bold) },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState())) {
                Text("Day", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = AuthMuted)
                Spacer(Modifier.height(6.dp))
                Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), verticalAlignment = Alignment.CenterVertically) {
                    days.forEachIndexed { i, d ->
                        if (i > 0) Spacer(Modifier.width(6.dp))
                        SportChip(if (d == "Every day") d else d.take(3), day == d) { day = d; error = null }
                    }
                }
                Text(
                    if (day == "Every day") "Runs on every date. A weekday slot at the same time replaces it on that day."
                    else "Only on ${day}s.",
                    fontSize = 11.5.sp, color = AuthMuted, modifier = Modifier.padding(top = 6.dp),
                )
                Spacer(Modifier.height(14.dp))
                Text("Starts at", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = AuthMuted)
                Spacer(Modifier.height(6.dp))
                OutlinedButton(
                    onClick = {
                        val m = minutes ?: (6 * 60)
                        android.app.TimePickerDialog(context, { _, h, min -> minutes = h * 60 + min; error = null }, m / 60, m % 60, false).show()
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                ) {
                    Icon(Icons.Filled.Schedule, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(8.dp))
                    Text(minutes?.let { label(it) } ?: "Pick a time", fontWeight = FontWeight.Bold)
                }
                Spacer(Modifier.height(12.dp))
                OutlinedTextField(
                    price, { price = it.filter { c -> c.isDigit() }; error = null },
                    label = { Text(if (courts.size > 1) "Price for all courts (₹)" else "Price (₹)") },
                    placeholder = { Text("Court rate") },
                    supportingText = {
                        Text(
                            if (hasCourts) "Empty = each court's own rate" + (courtRate?.let { " (from ₹$it)" } ?: "") + ". A price here is charged on every court at this time."
                            else "Empty = the venue's rate.",
                        )
                    },
                    singleLine = true, modifier = Modifier.fillMaxWidth(),
                )
                if (courts.size > 1) {
                    Spacer(Modifier.height(4.dp))
                    Text("Price per court", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = AuthMuted)
                    Text("A court's own price here beats the all-courts price.", fontSize = 11.5.sp, color = AuthMuted)
                    courts.forEach { c ->
                        Spacer(Modifier.height(6.dp))
                        OutlinedTextField(
                            courtPriceText[c.id].orEmpty(), { v -> courtPriceText[c.id] = v.filter { ch -> ch.isDigit() }; error = null },
                            label = { Text(c.name + " (₹)") },
                            placeholder = { Text(if (c.price > 0) "₹${c.price} court rate" else "Court rate") },
                            singleLine = true, modifier = Modifier.fillMaxWidth(),
                        )
                    }
                }
                if (!hasCourts) {
                    Spacer(Modifier.height(4.dp))
                    OutlinedTextField(
                        capacity, { capacity = it.filter { c -> c.isDigit() } },
                        label = { Text("Bookings at a time") }, singleLine = true, modifier = Modifier.fillMaxWidth(),
                    )
                }
                // Only worth asking on a venue that actually mixes sports.
                if (venueSports.size > 1) {
                    Spacer(Modifier.height(12.dp))
                    Text("Runs for", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = AuthMuted)
                    Spacer(Modifier.height(6.dp))
                    Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), verticalAlignment = Alignment.CenterVertically) {
                        SportChip("All sports", picked.isEmpty()) { picked.clear() }
                        venueSports.forEach { s ->
                            Spacer(Modifier.width(8.dp))
                            SportChip(s, s in picked) { if (s in picked) picked.remove(s) else picked.add(s) }
                        }
                    }
                }
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Open for booking", Modifier.weight(1f))
                    Switch(checked = isOpen, onCheckedChange = { isOpen = it })
                }
                error?.let {
                    Spacer(Modifier.height(6.dp))
                    Text(it, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = RED)
                }
            }
        },
        confirmButton = {
            TextButton(
                onClick = {
                    val m = minutes ?: return@TextButton
                    saving = true
                    scope.launch {
                        error = onSave(
                            day, label(m), price.toDoubleOrNull() ?: 0.0, capacity.toIntOrNull()?.coerceAtLeast(1) ?: 1, isOpen, picked.toList(),
                            if (courts.size > 1) courts.associate { c -> c.id to (courtPriceText[c.id]?.toDoubleOrNull() ?: 0.0) } else null,
                        )
                        saving = false
                    }
                },
                enabled = minutes != null && !saving,
            ) { Text(if (saving) "Saving…" else "Save") }
        },
        dismissButton = {
            Row {
                if (onDelete != null) {
                    TextButton(onClick = onDelete, enabled = !saving) { Text("Delete", color = RED, fontWeight = FontWeight.Bold) }
                }
                TextButton(onClick = onDismiss, enabled = !saving) { Text("Cancel") }
            }
        },
    )
}

// ---- Reports ------------------------------------------------------------

private fun pickDate(context: Context, current: Long, onPicked: (Long) -> Unit) {
    val c = java.util.Calendar.getInstance().apply { timeInMillis = current }
    android.app.DatePickerDialog(
        context,
        { _, y, m, d ->
            val nc = java.util.Calendar.getInstance()
            nc.set(y, m, d, 12, 0, 0)
            onPicked(nc.timeInMillis)
        },
        c.get(java.util.Calendar.YEAR),
        c.get(java.util.Calendar.MONTH),
        c.get(java.util.Calendar.DAY_OF_MONTH),
    ).show()
}

/** Notifications — the bell inbox broadcast from the Haraan team. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun NotificationsScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<NotificationsPage>>(UiState.Loading, reload) {
        value = runCatchingUi { api.notifications(token) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Notifications", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = {
                    val s = state
                    if (s is UiState.Data && s.value.unread > 0) {
                        TextButton(onClick = {
                            scope.launch { runCatching { api.markNotificationsRead(token) }; reload++ }
                        }) { Text("Mark all read", color = AuthAccent, fontWeight = FontWeight.SemiBold, fontSize = 13.sp) }
                    }
                },
            )
        },
    ) { padding ->
        LayoutBox(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Loaded(state) { p ->
                if (p.items.isEmpty()) {
                    EmptyState("No notifications yet.")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 14.dp, bottom = 28.dp),
                    ) {
                        items(p.items) { n ->
                            LayoutBox(Modifier.fillMaxWidth().premiumSurface(16.dp)) {
                                Row(Modifier.padding(15.dp)) {
                                    // Unread gets a dot; read rows stay quiet.
                                    LayoutBox(
                                        Modifier.padding(top = 5.dp).size(8.dp).clip(RoundedCornerShape(99.dp))
                                            .background(if (n.read) Color.Transparent else AuthAccent),
                                    )
                                    Spacer(Modifier.width(11.dp))
                                    Column(Modifier.weight(1f)) {
                                        Text(
                                            n.title,
                                            fontSize = 14.5.sp,
                                            fontWeight = if (n.read) FontWeight.SemiBold else FontWeight.Bold,
                                            color = AuthInk,
                                        )
                                        if (!n.body.isNullOrBlank()) {
                                            Spacer(Modifier.height(3.dp))
                                            Text(n.body, fontSize = 12.5.sp, color = AuthMuted, lineHeight = 17.sp)
                                        }
                                        n.createdAt?.let {
                                            Spacer(Modifier.height(6.dp))
                                            Text(it.take(10), fontSize = 11.sp, color = AuthMuted)
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

/** Support — the partner↔Haraan conversation, same thread the web panel shows. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SupportScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var draft by remember { mutableStateOf("") }
    var sending by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    val state by produceState<UiState<List<SupportMessage>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.supportThread(token) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Support", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().background(AuthPageBg).padding(padding).imePadding()) {
            LayoutBox(Modifier.weight(1f)) {
                Loaded(state) { msgs ->
                    if (msgs.isEmpty()) {
                        EmptyState("Ask us anything — we usually reply within a few hours.")
                    } else {
                        LazyColumn(
                            Modifier.fillMaxSize().padding(horizontal = 16.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp),
                            contentPadding = androidx.compose.foundation.layout.PaddingValues(vertical = 14.dp),
                        ) {
                            items(msgs) { m ->
                                Row(
                                    Modifier.fillMaxWidth(),
                                    horizontalArrangement = if (m.fromAdmin) Arrangement.Start else Arrangement.End,
                                ) {
                                    Column(
                                        Modifier.widthIn(max = 290.dp)
                                            .clip(RoundedCornerShape(16.dp))
                                            .background(if (m.fromAdmin) Color.White else AuthAccent)
                                            .padding(horizontal = 14.dp, vertical = 10.dp),
                                    ) {
                                        Text(
                                            m.body,
                                            fontSize = 13.5.sp, lineHeight = 19.sp,
                                            color = if (m.fromAdmin) AuthInk else Color.White,
                                        )
                                        m.createdAt?.let {
                                            Spacer(Modifier.height(4.dp))
                                            Text(
                                                it.substring(11, 16.coerceAtMost(it.length)),
                                                fontSize = 10.sp,
                                                color = if (m.fromAdmin) AuthMuted else Color(0xB3FFFFFF),
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            Row(
                Modifier.fillMaxWidth().background(Color.White).padding(12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                OutlinedTextField(
                    value = draft,
                    onValueChange = { draft = it },
                    placeholder = { Text("Type a message") },
                    shape = RoundedCornerShape(22.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = AuthAccent, cursorColor = AuthAccent,
                        unfocusedBorderColor = Color(0x1F0F172A),
                    ),
                    maxLines = 4,
                    modifier = Modifier.weight(1f),
                )
                Spacer(Modifier.width(9.dp))
                LayoutBox(
                    Modifier.size(46.dp).clip(RoundedCornerShape(99.dp))
                        .background(if (draft.isNotBlank() && !sending) AuthAccent else Color(0xFFCBD5E1))
                        .clickable(enabled = draft.isNotBlank() && !sending) {
                            val body = draft.trim()
                            draft = ""
                            sending = true
                            view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                            scope.launch {
                                runCatching { api.sendSupportMessage(token, body) }
                                sending = false
                                reload++
                            }
                        },
                    contentAlignment = Alignment.Center,
                ) { Text("→", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Color.White) }
            }
        }
    }
}

/**
 * Academy — coaching batches, their roster, and daily attendance.
 * The desk's morning job: open today's batch, tick who turned up.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AcademyScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var creating by remember { mutableStateOf(false) }
    var openBatch by remember { mutableStateOf<BatchRow?>(null) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<List<BatchRow>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.academy(token) }
    }

    openBatch?.let { b ->
        BatchRosterScreen(api, token, b, onBack = { openBatch = null; reload++ })
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Academy", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = { HeaderIcon(Icons.Filled.Add, "New batch") { creating = true }; Spacer(Modifier.width(4.dp)) },
            )
        },
    ) { padding ->
        LayoutBox(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Loaded(state) { batches ->
                if (batches.isEmpty()) {
                    EmptyState("No coaching batches yet. Tap + to add one.")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 14.dp, bottom = 28.dp),
                    ) {
                        items(batches) { b -> BatchCard(b) { openBatch = b } }
                    }
                }
            }
        }
    }

    if (creating) {
        NewBatchDialog(
            onDismiss = { creating = false },
            onSave = { name, coach, days, start, end, fee, cap ->
                creating = false
                scope.launch { runCatching { api.saveBatch(token, name, coach, days, start, end, fee, cap) }; reload++ }
            },
        )
    }
}

@Composable
private fun BatchCard(b: BatchRow, onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Column(Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(b.name, fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                        if (b.runsToday) {
                            Spacer(Modifier.width(8.dp))
                            Text(
                                "TODAY",
                                fontSize = 9.sp, fontWeight = FontWeight.Bold, color = GREEN, letterSpacing = 0.8.sp,
                                modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x1A16A34A))
                                    .padding(horizontal = 6.dp, vertical = 2.dp),
                            )
                        }
                    }
                    Spacer(Modifier.height(3.dp))
                    Text(
                        listOfNotNull(
                            b.coach,
                            b.days.takeIf { it.isNotEmpty() }?.joinToString(", "),
                            listOfNotNull(b.startTime, b.endTime).takeIf { it.size == 2 }?.joinToString("–"),
                        ).joinToString(" · ").ifBlank { "No schedule set" },
                        fontSize = 12.sp, color = AuthMuted, maxLines = 2,
                    )
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text("₹${b.monthlyFee}", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Text("per month", fontSize = 10.5.sp, color = AuthMuted)
                }
            }
            Spacer(Modifier.height(12.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x142F6BFF)).padding(horizontal = 9.dp, vertical = 4.dp),
                ) {
                    Icon(Icons.Filled.People, contentDescription = null, tint = AuthAccentDeep, modifier = Modifier.size(12.dp))
                    Spacer(Modifier.width(5.dp))
                    Text(
                        "${b.students}" + (b.capacity?.let { "/$it" } ?: "") + " students",
                        fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = AuthAccentDeep,
                    )
                }
                if (b.overdue > 0) {
                    Spacer(Modifier.width(8.dp))
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x14DC2626)).padding(horizontal = 9.dp, vertical = 4.dp),
                    ) {
                        LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(RED))
                        Spacer(Modifier.width(6.dp))
                        Text("${b.overdue} fees due", fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = RED)
                    }
                }
                Spacer(Modifier.weight(1f))
                Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
            }
        }
    }
}

/** Today's roster — tick who turned up. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun BatchRosterScreen(api: PartnerApi, token: String, batch: BatchRow, onBack: () -> Unit) {
    var dayMillis by remember { mutableStateOf(todayMillis()) }
    var reload by remember { mutableStateOf(0) }
    var enrolling by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    val date = apiDate(dayMillis)
    val state by produceState<UiState<RosterPage>>(UiState.Loading, dayMillis, reload) {
        value = runCatchingUi { api.batchRoster(token, batch.id, date) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text(batch.name, maxLines = 1, fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = { HeaderIcon(Icons.Filled.Add, "Enrol student") { enrolling = true }; Spacer(Modifier.width(4.dp)) },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp).premiumSurface(14.dp).padding(4.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = { dayMillis -= DAY_MS }) { Icon(Icons.Filled.ChevronLeft, contentDescription = "Previous day", tint = AuthAccent) }
                Text(prettyDate(dayMillis), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                IconButton(onClick = { dayMillis += DAY_MS }) { Icon(Icons.Filled.ChevronRight, contentDescription = "Next day", tint = AuthAccent) }
            }
            Loaded(state) { r ->
                if (!r.runsToday) {
                    Text(
                        "This batch doesn't run on this day.",
                        fontSize = 12.5.sp, color = AuthMuted,
                        modifier = Modifier.padding(horizontal = 18.dp, vertical = 2.dp),
                    )
                }
                if (r.students.isEmpty()) {
                    EmptyState("No students enrolled yet. Tap + to add one.")
                } else {
                    val present = r.students.count { it.present }
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 8.dp, bottom = 28.dp),
                    ) {
                        item {
                            Text(
                                "$present of ${r.students.size} present",
                                fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthMuted,
                                modifier = Modifier.padding(bottom = 2.dp),
                            )
                        }
                        items(r.students) { s ->
                            StudentCard(s) { nowPresent ->
                                view.performHapticFeedback(
                                    if (nowPresent) HapticFeedbackConstants.CONFIRM else HapticFeedbackConstants.KEYBOARD_TAP,
                                )
                                scope.launch {
                                    runCatching { api.markAttendance(token, s.id, date, nowPresent) }
                                    reload++
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (enrolling) {
        EnrollStudentDialog(
            fee = batch.monthlyFee,
            onDismiss = { enrolling = false },
            onEnroll = { name, phone, months ->
                enrolling = false
                scope.launch { runCatching { api.enrollStudent(token, batch.id, name, phone, months) }; reload++ }
            },
        )
    }
}

@Composable
private fun StudentCard(s: StudentRow, onToggle: (Boolean) -> Unit) {
    LayoutBox(Modifier.fillMaxWidth().premiumSurface(16.dp)) {
        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(40.dp).clip(RoundedCornerShape(99.dp))
                    .background(if (s.present) Color(0x1A16A34A) else Color(0x0F0F172A)),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    s.name.take(1).uppercase(),
                    fontSize = 15.sp, fontWeight = FontWeight.Bold,
                    color = if (s.present) GREEN else AuthMuted,
                )
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(s.name, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                    if (s.overdue) {
                        Spacer(Modifier.width(7.dp))
                        Text(
                            "FEE DUE",
                            fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = RED, letterSpacing = 0.8.sp,
                            modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x14DC2626))
                                .padding(horizontal = 6.dp, vertical = 2.dp),
                        )
                    }
                }
                Spacer(Modifier.height(2.dp))
                Text(
                    "${s.attended} classes" + (s.paidUntil?.let { " · paid to $it" } ?: ""),
                    fontSize = 11.5.sp, color = AuthMuted, maxLines = 1,
                )
            }
            // The tick is the whole job — big target, obvious state.
            LayoutBox(
                Modifier.size(38.dp).clip(RoundedCornerShape(12.dp))
                    .background(if (s.present) GREEN else Color(0xFFF1F5F9))
                    .clickable { onToggle(!s.present) },
                contentAlignment = Alignment.Center,
            ) {
                Text("✓", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = if (s.present) Color.White else Color(0xFFB6C0D0))
            }
        }
    }
}

@Composable
private fun NewBatchDialog(
    onDismiss: () -> Unit,
    onSave: (String, String?, List<String>, String?, String?, Int, Int?) -> Unit,
) {
    var name by remember { mutableStateOf("") }
    var coach by remember { mutableStateOf("") }
    var days by remember { mutableStateOf(setOf<String>()) }
    var start by remember { mutableStateOf("06:00") }
    var end by remember { mutableStateOf("07:00") }
    var fee by remember { mutableStateOf("") }
    var cap by remember { mutableStateOf("") }
    val view = LocalView.current
    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    val valid = name.isNotBlank() && (fee.toIntOrNull() ?: -1) >= 0

    Dialog(onDismissRequest = onDismiss) {
        Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).verticalScroll(rememberScrollState()).padding(22.dp)) {
            Text("New batch", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("A recurring coaching class.", fontSize = 12.sp, color = AuthMuted)
            Spacer(Modifier.height(16.dp))
            OutlinedTextField(name, { name = it }, label = { Text("Batch name") }, placeholder = { Text("Junior Badminton") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(coach, { coach = it }, label = { Text("Coach") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(16.dp))
            Text("DAYS", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp)
            Spacer(Modifier.height(9.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(5.dp)) {
                WEEK_DAYS.forEach { d ->
                    val on = d in days
                    LayoutBox(
                        Modifier.weight(1f).height(38.dp).clip(RoundedCornerShape(10.dp))
                            .background(if (on) AuthAccent else Color(0xFFF1F5F9))
                            .clickable { days = if (on) days - d else days + d; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) },
                        contentAlignment = Alignment.Center,
                    ) { Text(d.take(1), fontSize = 12.sp, fontWeight = FontWeight.Bold, color = if (on) Color.White else AuthMuted) }
                }
            }
            Spacer(Modifier.height(14.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(start, { start = it.take(5) }, label = { Text("From") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.weight(1f))
                OutlinedTextField(end, { end = it.take(5) }, label = { Text("To") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.weight(1f))
            }
            Spacer(Modifier.height(10.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(fee, { fee = it.filter { c -> c.isDigit() }.take(7) }, label = { Text("Fee ₹/month") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.weight(1f))
                OutlinedTextField(cap, { cap = it.filter { c -> c.isDigit() }.take(3) }, label = { Text("Capacity") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.weight(1f))
            }
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Create batch", enabled = valid, loading = false) {
                onSave(name.trim(), coach.trim().ifBlank { null }, days.toList(), start.ifBlank { null }, end.ifBlank { null }, fee.toInt(), cap.toIntOrNull())
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) { Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold) }
        }
    }
}

@Composable
private fun EnrollStudentDialog(fee: Int, onDismiss: () -> Unit, onEnroll: (String, String, Int) -> Unit) {
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var months by remember { mutableStateOf(1) }
    val view = LocalView.current
    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    Dialog(onDismissRequest = onDismiss) {
        Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).padding(22.dp)) {
            Text("Enrol student", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("Already enrolled? This extends their fees instead of adding them twice.", fontSize = 12.sp, color = AuthMuted, lineHeight = 16.sp)
            Spacer(Modifier.height(16.dp))
            OutlinedTextField(name, { name = it }, label = { Text("Student name") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(phone, { phone = it.filter { c -> c.isDigit() }.take(10) }, label = { Text("Phone") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(16.dp))
            Text("MONTHS PAID", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp)
            Spacer(Modifier.height(9.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf(1, 3, 6, 12).forEach { m ->
                    val on = months == m
                    LayoutBox(
                        Modifier.weight(1f).height(40.dp).clip(RoundedCornerShape(10.dp))
                            .background(if (on) Color(0x142F6BFF) else Color(0xFFF8FAFC))
                            .border(if (on) 1.5.dp else 1.dp, if (on) AuthAccent else Color(0x1F0F172A), RoundedCornerShape(10.dp))
                            .clickable { months = m; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) },
                        contentAlignment = Alignment.Center,
                    ) { Text("$m", fontSize = 13.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.Medium, color = if (on) AuthAccentDeep else AuthInk) }
                }
            }
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Enrol · ₹" + formatInr((fee.toLong() * months).toDouble()), enabled = name.isNotBlank() && phone.length == 10, loading = false) {
                onEnroll(name.trim(), phone, months)
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) { Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold) }
        }
    }
}

/**
 * Packages — the memberships a venue sells, and who's currently on one.
 * Selling here is what makes "Use a session" appear on the walk-in sheet.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PackagesScreen(api: PartnerApi, token: String, venueId: Long? = null, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var creating by remember { mutableStateOf(false) }
    var selling by remember { mutableStateOf<VenuePackageRow?>(null) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<PackagesPage>>(UiState.Loading, reload, venueId) {
        value = runCatchingUi { api.packages(token, venueId) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Packages", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = { HeaderIcon(Icons.Filled.Add, "New package") { creating = true }; Spacer(Modifier.width(4.dp)) },
            )
        },
    ) { padding ->
        LayoutBox(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Loaded(state) { p ->
                LazyColumn(
                    Modifier.fillMaxSize().padding(horizontal = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 14.dp, bottom = 28.dp),
                ) {
                    item { SectionLabel("WHAT YOU SELL") }
                    if (p.packages.isEmpty()) {
                        item {
                            LayoutBox(Modifier.fillMaxWidth().premiumSurface().padding(20.dp)) {
                                Text(
                                    "No packages yet. Tap + to create one — e.g. 10 sessions for ₹4,000.",
                                    fontSize = 13.sp, color = AuthMuted, lineHeight = 18.sp,
                                )
                            }
                        }
                    } else {
                        items(p.packages) { pkg -> PackageCard(pkg) { selling = pkg } }
                    }
                    item { SectionLabel("ON A PASS (${p.holders.size})") }
                    if (p.holders.isEmpty()) {
                        item {
                            LayoutBox(Modifier.fillMaxWidth().premiumSurface().padding(20.dp)) {
                                Text("Nobody is on a package yet.", fontSize = 13.sp, color = AuthMuted)
                            }
                        }
                    } else {
                        items(p.holders) { h -> HolderCard(h) }
                    }
                }
            }
        }
    }

    if (creating) {
        NewPackageDialog(
            onDismiss = { creating = false },
            onSave = { name, price, sessions, days ->
                creating = false
                scope.launch { runCatching { api.savePackage(token, name, price, sessions, days) }; reload++ }
            },
        )
    }
    selling?.let { pkg ->
        SellPackageDialog(
            pkg = pkg,
            onDismiss = { selling = null },
            onSell = { phone, name ->
                selling = null
                scope.launch { runCatching { api.sellPackage(token, pkg.id, phone, name, venueId = venueId) }; reload++ }
            },
        )
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(text, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp, modifier = Modifier.padding(top = 4.dp))
}

@Composable
private fun PackageCard(p: VenuePackageRow, onSell: () -> Unit) {
    PressableSurface(onClick = onSell) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(p.name, fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                Spacer(Modifier.height(3.dp))
                Text(
                    "${p.sessions} sessions · ₹${p.perSession}/session" +
                        (p.validityDays?.let { " · ${it}d validity" } ?: " · no expiry"),
                    fontSize = 12.sp, color = AuthMuted,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                Text("₹" + formatInr(p.price.toDouble()), fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                Spacer(Modifier.height(4.dp))
                Text(
                    "SELL",
                    fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep, letterSpacing = 0.8.sp,
                    modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x142F6BFF)).padding(horizontal = 9.dp, vertical = 4.dp),
                )
            }
        }
    }
}

@Composable
private fun HolderCard(h: PackageHolder) {
    val frac = if (h.total > 0) h.remaining.toFloat() / h.total else 0f
    LayoutBox(Modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
        Column {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(h.name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                    Spacer(Modifier.height(1.dp))
                    Text("${h.packageName} · +91 ${h.phone}", fontSize = 12.sp, color = AuthMuted, maxLines = 1)
                }
                Text("${h.remaining}/${h.total}", fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = if (h.expired) RED else GREEN)
            }
            Spacer(Modifier.height(10.dp))
            LayoutBox(Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFFEDF0F5))) {
                LayoutBox(Modifier.fillMaxWidth(frac).fillMaxHeight().clip(RoundedCornerShape(99.dp)).background(if (h.expired) RED else GREEN))
            }
            if (h.expiresAt != null) {
                Spacer(Modifier.height(8.dp))
                Text(
                    if (h.expired) "Expired ${h.expiresAt}" else "Valid until ${h.expiresAt}",
                    fontSize = 11.5.sp, color = if (h.expired) RED else AuthMuted,
                )
            }
        }
    }
}

@Composable
private fun NewPackageDialog(onDismiss: () -> Unit, onSave: (String, Int, Int, Int?) -> Unit) {
    var name by remember { mutableStateOf("") }
    var price by remember { mutableStateOf("") }
    var sessions by remember { mutableStateOf("") }
    var days by remember { mutableStateOf("") }
    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    val valid = name.isNotBlank() && (price.toIntOrNull() ?: 0) > 0 && (sessions.toIntOrNull() ?: 0) > 0

    Dialog(onDismissRequest = onDismiss) {
        Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).verticalScroll(rememberScrollState()).padding(22.dp)) {
            Text("New package", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("A prepaid bundle of sessions customers can buy.", fontSize = 12.sp, color = AuthMuted)
            Spacer(Modifier.height(16.dp))
            OutlinedTextField(name, { name = it }, label = { Text("Name") }, placeholder = { Text("10 Session Pass") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(price, { price = it.filter { c -> c.isDigit() }.take(7) }, label = { Text("Price ₹") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.weight(1f))
                OutlinedTextField(sessions, { sessions = it.filter { c -> c.isDigit() }.take(3) }, label = { Text("Sessions") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.weight(1f))
            }
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(days, { days = it.filter { c -> c.isDigit() }.take(4) }, label = { Text("Validity in days (blank = never expires)") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth())
            if (valid) {
                Spacer(Modifier.height(10.dp))
                Text(
                    "That's ₹${(price.toInt() / sessions.toInt())} per session.",
                    fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthAccentDeep,
                )
            }
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Create package", enabled = valid, loading = false) {
                onSave(name.trim(), price.toInt(), sessions.toInt(), days.toIntOrNull())
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) { Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold) }
        }
    }
}

@Composable
private fun SellPackageDialog(pkg: VenuePackageRow, onDismiss: () -> Unit, onSell: (String, String) -> Unit) {
    var phone by remember { mutableStateOf("") }
    var name by remember { mutableStateOf("") }
    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    Dialog(onDismissRequest = onDismiss) {
        Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).padding(22.dp)) {
            Text("Sell ${pkg.name}", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("${pkg.sessions} sessions · ₹${formatInr(pkg.price.toDouble())}", fontSize = 12.5.sp, color = AuthMuted)
            Spacer(Modifier.height(16.dp))
            OutlinedTextField(name, { name = it }, label = { Text("Customer name") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(phone, { phone = it.filter { c -> c.isDigit() }.take(10) }, label = { Text("Phone") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            Text(
                "The pass is tied to this number — it appears automatically when they next book.",
                fontSize = 11.5.sp, color = AuthMuted, lineHeight = 16.sp,
            )
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Sell for ₹" + formatInr(pkg.price.toDouble()), enabled = phone.length == 10 && name.isNotBlank(), loading = false) {
                onSell(phone, name.trim())
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) { Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold) }
        }
    }
}

/**
 * Customers — who books here, how often, and what they're worth. Identity is the
 * customer's phone, so a person who books online and later walks in is one row.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CustomersScreen(api: PartnerApi, token: String, venueId: Long? = null, onBack: () -> Unit) {
    var query by remember { mutableStateOf("") }
    var contacting by remember { mutableStateOf<CustomerRow?>(null) }
    val state by produceState<UiState<CustomersPage>>(UiState.Loading, query, venueId) {
        // Debounce so typing doesn't fire a request per keystroke.
        if (query.isNotBlank()) kotlinx.coroutines.delay(300)
        value = runCatchingUi { api.customers(token, query, venueId) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Customers", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            OutlinedTextField(
                value = query,
                onValueChange = { query = it },
                placeholder = { Text("Search name or phone") },
                singleLine = true,
                shape = RoundedCornerShape(14.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = AuthAccent, cursorColor = AuthAccent,
                    unfocusedBorderColor = Color(0x1F0F172A),
                    focusedContainerColor = Color.White, unfocusedContainerColor = Color.White,
                ),
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
            )
            Loaded(state) { p ->
                if (p.data.isEmpty()) {
                    EmptyState(if (query.isBlank()) "No customers yet." else "No customer matches \"$query\".")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 24.dp),
                    ) {
                        item { CustomerSummaryStrip(p) }
                        items(p.data) { c -> CustomerCard(c) { contacting = c } }
                    }
                }
            }
        }
    }

    contacting?.let { c -> ContactCustomerDialog(c) { contacting = null } }
}

@Composable
private fun CustomerSummaryStrip(p: CustomersPage) {
    Row(
        Modifier.fillMaxWidth().premiumSurface().padding(vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        listOf(
            "Customers" to p.total.toString(),
            "Repeat" to p.repeat.toString(),
            "No phone" to p.anonymous.toString(),
        ).forEachIndexed { i, (label, value) ->
            Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                Text(value, fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                Spacer(Modifier.height(1.dp))
                Text(label, fontSize = 11.5.sp, color = AuthMuted)
            }
            if (i < 2) LayoutBox(Modifier.width(1.dp).height(34.dp).background(Hairline))
        }
    }
}

@Composable
private fun CustomerCard(c: CustomerRow, onClick: () -> Unit) {
    PressableSurface(onClick = onClick, radius = 16.dp) {
        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(42.dp).clip(RoundedCornerShape(99.dp))
                    .background(if (c.isRepeat) Color(0x1A16A34A) else Color(0x142F6BFF)),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    c.name.take(1).uppercase(),
                    fontSize = 16.sp, fontWeight = FontWeight.Bold,
                    color = if (c.isRepeat) GREEN else AuthAccentDeep,
                )
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(c.name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                    if (c.isRepeat) {
                        Spacer(Modifier.width(7.dp))
                        Text(
                            "REGULAR",
                            fontSize = 9.sp, fontWeight = FontWeight.Bold, color = GREEN, letterSpacing = 0.8.sp,
                            modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x1A16A34A))
                                .padding(horizontal = 6.dp, vertical = 2.dp),
                        )
                    }
                }
                Spacer(Modifier.height(2.dp))
                Text(
                    "${c.bookings} booking${if (c.bookings == 1) "" else "s"}" +
                        (c.lastVisit?.let { " · last $it" } ?: ""),
                    fontSize = 12.sp, color = AuthMuted, maxLines = 1,
                )
            }
            Text("₹" + formatInr(c.spent), fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
        }
    }
}

/** Reach a customer straight from the list — the point of having their number. */
@Composable
private fun ContactCustomerDialog(c: CustomerRow, onDismiss: () -> Unit) {
    val context = LocalContext.current
    Dialog(onDismissRequest = onDismiss) {
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).padding(22.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            LayoutBox(
                Modifier.size(56.dp).clip(RoundedCornerShape(99.dp)).background(Color(0x142F6BFF)),
                contentAlignment = Alignment.Center,
            ) { Text(c.name.take(1).uppercase(), fontSize = 22.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep) }
            Spacer(Modifier.height(12.dp))
            Text(c.name, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("+91 ${c.phone}", fontSize = 13.sp, color = AuthMuted)
            Spacer(Modifier.height(14.dp))
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Color(0xFFF6F8FC))
                    .border(1.dp, CardBorder, RoundedCornerShape(14.dp)).padding(14.dp),
                horizontalArrangement = Arrangement.SpaceAround,
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("${c.bookings}", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Text("bookings", fontSize = 11.sp, color = AuthMuted)
                }
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("₹" + formatInr(c.spent), fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Text("spent", fontSize = 11.sp, color = AuthMuted)
                }
            }
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Message on WhatsApp", enabled = true, loading = false) {
                runCatching {
                    context.startActivity(
                        Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/91${c.phone}"))
                            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
                    )
                }
                onDismiss()
            }
            Spacer(Modifier.height(10.dp))
            SocialButton(
                text = "Call",
                leading = { Icon(Icons.Filled.Phone, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(19.dp)) },
            ) {
                runCatching {
                    context.startActivity(
                        Intent(Intent.ACTION_DIAL, Uri.parse("tel:+91${c.phone}"))
                            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
                    )
                }
                onDismiss()
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) {
                Text("Close", color = AuthMuted, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

/**
 * Payouts — the settlement home: what the venue is owed, where it's sent, and
 * what's already landed. Balance figures come from the same PartnerSettlement
 * service the web page and /control settle against, so the app can never show a
 * different "available" than the console.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PayoutsScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var editing by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<PayoutsPage>>(UiState.Loading, reload) {
        value = runCatchingUi { api.payouts(token) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Payouts", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
            )
        },
    ) { padding ->
        LayoutBox(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Loaded(state) { p ->
                LazyColumn(
                    Modifier.fillMaxSize().padding(horizontal = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp),
                    contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 14.dp, bottom = 28.dp),
                ) {
                    item { PayoutBalanceHero(p) }
                    item { PayoutAccountCard(p.account) { editing = true } }
                    item {
                        Text(
                            "SETTLEMENT HISTORY",
                            fontSize = 10.sp, fontWeight = FontWeight.Bold,
                            color = AuthMuted, letterSpacing = 1.4.sp,
                            modifier = Modifier.padding(top = 4.dp),
                        )
                    }
                    if (p.batches.isEmpty()) {
                        item {
                            LayoutBox(Modifier.fillMaxWidth().premiumSurface().padding(20.dp)) {
                                Text(
                                    "No settlements yet. Money you collect shows as available until it's transferred.",
                                    fontSize = 13.sp, color = AuthMuted, lineHeight = 18.sp,
                                )
                            }
                        }
                    } else {
                        items(p.batches) { b -> PayoutBatchCard(b) }
                    }
                }
            }
        }
    }

    if (editing) {
        PayoutAccountDialog(
            onDismiss = { editing = false },
            onSave = { method, holder, bank, acct, ifsc, vpa ->
                editing = false
                scope.launch {
                    runCatching { api.savePayoutAccount(token, method, holder, bank, acct, ifsc, vpa) }
                    reload++
                }
            },
        )
    }
}

@Composable
private fun PayoutBalanceHero(p: PayoutsPage) {
    LayoutBox(
        Modifier.fillMaxWidth()
            .shadow(18.dp, RoundedCornerShape(22.dp), clip = false, spotColor = AuthInkTop)
            .clip(RoundedCornerShape(22.dp))
            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid, AuthInkBot))),
    ) {
        LayoutBox(
            Modifier.matchParentSize().background(
                Brush.radialGradient(listOf(Color(0x553B82F6), Color(0x00000000)), center = Offset(120f, 40f), radius = 520f)
            )
        )
        Column(Modifier.fillMaxWidth().padding(20.dp)) {
            Text("AVAILABLE TO SETTLE", color = Color(0xB3CFE0FF), fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.5.sp)
            Spacer(Modifier.height(8.dp))
            Text("₹" + formatInr(p.available), color = Color.White, fontSize = 34.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-1).sp)
            if (p.inFlight > 0) {
                Spacer(Modifier.height(8.dp))
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x33F59E0B)).padding(horizontal = 10.dp, vertical = 5.dp),
                ) {
                    LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFFFCD34D)))
                    Spacer(Modifier.width(7.dp))
                    Text("₹" + formatInr(p.inFlight) + " being transferred", fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFFFCD34D))
                }
            }
            Spacer(Modifier.height(18.dp))
            Row(Modifier.fillMaxWidth()) {
                PayoutStat(Modifier.weight(1f), "Collected", p.collected)
                LayoutBox(Modifier.width(1.dp).height(34.dp).background(Color(0x33FFFFFF)))
                PayoutStat(Modifier.weight(1f), "Settled", p.settled)
            }
        }
    }
}

@Composable
private fun PayoutStat(modifier: Modifier, label: String, value: Double) {
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Text("₹" + formatInr(value), color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(2.dp))
        Text(label, color = Color(0x99CFE0FF), fontSize = 11.sp)
    }
}

@Composable
private fun PayoutAccountCard(account: PayoutAccount?, onEdit: () -> Unit) {
    PressableSurface(onClick = onEdit) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(44.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFEAF1FF), Color(0xFFDCE8FF)))),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.Payments, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(22.dp)) }
            Spacer(Modifier.width(13.dp))
            Column(Modifier.weight(1f)) {
                Text(
                    if (account == null) "Add settlement account" else "Money is sent to",
                    fontSize = 12.sp, color = AuthMuted,
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    account?.masked ?: "No account yet — tap to add",
                    fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1,
                )
                if (account != null) {
                    Spacer(Modifier.height(6.dp))
                    val tone = if (account.verified) GREEN else Color(0xFFB45309)
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(tone.copy(alpha = 0.12f)).padding(horizontal = 9.dp, vertical = 4.dp),
                    ) {
                        LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(tone))
                        Spacer(Modifier.width(6.dp))
                        Text(
                            if (account.verified) "Verified" else "Pending verification",
                            fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = tone,
                        )
                    }
                }
            }
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
        }
    }
}

@Composable
private fun PayoutBatchCard(b: PayoutBatchRow) {
    val tone = if (b.isPaid) GREEN else Color(0xFFB45309)
    LayoutBox(Modifier.fillMaxWidth().premiumSurface()) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text("₹" + formatInr(b.amount), fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                Spacer(Modifier.height(3.dp))
                Text(
                    listOfNotNull(b.date, b.period).joinToString(" · ").ifBlank { "—" },
                    fontSize = 12.sp, color = AuthMuted,
                )
                if (!b.reference.isNullOrBlank()) {
                    Spacer(Modifier.height(3.dp))
                    Text("Ref ${b.reference}", fontSize = 11.sp, color = AuthMuted)
                }
            }
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(tone.copy(alpha = 0.12f)).padding(horizontal = 10.dp, vertical = 6.dp),
            ) {
                LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(tone))
                Spacer(Modifier.width(6.dp))
                Text(b.status.replaceFirstChar { it.uppercase() }, fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = tone)
            }
        }
    }
}

/** Enter where settlements are sent. Never prefilled — changing the destination
 *  means re-entering it, and saving clears verification. */
@Composable
private fun PayoutAccountDialog(
    onDismiss: () -> Unit,
    onSave: (method: String, holder: String, bank: String?, acct: String?, ifsc: String?, vpa: String?) -> Unit,
) {
    var method by remember { mutableStateOf("bank") }
    var holder by remember { mutableStateOf("") }
    var bank by remember { mutableStateOf("") }
    var acct by remember { mutableStateOf("") }
    var ifsc by remember { mutableStateOf("") }
    var vpa by remember { mutableStateOf("") }
    val view = LocalView.current

    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    val valid = holder.isNotBlank() && if (method == "bank") acct.length >= 6 && ifsc.length >= 6 else vpa.length >= 3

    Dialog(onDismissRequest = onDismiss) {
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White)
                .verticalScroll(rememberScrollState()).padding(22.dp),
        ) {
            Text("Settlement account", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("Where your collected money is transferred.", fontSize = 12.sp, color = AuthMuted)

            Spacer(Modifier.height(16.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("bank" to "Bank account", "upi" to "UPI").forEach { (key, label) ->
                    val on = method == key
                    LayoutBox(
                        Modifier.weight(1f).clip(RoundedCornerShape(12.dp))
                            .background(if (on) Color(0x142F6BFF) else Color(0xFFF8FAFC))
                            .border(if (on) 1.5.dp else 1.dp, if (on) AuthAccent else Color(0x1F0F172A), RoundedCornerShape(12.dp))
                            .clickable { method = key; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }
                            .padding(vertical = 11.dp),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(label, fontSize = 13.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.Medium, color = if (on) AuthAccentDeep else AuthInk)
                    }
                }
            }

            Spacer(Modifier.height(14.dp))
            OutlinedTextField(
                value = holder, onValueChange = { holder = it },
                label = { Text("Account holder name") }, singleLine = true,
                shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
            )

            if (method == "bank") {
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = bank, onValueChange = { bank = it },
                    label = { Text("Bank name") }, singleLine = true,
                    shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = acct, onValueChange = { acct = it.filter { c -> c.isDigit() }.take(18) },
                    label = { Text("Account number") }, singleLine = true,
                    shape = RoundedCornerShape(12.dp), colors = colors,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = ifsc, onValueChange = { ifsc = it.uppercase().take(11) },
                    label = { Text("IFSC code") }, singleLine = true,
                    shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                )
            } else {
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = vpa, onValueChange = { vpa = it },
                    label = { Text("UPI ID") }, placeholder = { Text("name@bank") }, singleLine = true,
                    shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                )
            }

            Spacer(Modifier.height(12.dp))
            Text(
                "Saving sends this for re-verification before the next settlement.",
                fontSize = 11.5.sp, color = AuthMuted, lineHeight = 16.sp,
            )

            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Save account", enabled = valid, loading = false) {
                view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                onSave(method, holder.trim(), bank.trim().ifBlank { null }, acct.trim().ifBlank { null }, ifsc.trim().ifBlank { null }, vpa.trim().ifBlank { null })
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) {
                Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

private const val NO_VENUE_NOTE =
    "This account has no venue yet, or it couldn't be loaded. Refresh Home, or ask Haraan to assign your venue."

/**
 * A venue tool that needs the venue's courts: loads them, and says what's wrong in
 * plain words - no venue, the load failed (with Retry), or no courts yet - instead
 * of opening the tool on invented courts or on another business's venue id.
 */
@Composable
private fun WithVenueCourts(
    api: PartnerApi,
    token: String,
    venueId: Long?,
    title: String,
    onBack: () -> Unit,
    content: @Composable (venueId: Long, courts: List<Pair<Long, String>>) -> Unit,
) {
    if (venueId == null) {
        VenueToolNotice(title, "$title needs a venue", NO_VENUE_NOTE, onBack = onBack)
        return
    }
    var attempt by remember { mutableStateOf(0) }
    val courts by produceState<UiState<List<Pair<Long, String>>>>(UiState.Loading, venueId, attempt) {
        value = runCatchingUi { api.venueCourts(token, venueId).map { it.id to it.name } }
    }
    when (val c = courts) {
        is UiState.Loading -> VenueToolNotice(title, null, null, loading = true, onBack = onBack)
        is UiState.Error -> VenueToolNotice(title, "Couldn't load your courts", c.message, onBack = onBack, onRetry = { attempt++ })
        is UiState.Data -> if (c.value.isEmpty()) {
            VenueToolNotice(title, "Add a court first", "$title works per court. Add your courts in Pricing & slots, then come back.", onBack = onBack)
        } else {
            content(venueId, c.value)
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VenueToolNotice(
    title: String,
    heading: String?,
    body: String?,
    loading: Boolean = false,
    onBack: () -> Unit,
    onRetry: (() -> Unit)? = null,
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(title) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back") } },
            )
        },
    ) { padding ->
        Column(
            Modifier.fillMaxSize().padding(padding).padding(32.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            if (loading) {
                CircularProgressIndicator(strokeWidth = 2.5.dp, modifier = Modifier.size(28.dp))
            } else {
                if (heading != null) Text(heading, fontSize = 17.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
                if (body != null) {
                    Spacer(Modifier.height(6.dp))
                    Text(body, fontSize = 13.5.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, textAlign = TextAlign.Center)
                }
                if (onRetry != null) {
                    Spacer(Modifier.height(16.dp))
                    Button(onClick = onRetry) { Text("Try again") }
                }
            }
        }
    }
}

// ---- Staff (desk persons) ----------------------------------------------

private fun permLabel(p: String): String = when (p) {
    "bookings" -> "Bookings & walk-ins"
    "checkin" -> "Ticket / slot check-in"
    "pricing" -> "Pricing & slots"
    "reports" -> "Reports"
    else -> p
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun StaffScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var adding by remember { mutableStateOf(false) }
    var editing by remember { mutableStateOf<StaffMember?>(null) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<List<StaffMember>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.staff(token) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Desk staff") },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back") } },
                actions = { IconButton(onClick = { adding = true }) { Icon(Icons.Filled.Add, contentDescription = "Add") } },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            Loaded(state) { list ->
                if (list.isEmpty()) {
                    EmptyState("No desk staff yet. Tap + to add a front-desk login.")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                    ) {
                        items(list) { m ->
                            Card(
                                Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = Color.White),
                                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                            ) {
                                Column(Modifier.padding(16.dp)) {
                                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                        Column(Modifier.weight(1f)) {
                                            Text(m.name, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                                            Text(m.email, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                        }
                                        IconButton(onClick = { editing = m }) { Icon(Icons.Filled.Edit, contentDescription = "Edit") }
                                        IconButton(onClick = { scope.launch { runCatching { api.deleteStaff(token, m.id) }; reload++ } }) {
                                            Icon(Icons.Filled.Delete, contentDescription = "Delete", tint = RED)
                                        }
                                    }
                                    Spacer(Modifier.height(6.dp))
                                    Text(
                                        if (m.permissions.isEmpty()) "No permissions" else m.permissions.joinToString(" · ") { permLabel(it) },
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.primary,
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (adding) {
        StaffDialog(
            existing = null,
            onDismiss = { adding = false },
            onSaveNew = { name, email, pass, perms ->
                adding = false
                scope.launch { runCatching { api.createStaff(token, name, email, pass, perms) }; reload++ }
            },
            onSavePerms = {},
        )
    }
    editing?.let { m ->
        StaffDialog(
            existing = m,
            onDismiss = { editing = null },
            onSaveNew = { _, _, _, _ -> },
            onSavePerms = { perms ->
                editing = null
                scope.launch { runCatching { api.updateStaff(token, m.id, perms) }; reload++ }
            },
        )
    }
}

@Composable
private fun StaffDialog(
    existing: StaffMember?,
    onDismiss: () -> Unit,
    onSaveNew: (name: String, email: String, password: String, perms: List<String>) -> Unit,
    onSavePerms: (perms: List<String>) -> Unit,
) {
    var name by remember { mutableStateOf(existing?.name ?: "") }
    var email by remember { mutableStateOf(existing?.email ?: "") }
    var password by remember { mutableStateOf("") }
    val perms = remember { mutableStateListOf<String>().apply { addAll(existing?.permissions ?: listOf("bookings", "checkin")) } }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(if (existing == null) "Add desk person" else "Edit permissions") },
        text = {
            Column {
                if (existing == null) {
                    OutlinedTextField(name, { name = it }, label = { Text("Name") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(email, { email = it }, label = { Text("Email") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(password, { password = it }, label = { Text("Password (min 6)") }, singleLine = true, visualTransformation = PasswordVisualTransformation(), modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(12.dp))
                }
                Text("Permissions", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                STAFF_PERMISSIONS.forEach { p ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Checkbox(checked = p in perms, onCheckedChange = { if (it) perms.add(p) else perms.remove(p) })
                        Text(permLabel(p))
                    }
                }
            }
        },
        confirmButton = {
            TextButton(
                onClick = {
                    if (existing == null) onSaveNew(name.trim(), email.trim(), password, perms.toList())
                    else onSavePerms(perms.toList())
                },
                enabled = existing != null || (name.isNotBlank() && email.isNotBlank() && password.length >= 6),
            ) { Text("Save") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

// ---- Analytics detail ---------------------------------------------------

private enum class AnalyticsKind { Event, Venue }
private data class AnalyticsTarget(val kind: AnalyticsKind, val id: Long, val name: String)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AnalyticsScreen(api: PartnerApi, token: String, target: AnalyticsTarget, onBack: () -> Unit) {
    val state by produceState<UiState<Analytics>>(UiState.Loading, target.id) {
        value = runCatchingUi {
            when (target.kind) {
                AnalyticsKind.Event -> api.eventAnalytics(token, target.id)
                AnalyticsKind.Venue -> api.venueAnalytics(token, target.id)
            }
        }
    }
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(target.name, maxLines = 1) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            Loaded(state) { a ->
                LazyColumn(
                    Modifier.fillMaxSize().padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp),
                ) {
                    item { StatGrid(a.stats) }
                    item { TrendCard(a) }
                    if (a.tiers.isNotEmpty()) item { TiersCard(a.tiers) }
                }
            }
        }
    }
}

@Composable
private fun StatGrid(stats: List<StatItem>) {
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        stats.chunked(2).forEach { row ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                row.forEach { s ->
                    Card(
                        Modifier.weight(1f),
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(containerColor = Color.White),
                        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                    ) {
                        Column(Modifier.padding(14.dp)) {
                            Text(
                                s.label,
                                style = MaterialTheme.typography.labelMedium,
                                color = MaterialTheme.colorScheme.primary,
                            )
                            Text(s.value, style = MaterialTheme.typography.titleLarge)
                        }
                    }
                }
                if (row.size == 1) Spacer(Modifier.weight(1f))
            }
        }
    }
}

@Composable
private fun TrendCard(a: Analytics) {
    Card(
        Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Text("Revenue — last 14 days", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(12.dp))
            BarChart(
                values = a.sales.map { it.revenue },
                modifier = Modifier.fillMaxWidth().height(140.dp),
            )
            Spacer(Modifier.height(8.dp))
            val totalSecondary = a.sales.sumOf { it.secondary }
            val totalRevenue = a.sales.sumOf { it.revenue }
            Text(
                "₹${formatInr(totalRevenue)} · $totalSecondary ${a.secondaryLabel.lowercase()} in this window",
                style = MaterialTheme.typography.bodyMedium,
            )
        }
    }
}

@Composable
private fun BarChart(values: List<Double>, modifier: Modifier = Modifier) {
    val max = (values.maxOrNull() ?: 0.0).coerceAtLeast(1.0)
    val barColor = MaterialTheme.colorScheme.primary
    Canvas(modifier) {
        if (values.isEmpty()) return@Canvas
        val gap = 6f
        val barWidth = ((size.width - gap * (values.size - 1)) / values.size).coerceAtLeast(1f)
        values.forEachIndexed { i, v ->
            val h = (v / max * size.height).toFloat().coerceAtLeast(if (v > 0) 3f else 0f)
            val x = i * (barWidth + gap)
            drawRect(
                color = barColor,
                topLeft = Offset(x, size.height - h),
                size = androidx.compose.ui.geometry.Size(barWidth, h),
            )
        }
    }
}

@Composable
private fun TiersCard(tiers: List<TierRow>) {
    Card(
        Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Text("Revenue by ticket tier", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(8.dp))
            tiers.forEach { t ->
                Row(Modifier.fillMaxWidth().padding(vertical = 6.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                    Column(Modifier.weight(1f)) {
                        Text(t.name, style = MaterialTheme.typography.bodyLarge)
                        Text("${t.tickets} tickets · ${t.orders} orders", style = MaterialTheme.typography.bodySmall)
                    }
                    Column(horizontalAlignment = Alignment.End) {
                        Text("₹" + formatInr(t.revenue), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.primary)
                        Text("${t.pct}%", style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }
    }
}

@Composable
private fun SalesTab(api: PartnerApi, token: String, venueId: Long? = null) {
    RefreshableContent(token to venueId, load = { api.bookings(token, venueId) }) { list ->
        if (list.isEmpty()) {
            EmptyState("No bookings yet")
        } else {
            // Grouped by day, because a flat feed of near-identical venue names
            // can't answer the only question the owner opens this for: what came
            // in today. Order is preserved — the API already sorts newest first.
            val groups = remember(list) { list.groupBy { it.slotDate ?: "" }.toList() }
            // Same rule the shift board on Home applies, so the two screens can
            // never quote different debts: zero-rupee rows are data artefacts,
            // not money, and what is owed is the balance, not the whole ticket.
            val owed = remember(list) {
                list.filter { it.paymentStatus.lowercase() != "paid" && !it.isCancelled && it.amount > 0 }
            }
            val outstanding = remember(owed) {
                owed.sumOf { (it.amount - it.amountPaid).coerceAtLeast(0.0) }
            }

            LazyColumn(
                Modifier.fillMaxSize().padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 14.dp, bottom = 26.dp),
            ) {
                // Money still to collect leads — it's the only actionable total here.
                if (owed.isNotEmpty()) {
                    item {
                        Row(
                            Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp))
                                .background(Color(0x14DC2626))
                                .border(1.dp, Color(0x33DC2626), RoundedCornerShape(14.dp))
                                .padding(horizontal = 14.dp, vertical = 11.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            LayoutBox(Modifier.size(7.dp).clip(RoundedCornerShape(99.dp)).background(RED))
                            Spacer(Modifier.width(9.dp))
                            Text(
                                "${owed.size} unpaid · ₹" + formatInr(outstanding) + " to collect",
                                fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = RED,
                            )
                        }
                    }
                }
                groups.forEach { (date, rows) ->
                    item(key = "h-$date") { BookingDayHeader(date, rows.sumOf { it.amount }) }
                    items(rows, key = { it.id }) { b ->
                        BookingRow(b, showBranch = venueId == null)
                    }
                }
            }
        }
    }
}

/** True for a booking whose money no longer counts. */
private val BookingSummary.isCancelled: Boolean
    get() = status?.lowercase()?.let { it == "cancelled" || it == "refunded" || it == "failed" } == true

/** "Today · ₹2,400" — a day's takings, so the list has a spine. */
@Composable
private fun BookingDayHeader(date: String, total: Double) {
    val label = remember(date) { friendlyDay(date) }
    Row(
        Modifier.fillMaxWidth().padding(top = 6.dp, bottom = 2.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(label, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.sp)
        Spacer(Modifier.width(9.dp))
        LayoutBox(Modifier.weight(1f).height(1.dp).background(Hairline))
        Spacer(Modifier.width(9.dp))
        Text("₹" + formatInr(total), fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = AuthInk)
    }
}

/** TODAY / YESTERDAY / "SAT, 15 AUG" — relative where it helps, absolute otherwise. */
private fun friendlyDay(date: String): String {
    if (date.isBlank()) return "NO DATE"
    val parsed = runCatching {
        java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).parse(date)
    }.getOrNull() ?: return date.uppercase()
    val day = 86_400_000L
    val today = todayMillis()
    val diff = ((today - (parsed.time + 12 * 3600_000L)) / day)
    return when (diff) {
        0L -> "TODAY"
        1L -> "YESTERDAY"
        -1L -> "TOMORROW"
        else -> java.text.SimpleDateFormat("EEE, dd MMM", java.util.Locale.getDefault())
            .format(parsed).uppercase()
    }
}

@Composable
private fun BookingRow(b: BookingSummary, showBranch: Boolean) {
    val walkIn = b.channel.lowercase() == "offline"
    // A zero-rupee booking has nothing to collect, so flagging it UNPAID puts a
    // debt on screen that nobody can ever settle.
    val unpaid = b.paymentStatus.lowercase() != "paid" && !b.isCancelled && b.amount > 0
    LayoutBox(Modifier.fillMaxWidth().premiumSurface(16.dp)) {
        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            // Initial, tinted by channel — walk-in vs online is the first thing
            // the desk needs to tell apart.
            LayoutBox(
                Modifier.size(38.dp).clip(RoundedCornerShape(99.dp))
                    .background(if (walkIn) Color(0x1A0EA5E9) else Color(0x142F6BFF)),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    b.customer.trim().take(1).uppercase(),
                    fontSize = 15.sp, fontWeight = FontWeight.Bold,
                    color = if (walkIn) Color(0xFF0369A1) else AuthAccentDeep,
                )
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        b.customer,
                        fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = AuthInk,
                        maxLines = 1, overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f, fill = false),
                    )
                    if (b.isCancelled) {
                        Spacer(Modifier.width(7.dp))
                        Text(
                            b.status!!.uppercase(),
                            fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = RED, letterSpacing = 0.6.sp,
                            modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x14DC2626))
                                .padding(horizontal = 6.dp, vertical = 2.dp),
                        )
                    } else if (b.checkedIn > 0) {
                        Spacer(Modifier.width(7.dp))
                        Text(
                            "✓ IN",
                            fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = GREEN, letterSpacing = 0.6.sp,
                            modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x1A16A34A))
                                .padding(horizontal = 6.dp, vertical = 2.dp),
                        )
                    }
                }
                Spacer(Modifier.height(2.dp))
                Text(
                    listOfNotNull(
                        if (walkIn) "Walk-in" else "Online",
                        b.slotLabel?.takeIf { it.isNotBlank() } ?: b.label,
                        if (showBranch) b.branch else null,
                    ).joinToString(" · "),
                    fontSize = 11.5.sp, color = AuthMuted, maxLines = 1, overflow = TextOverflow.Ellipsis,
                )
            }
            Spacer(Modifier.width(10.dp))
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    "₹" + formatInr(b.amount),
                    fontSize = 15.sp, fontWeight = FontWeight.ExtraBold,
                    color = if (b.isCancelled) AuthMuted else AuthInk,
                )
                // How it was taken, not just whether — the desk wants to see CASH
                // vs UPI vs ONLINE at a glance. UNPAID is reserved for money that
                // genuinely never arrived.
                if (!b.isCancelled) {
                    val method = b.paymentMethod?.uppercase()
                    val (label, tone) = when {
                        unpaid -> "UNPAID" to RED
                        method != null -> method to GREEN
                        b.amount <= 0 -> "PASS" to Color(0xFF6D28D9)
                        else -> "PAID" to GREEN
                    }
                    Spacer(Modifier.height(4.dp))
                    Text(
                        label,
                        fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = tone, letterSpacing = 0.6.sp,
                        modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(tone.copy(alpha = 0.10f))
                            .padding(horizontal = 6.dp, vertical = 2.dp),
                    )
                }
            }
        }
    }
}

@Composable
private fun ScanTab(api: PartnerApi, token: String, bottomInset: Dp = 0.dp) {
    // The whole tab is the viewfinder. See ScanScreen.kt for why the poster that
    // used to live here — icon, paragraph, "Scan ticket QR" button — was the
    // wrong shape for somebody standing at a gate.
    ScanScreen(api = api, token = token, accent = AuthAccent, bottomInset = bottomInset)
}

@Composable
private fun <T> Loaded(state: UiState<T>, content: @Composable (T) -> Unit) {
    when (state) {
        // A skeleton of the shape that's coming, not a spinner in the void: the
        // screen keeps its silhouette while the data lands, so switching sections
        // reads as one continuous surface instead of a blank flash.
        is UiState.Loading -> SkeletonList()
        is UiState.Error -> EmptyState(state.message)
        is UiState.Data -> ScreenEnter { content(state.value) }
    }
}

/**
 * The shared loading placeholder: card silhouettes with a light sweep moving
 * across them. Sized like the real rows so nothing jumps when data arrives.
 */
@Composable
private fun SkeletonList(rows: Int = 5) {
    val t = rememberInfiniteTransition(label = "shimmer")
    // Sweeps left→right forever; the gradient is wider than the card so the
    // highlight travels through rather than pulsing in place.
    val x by t.animateFloat(
        initialValue = -700f,
        targetValue = 1400f,
        animationSpec = infiniteRepeatable(tween(1250, easing = LinearEasing)),
        label = "sweep",
    )
    val sweep = Brush.linearGradient(
        colors = listOf(Color(0xFFEDF1F7), Color(0xFFF7FAFE), Color(0xFFEDF1F7)),
        start = Offset(x, 0f),
        end = Offset(x + 700f, 0f),
    )

    Column(
        Modifier.fillMaxSize().background(AuthPageBg).padding(horizontal = 16.dp, vertical = 14.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        repeat(rows) { i ->
            Row(
                Modifier.fillMaxWidth().premiumSurface(16.dp).padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                LayoutBox(Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(sweep))
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    // Vary the widths so it reads as content, not a test pattern.
                    LayoutBox(
                        Modifier.fillMaxWidth(if (i % 2 == 0) 0.55f else 0.42f)
                            .height(13.dp).clip(RoundedCornerShape(99.dp)).background(sweep),
                    )
                    Spacer(Modifier.height(8.dp))
                    LayoutBox(
                        Modifier.fillMaxWidth(if (i % 2 == 0) 0.34f else 0.48f)
                            .height(10.dp).clip(RoundedCornerShape(99.dp)).background(sweep),
                    )
                }
                Spacer(Modifier.width(10.dp))
                LayoutBox(Modifier.width(52.dp).height(15.dp).clip(RoundedCornerShape(99.dp)).background(sweep))
            }
        }
    }
}

/**
 * The entrance every screen's content gets: a short fade with a small rise.
 * Keyed on nothing, so it plays once per composition — which is exactly when
 * the user has just switched section and needs to see that something changed.
 */
@Composable
private fun ScreenEnter(content: @Composable () -> Unit) {
    val anim = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        anim.animateTo(1f, tween(durationMillis = 260, easing = FastOutSlowInEasing))
    }
    LayoutBox(
        Modifier.graphicsLayer {
            alpha = anim.value
            translationY = (1f - anim.value) * 26f
        },
    ) { content() }
}

/**
 * Fills the pane and centres one thing in it — the loading/empty pedestal.
 *
 * Deliberately NOT named `Box`: this file aliases the real layout Box to
 * `LayoutBox`, so a helper called `Box` silently wins name resolution at every
 * bare `Box { }` call site and swaps a wrap-content container for a
 * `fillMaxSize()` one. That is exactly how the branch chip once stretched the
 * whole top bar to full height and squeezed the home screen to nothing.
 */
@Composable
private fun CenteredPane(content: @Composable () -> Unit) {
    Column(
        Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) { content() }
}

@Composable
private fun EmptyState(message: String) {
    Column(
        Modifier.fillMaxSize().background(AuthPageBg).padding(32.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        LayoutBox(
            Modifier.size(64.dp).clip(RoundedCornerShape(20.dp))
                .background(Brush.linearGradient(listOf(Color(0xFFEAF1FF), Color(0xFFDCE8FF)))),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.Filled.Description, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(28.dp)) }
        Spacer(Modifier.height(16.dp))
        Text(message, textAlign = TextAlign.Center, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = AuthInk)
        Spacer(Modifier.height(4.dp))
        Text("Pull down to refresh.", textAlign = TextAlign.Center, fontSize = 12.5.sp, color = AuthMuted)
    }
}

@Composable
private fun ListCard(title: String, subtitle: String, trailing: String, footer: String, onClick: (() -> Unit)? = null) {
    val inner: @Composable () -> Unit = {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.Top) {
                Text(title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1, modifier = Modifier.weight(1f, false))
                Spacer(Modifier.width(10.dp))
                Text(trailing, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            }
            Spacer(Modifier.height(3.dp))
            Text(subtitle, fontSize = 12.5.sp, color = AuthMuted)
            if (footer.isNotBlank()) {
                Spacer(Modifier.height(8.dp))
                Text(footer, fontSize = 11.5.sp, fontWeight = FontWeight.Medium, color = AuthAccentDeep)
            }
        }
    }
    if (onClick != null) PressableSurface(onClick = onClick) { inner() }
    else LayoutBox(Modifier.fillMaxWidth().premiumSurface()) { inner() }
}

private suspend fun <T> runCatchingUi(block: suspend () -> T): UiState<T> = try {
    UiState.Data(block())
} catch (e: ApiException) {
    UiState.Error(e.message ?: "Error")
} catch (e: Exception) {
    UiState.Error(e.message ?: "Something went wrong")
}

