package com.haraan.partner

import android.content.Context
import android.content.Intent
import androidx.core.content.FileProvider
import java.io.File
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.layout.Arrangement
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
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.ChevronLeft
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.CurrencyRupee
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Tune
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Today
import androidx.compose.material3.Button
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import kotlinx.coroutines.launch

private sealed interface UiState<out T> {
    data object Loading : UiState<Nothing>
    data class Error(val message: String) : UiState<Nothing>
    data class Data<T>(val value: T) : UiState<T>
}

@Composable
fun PartnerApp() {
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

// ---- Login --------------------------------------------------------------

@Composable
private fun LoginScreen(api: PartnerApi, session: Session, onSignedIn: () -> Unit) {
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    Column(
        modifier = Modifier.fillMaxSize().padding(24.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Image(
            painter = painterResource(R.drawable.haraan_logo),
            contentDescription = "Haraan",
            contentScale = ContentScale.Fit,
            modifier = Modifier.fillMaxWidth(0.62f).height(72.dp),
        )
        Spacer(Modifier.height(6.dp))
        Text(
            "Partner",
            style = MaterialTheme.typography.titleMedium,
            color = MaterialTheme.colorScheme.primary,
        )
        Spacer(Modifier.height(4.dp))
        Text(
            "Sign in to manage your events and venues",
            style = MaterialTheme.typography.bodyMedium,
            textAlign = TextAlign.Center,
        )
        Spacer(Modifier.height(24.dp))
        OutlinedTextField(
            value = email,
            onValueChange = { email = it; error = null },
            label = { Text("Email") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )
        Spacer(Modifier.height(12.dp))
        OutlinedTextField(
            value = password,
            onValueChange = { password = it; error = null },
            label = { Text("Password") },
            singleLine = true,
            visualTransformation = PasswordVisualTransformation(),
            modifier = Modifier.fillMaxWidth(),
        )
        if (error != null) {
            Spacer(Modifier.height(12.dp))
            Text(error!!, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium)
        }
        Spacer(Modifier.height(20.dp))
        Button(
            enabled = !loading && email.isNotBlank() && password.isNotBlank(),
            onClick = {
                loading = true
                error = null
                scope.launch {
                    try {
                        val result = api.login(email.trim(), password)
                        // Confirm this really is a partner account before entering.
                        api.overview(result.token)
                        session.token = result.token
                        session.name = result.name
                        session.partnerType = result.partnerType
                        session.isDesk = result.isDesk
                        session.permissionsCsv = result.permissions.joinToString(",")
                        onSignedIn()
                    } catch (e: ApiException) {
                        error = e.message
                    } catch (e: Exception) {
                        error = e.message ?: "Unable to sign in"
                    } finally {
                        loading = false
                    }
                }
            },
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text(if (loading) "Signing in…" else "Sign in")
        }
    }
}

// ---- Home scaffold + tabs ----------------------------------------------

private enum class Tab(val label: String) { Home("Home"), Events("Events"), Venues("Venues"), Sales("Sales"), Scan("Scan") }

/** Which lane the signed-in partner belongs to. Drives the whole shell. */
private enum class Lane { EVENT, VENUE, BOTH }

private fun laneOf(partnerType: String?): Lane = when (partnerType?.lowercase()) {
    "event", "host", "organiser", "organizer" -> Lane.EVENT
    "venue" -> Lane.VENUE
    else -> Lane.BOTH // legacy / no type / admin → combined
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HomeScaffold(api: PartnerApi, session: Session, onSignedOut: () -> Unit) {
    var detail by remember { mutableStateOf<AnalyticsTarget?>(null) }
    var manageVenue by remember { mutableStateOf<Pair<Long, String>?>(null) }
    var showReports by remember { mutableStateOf(false) }
    var showStaff by remember { mutableStateOf(false) }
    val token = session.token ?: return

    if (showReports) {
        ReportsScreen(api, token, onBack = { showReports = false })
        return
    }
    if (showStaff) {
        StaffScreen(api, token, onBack = { showStaff = false })
        return
    }

    // Build the visible tabs from the partner's lane so an event host never sees
    // venue tabs and vice-versa. Sales is shared but relabelled per lane. The lane
    // seeds from the cached type and is corrected once the server confirms it.
    var lane by remember { mutableStateOf(laneOf(session.partnerType)) }
    val tabs = remember(lane) {
        buildList {
            add(Tab.Home)
            if (lane != Lane.VENUE) add(Tab.Events)
            if (lane != Lane.EVENT) add(Tab.Venues)
            add(Tab.Sales)
            add(Tab.Scan)
        }
    }
    var tab by remember { mutableStateOf(Tab.Home) }
    // If the lane resolves and the current tab is no longer valid, fall back Home.
    LaunchedEffect(tabs) { if (tab !in tabs) tab = Tab.Home }

    detail?.let { target ->
        AnalyticsScreen(api, token, target, onBack = { detail = null })
        return
    }

    manageVenue?.let { (id, name) ->
        VenueDayScreen(
            api, token, id, name,
            onBack = { manageVenue = null },
            onAnalytics = { detail = AnalyticsTarget(AnalyticsKind.Venue, id, name) },
            canPricing = session.can("pricing"),
            canBookings = session.can("bookings"),
        )
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Image(
                        painter = painterResource(R.drawable.haraan_logo),
                        contentDescription = "Haraan",
                        contentScale = ContentScale.Fit,
                        modifier = Modifier.height(24.dp),
                    )
                },
                actions = {
                    if (!session.isDesk) {
                        IconButton(onClick = { showStaff = true }) { Icon(Icons.Filled.People, contentDescription = "Staff") }
                    }
                    if (session.can("reports")) {
                        IconButton(onClick = { showReports = true }) { Icon(Icons.Filled.Description, contentDescription = "Reports") }
                    }
                    TextButton(onClick = { session.clear(); onSignedOut() }) { Text("Sign out") }
                },
            )
        },
        bottomBar = {
            NavigationBar {
                tabs.forEach { t ->
                    val label = labelFor(t, lane)
                    NavigationBarItem(
                        selected = tab == t,
                        onClick = { tab = t },
                        icon = { Icon(iconFor(t), contentDescription = label) },
                        label = { Text(label) },
                    )
                }
            }
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            when (tab) {
                Tab.Home -> HomeTab(api, token, session.name ?: "Partner", lane) { serverType ->
                    if (serverType != null) {
                        session.partnerType = serverType
                        lane = laneOf(serverType)
                    }
                }
                Tab.Events -> EventsTab(api, token) { id, name ->
                    detail = AnalyticsTarget(AnalyticsKind.Event, id, name)
                }
                Tab.Venues -> VenuesTab(api, token) { id, name ->
                    manageVenue = id to name
                }
                Tab.Sales -> SalesTab(api, token)
                Tab.Scan -> ScanTab(api, token)
            }
        }
    }
}

private fun iconFor(tab: Tab) = when (tab) {
    Tab.Home -> Icons.Filled.Home
    Tab.Events -> Icons.Filled.CalendarMonth
    Tab.Venues -> Icons.Filled.Place
    Tab.Sales -> Icons.Filled.Payments
    Tab.Scan -> Icons.Filled.QrCodeScanner
}

/** Venue owners see "Bookings" where event hosts see "Sales". */
private fun labelFor(tab: Tab, lane: Lane): String =
    if (tab == Tab.Sales && lane == Lane.VENUE) "Bookings" else tab.label

/**
 * Loads data once, shows it, and lets the user swipe down to reload. Keeps the
 * current content on screen while a refresh spins, so it never flashes empty.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun <T> RefreshableContent(key: Any?, load: suspend () -> T, content: @Composable (T) -> Unit) {
    var data by remember(key) { mutableStateOf<UiState<T>>(UiState.Loading) }
    var refreshing by remember(key) { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    LaunchedEffect(key) { data = runCatchingUi { load() } }
    PullToRefreshBox(
        isRefreshing = refreshing,
        onRefresh = {
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
private fun HomeTab(api: PartnerApi, token: String, name: String, lane: Lane, onLane: (String?) -> Unit) {
    RefreshableContent(token, load = { api.overview(token) }) { o ->
        // Report the server's authoritative type up so the tab bar matches.
        LaunchedEffect(o.type) { onLane(o.type) }
        // If the server knows a more specific lane than the cached one, honour it.
        val effective = if (o.type != null) laneOf(o.type) else lane
        val subtitle = when (effective) {
            Lane.EVENT -> "Event organiser"
            Lane.VENUE -> "Venue owner"
            Lane.BOTH -> "Partner"
        }
        val tiles = when (effective) {
            Lane.EVENT -> listOf(
                Tile(Icons.Filled.ConfirmationNumber, "Events", o.eventsTotal.toString(), "${o.eventsUpcoming} upcoming"),
                Tile(Icons.Filled.ConfirmationNumber, "Tickets", o.ticketsSold.toString(), "sold all-time"),
                Tile(Icons.Filled.Today, "Today", o.bookingsToday.toString(), "bookings today"),
                Tile(Icons.Filled.Close, "Cancelled", o.cancelled.toString(), "all-time"),
            )
            Lane.VENUE -> listOf(
                Tile(Icons.Filled.Place, "Venues", o.venuesTotal.toString(), "turfs & spaces"),
                Tile(Icons.Filled.ConfirmationNumber, "Bookings", o.bookingsTotal.toString(), "all-time"),
                Tile(Icons.Filled.Today, "Today", o.bookingsToday.toString(), "bookings today"),
            )
            Lane.BOTH -> listOf(
                Tile(Icons.Filled.ConfirmationNumber, "Events", o.eventsTotal.toString(), "${o.eventsUpcoming} upcoming"),
                Tile(Icons.Filled.Place, "Venues", o.venuesTotal.toString(), "turfs & spaces"),
                Tile(Icons.Filled.Today, "Today", o.bookingsToday.toString(), "bookings today"),
                Tile(Icons.Filled.ConfirmationNumber, "Bookings", o.bookingsTotal.toString(), "all-time"),
            )
        }

        LazyColumn(
            Modifier.fillMaxSize().padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(vertical = 16.dp),
        ) {
            item { GreetingHeader(name, subtitle) }
            item { RevenueHero(o) }
            items(tiles.chunked(2)) { row ->
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                    row.forEach { t -> StatTile(Modifier.weight(1f), t.icon, t.label, t.value, t.hint) }
                    if (row.size == 1) Spacer(Modifier.weight(1f))
                }
            }
            if (effective != Lane.EVENT) {
                item { BookingsMixCard(o) }
            }
        }
    }
}

@Composable
private fun BookingsMixCard(o: Overview) {
    val online = o.online.coerceAtLeast(0)
    val offline = o.offline.coerceAtLeast(0)
    val total = (online + offline).coerceAtLeast(1)
    val onlineColor = MaterialTheme.colorScheme.primary
    val offlineColor = Color(0xFF0F766E)

    Card(
        Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Text("Bookings mix", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(12.dp))
            Row(
                Modifier.fillMaxWidth().height(14.dp).clip(RoundedCornerShape(7.dp)),
            ) {
                if (online > 0) LayoutBox(Modifier.weight(online.toFloat()).fillMaxHeight().background(onlineColor))
                if (offline > 0) LayoutBox(Modifier.weight(offline.toFloat()).fillMaxHeight().background(offlineColor))
                if (online == 0 && offline == 0) LayoutBox(Modifier.weight(1f).fillMaxHeight().background(Color(0xFFE5E7EB)))
            }
            Spacer(Modifier.height(12.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(20.dp)) {
                MixLegend(onlineColor, "Online", online)
                MixLegend(offlineColor, "Walk-in", offline)
            }
            if (o.cancelled > 0) {
                Spacer(Modifier.height(10.dp))
                Text(
                    "${o.cancelled} cancelled / refunded",
                    style = MaterialTheme.typography.bodySmall,
                    color = RED,
                )
            }
        }
    }
}

@Composable
private fun MixLegend(color: Color, label: String, count: Int) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        LayoutBox(Modifier.size(10.dp).clip(RoundedCornerShape(5.dp)).background(color))
        Spacer(Modifier.width(6.dp))
        Text("$label · $count", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
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
        Text(greeting(), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(name, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
        Text(subtitle, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
    }
}

@Composable
private fun RevenueHero(o: Overview) {
    val gradient = Brush.linearGradient(listOf(Color(0xFF1D4ED8), Color(0xFF2563EB), Color(0xFF0F766E)))
    Card(
        Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.Transparent),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
    ) {
        Column(Modifier.background(gradient).fillMaxWidth().padding(20.dp)) {
            Text("Total revenue", color = Color.White.copy(alpha = 0.85f), style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(6.dp))
            Text(
                "₹" + formatInr(o.revenue),
                color = Color.White,
                style = MaterialTheme.typography.displaySmall,
                fontWeight = FontWeight.Bold,
            )
            Spacer(Modifier.height(4.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.AutoMirrored.Filled.TrendingUp, contentDescription = null, tint = Color.White.copy(alpha = 0.9f), modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(6.dp))
                Text("${o.ticketsSold} tickets · ${o.bookingsToday} booked today", color = Color.White.copy(alpha = 0.9f), style = MaterialTheme.typography.bodyMedium)
            }
            if (o.trend.any { it > 0 }) {
                Spacer(Modifier.height(16.dp))
                Sparkline(o.trend, Modifier.fillMaxWidth().height(48.dp), Color.White)
                Text("last 14 days", color = Color.White.copy(alpha = 0.7f), style = MaterialTheme.typography.labelSmall)
            }
        }
    }
}

@Composable
private fun StatTile(modifier: Modifier, icon: ImageVector, label: String, value: String, hint: String) {
    Card(
        modifier,
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            LayoutBox(
                Modifier.size(38.dp).background(MaterialTheme.colorScheme.primaryContainer, RoundedCornerShape(12.dp)),
                contentAlignment = Alignment.Center,
            ) {
                Icon(icon, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(20.dp))
            }
            Spacer(Modifier.height(12.dp))
            Text(value, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Text(label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            Text(hint, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

@Composable
private fun Sparkline(values: List<Double>, modifier: Modifier, color: Color) {
    val max = (values.maxOrNull() ?: 0.0).coerceAtLeast(1.0)
    Canvas(modifier) {
        if (values.size < 2) return@Canvas
        val stepX = size.width / (values.size - 1)
        val pts = values.mapIndexed { i, v ->
            Offset(i * stepX, size.height - (v / max * size.height).toFloat())
        }
        for (i in 0 until pts.size - 1) {
            drawLine(color, pts[i], pts[i + 1], strokeWidth = 4f)
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

    Card(
        Modifier.fillMaxWidth().clickable { onClick() },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
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
            LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                items(list) { v -> VenueCard(v) { onOpen(v.id, v.name) } }
            }
    }
}

@Composable
private fun VenueCard(v: VenueSummary, onClick: () -> Unit) {
    Card(
        Modifier.fillMaxWidth().clickable { onClick() },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(44.dp).background(MaterialTheme.colorScheme.primaryContainer, RoundedCornerShape(12.dp)),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.Place, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(24.dp))
            }
            Spacer(Modifier.width(14.dp))
            Column(Modifier.weight(1f)) {
                Text(v.name, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, maxLines = 1)
                Text(v.location ?: "—", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Spacer(Modifier.height(6.dp))
                Text("${v.bookings} bookings", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
            }
            Text("₹" + formatInr(v.revenue), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
        }
    }
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
) {
    var dayMillis by remember { mutableStateOf(todayMillis()) }
    var reload by remember { mutableStateOf(0) }
    var addForSlot by remember { mutableStateOf<DaySlot?>(null) }
    var showPricing by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val date = apiDate(dayMillis)
    val state by produceState<UiState<DayGrid>>(UiState.Loading, dayMillis, reload) {
        value = runCatchingUi { api.venueDay(token, venueId, date) }
    }

    if (showPricing) {
        VenuePricingScreen(api, token, venueId, venueName, onBack = { showPricing = false; reload++ })
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(venueName, maxLines = 1) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back") }
                },
                actions = {
                    if (canPricing) {
                        IconButton(onClick = { showPricing = true }) { Icon(Icons.Filled.Tune, contentDescription = "Pricing & slots") }
                    }
                    IconButton(onClick = onAnalytics) { Icon(Icons.Filled.BarChart, contentDescription = "Analytics") }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 8.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = { dayMillis -= DAY_MS }) { Icon(Icons.Filled.ChevronLeft, contentDescription = "Previous day") }
                Text(prettyDate(dayMillis), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                IconButton(onClick = { dayMillis += DAY_MS }) { Icon(Icons.Filled.ChevronRight, contentDescription = "Next day") }
            }
            Loaded(state) { grid ->
                LazyColumn(
                    Modifier.fillMaxSize().padding(horizontal = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 24.dp),
                ) {
                    item {
                        if (grid.isBlocked) {
                            Card(
                                Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = Color.White),
                            ) {
                                Row(Modifier.padding(16.dp).fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                                    Text("Closed on this day", color = RED, style = MaterialTheme.typography.titleSmall)
                                    if (canBookings) {
                                        TextButton(onClick = { scope.launch { runCatching { api.setDateClosed(token, venueId, date, false) }; reload++ } }) { Text("Reopen") }
                                    }
                                }
                            }
                        } else if (canBookings) {
                            TextButton(onClick = { scope.launch { runCatching { api.setDateClosed(token, venueId, date, true) }; reload++ } }) {
                                Text("Close this day (maintenance/holiday)")
                            }
                        }
                    }
                    if (grid.slots.isEmpty()) {
                        item { Text("No slots configured for this venue.", Modifier.padding(16.dp), color = MaterialTheme.colorScheme.onSurfaceVariant) }
                    }
                    items(grid.slots) { slot ->
                        SlotCard(
                            slot = slot,
                            blocked = grid.isBlocked,
                            canBookings = canBookings,
                            onAdd = { addForSlot = slot },
                            onCancel = { bid -> scope.launch { runCatching { api.cancelBooking(token, bid) }; reload++ } },
                        )
                    }
                }
            }
        }
    }

    addForSlot?.let { slot ->
        WalkInDialog(
            slotLabel = slot.time ?: slot.label,
            onDismiss = { addForSlot = null },
            onConfirm = { name, phone ->
                addForSlot = null
                scope.launch { runCatching { api.createWalkIn(token, venueId, slot.slotId, date, name, phone) }; reload++ }
            },
        )
    }
}

@Composable
private fun SlotCard(slot: DaySlot, blocked: Boolean, canBookings: Boolean, onAdd: () -> Unit, onCancel: (Long) -> Unit) {
    val full = slot.available <= 0
    Card(
        Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column {
                    Text(slot.time ?: slot.label, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                    if (slot.price > 0) Text("₹" + formatInr(slot.price), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Text(
                    "${slot.booked}/${slot.capacity}",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = if (full) RED else GREEN,
                )
            }
            slot.bookings.forEach { b ->
                Spacer(Modifier.height(8.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(b.customer, style = MaterialTheme.typography.bodyMedium)
                        Text(
                            (if (b.channel == "offline") "Walk-in" else "Online") + (if (b.checkedIn > 0) " · checked in" else ""),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    if (canBookings) {
                        IconButton(onClick = { onCancel(b.id) }) { Icon(Icons.Filled.Close, contentDescription = "Cancel", tint = RED) }
                    }
                }
            }
            if (canBookings && !blocked && !full && slot.isOpen) {
                Spacer(Modifier.height(8.dp))
                TextButton(onClick = onAdd, modifier = Modifier.fillMaxWidth()) {
                    Icon(Icons.Filled.Add, contentDescription = null)
                    Text("  Add walk-in booking")
                }
            }
        }
    }
}

@Composable
private fun WalkInDialog(slotLabel: String, onDismiss: () -> Unit, onConfirm: (String, String) -> Unit) {
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Walk-in booking") },
        text = {
            Column {
                Text(slotLabel, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(name, { name = it }, label = { Text("Customer name") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(phone, { phone = it }, label = { Text("Phone (optional)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
            }
        },
        confirmButton = { TextButton(onClick = { onConfirm(name.trim(), phone.trim()) }, enabled = name.isNotBlank()) { Text("Book") } },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

// ---- Venue pricing / slot editor ---------------------------------------

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VenuePricingScreen(api: PartnerApi, token: String, venueId: Long, venueName: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var editing by remember { mutableStateOf<SlotEdit?>(null) }
    var adding by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<List<SlotEdit>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.venueSlots(token, venueId) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Pricing & slots", maxLines = 1) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back") }
                },
                actions = {
                    IconButton(onClick = { adding = true }) { Icon(Icons.Filled.Add, contentDescription = "Add slot") }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            Text(
                venueName,
                Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
            )
            Loaded(state) { slots ->
                if (slots.isEmpty()) {
                    EmptyState("No slots yet. Tap + to add your first bookable slot.")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 24.dp),
                    ) {
                        items(slots) { slot ->
                            Card(
                                Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = Color.White),
                                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                            ) {
                                Row(Modifier.padding(16.dp).fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                    Column(Modifier.weight(1f)) {
                                        Text(slot.time, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                                        Text(
                                            listOfNotNull(
                                                slot.day,
                                                "cap ${slot.capacity}",
                                                if (slot.isOpen) "open" else "closed",
                                            ).joinToString(" · "),
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                    Text(
                                        "₹" + formatInr(slot.price),
                                        style = MaterialTheme.typography.titleMedium,
                                        fontWeight = FontWeight.Bold,
                                        color = MaterialTheme.colorScheme.primary,
                                    )
                                    IconButton(onClick = { editing = slot }) { Icon(Icons.Filled.Edit, contentDescription = "Edit") }
                                    IconButton(onClick = { scope.launch { runCatching { api.deleteSlot(token, venueId, slot.id) }; reload++ } }) {
                                        Icon(Icons.Filled.Delete, contentDescription = "Delete", tint = RED)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (adding) {
        SlotEditDialog(
            existing = null,
            onDismiss = { adding = false },
            onSave = { day, time, price, capacity, isOpen ->
                adding = false
                scope.launch { runCatching { api.saveSlot(token, venueId, null, day, time, price, capacity, isOpen) }; reload++ }
            },
        )
    }
    editing?.let { slot ->
        SlotEditDialog(
            existing = slot,
            onDismiss = { editing = null },
            onSave = { day, time, price, capacity, isOpen ->
                editing = null
                scope.launch { runCatching { api.saveSlot(token, venueId, slot.id, day, time, price, capacity, isOpen) }; reload++ }
            },
        )
    }
}

@Composable
private fun SlotEditDialog(
    existing: SlotEdit?,
    onDismiss: () -> Unit,
    onSave: (day: String?, time: String, price: Double, capacity: Int, isOpen: Boolean) -> Unit,
) {
    var day by remember { mutableStateOf(existing?.day ?: "") }
    var time by remember { mutableStateOf(existing?.time ?: "") }
    var price by remember { mutableStateOf(existing?.price?.let { if (it == it.toLong().toDouble()) it.toLong().toString() else it.toString() } ?: "") }
    var capacity by remember { mutableStateOf((existing?.capacity ?: 1).toString()) }
    var isOpen by remember { mutableStateOf(existing?.isOpen ?: true) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(if (existing == null) "Add slot" else "Edit slot") },
        text = {
            Column {
                OutlinedTextField(day, { day = it }, label = { Text("Day (e.g. Mon-Fri, Sat-Sun)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(time, { time = it }, label = { Text("Time (e.g. 06:00 AM - 07:00 AM)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(price, { price = it.filter { c -> c.isDigit() } }, label = { Text("Price (₹)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(capacity, { capacity = it.filter { c -> c.isDigit() } }, label = { Text("Capacity (courts)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Open for booking", Modifier.weight(1f))
                    Switch(checked = isOpen, onCheckedChange = { isOpen = it })
                }
            }
        },
        confirmButton = {
            TextButton(
                onClick = { onSave(day.trim().ifBlank { null }, time.trim(), price.toDoubleOrNull() ?: 0.0, capacity.toIntOrNull() ?: 1, isOpen) },
                enabled = time.isNotBlank(),
            ) { Text("Save") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
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

private fun shareCsv(context: Context, from: String, to: String, csv: String) {
    val file = File(context.cacheDir, "bookings_${from}_to_$to.csv")
    file.writeText(csv)
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    val send = Intent(Intent.ACTION_SEND).apply {
        type = "text/csv"
        putExtra(Intent.EXTRA_STREAM, uri)
        putExtra(Intent.EXTRA_SUBJECT, "Booking report $from to $to")
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }
    context.startActivity(Intent.createChooser(send, "Share report").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ReportsScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    val context = LocalContext.current
    var fromMs by remember { mutableStateOf(todayMillis() - 30 * DAY_MS) }
    var toMs by remember { mutableStateOf(todayMillis()) }
    var busy by remember { mutableStateOf(false) }
    var msg by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Reports") },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back") } },
            )
        },
    ) { padding ->
        Column(
            Modifier.fillMaxSize().padding(padding).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Text("Booking report", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text(
                "Download a CSV of all bookings across your events and venues for a date range.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            DateRow("From", prettyDate(fromMs)) { pickDate(context, fromMs) { fromMs = it } }
            DateRow("To", prettyDate(toMs)) { pickDate(context, toMs) { toMs = it } }
            Button(
                enabled = !busy,
                onClick = {
                    busy = true
                    msg = null
                    scope.launch {
                        try {
                            val csv = api.reportCsv(token, apiDate(fromMs), apiDate(toMs))
                            shareCsv(context, apiDate(fromMs), apiDate(toMs), csv)
                            msg = "Report ready — pick where to save or share it."
                        } catch (e: Exception) {
                            msg = e.message ?: "Could not build report"
                        } finally {
                            busy = false
                        }
                    }
                },
                modifier = Modifier.fillMaxWidth(),
            ) { Text(if (busy) "Preparing…" else "Download / share CSV") }
            if (msg != null) {
                Text(msg!!, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.primary)
            }
        }
    }
}

@Composable
private fun DateRow(label: String, value: String, onClick: () -> Unit) {
    Card(
        Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text(label, style = MaterialTheme.typography.bodyLarge)
            TextButton(onClick = onClick) { Text(value) }
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
private fun SalesTab(api: PartnerApi, token: String) {
    RefreshableContent(token, load = { api.bookings(token) }) { list ->
        if (list.isEmpty()) EmptyState("No bookings yet") else
            LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                items(list) { b ->
                    ListCard(
                        title = b.label ?: (b.ticketCode ?: "Booking #${b.id}"),
                        subtitle = "${b.quantity} × · ${b.status ?: ""}",
                        trailing = "₹" + formatInr(b.amount),
                        footer = if (b.checkedIn > 0) "Checked in: ${b.checkedIn}" else b.ticketCode ?: "",
                    )
                }
            }
    }
}

@Composable
private fun ScanTab(api: PartnerApi, token: String) {
    var code by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    var result by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun submit(value: String) {
        val trimmed = value.trim()
        if (trimmed.isEmpty() || busy) return
        busy = true
        result = null
        scope.launch {
            result = try {
                api.checkIn(token, trimmed).message
            } catch (e: ApiException) {
                e.message
            } catch (e: Exception) {
                e.message ?: "Check-in failed"
            } finally {
                busy = false
                code = ""
            }
        }
    }

    val scanLauncher = rememberLauncherForActivityResult(ScanContract()) { scan ->
        scan.contents?.let { submit(it) }
    }

    Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.Center) {
        Text("Ticket check-in", style = MaterialTheme.typography.titleLarge)
        Spacer(Modifier.height(4.dp))
        Text(
            "Scan the attendee's ticket QR, or enter the code by hand.",
            style = MaterialTheme.typography.bodyMedium,
        )
        Spacer(Modifier.height(16.dp))
        Button(
            enabled = !busy,
            onClick = {
                result = null
                scanLauncher.launch(
                    ScanOptions()
                        .setDesiredBarcodeFormats(ScanOptions.QR_CODE)
                        .setPrompt("Scan ticket QR")
                        .setBeepEnabled(true)
                        .setOrientationLocked(false),
                )
            },
            modifier = Modifier.fillMaxWidth(),
        ) {
            Icon(Icons.Filled.QrCodeScanner, contentDescription = null)
            Spacer(Modifier.height(0.dp))
            Text("  Scan QR")
        }
        Spacer(Modifier.height(16.dp))
        OutlinedTextField(
            value = code,
            onValueChange = { code = it; result = null },
            label = { Text("Ticket code") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )
        Spacer(Modifier.height(12.dp))
        TextButton(
            enabled = !busy && code.isNotBlank(),
            onClick = { submit(code) },
            modifier = Modifier.fillMaxWidth(),
        ) { Text(if (busy) "Checking…" else "Check in by code") }
        if (result != null) {
            Spacer(Modifier.height(16.dp))
            Text(result!!, style = MaterialTheme.typography.titleMedium)
        }
    }
}

// ---- Small building blocks ---------------------------------------------

@Composable
private fun <T> Loaded(state: UiState<T>, content: @Composable (T) -> Unit) {
    when (state) {
        is UiState.Loading -> Box { CircularProgressIndicator(Modifier.padding(32.dp)) }
        is UiState.Error -> EmptyState(state.message)
        is UiState.Data -> content(state.value)
    }
}

@Composable
private fun Box(content: @Composable () -> Unit) {
    Column(
        Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) { content() }
}

@Composable
private fun EmptyState(message: String) {
    Column(
        Modifier.fillMaxSize().padding(32.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(message, textAlign = TextAlign.Center, style = MaterialTheme.typography.bodyLarge)
    }
}

@Composable
private fun ListCard(title: String, subtitle: String, trailing: String, footer: String, onClick: (() -> Unit)? = null) {
    val base = Modifier.fillMaxWidth()
    Card(
        modifier = if (onClick != null) base.clickable { onClick() } else base,
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(title, style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f, false))
                Text(trailing, style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.primary)
            }
            Text(subtitle, style = MaterialTheme.typography.bodyMedium)
            if (footer.isNotBlank()) {
                Spacer(Modifier.height(6.dp))
                Text(footer, style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}

private suspend fun <T> runCatchingUi(block: suspend () -> T): UiState<T> = try {
    UiState.Data(block())
} catch (e: ApiException) {
    UiState.Error(e.message ?: "Error")
} catch (e: Exception) {
    UiState.Error(e.message ?: "Something went wrong")
}

